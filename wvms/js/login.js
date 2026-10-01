(function () {
  var AKUN = [{
    email: 'admin@perusahaan.co.id',
    sandi: 'admin123'
  }];
  var $ = function (i) { return document.getElementById(i); };
  var root = document.documentElement;

  try { root.dataset.p = localStorage.getItem('wvms.palet') || 'teal'; } catch (e) {}

  // Check if already logged in via backend
  if (!/[?&]keluar=1/.test(location.search) && !/[?&]expired=1/.test(location.search)) {
    fetch('backend/index.php?action=me', { credentials: 'same-origin' })
      .then(function(res) {
        if (res.ok) {
          location.replace('index.html');
        }
      })
      .catch(function() {});
  }

  var t = localStorage.getItem('wvms.tema') ||
    (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

  function setTema(v) {
    root.dataset.t = v;
    $('tema').textContent = v === 'dark' ? '☀️' : '🌙';
    try { localStorage.setItem('wvms.tema', v); } catch (e) {}
  }

  setTema(t);
  $('tema').onclick = function () {
    setTema(root.dataset.t === 'dark' ? 'light' : 'dark');
  };

  function pesan(teks, jenis) {
    $('pesan').innerHTML = teks ? '<div class="msg ' + jenis + '">' + teks + '</div>' : '';
  }

  if (/[?&]keluar=1/.test(location.search)) {
    pesan('Anda telah keluar dengan aman.', 'ok');
    history.replaceState(null, '', 'login.html');
  }

  if (/[?&]expired=1/.test(location.search)) {
    pesan('Sesi Anda telah berakhir. Silakan masuk kembali.', 'er');
    history.replaceState(null, '', 'login.html');
  }

  $('eye').onclick = function () {
    var p = $('sandi'), lihat = p.type === 'password';
    p.type = lihat ? 'text' : 'password';
    this.textContent = lihat ? 'Sembunyikan' : 'Lihat';
  };

  $('lupa').onclick = function (e) {
    e.preventDefault();
    pesan('Hubungi administrator untuk mengatur ulang kata sandi Anda.', 'er');
  };

  $('isi').onclick = function () {
    $('email').value = AKUN[0].email;
    $('sandi').value = AKUN[0].sandi;
  };

  $('form').onsubmit = function (e) {
    e.preventDefault();

    var em = $('email').value.trim().toLowerCase();
    var pw = $('sandi').value;
    var ingat = $('ingat').checked;

    if (!em || !pw) return gagal('Isi email dan kata sandi terlebih dahulu.');

    var tombol = $('go');
    tombol.disabled = true;
    tombol.innerHTML = '<span class="spin"></span> Masuk…';

    fetch('backend/index.php?action=login', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: em, sandi: pw, ingat: ingat })
    })
    .then(function(res) {
      return res.json().then(function(json) {
        if (!res.ok || json.status !== 'success') {
          throw new Error(json.message || 'Login gagal.');
        }
        return json;
      });
    })
    .then(function(data) {
      location.replace('index.html');
    })
    .catch(function(err) {
      tombol.disabled = false;
      tombol.innerHTML = 'Masuk';
      gagal(err.message);
    });
  };

  function gagal(teks) {
    pesan(teks, 'er');
    var b = $('form');
    b.classList.remove('shake');
    void b.offsetWidth;
    b.classList.add('shake');
  }
})();
