<?php
require_once 'config/config.php';
requireRole(['admin', 'kasir']);

require_once 'models/Barang.php';
require_once 'models/Penjualan.php';
require_once 'models/Customer.php';
require_once 'models/Pengaturan.php';

$database = new Database();
$db = $database->getConnection();

$barang = new Barang($db);
$penjualan = new Penjualan($db);
$customer = new Customer($db);
$pengaturan = new Pengaturan($db);

// Get PPN setting
$ppn_persen = floatval($pengaturan->get('ppn_persen') ?? 10);

$message = '';
$message_type = '';

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'search_barang':
            $keyword = sanitizeInput($_GET['keyword']);
            $stmt = $barang->search($keyword);
            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = $row;
            }
            echo json_encode($results);
            exit;
            
        case 'get_barang':
            $barang_id = sanitizeInput($_GET['barang_id']);
            $barang->id = $barang_id;
            if ($barang->readOne()) {
                echo json_encode([
                    'id' => $barang->id,
                    'kode_barang' => $barang->kode_barang,
                    'nama_barang' => $barang->nama_barang,
                    'harga_jual' => $barang->harga_jual,
                    'diskon' => $barang->diskon ?? 0,
                    'stok' => $barang->stok
                ]);
            } else {
                echo json_encode(['error' => 'barang tidak ditemukan']);
            }
            exit;
    }
}

