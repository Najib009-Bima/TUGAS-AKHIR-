-- =====================================================
-- DATABASE BARU WVMS  (nama database: db_wvms)
-- Cara pakai: phpMyAdmin -> tab Import -> pilih file ini -> Import
-- Aman diimpor berulang (tidak ada DROP / hapus data).
-- =====================================================

CREATE DATABASE IF NOT EXISTS `db_wvms`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_wvms`;

-- 1. Pengguna aplikasi (untuk login)
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama`       VARCHAR(100) NOT NULL,
  `email`      VARCHAR(150) NOT NULL,
  `password`   VARCHAR(255) NOT NULL,              -- berisi HASH, bukan password asli
  `role`       ENUM('Admin','Staff','Viewer') NOT NULL DEFAULT 'Staff',
  `status`     ENUM('Aktif','Nonaktif') NOT NULL DEFAULT 'Aktif',
  `last_login` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB;

-- 2. Data wisma
CREATE TABLE IF NOT EXISTS `wisma` (
  `id`        VARCHAR(30)  NOT NULL,
  `kode`      VARCHAR(30)  NOT NULL,
  `nama`      VARCHAR(150) NOT NULL,
  `alamat`    TEXT NULL,
  `pic`       VARCHAR(100) NULL,
  `status`    VARCHAR(20)  NOT NULL DEFAULT 'Aktif',
  `kapasitas` INT NOT NULL DEFAULT 0,
  `fasilitas` TEXT NULL,
  `lat`       DECIMAL(10,7) NULL,
  `lng`       DECIMAL(10,7) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wisma_kode` (`kode`)
) ENGINE=InnoDB;

-- 3. Kunjungan ke wisma (terhubung ke wisma lewat kode)
CREATE TABLE IF NOT EXISTS `kunjungan` (
  `id`         VARCHAR(30) NOT NULL,
  `wisma_kode` VARCHAR(30) NOT NULL,
  `tgl`        DATE NOT NULL,                      -- tanggal rencana
  `tgl_real`   DATE NULL,                          -- tanggal realisasi (kosong = belum selesai)
  `tujuan`     VARCHAR(150) NULL,
  `prioritas`  VARCHAR(20) NULL,
  `catatan`    TEXT NULL,
  `batal`      TINYINT(1) NOT NULL DEFAULT 0,
  `bukti`      LONGTEXT NULL,                      -- bukti check-in (GPS, foto, tanda tangan) dalam JSON
  PRIMARY KEY (`id`),
  KEY `idx_kunjungan_wisma` (`wisma_kode`),
  CONSTRAINT `fk_kunjungan_wisma` FOREIGN KEY (`wisma_kode`)
    REFERENCES `wisma` (`kode`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Tiket tindak lanjut (temuan saat check-in)
CREATE TABLE IF NOT EXISTS `tiket` (
  `id`           VARCHAR(60) NOT NULL,
  `wisma_kode`   VARCHAR(30) NOT NULL,
  `kunjungan_id` VARCHAR(30) NULL,
  `item`         VARCHAR(255) NOT NULL,
  `status`       VARCHAR(20) NOT NULL DEFAULT 'Baru',   -- Baru / Diproses / Selesai
  `tgl`          DATE NOT NULL,
  `tgl_selesai`  DATE NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tiket_wisma` (`wisma_kode`),
  CONSTRAINT `fk_tiket_wisma` FOREIGN KEY (`wisma_kode`)
    REFERENCES `wisma` (`kode`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_tiket_kunjungan` FOREIGN KEY (`kunjungan_id`)
    REFERENCES `kunjungan` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- 5. Riwayat aktivitas
CREATE TABLE IF NOT EXISTS `log_aktivitas` (
  `id`       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `waktu`    BIGINT NOT NULL,                      -- timestamp milidetik dari browser
  `pengguna` VARCHAR(100) NULL,
  `aksi`     VARCHAR(255) NOT NULL,
  `detail`   TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_log_waktu` (`waktu`)
) ENGINE=InnoDB;

-- 6. Pengaturan (nama instansi, tema, dll) dan penanda notifikasi yang sudah dibaca
CREATE TABLE IF NOT EXISTS `konfigurasi` (
  `kunci` VARCHAR(100) NOT NULL,
  `nilai` LONGTEXT NULL,                           -- disimpan sebagai JSON
  PRIMARY KEY (`kunci`)
) ENGINE=InnoDB;

-- Catatan: akun admin dibuat lewat backend/setup_admin.php (lihat panduan),
-- supaya password disimpan sebagai hash yang dibuat oleh PHP sendiri.
