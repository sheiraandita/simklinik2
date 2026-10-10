<?php
require_once 'config/config.php';
requireRole(['admin', 'kasir']);

require_once 'models/Penjualan.php';
require_once 'models/Customer.php';
require_once 'models/Pengaturan.php';

$database = new Database();
$db = $database->getConnection();

$penjualan = new Penjualan($db);
$customer = new Customer($db);
$pengaturan = new Pengaturan($db);

// Get settings
$nama_toko = $pengaturan->get('nama_toko') ?? APP_NAME;
$alamat_toko = $pengaturan->get('alamat_toko') ?? '';
$telepon_toko = $pengaturan->get('telepon_toko') ?? '';
$email_toko = $pengaturan->get('email_toko') ?? '';
$ppn_persen = floatval($pengaturan->get('ppn_persen') ?? 10);
$footer_struk = $pengaturan->get('footer_struk') ?? 'Terima kasih atas kunjungan Anda!';

$penjualan_id = sanitizeInput($_GET['id']);
$penjualan->id = $penjualan_id;

if (!$penjualan->readOne()) {
    header('Location: penjualan.php');
    exit();
}

// Get customer data if exists
$customer_name = '';
if (!empty($penjualan->customer_id)) {
    $customer->id = $penjualan->customer_id;
    if ($customer->readOne()) {
        $customer_name = $customer->nama_customer;
    }
}