// Handle transaction submission
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'process_transaction') {
    try {
        $db->beginTransaction();
        
        // Create penjualan record
        $penjualan->no_transaksi = $penjualan->generateNoTransaksi();
        $penjualan->user_id = $_SESSION['user_id'];
        $penjualan->customer_id = !empty($_POST['customer_id']) ? sanitizeInput($_POST['customer_id']) : null;
        $penjualan->diskon = sanitizeInput($_POST['diskon']);
        $penjualan->ppn = sanitizeInput($_POST['ppn']);
        $penjualan->total_harga = sanitizeInput($_POST['total_harga']);
        $penjualan->total_bayar = sanitizeInput($_POST['total_bayar']);
        $penjualan->kembalian = sanitizeInput($_POST['kembalian']);
        $penjualan->note = !empty($_POST['note']) ? sanitizeInput($_POST['note']) : null;
        
        if (!$penjualan->create()) {
            throw new Exception('Gagal membuat transaksi');
        }
        
        $penjualan_id = $db->lastInsertId();
        
        // Process detail penjualan
        $items = json_decode($_POST['items'], true);
        foreach ($items as $item) {
            // Insert detail penjualan
            $detail_query = "INSERT INTO detail_penjualan (penjualan_id, barang_id, jumlah, harga_satuan, diskon, subtotal) 
                             VALUES (:penjualan_id, :barang_id, :jumlah, :harga_satuan, :diskon, :subtotal)";
            $detail_stmt = $db->prepare($detail_query);
            $detail_stmt->bindParam(':penjualan_id', $penjualan_id);
            $detail_stmt->bindParam(':barang_id', $item['id']);
            $detail_stmt->bindParam(':jumlah', $item['quantity']);
            $detail_stmt->bindParam(':harga_satuan', $item['price']);
            $item_diskon = isset($item['diskon']) ? $item['diskon'] : 0;
            $detail_stmt->bindParam(':diskon', $item_diskon);
            $detail_stmt->bindParam(':subtotal', $item['subtotal']);
            
            if (!$detail_stmt->execute()) {
                throw new Exception('Gagal menyimpan detail penjualan');
            }
            
            // Update stok barang
            $barang->updateStok($item['id'], -$item['quantity']);
        }
        
        $db->commit();
        
        // Redirect to receipt
        header('Location: struk.php?id=' . $penjualan_id);
        exit();
        
    } catch (Exception $e) {
        $db->rollBack();
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get all customer for dropdown
$customer_stmt = $customer->readAll();
$customer_stmt->execute();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penjualan - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dynamic.php">
    <style>
        .pos-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 20px;
            height: calc(100vh - 120px);
        }
        
        .product-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            overflow-y: auto;
        }
        
        .cart-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            display: flex;
            flex-direction: column;
        }
        
        .search-box {
            margin-bottom: 20px;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .product-card {
            border: 1px solid #e1e1e1;
            border-radius: 8px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .product-card:hover {
            border-color: #3498db;
            box-shadow: 0 2px 8px rgba(52, 152, 219, 0.2);
        }
        
        .product-card.selected {
            border-color: #27ae60;
            background: #f8fff8;
        }
        
        .product-name {
            font-weight: 600;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .product-price {
            color: #27ae60;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .product-stock {
            font-size: 12px;
            color: #7f8c8d;
        }
        
        .cart-items {
            flex: 1;
            overflow-y: auto;
            margin-bottom: 20px;
            max-height: calc(100vh - 500px);
            padding-right: 5px;
        }
        
        .cart-items::-webkit-scrollbar {
            width: 6px;
        }
        
        .cart-items::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .cart-items::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        
        .cart-items::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        .cart-item {
            background: #f8f9fa;
            border: 1px solid #e1e1e1;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 12px;
            transition: all 0.3s ease;
        }
        
        .cart-item:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-color: #3498db;
        }
        
        .item-header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .item-info {
            flex: 1;
            min-width: 0;
        }
        
        .item-name {
            font-weight: 600;
            font-size: 14px;
            color: #2c3e50;
            margin-bottom: 5px;
            word-wrap: break-word;
        }
        
        .item-details {
            display: flex;
            flex-direction: column;
            gap: 3px;
            font-size: 12px;
            color: #7f8c8d;
        }
        
        .item-price-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .item-price {
            color: #7f8c8d;
        }
        
        .item-diskon-badge {
            background: #ffe6e6;
            color: #e74c3c;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .item-subtotal {
            font-weight: bold;
            color: #27ae60;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .item-controls {
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: flex-end;
            min-width: 140px;
        }
        
        .control-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
            width: 100%;
        }
        
        .control-label {
            font-size: 10px;
            color: #7f8c8d;
            font-weight: 500;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 5px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 2px;
        }
        
        .quantity-control button {
            width: 28px;
            height: 28px;
            border: none;
            background: #f8f9fa;
            cursor: pointer;
            border-radius: 4px;
            font-weight: bold;
            color: #2c3e50;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .quantity-control button:hover {
            background: #e9ecef;
            transform: scale(1.1);
        }
        
        .quantity-control button:active {
            transform: scale(0.95);
        }
        
        .quantity-control input {
            width: 45px;
            text-align: center;
            border: none;
            padding: 4px;
            font-size: 13px;
            font-weight: 600;
            background: transparent;
        }
        
        .diskon-input-group {
            display: flex;
            align-items: center;
            gap: 5px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 4px 8px;
        }
        
        .diskon-input-group input {
            width: 70px;
            border: none;
            padding: 4px;
            font-size: 12px;
            text-align: right;
            background: transparent;
        }
        
        .btn-remove {
            width: 100%;
            padding: 6px 12px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .btn-remove:hover {
            background: #c0392b;
            transform: translateY(-1px);
        }
        
        .empty-cart {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }
        
        .empty-cart-icon {
            font-size: 48px;
            margin-bottom: 10px;
            opacity: 0.5;
        }
        
        .cart-summary {
            border-top: 2px solid #e1e1e1;
            padding-top: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .summary-row.total {
            font-weight: bold;
            font-size: 18px;
            color: #2c3e50;
            border-top: 1px solid #e1e1e1;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .payment-section {
            margin-top: 20px;
        }
        
        .payment-section input,
        .payment-section select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .payment-section .form-group {
            margin-bottom: 15px;
        }
        
        .payment-section .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .btn-process {
            width: 100%;
            padding: 15px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .btn-process:hover {
            background: #229954;
        }
        
        .btn-process:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Sidebar -->
        <?php 
        $role = $_SESSION['user_role'];
        require_once 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Navigation -->
            <header class="top-nav">
                <h1>Point of Sale (POS)</h1>
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['nama_lengkap'], 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?php echo $_SESSION['nama_lengkap']; ?></div>
                        <div class="user-role"><?php echo ucfirst($_SESSION['user_role']); ?></div>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="content">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <div class="pos-container">
                    <!-- Product Section -->
                    <div class="product-section">
                        <div class="search-box">
                            <input type="text" id="searchInput" placeholder="Cari barang..." onkeyup="searchProducts()">
                        </div>
                        
                        <div id="productGrid" class="product-grid">
                            <!-- Products will be loaded here -->
                        </div>
                    </div>

                    <!-- Cart Section -->
                    <div class="cart-section">
                        <h3>Keranjang Belanja</h3>
                        
                        <div class="cart-items" id="cartItems">
                            <p style="text-align: center; color: #7f8c8d; padding: 20px;">
                                Keranjang kosong
                            </p>
                        </div>
                        
                        <div class="cart-summary">
                            <div class="summary-row">
                                <span>Subtotal:</span>
                                <span id="subtotal">Rp 0</span>
                            </div>
                            <div class="summary-row">
                                <span>Diskon:</span>
                                <span id="diskonDisplay">Rp 0</span>
                            </div>
                            <div class="summary-row">
                                <span>PPN (<?php echo $ppn_persen; ?>%):</span>
                                <span id="ppn">Rp 0</span>
                            </div>
                            <div class="summary-row total">
                                <span>Total:</span>
                                <span id="total">Rp 0</span>
                            </div>
                        </div>
                        
                        <div class="payment-section">
                            <div class="form-group">
                                <label for="customer_id">Customer</label>
                                <select id="customer_id" name="customer_id">
                                    <option value="">Pilih customer</option>
                                    <?php 
                                    while ($row = $customer_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo $row['id']; ?>"><?php echo $row['nama_customer']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="diskonInput">Diskon (Rp)</label>
                                <input type="number" id="diskonInput" placeholder="Masukkan diskon" value="0" min="0" onkeyup="updateSummary()" onchange="updateSummary()">
                            </div>
                            <input type="number" id="paymentInput" placeholder="Jumlah Bayar" onkeyup="calculateChange()">
                            <div class="summary-row">
                                <span>Kembalian:</span>
                                <span id="change">Rp 0</span>
                            </div>
                            <input type="text" id="note" placeholder="Catatan">
                            <button class="btn-process" id="processBtn" onclick="processTransaction()" disabled>
                                Proses Transaksi
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        let cart = [];
        let products = [];

        // Load products on page load
        window.onload = function() {
            searchProducts();
        };

        function searchProducts() {
            const keyword = document.getElementById('searchInput').value;
            
            fetch(`penjualan.php?action=search_barang&keyword=${encodeURIComponent(keyword)}`)
                .then(response => response.json())
                .then(data => {
                    products = data;
                    displayProducts(data);
                })
                .catch(error => console.error('Error:', error));
        }

        function displayProducts(products) {
            const grid = document.getElementById('productGrid');
            grid.innerHTML = '';

            products.forEach(product => {
                const productCard = document.createElement('div');
                productCard.className = 'product-card';
                productCard.onclick = () => addToCart(product);
                
                productCard.innerHTML = `
                    <div class="product-name">${product.nama_barang}</div>
                    <div class="product-price">${formatCurrency(product.harga_jual)}</div>
                    <div class="product-stock">Stok: ${product.stok} ${product.satuan}</div>
                `;
                
                grid.appendChild(productCard);
            });
        }

        function addToCart(product) {
            if (product.stok <= 0) {
                alert('Stok barang habis!');
                return;
            }

            const existingItem = cart.find(item => item.id === product.id);
            
            if (existingItem) {
                if (existingItem.quantity < product.stok) {
                    existingItem.quantity++;
                } else {
                    alert('Stok tidak mencukupi!');
                    return;
                }
                // Update diskon jika belum ada atau tetap gunakan yang sudah ada
                if (existingItem.diskon === undefined || existingItem.diskon === 0) {
                    existingItem.diskon = parseFloat(product.diskon || 0);
                }
            } else {
                cart.push({
                    id: product.id,
                    kode_barang: product.kode_barang,
                    nama_barang: product.nama_barang,
                    price: parseFloat(product.harga_jual),
                    diskon: parseFloat(product.diskon || 0),
                    quantity: 1,
                    max_stock: product.stok
                });
            }
            
            updateCartDisplay();
        }

        function updateCartDisplay() {
            const cartItems = document.getElementById('cartItems');
            
            if (cart.length === 0) {
                cartItems.innerHTML = `
                    <div class="empty-cart">
                        <div class="empty-cart-icon">🛒</div>
                        <p style="font-size: 14px; margin: 0;">Keranjang kosong</p>
                        <p style="font-size: 12px; margin: 5px 0 0 0; opacity: 0.7;">Pilih barang untuk menambahkannya ke keranjang</p>
                    </div>
                `;
                updateSummary();
                return;
            }

            cartItems.innerHTML = '';
            
            cart.forEach((item, index) => {
                const cartItem = document.createElement('div');
                cartItem.className = 'cart-item';
                
                const itemSubtotal = (item.price * item.quantity) - (item.diskon || 0);
                const itemTotalBeforeDiskon = item.price * item.quantity;
                
                cartItem.innerHTML = `
                    <div class="item-header-row">
                        <div class="item-info">
                            <div class="item-name">${item.nama_barang}</div>
                            <div class="item-details">
                                <div class="item-price-row">
                                    <span class="item-price">${formatCurrency(item.price)} × ${item.quantity}</span>
                                    ${item.diskon > 0 ? `<span class="item-diskon-badge">Diskon: ${formatCurrency(item.diskon)}</span>` : ''}
                                </div>
                                ${item.diskon > 0 ? `
                                    <div style="font-size: 11px; color: #999; text-decoration: line-through;">
                                        ${formatCurrency(itemTotalBeforeDiskon)}
                                    </div>
                                ` : ''}
                                <div class="item-subtotal">
                                    Subtotal: ${formatCurrency(Math.max(0, itemSubtotal))}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="item-controls">
                        <div class="control-group">
                            <label class="control-label">Jumlah</label>
                            <div class="quantity-control">
                                <button onclick="updateQuantity(${index}, -1)" title="Kurangi">−</button>
                                <input type="number" value="${item.quantity}" min="1" max="${item.max_stock}" 
                                       onchange="setQuantity(${index}, this.value)"
                                       onkeyup="setQuantity(${index}, this.value)">
                                <button onclick="updateQuantity(${index}, 1)" title="Tambah">+</button>
                            </div>
                        </div>
                        <div class="control-group">
                            <label class="control-label">Diskon (Rp)</label>
                            <div class="diskon-input-group">
                                <input type="number" value="${item.diskon || 0}" min="0" step="100" 
                                       placeholder="0"
                                       onchange="updateItemDiskon(${index}, this.value)" 
                                       onkeyup="updateItemDiskon(${index}, this.value)">
                            </div>
                        </div>
                        <button onclick="removeFromCart(${index})" class="btn-remove" title="Hapus dari keranjang">
                            🗑️ Hapus
                        </button>
                    </div>
                `;
                
                cartItems.appendChild(cartItem);
            });
            
            updateSummary();
        }

        function updateQuantity(index, change) {
            const item = cart[index];
            const newQuantity = item.quantity + change;
            
            if (newQuantity >= 1 && newQuantity <= item.max_stock) {
                item.quantity = newQuantity;
                updateCartDisplay();
            }
        }

        function setQuantity(index, value) {
            const item = cart[index];
            const newQuantity = parseInt(value);
            
            if (newQuantity >= 1 && newQuantity <= item.max_stock) {
                item.quantity = newQuantity;
                updateCartDisplay();
            }
        }

        function removeFromCart(index) {
            cart.splice(index, 1);
            updateCartDisplay();
        }

        function updateItemDiskon(index, diskonValue) {
            const item = cart[index];
            item.diskon = parseFloat(diskonValue) || 0;
            updateCartDisplay();
        }

        function updateSummary() {
            // Hitung subtotal dengan diskon per item
            const subtotal = cart.reduce((sum, item) => {
                const itemSubtotal = (item.price * item.quantity) - (item.diskon || 0);
                return sum + Math.max(0, itemSubtotal);
            }, 0);
            
            const diskon = parseFloat(document.getElementById('diskonInput').value) || 0;
            const subtotalAfterDiskon = Math.max(0, subtotal - diskon);
            const ppnPersen = <?php echo $ppn_persen; ?>;
            const ppn = subtotalAfterDiskon * (ppnPersen / 100);
            const total = subtotalAfterDiskon + ppn;
            
            document.getElementById('subtotal').textContent = formatCurrency(subtotal);
            document.getElementById('diskonDisplay').textContent = formatCurrency(diskon);
            document.getElementById('ppn').textContent = formatCurrency(ppn);
            document.getElementById('total').textContent = formatCurrency(total);
            
            calculateChange();
        }

        function calculateChange() {
            // Hitung subtotal dengan diskon per item
            const subtotal = cart.reduce((sum, item) => {
                const itemSubtotal = (item.price * item.quantity) - (item.diskon || 0);
                return sum + Math.max(0, itemSubtotal);
            }, 0);
            
            const diskon = parseFloat(document.getElementById('diskonInput').value) || 0;
            const subtotalAfterDiskon = Math.max(0, subtotal - diskon);
            const ppnPersen = <?php echo $ppn_persen; ?>;
            const totalWithPPN = subtotalAfterDiskon * (1 + (ppnPersen / 100));
            const payment = parseFloat(document.getElementById('paymentInput').value) || 0;
            const change = payment - totalWithPPN;
            
            document.getElementById('change').textContent = formatCurrency(Math.max(0, change));
            
            const processBtn = document.getElementById('processBtn');
            processBtn.disabled = cart.length === 0 || payment < totalWithPPN;
        }

        function processTransaction() {
            // Hitung subtotal dengan diskon per item
            const subtotal = cart.reduce((sum, item) => {
                const itemSubtotal = (item.price * item.quantity) - (item.diskon || 0);
                return sum + Math.max(0, itemSubtotal);
            }, 0);
            
            const diskon = parseFloat(document.getElementById('diskonInput').value) || 0;
            const subtotalAfterDiskon = Math.max(0, subtotal - diskon);
            const ppnPersen = <?php echo $ppn_persen; ?>;
            const ppn = subtotalAfterDiskon * (ppnPersen / 100);
            const totalWithPPN = subtotalAfterDiskon + ppn;
            const payment = parseFloat(document.getElementById('paymentInput').value);
            const change = payment - totalWithPPN;
            const customerId = document.getElementById('customer_id').value;
            const note = document.getElementById('note').value;
            
            if (payment < totalWithPPN) {
                alert('Jumlah bayar kurang!');
                return;
            }
            
            // Calculate subtotal for each item (with item discount, without PPN)
            cart.forEach(item => {
                item.subtotal = Math.max(0, (item.price * item.quantity) - (item.diskon || 0));
            });
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="process_transaction">
                <input type="hidden" name="items" value='${JSON.stringify(cart)}'>
                <input type="hidden" name="customer_id" value="${customerId}">
                <input type="hidden" name="diskon" value="${diskon}">
                <input type="hidden" name="ppn" value="${ppn}">
                <input type="hidden" name="total_harga" value="${totalWithPPN}">
                <input type="hidden" name="total_bayar" value="${payment}">
                <input type="hidden" name="kembalian" value="${change}">
                <input type="hidden" name="note" value="${note}">
            `;
            
            document.body.appendChild(form);
            form.submit();
        }

        function formatCurrency(amount) {
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount);
        }
    </script>
</body>
</html>
