<?php
// =====================================================
// API WVMS (PHP + MySQL)
// Dipanggil front end lewat: backend/index.php?action=NAMA
// Action: health, login, logout, me, state, sync
// Semua jawaban berupa JSON: {"status":"success"|"error", "message":"..."}
// =====================================================
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// ---------- Session (penanda user sudah login) ----------
session_name('WVMSSESS');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// ---------- Error buatan sendiri ----------
class ApiError extends Exception
{
    public $httpCode;
    public function __construct($pesan, $httpCode = 400)
    {
        parent::__construct($pesan);
        $this->httpCode = $httpCode;
    }
}

// ---------- Fungsi bantu umum ----------
function jawab($data, $httpCode = 200)
{
    http_response_code($httpCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function wajibPost()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new ApiError('Endpoint ini harus memakai metode POST.', 405);
    }
}

function bacaBody()
{
    $mentah = file_get_contents('php://input');
    if ($mentah === false || $mentah === '') {
        return [];
    }
    $data = json_decode($mentah, true);
    if (!is_array($data)) {
        throw new ApiError('Data yang dikirim harus berformat JSON.', 400);
    }
    return $data;
}

// Ambil teks dari array, bersihkan spasi, cek panjang & wajib isi
function teks($arr, $kunci, $maks, $label, $wajib = false)
{
    $v = (isset($arr[$kunci]) && is_scalar($arr[$kunci])) ? trim((string)$arr[$kunci]) : '';
    if ($wajib && $v === '') {
        throw new ApiError($label . ' wajib diisi.', 422);
    }
    if (mb_strlen($v) > $maks) {
        throw new ApiError($label . ' terlalu panjang (maksimal ' . $maks . ' karakter).', 422);
    }
    return $v;
}

// Ambil tanggal format YYYY-MM-DD. Kosong -> null
function tanggal($arr, $kunci, $label, $wajib = false)
{
    $v = teks($arr, $kunci, 10, $label, $wajib);
    if ($v === '') {
        return null;
    }
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
        throw new ApiError($label . ' tidak valid (format harus YYYY-MM-DD).', 422);
    }
    return $v;
}

// Ambil koordinat (lat/lng). Kosong -> null
function koordinat($arr, $kunci, $min, $maks, $label)
{
    if (!isset($arr[$kunci]) || $arr[$kunci] === '' || $arr[$kunci] === null) {
        return null;
    }
    if (!is_numeric($arr[$kunci])) {
        throw new ApiError($label . ' harus berupa angka.', 422);
    }
    $n = (float)$arr[$kunci];
    if ($n < $min || $n > $maks) {
        throw new ApiError($label . ' di luar jangkauan.', 422);
    }
    return $n;
}

// Cek apakah sebuah baris ada. (Nama tabel/kolom hanya dari kode kita sendiri, bukan dari user)
function ada($tabel, $kolom, $nilai)
{
    $st = db()->prepare("SELECT 1 FROM `$tabel` WHERE `$kolom` = ? LIMIT 1");
    $st->execute([$nilai]);
    return (bool)$st->fetchColumn();
}

// ---------- Login & hak akses ----------
function penggunaLogin()
{
    if (empty($_SESSION['uid'])) {
        throw new ApiError('Sesi login tidak ditemukan.', 401);
    }
    // Data user dibaca ulang dari database setiap request,
    // jadi perubahan role/status langsung berlaku.
    $st = db()->prepare('SELECT id, nama, email, role, status FROM users WHERE id = ?');
    $st->execute([$_SESSION['uid']]);
    $u = $st->fetch();
    if (!$u || $u['status'] !== 'Aktif') {
        $_SESSION = [];
        session_destroy();
        throw new ApiError('Sesi login tidak ditemukan.', 401);
    }
    $u['id'] = (int)$u['id'];
    return $u;
}

