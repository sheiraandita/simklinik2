<?php

class Manajemen_user
{
    private $conn;
    private $table = 'users';

    public $id;
    public $username;
    public $password;
    public $nama_lengkap;
    public $email;
    public $role;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Menampilkan semua data user
    public function readAll()
    {
        $query = "SELECT * FROM " . $this->table . " ORDER BY id DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt;
    }

    // Menambahkan user
    public function create()
    {
        try {
            // Cek username sebelum menambahkan data
            $cek = "SELECT COUNT(*) FROM " . $this->table . "
                    WHERE username = :username";

            $stmt = $this->conn->prepare($cek);
            $stmt->bindParam(':username', $this->username);
            $stmt->execute();

            if ($stmt->fetchColumn() > 0) {
                return false;
            }

            // Tambahkan user baru
            $query = "INSERT INTO " . $this->table . "
                      (username, password, nama_lengkap, email, role)
                      VALUES
                      (:username, :password, :nama_lengkap, :email, :role)";

            $stmt = $this->conn->prepare($query);

            $password_hash = password_hash(
                $this->password,
                PASSWORD_DEFAULT
            );

            $stmt->bindParam(':username', $this->username);
            $stmt->bindParam(':password', $password_hash);
            $stmt->bindParam(':nama_lengkap', $this->nama_lengkap);
            $stmt->bindParam(':email', $this->email);
            $stmt->bindParam(':role', $this->role);

            return $stmt->execute();

        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    // Mengubah data user
    public function update()
    {
        try {
            // Pastikan username tidak digunakan user lain
            $cek = "SELECT COUNT(*) FROM " . $this->table . "
                    WHERE username = :username AND id != :id";

            $stmt = $this->conn->prepare($cek);
            $stmt->bindParam(':username', $this->username);
            $stmt->bindParam(':id', $this->id);
            $stmt->execute();

            if ($stmt->fetchColumn() > 0) {
                return false;
            }

            $query = "UPDATE " . $this->table . "
                      SET username = :username,
                          nama_lengkap = :nama_lengkap,
                          email = :email,
                          role = :role
                      WHERE id = :id";

            $stmt = $this->conn->prepare($query);

            $stmt->bindParam(':username', $this->username);
            $stmt->bindParam(':nama_lengkap', $this->nama_lengkap);
            $stmt->bindParam(':email', $this->email);
            $stmt->bindParam(':role', $this->role);
            $stmt->bindParam(':id', $this->id);

            return $stmt->execute();

        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    // Menghapus user
    public function delete()
    {
        try {
            $query = "DELETE FROM " . $this->table . " WHERE id = :id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $this->id);

            return $stmt->execute();

        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    // Mengubah password user
    public function changePassword($new_password)
    {
        try {
            $query = "UPDATE " . $this->table . "
                      SET password = :password
                      WHERE id = :id";

            $stmt = $this->conn->prepare($query);

            $password_hash = password_hash(
                $new_password,
                PASSWORD_DEFAULT
            );

            $stmt->bindParam(':password', $password_hash);
            $stmt->bindParam(':id', $this->id);

            return $stmt->execute();

        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }
}