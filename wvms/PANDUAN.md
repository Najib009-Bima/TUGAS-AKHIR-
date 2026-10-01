# Backend & Database Baru WVMS

Isi paket:
```
backend/   config.php, db.php, index.php, setup_admin.php
database/  db_wvms.sql, data_contoh.sql (opsional)
```

## Langkah pemasangan
1. Salin folder `backend` ke dalam folder front end: `C:\xampp\htdocs\wvms\backend\`
   (sejajar dengan `index.html` dan `login.html`).
2. Start **Apache** dan **MySQL** di XAMPP.
3. phpMyAdmin (`http://localhost/phpmyadmin`) -> **Import** -> pilih `database/db_wvms.sql`.
   Opsional: import juga `database/data_contoh.sql`.
4. Buka `http://localhost/wvms/backend/setup_admin.php` (membuat akun admin), lalu **hapus** file itu.
5. Cek: `http://localhost/wvms/backend/index.php?action=health` -> `"status":"success"`.
6. Buka `http://localhost/wvms/` -> login `admin@wvms.test` / `admin123`.

## Akun admin
Email dan password awal ada di bagian atas `backend/setup_admin.php`. Ubah sebelum dijalankan jika perlu.

## Alur kerja
Browser -> `backend/index.php?action=...` -> MySQL `db_wvms`

| action | method | fungsi |
|---|---|---|
| health | GET | cek PHP dan MySQL |
| login | POST | cek email dan password, buat session |
| logout | POST | hapus session |
| me | GET | data user yang sedang login |
| state | GET | kirim semua data ke front end |
| sync | POST | simpan perubahan (tambah / ubah / hapus) dalam satu transaksi |

## Hak akses (dicek di server)
- Admin: semua
- Staff: tambah dan ubah, tidak boleh hapus
- Viewer: hanya melihat