// ---------- ACTION: login ----------
function aksiLogin()
{
    wajibPost();
    $in    = bacaBody();
    $email = strtolower(trim((string)(isset($in['email']) ? $in['email'] : '')));
    $sandi = (string)(isset($in['sandi']) ? $in['sandi'] : '');
    if ($email === '' || $sandi === '') {
        throw new ApiError('Email dan kata sandi wajib diisi.', 422);
    }

    $st = db()->prepare('SELECT id, nama, email, password, role, status FROM users WHERE LOWER(email) = ? LIMIT 1');
    $st->execute([$email]);
    $u = $st->fetch();

    // Pesan sengaja dibuat sama agar orang luar tidak bisa menebak email mana yang terdaftar
    if (!$u || !password_verify($sandi, $u['password'])) {
        throw new ApiError('Email atau kata sandi salah.', 401);
    }
    if ($u['status'] !== 'Aktif') {
        throw new ApiError('Akun Anda tidak aktif. Hubungi administrator.', 403);
    }

    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];

    // "Ingat saya": cookie session berlaku 30 hari
    if (!empty($in['ingat'])) {
        setcookie(session_name(), session_id(), [
            'expires'  => time() + 30 * 24 * 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    db()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$u['id']]);

    jawab([
        'status'  => 'success',
        'message' => 'Login berhasil.',
        'user'    => ['id' => (int)$u['id'], 'nama' => $u['nama'], 'email' => $u['email'], 'role' => $u['role']],
    ]);
}

// ---------- ACTION: logout ----------
function aksiLogout()
{
    wajibPost();
    $_SESSION = [];
    session_destroy();
    jawab(['status' => 'success', 'message' => 'Logout berhasil.']);
}

// ---------- ACTION: state (kirim SEMUA data ke front end) ----------
function aksiState()
{
    $user = penggunaLogin();
    $pdo  = db();

    $wisma = [];
    foreach ($pdo->query('SELECT * FROM wisma ORDER BY kode')->fetchAll() as $r) {
        $wisma[] = [
            'id'        => (string)$r['id'],
            'kode'      => (string)$r['kode'],
            'nama'      => (string)$r['nama'],
            'alamat'    => (string)$r['alamat'],
            'pic'       => (string)$r['pic'],
            'status'    => (string)$r['status'],
            'kapasitas' => (int)$r['kapasitas'],
            'fasilitas' => (string)$r['fasilitas'],
            'lat'       => $r['lat'] === null ? '' : (float)$r['lat'],
            'lng'       => $r['lng'] === null ? '' : (float)$r['lng'],
        ];
    }

    $kunjungan = [];
    foreach ($pdo->query('SELECT * FROM kunjungan ORDER BY tgl, id')->fetchAll() as $r) {
        $k = [
            'id'        => (string)$r['id'],
            'kode'      => (string)$r['wisma_kode'],
            'tgl'       => (string)$r['tgl'],
            'real'      => $r['tgl_real'] === null ? '' : (string)$r['tgl_real'],
            'tujuan'    => (string)$r['tujuan'],
            'prioritas' => (string)$r['prioritas'],
            'catatan'   => (string)$r['catatan'],
            'batal'     => (int)$r['batal'] === 1,
        ];
        if ($r['bukti'] !== null && $r['bukti'] !== '') {
            $k['bukti'] = json_decode($r['bukti'], true);
        }
        $kunjungan[] = $k;
    }

    $tiket = [];
    foreach ($pdo->query('SELECT * FROM tiket ORDER BY tgl, id')->fetchAll() as $r) {
        $tiket[] = [
            'id'      => (string)$r['id'],
            'kode'    => (string)$r['wisma_kode'],
            'kid'     => (string)($r['kunjungan_id'] === null ? '' : $r['kunjungan_id']),
            'item'    => (string)$r['item'],
            'status'  => (string)$r['status'],
            'tgl'     => (string)$r['tgl'],
            'selesai' => $r['tgl_selesai'] === null ? '' : (string)$r['tgl_selesai'],
        ];
    }

    $log = [];
    foreach ($pdo->query('SELECT waktu, pengguna, aksi, detail FROM log_aktivitas ORDER BY waktu DESC, id DESC LIMIT 100')->fetchAll() as $r) {
        $log[] = ['t' => (int)$r['waktu'], 'u' => (string)$r['pengguna'], 'a' => (string)$r['aksi'], 'd' => (string)$r['detail']];
    }

    // Pengaturan. Kunci "read_<id user>" khusus penanda notifikasi, tidak ikut ke cfg.
    $cfg  = [];
    $read = [];
    foreach ($pdo->query('SELECT kunci, nilai FROM konfigurasi')->fetchAll() as $r) {
        $nilai = $r['nilai'] === null ? null : json_decode($r['nilai'], true);
        if ($r['kunci'] === 'read_' . $user['id']) {
            $read = is_array($nilai) ? $nilai : [];
        } elseif (strpos($r['kunci'], 'read_') !== 0) {
            $cfg[$r['kunci']] = $nilai;
        }
    }
    $cfg['me'] = ['nama' => $user['nama'], 'email' => $user['email'], 'role' => $user['role']];

    // Daftar pengguna hanya untuk Admin
    $users = [];
    if ($user['role'] === 'Admin') {
        foreach ($pdo->query('SELECT id, nama, email, role, status, last_login FROM users ORDER BY id')->fetchAll() as $r) {
            $users[] = [
                'id'     => 'USR-' . $r['id'],
                'nama'   => $r['nama'],
                'email'  => $r['email'],
                'role'   => $r['role'],
                'status' => $r['status'],
                'login'  => $r['last_login'] === null ? '-' : $r['last_login'],
            ];
        }
    }

    jawab([
        'status' => 'success',
        'user'   => $user,
        'data'   => [
            'wisma' => $wisma, 'kunjungan' => $kunjungan, 'tiket' => $tiket,
            'users' => $users, 'log' => $log, 'read' => $read, 'cfg' => $cfg,
        ],
    ]);
}

