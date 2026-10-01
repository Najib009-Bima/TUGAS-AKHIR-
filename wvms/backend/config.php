<?php
// =====================================================
// PENGATURAN KONEKSI DATABASE
// Ubah DB_PASS jika MySQL Anda memakai password
// (XAMPP bawaan: user "root", password kosong)
// =====================================================
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'db_wvms');
define('DB_USER', 'root');
define('DB_PASS', '');

// true = pesan error teknis ikut ditampilkan (hanya untuk belajar/development)
define('APP_DEBUG', false);

date_default_timezone_set('Asia/Jakarta');
