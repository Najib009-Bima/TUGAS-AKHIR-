-- DATA CONTOH (OPSIONAL) - import SETELAH db_wvms.sql
-- Berguna supaya dashboard tidak kosong saat demo.
USE `db_wvms`;

INSERT IGNORE INTO `wisma` (`id`,`kode`,`nama`,`alamat`,`pic`,`status`,`kapasitas`,`fasilitas`) VALUES
('ID-001','WSM-001','Wisma Pahlawan','Jl. Pahlawan No. 12, Cicaheum, Bandung','Hendra Wijaya','Aktif',20,'AC, WiFi, Parkir'),
('ID-002','WSM-002','Wisma Cikutra','Jl. Cikutra No. 88, Bandung','Ratna Dewi','Aktif',15,'AC, WiFi'),
('ID-003','WSM-003','Wisma Dago','Jl. Ir. H. Juanda No. 141, Bandung','Asep Gunawan','Aktif',25,'AC, WiFi, Aula'),
('ID-004','WSM-004','Wisma Pasteur','Jl. Dr. Djundjunan No. 45, Bandung','Budi Santoso','Aktif',18,'AC, Parkir'),
('ID-005','WSM-005','Wisma Buah Batu','Jl. Buah Batu No. 210, Bandung','Siti Nurhaliza','Aktif',12,'WiFi'),
('ID-006','WSM-006','Wisma Lembang','Jl. Raya Lembang No. 33, Bandung Barat','Tri Haryanto','Aktif',30,'AC, WiFi, Taman'),
('ID-007','WSM-007','Wisma Cimahi','Jl. Amir Machmud No. 71, Cimahi','Dedi Rohimat','Aktif',14,'AC'),
('ID-008','WSM-008','Wisma Sumedang','Jl. Prabu Geusan Ulun No. 9, Sumedang','Fahmi Lubis','Aktif',10,'WiFi, Parkir');

INSERT IGNORE INTO `kunjungan` (`id`,`wisma_kode`,`tgl`,`tgl_real`,`tujuan`,`prioritas`,`catatan`,`batal`) VALUES
('VIS-001','WSM-001', DATE_SUB(CURDATE(), INTERVAL 20 DAY), DATE_SUB(CURDATE(), INTERVAL 20 DAY), 'Audit K3',        'Tinggi', 'Audit K3 dan pemeliharaan genset', 0),
('VIS-002','WSM-002', DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_SUB(CURDATE(), INTERVAL 9 DAY),  'Inspeksi Rutin',  'Normal', 'Inspeksi kamar dan fasilitas', 0),
('VIS-003','WSM-003', DATE_SUB(CURDATE(), INTERVAL 5 DAY),  NULL,                                 'Servis AC',       'Normal', 'Penggantian filter AC', 0),
('VIS-004','WSM-004', CURDATE(),                            NULL,                                 'Cek APAR',        'Tinggi', 'Pengecekan APAR', 0),
('VIS-005','WSM-005', DATE_ADD(CURDATE(), INTERVAL 7 DAY),  NULL,                                 'Serah Terima',    'Normal', 'Serah terima kunci', 0),
('VIS-006','WSM-006', DATE_SUB(CURDATE(), INTERVAL 12 DAY), NULL,                                 'Audit Kebersihan','Rendah', 'Dibatalkan oleh PIC', 1);