// ---------- Simpan satu baris WISMA ----------
function simpanWisma($d, $baru)
{
    $id   = teks($d, 'id', 30, 'ID wisma', true);
    $kode = teks($d, 'kode', 30, 'Kode wisma', true);
    $p = [
        $kode,
        teks($d, 'nama', 150, 'Nama wisma', true),
        teks($d, 'alamat', 5000, 'Alamat'),
        teks($d, 'pic', 100, 'PIC'),
        teks($d, 'status', 20, 'Status') ?: 'Aktif',
        max(0, (int)(isset($d['kapasitas']) ? $d['kapasitas'] : 0)),
        teks($d, 'fasilitas', 5000, 'Fasilitas'),
        koordinat($d, 'lat', -90, 90, 'Latitude'),
        koordinat($d, 'lng', -180, 180, 'Longitude'),
        $id,
    ];

    if ($baru) {
        if (ada('wisma', 'id', $id)) {
            throw new ApiError('ID wisma ' . $id . ' sudah dipakai. Muat ulang halaman lalu coba lagi.', 409);
        }
        if (ada('wisma', 'kode', $kode)) {
            throw new ApiError('Kode wisma ' . $kode . ' sudah dipakai.', 409);
        }
        db()->prepare('INSERT INTO wisma (kode, nama, alamat, pic, status, kapasitas, fasilitas, lat, lng, id)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute($p);
    } else {
        if (!ada('wisma', 'id', $id)) {
            throw new ApiError('Wisma tidak ditemukan (mungkin sudah dihapus).', 404);
        }
        db()->prepare('UPDATE wisma SET kode=?, nama=?, alamat=?, pic=?, status=?, kapasitas=?, fasilitas=?, lat=?, lng=?
                       WHERE id=?')->execute($p);
    }
}

// ---------- Simpan satu baris KUNJUNGAN ----------
function simpanKunjungan($d, $baru)
{
    $id   = teks($d, 'id', 30, 'ID kunjungan', true);
    $kode = teks($d, 'kode', 30, 'Kode wisma', true);
    if (!ada('wisma', 'kode', $kode)) {
        throw new ApiError('Wisma dengan kode ' . $kode . ' tidak ditemukan.', 422);
    }
    $bukti = (isset($d['bukti']) && is_array($d['bukti']) && count($d['bukti']) > 0)
        ? json_encode($d['bukti'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : null;
    $p = [
        $kode,
        tanggal($d, 'tgl', 'Tanggal rencana', true),
        tanggal($d, 'real', 'Tanggal realisasi'),
        teks($d, 'tujuan', 150, 'Tujuan'),
        teks($d, 'prioritas', 20, 'Prioritas'),
        teks($d, 'catatan', 5000, 'Catatan'),
        empty($d['batal']) ? 0 : 1,
        $bukti,
        $id,
    ];

    if ($baru) {
        if (ada('kunjungan', 'id', $id)) {
            throw new ApiError('ID kunjungan ' . $id . ' sudah dipakai. Muat ulang halaman lalu coba lagi.', 409);
        }
        db()->prepare('INSERT INTO kunjungan (wisma_kode, tgl, tgl_real, tujuan, prioritas, catatan, batal, bukti, id)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute($p);
    } else {
        if (!ada('kunjungan', 'id', $id)) {
            throw new ApiError('Kunjungan tidak ditemukan (mungkin sudah dihapus).', 404);
        }
        db()->prepare('UPDATE kunjungan SET wisma_kode=?, tgl=?, tgl_real=?, tujuan=?, prioritas=?, catatan=?, batal=?, bukti=?
                       WHERE id=?')->execute($p);
    }
}

// ---------- Simpan satu baris TIKET ----------
function simpanTiket($d, $baru)
{
    $id   = teks($d, 'id', 60, 'ID tiket', true);
    $kode = teks($d, 'kode', 30, 'Kode wisma', true);
    if (!ada('wisma', 'kode', $kode)) {
        throw new ApiError('Wisma dengan kode ' . $kode . ' tidak ditemukan.', 422);
    }
    $status = teks($d, 'status', 20, 'Status tiket') ?: 'Baru';
    if (!in_array($status, ['Baru', 'Diproses', 'Selesai'], true)) {
        throw new ApiError('Status tiket harus Baru, Diproses, atau Selesai.', 422);
    }
    // Jika kunjungan asalnya sudah tidak ada, kosongkan saja relasinya
    $kid = teks($d, 'kid', 30, 'ID kunjungan');
    if ($kid === '' || !ada('kunjungan', 'id', $kid)) {
        $kid = null;
    }
    $p = [
        $kode,
        $kid,
        teks($d, 'item', 255, 'Temuan', true),
        $status,
        tanggal($d, 'tgl', 'Tanggal tiket') ?: date('Y-m-d'),
        tanggal($d, 'selesai', 'Tanggal selesai'),
        $id,
    ];

    if ($baru) {
        if (ada('tiket', 'id', $id)) {
            throw new ApiError('ID tiket ' . $id . ' sudah dipakai.', 409);
        }
        db()->prepare('INSERT INTO tiket (wisma_kode, kunjungan_id, item, status, tgl, tgl_selesai, id)
                       VALUES (?, ?, ?, ?, ?, ?, ?)')->execute($p);
    } else {
        if (!ada('tiket', 'id', $id)) {
            throw new ApiError('Tiket tidak ditemukan (mungkin sudah dihapus).', 404);
        }
        db()->prepare('UPDATE tiket SET wisma_kode=?, kunjungan_id=?, item=?, status=?, tgl=?, tgl_selesai=?
                       WHERE id=?')->execute($p);
    }
}

// Ambil daftar create/update/delete dari "ops" dengan aman
function daftarOps($ops, $entitas, $jenis)
{
    if (isset($ops[$entitas][$jenis]) && is_array($ops[$entitas][$jenis])) {
        return $ops[$entitas][$jenis];
    }
    return [];
}

// ---------- ACTION: sync (simpan perubahan dari front end) ----------
function aksiSync()
{
    wajibPost();
    $user = penggunaLogin();
    $in   = bacaBody();
    if (!isset($in['ops']) || !is_array($in['ops'])) {
        throw new ApiError('Data harus berisi "ops".', 422);
    }
    $ops  = $in['ops'];
    $role = $user['role'];

    // --- Cek hak akses ---
    $adaTulis = !empty($ops['wisma']) || !empty($ops['kunjungan']) || !empty($ops['tiket']) || !empty($ops['cfg']);
    if ($role === 'Viewer' && $adaTulis) {
        throw new ApiError('Peran Viewer hanya dapat melihat data.', 403);
    }
    $entitasList = ['wisma', 'kunjungan', 'tiket'];
    if ($role !== 'Admin') {
        foreach ($entitasList as $e) {
            if (count(daftarOps($ops, $e, 'delete')) > 0) {
                throw new ApiError('Hanya Admin yang boleh menghapus data.', 403);
            }
        }
    }
    $kunciAdmin = ['company', 'alamat', 'email', 'telp', 'logo'];
    $cfgSet   = (isset($ops['cfg']['set']) && is_array($ops['cfg']['set'])) ? $ops['cfg']['set'] : [];
    $cfgUnset = (isset($ops['cfg']['unset']) && is_array($ops['cfg']['unset'])) ? $ops['cfg']['unset'] : [];
    if ($role !== 'Admin') {
        foreach (array_merge(array_keys($cfgSet), $cfgUnset) as $k) {
            if (in_array((string)$k, $kunciAdmin, true)) {
                throw new ApiError('Hanya Admin yang boleh mengubah informasi instansi.', 403);
            }
        }
    }

    // --- Simpan dalam SATU transaksi: semua berhasil, atau semua dibatalkan ---
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Urutan simpan: wisma -> kunjungan -> tiket (induk dulu, karena ada foreign key)
        $penyimpan = ['wisma' => 'simpanWisma', 'kunjungan' => 'simpanKunjungan', 'tiket' => 'simpanTiket'];
        foreach ($penyimpan as $entitas => $fungsi) {
            foreach (daftarOps($ops, $entitas, 'create') as $item) {
                if (!is_array($item)) throw new ApiError('Format data ' . $entitas . ' tidak valid.', 422);
                $fungsi($item, true);
            }
            foreach (daftarOps($ops, $entitas, 'update') as $item) {
                if (!is_array($item)) throw new ApiError('Format data ' . $entitas . ' tidak valid.', 422);
                $fungsi($item, false);
            }
        }

        // Urutan hapus: tiket -> kunjungan -> wisma (anak dulu)
        foreach (['tiket', 'kunjungan', 'wisma'] as $tabel) {
            foreach (daftarOps($ops, $tabel, 'delete') as $idHapus) {
                if (is_scalar($idHapus)) {
                    $pdo->prepare("DELETE FROM `$tabel` WHERE id = ?")->execute([(string)$idHapus]);
                }
            }
        }

        // Pengaturan (tema, nama instansi, dll)
        foreach ($cfgSet as $kunci => $nilai) {
            $kunci = (string)$kunci;
            if ($kunci === 'me' || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,99}$/', $kunci) || strpos($kunci, 'read_') === 0) {
                continue;
            }
            $json = json_encode($nilai, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false || strlen($json) > 2000000) {
                throw new ApiError('Nilai pengaturan "' . $kunci . '" tidak valid atau terlalu besar.', 422);
            }
            $pdo->prepare('INSERT INTO konfigurasi (kunci, nilai) VALUES (?, ?)
                           ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)')->execute([$kunci, $json]);
        }
        foreach ($cfgUnset as $kunci) {
            if (is_scalar($kunci) && strpos((string)$kunci, 'read_') !== 0) {
                $pdo->prepare('DELETE FROM konfigurasi WHERE kunci = ?')->execute([(string)$kunci]);
            }
        }

        // Riwayat aktivitas (nama pelaku diambil dari session, bukan dari browser)
        if (!empty($ops['log']) && is_array($ops['log'])) {
            $st = $pdo->prepare('INSERT INTO log_aktivitas (waktu, pengguna, aksi, detail) VALUES (?, ?, ?, ?)');
            foreach (array_slice($ops['log'], 0, 50) as $l) {
                if (!is_array($l)) continue;
                $st->execute([
                    (int)(isset($l['t']) ? $l['t'] : round(microtime(true) * 1000)),
                    $user['nama'],
                    teks($l, 'a', 255, 'Aksi log'),
                    teks($l, 'd', 5000, 'Detail log'),
                ]);
            }
        }

        // Notifikasi yang sudah dibaca (per pengguna)
        if (isset($ops['read']) && is_array($ops['read'])) {
            $pdo->prepare('INSERT INTO konfigurasi (kunci, nilai) VALUES (?, ?)
                           ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)')
                ->execute(['read_' . $user['id'], json_encode(array_values($ops['read']))]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();   // batalkan semua perubahan jika ada yang gagal
        }
        throw $e;
    }

    jawab(['status' => 'success', 'message' => 'Perubahan tersimpan di MySQL.']);
}

// ---------- ACTION: health (cek backend & database) ----------
function aksiHealth()
{
    $pdo = db();
    $tabel = [];
    foreach (['users', 'wisma', 'kunjungan', 'tiket', 'log_aktivitas', 'konfigurasi'] as $t) {
        $tabel[$t] = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    }
    jawab([
        'status'   => 'success',
        'message'  => 'Backend dan MySQL aktif.',
        'php'      => PHP_VERSION,
        'database' => DB_NAME,
        'jumlah_baris' => $tabel,
    ]);
}

// ---------- ROUTER: pilih fungsi berdasarkan ?action= ----------
try {
    $aksi = strtolower(trim((string)(isset($_GET['action']) ? $_GET['action'] : '')));
    switch ($aksi) {
        case 'health': aksiHealth();  break;
        case 'login':  aksiLogin();   break;
        case 'logout': aksiLogout();  break;
        case 'me':
            jawab(['status' => 'success', 'user' => penggunaLogin()]);
            break;
        case 'state':  aksiState();   break;
        case 'sync':   aksiSync();    break;
        default:
            jawab(['status' => 'success', 'message' => 'WVMS backend aktif.',
                   'actions' => ['health', 'login', 'logout', 'me', 'state', 'sync']]);
    }
} catch (ApiError $e) {
    jawab(['status' => 'error', 'message' => $e->getMessage()], $e->httpCode);
} catch (PDOException $e) {
    // Terjemahkan error MySQL ke pesan yang mudah dipahami
    $kodeDb = (int)(isset($e->errorInfo[1]) ? $e->errorInfo[1] : 0);
    if ($kodeDb === 0 && preg_match('/\[(\d{4})\]/', $e->getMessage(), $m)) {
        $kodeDb = (int)$m[1];
    }
    $http = 500;
    switch ($kodeDb) {
        case 2002: $pesan = 'MySQL tidak dapat dihubungi. Pastikan MySQL di XAMPP sudah Start.'; break;
        case 1045: $pesan = 'Login MySQL ditolak. Periksa DB_USER dan DB_PASS di backend/config.php.'; break;
        case 1049: $pesan = 'Database ' . DB_NAME . ' belum ada. Import database/db_wvms.sql lewat phpMyAdmin.'; break;
        case 1146: $pesan = 'Tabel belum ada. Import database/db_wvms.sql lewat phpMyAdmin.'; break;
        case 1062: $pesan = 'Data duplikat: ID atau kode sudah dipakai.'; $http = 409; break;
        case 1452: $pesan = 'Relasi data tidak valid (mis. kode wisma tidak ada).'; $http = 422; break;
        case 1153: case 2006: $pesan = 'Data terlalu besar untuk MySQL (mis. foto). Naikkan max_allowed_packet di my.ini.'; $http = 413; break;
        default:   $pesan = 'Terjadi kesalahan pada database.';
    }
    error_log('[WVMS] ' . $e->getMessage());
    $out = ['status' => 'error', 'message' => $pesan];
    if (APP_DEBUG) $out['debug'] = $e->getMessage();
    jawab($out, $http);
} catch (Throwable $e) {
    error_log('[WVMS] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $out = ['status' => 'error', 'message' => 'Terjadi kesalahan pada server.'];
    if (APP_DEBUG) $out['debug'] = $e->getMessage();
    jawab($out, 500);
}
