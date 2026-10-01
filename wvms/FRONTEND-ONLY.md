# WVMS — Frontend Only

Versi ini sengaja tidak memakai PHP, MySQL, atau backend lama.

## Menjalankan
- Jika memakai XAMPP, cukup taruh folder `wvms` di `htdocs`, lalu buka:
  `http://localhost/wvms/`
- Apache saja sudah cukup. MySQL tidak diperlukan.
- Bisa juga dijalankan melalui web server statis sederhana.

## Login demo
- Email: `admin@perusahaan.co.id`
- Password: `admin123`

## Penyimpanan sementara
Data CRUD disimpan di browser menggunakan `localStorage` dengan key:
`wvms.frontend.data.v1`

Session login:
`wvms.sesi`

Ini hanya untuk demo/testing frontend. Untuk produksi, backend baru harus menggantikan penyimpanan ini dengan API + database.

## Yang dipertahankan
UI/UX, halaman, CSS, komponen, modal, navigasi, dashboard, wisma, kunjungan, laporan, pengaturan, profil, dan fitur frontend tetap berasal dari project asli.

## Untuk pembuat backend baru
Struktur data utama yang dipakai frontend:
- `wisma`
- `kunjungan`
- `tiket`
- `users`
- `log`
- `read`
- `cfg`

Field penting yang dipakai frontend:
- wisma: `id`, `kode`, `nama`, `alamat`, `pic`, `status`, `kapasitas`, `fasilitas`, `lat`, `lng`, `meta_json`
- kunjungan: `id`, `kode`, `tgl`, `real`, `tujuan`, `prioritas`, `catatan`, `batal`, `bukti`, `bukti_json`, `meta_json`
- tiket: `id`, `kode`/`wisma_kode`, `kunjungan_id`, `item`, `status`, `tgl`, `selesai`, `meta_json`
- users: `id`, `nama`, `email`, `role`, `status`
- log: `t`, `u`, `a`, `d`
- cfg: object konfigurasi aplikasi

Backend baru bebas memakai desain database/API sendiri selama data yang dikirim ke frontend mengikuti bentuk yang dibutuhkan modul-modul JavaScript.