$detail_stmt = $penjualan->getDetailPenjualan($penjualan_id);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Penjualan - <?php echo APP_NAME; ?></title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
            background: white;
        }
        
        .receipt {
            width: 300px;
            margin: 0 auto;
            border: 1px solid #000;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .header h1 {
            font-size: 18px;
            margin: 0 0 5px 0;
            font-weight: bold;
        }
        
        .header p {
            margin: 0;
            font-size: 10px;
        }
        
        .transaction-info {
            border-bottom: 1px dashed #000;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        
        .items {
            margin-bottom: 15px;
        }
        
        .item-header {
            display: grid;
            grid-template-columns: 3fr 1fr 1fr 1.5fr;
            gap: 5px;
            font-weight: bold;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
            margin-bottom: 5px;
        }
        
        .item-row {
            display: grid;
            grid-template-columns: 3fr 1fr 1fr 1.5fr;
            gap: 5px;
            margin-bottom: 3px;
            font-size: 11px;
        }
        
        .item-row span:last-child {
            text-align: right;
        }
        
        .summary {
            border-top: 1px dashed #000;
            padding-top: 10px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        
        .summary-row.total {
            font-weight: bold;
            border-top: 1px solid #000;
            padding-top: 5px;
            margin-top: 5px;
        }
        
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 10px;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            
            .receipt {
                border: none;
                width: 100%;
                max-width: 300px;
            }
            
            .no-print {
                display: none !important;
            }
        }
        
        .no-print {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .item-diskon {
            color: #e74c3c;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="btn">Cetak Struk</button>
        <a href="penjualan.php" class="btn">Transaksi Baru</a>
        <a href="dashboard.php" class="btn">Dashboard</a>
    </div>

    <div class="receipt">
        <div class="header">
            <h1><?php echo htmlspecialchars($nama_toko); ?></h1>
            <p><?php if ($alamat_toko): ?><?php echo htmlspecialchars($alamat_toko); ?><br><?php endif; ?>
            <?php if ($telepon_toko): ?>Telp: <?php echo htmlspecialchars($telepon_toko); ?><br><?php endif; ?>
            <?php if ($email_toko): ?>Email: <?php echo htmlspecialchars($email_toko); ?><?php endif; ?></p>
        </div>
        
        <div class="transaction-info">
            <div class="info-row">
                <span>No. Transaksi:</span>
                <span><?php echo $penjualan->no_transaksi; ?></span>
            </div>
            <div class="info-row">
                <span>Tanggal:</span>
                <span><?php echo date('d/m/Y H:i:s', strtotime($penjualan->tanggal_penjualan)); ?></span>
            </div>
            <?php if (!empty($customer_name)): ?>
            <div class="info-row">
                <span>Customer:</span>
                <span><?php echo $customer_name; ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <span>Kasir:</span>
                <span><?php echo $_SESSION['nama_lengkap']; ?></span>
            </div>
        </div>
        
        <div class="items">
            <div class="item-header">
                <span>Item</span>
                <span>Qty</span>
                <span>Harga</span>
                <span>Total</span>
            </div>
            
            <?php 
            $subtotal = 0;
            // Reset statement untuk fetch ulang
            $detail_stmt = $penjualan->getDetailPenjualan($penjualan_id);
            while ($row = $detail_stmt->fetch(PDO::FETCH_ASSOC)): 
                $item_diskon = isset($row['diskon']) ? floatval($row['diskon']) : 0;
                $item_total_before_diskon = $row['harga_satuan'] * $row['jumlah'];
                $item_total_after_diskon = $item_total_before_diskon - $item_diskon;
                $subtotal += max(0, $item_total_after_diskon);
            ?>
            <div class="item-row">
                <span><?php echo htmlspecialchars($row['nama_barang']); ?></span>
                <span><?php echo $row['jumlah']; ?></span>
                <span><?php echo number_format($row['harga_satuan'], 0, ',', '.'); ?></span>
                <span>
                    <?php if ($item_diskon > 0): ?>
                        <span style="text-decoration: line-through; color: #999; font-size: 10px;">
                            <?php echo number_format($item_total_before_diskon, 0, ',', '.'); ?>
                        </span><br>
                        <span style="color: #e74c3c; font-weight: bold;">
                            -<?php echo number_format($item_diskon, 0, ',', '.'); ?>
                        </span><br>
                    <?php endif; ?>
                    <?php echo number_format(max(0, $item_total_after_diskon), 0, ',', '.'); ?>
                </span>
            </div>
            <?php endwhile; ?>
        </div>
        
        <div class="summary">
            <div class="summary-row">
                <span>Subtotal:</span>
                <span>Rp <?php echo number_format($subtotal, 0, ',', '.'); ?></span>
            </div>
            <?php if (!empty($penjualan->diskon) && $penjualan->diskon > 0): ?>
            <div class="summary-row">
                <span>Diskon:</span>
                <span>Rp <?php echo number_format($penjualan->diskon, 0, ',', '.'); ?></span>
            </div>
            <?php endif; ?>
            <div class="summary-row">
                <span>PPN (<?php echo $ppn_persen; ?>%):</span>
                <span>Rp <?php echo number_format($penjualan->ppn ?? 0, 0, ',', '.'); ?></span>
            </div>
            <div class="summary-row total">
                <span>Total:</span>
                <span>Rp <?php echo number_format($penjualan->total_harga, 0, ',', '.'); ?></span>
            </div>
            <div class="summary-row">
                <span>Bayar:</span>
                <span>Rp <?php echo number_format($penjualan->total_bayar, 0, ',', '.'); ?></span>
            </div>
            <div class="summary-row">
                <span>Kembalian:</span>
                <span>Rp <?php echo number_format($penjualan->kembalian, 0, ',', '.'); ?></span>
            </div>
        </div>
        
        <?php if (!empty($penjualan->note)): ?>
        <div class="note-section" style="margin-top: 15px; padding-top: 10px; border-top: 1px dashed #000;">
            <div style="font-weight: bold; margin-bottom: 5px;">Catatan:</div>
            <div><?php echo htmlspecialchars($penjualan->note); ?></div>
        </div>
        <?php endif; ?>
        
        <div class="footer">
            <p><?php echo htmlspecialchars($footer_struk); ?></p>
            <p>Barang yang sudah dibeli tidak dapat dikembalikan</p>
        </div>
    </div>

    <script>
        // Auto print when page loads (optional)
        // window.onload = function() {
        //     window.print();
        // };
    </script>
</body>
</html>
