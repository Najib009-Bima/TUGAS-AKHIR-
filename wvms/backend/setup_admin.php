<?php
// =====================================================
// Membuat akun Admin pertama. Jalankan SEKALI lewat browser:
//   http://localhost/wvms/backend/setup_admin.php
// File ini hanya bekerja jika tabel users masih KOSONG.
// Setelah berhasil, HAPUS file ini.
// =====================================================
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/db.php';

// Ganti nilai di bawah jika ingin email/password lain
$nama     = 'Administrator';
$email    = 'admin@wvms.test';
$password = 'admin123';

try {
    $pdo = db();
    $jumlah = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($jumlah > 0) {
        exit("Sudah ada pengguna di tabel users. File ini tidak diperlukan lagi, silakan hapus.\n");
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $st = $pdo->prepare('INSERT INTO users (nama, email, password, role, status) VALUES (?, ?, ?, ?, ?)');
    $st->execute([$nama, $email, $hash, 'Admin', 'Aktif']);
    echo "Akun admin berhasil dibuat.\n";
    echo "Email    : $email\n";
    echo "Password : $password\n\n";
    echo "PENTING: hapus file setup_admin.php sekarang.\n";
} catch (PDOException $e) {
    echo "Gagal: " . $e->getMessage() . "\n";
    echo "Pastikan MySQL menyala dan db_wvms.sql sudah di-import.\n";
}
