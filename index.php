<?php
require_once 'config/auth.php';
if (isLoggedIn()) { header('Location: dashboard.php'); exit(); }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid_form()) {
        $error = 'Sesi form kedaluwarsa, silakan coba lagi.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if (empty($username) || empty($password)) { $error = 'Username dan password wajib diisi.'; }
        else {
            global $conn;
            $found = null;
            $stmt = $conn->prepare("SELECT u.*, r.kode AS role_kode, r.nama_role FROM users u
                                     JOIN roles r ON r.id = u.role_id
                                     WHERE u.username=? OR u.email=?");
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $candidate = $stmt->get_result()->fetch_assoc();

            if ($candidate && $candidate['status'] !== 'aktif') {
                $error = 'Akun tidak aktif. Hubungi Administrator.';
            } elseif ($candidate && isAccountLocked($candidate)) {
                $sisa = ceil((strtotime($candidate['locked_until']) - time()) / 60);
                $error = "Akun terkunci sementara karena terlalu banyak percobaan gagal. Coba lagi dalam $sisa menit.";
            } elseif ($candidate && password_verify($password, $candidate['password'])) {
                $found = $candidate;
            }

            if ($found) {
                resetFailedLogin($found['id']);
                session_regenerate_id(true);
                $_SESSION['user_id'] = $found['id'];
                logAktivitas($found['id'], 'auth', 'Login ke sistem');
                header('Location: dashboard.php'); exit();
            } elseif (!$error) {
                if ($candidate) {
                    $attempts = registerFailedLogin($candidate['id']);
                    $sisa = LOGIN_MAX_ATTEMPTS - $attempts;
                    $error = $sisa > 0
                        ? "Username atau password salah. Sisa percobaan: $sisa."
                        : "Akun terkunci " . LOGIN_LOCKOUT_MINUTES . " menit karena terlalu banyak percobaan gagal.";
                } else {
                    $error = 'Username atau password salah.';
                }
            }
        }
    }
}

$demoRoles = [
    ['u' => 'admin', 'label' => 'Administrator', 'icon' => 'crown'],
    ['u' => 'manajerga', 'label' => 'Manajer GA', 'icon' => 'briefcase'],
    ['u' => 'spvinventaris', 'label' => 'SPV Inventaris', 'icon' => 'package'],
    ['u' => 'staffinventaris', 'label' => 'Staff Inventaris', 'icon' => 'boxes'],
    ['u' => 'spvsapras', 'label' => 'SPV Sapras', 'icon' => 'hard-hat'],
    ['u' => 'teamleader', 'label' => 'Team Leader', 'icon' => 'users'],
    ['u' => 'engineering', 'label' => 'Engineering', 'icon' => 'wrench'],
    ['u' => 'cso1', 'label' => 'CSO', 'icon' => 'shield'],
    ['u' => 'gardener1', 'label' => 'Gardener', 'icon' => 'leaf'],
    ['u' => 'manajerhrd', 'label' => 'Manajer HRD', 'icon' => 'id-card'],
    ['u' => 'staffhrd', 'label' => 'Staff HRD', 'icon' => 'id-card'],
    ['u' => 'manajermarkom', 'label' => 'Manajer MARKOM', 'icon' => 'megaphone'],
    ['u' => 'spvcrm', 'label' => 'SPV CRM', 'icon' => 'calendar-check'],
    ['u' => 'staffcrm', 'label' => 'Staff CRM', 'icon' => 'calendar'],
    ['u' => 'manajerqms', 'label' => 'Manajer QMS', 'icon' => 'folder-cog'],
    ['u' => 'staffqms', 'label' => 'Staff QMS', 'icon' => 'folder'],
    ['u' => 'tu_sd', 'label' => 'TU (SD)', 'icon' => 'file-text'],
    ['u' => 'kepsek_sd', 'label' => 'Kepala Sekolah (SD)', 'icon' => 'graduation-cap'],
    ['u' => 'pengguna1', 'label' => 'Pengguna Unit', 'icon' => 'user'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Masuk — AL-SYUKROSMART OPS</title>
<link rel="icon" type="image/x-icon" href="favicon.ico">
<link rel="icon" type="image/png" sizes="512x512" href="logo-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<!-- Bootstrap 5 (framework CSS utama aplikasi) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bs-primary:#145c3f; --bs-primary-rgb:20,92,63;
  --bs-success:#145c3f; --bs-success-rgb:20,92,63;
  --primary:#145c3f; --primary-dark:#0c3f2b; --primary-tint:#e7f1ec;
  --gold:#c79a3f; --gold-deep:#a97c22; --gold-tint:#f6ecd6;
  --bg:#f6f4ee; --card:#fffefb; --text:#1c2a22; --muted:#6f7669; --border:#e6e1d3;
  --font-display:'Fraunces',ui-serif,Georgia,serif; --font-body:'Inter',ui-sans-serif,sans-serif;
}
html,body{height:100%;font-family:var(--font-body);background:var(--bg);color:var(--text)}
.wrap{display:flex;min-height:100vh}

/* — Panel kiri: identitas institusi dengan motif geometris bintang delapan (khas ornamen Islam) — */
.lft{
  width:46%;position:relative;overflow:hidden;display:flex;flex-direction:column;justify-content:space-between;
  padding:52px 56px;color:#f3f6f3;
  background:
    radial-gradient(90% 70% at 85% -10%, rgba(199,154,63,.16) 0%, transparent 55%),
    linear-gradient(200deg,#123d2c 0%,#0a2e21 100%);
}
.motif{position:absolute;inset:0;opacity:.12;pointer-events:none}
.lft-top{position:relative;z-index:1}
.logo-badge-lg{display:inline-flex;align-items:center;gap:13px;background:#fffefb;padding:11px 18px 11px 11px;border-radius:14px;box-shadow:0 10px 26px rgba(0,0,0,.22)}
.logo-badge-lg img{height:46px;width:auto;display:block}
.logo-badge-lg .lb-text strong{display:block;font-family:var(--font-display);font-size:13px;color:var(--primary-dark);font-weight:600;line-height:1.25}
.logo-badge-lg .lb-text span{font-size:10.5px;color:#5c6f63}
.lft-mid{position:relative;z-index:1;max-width:430px}
.eyebrow-line{display:flex;align-items:center;gap:10px;margin:38px 0 16px;color:#c9a35c;font-size:12px;letter-spacing:.4px}
.eyebrow-line::before{content:'';width:26px;height:1px;background:#c9a35c}
.headline{font-family:var(--font-display);font-size:40px;font-weight:600;line-height:1.18;letter-spacing:.2px;margin-bottom:16px}
.sub-txt{font-size:14.5px;line-height:1.7;color:#cfe0d5;max-width:400px}
.lft-bottom{position:relative;z-index:1;display:flex;gap:0;border-top:1px solid rgba(255,255,255,.14);padding-top:20px}
.lft-bottom div{flex:1;padding-right:14px}
.lft-bottom strong{display:block;font-family:var(--font-display);font-size:20px;font-weight:600;color:#fff}
.lft-bottom span{font-size:11px;color:#9fb8a8}

/* — Panel kanan: form — */
.rgt{flex:1;display:flex;align-items:center;justify-content:center;padding:32px;background:var(--bg)}
.login-card{background:var(--card);width:100%;max-width:440px;padding:42px 40px;border-radius:20px;border:1px solid var(--border);box-shadow:0 18px 40px rgba(20,30,23,.07)}
.fwelcome{margin-bottom:26px}
.fwelcome h2{font-family:var(--font-display);font-size:25px;font-weight:600;letter-spacing:.1px;margin-bottom:7px}
.fwelcome p{color:var(--muted);font-size:13.5px}
.falert{padding:12px 14px;border-radius:10px;font-size:13px;font-weight:500;display:flex;align-items:center;gap:10px;margin-bottom:20px}
.falert svg{width:18px;height:18px;flex-shrink:0}
.ferr{background:#f8e6e2;color:#8f2c20;border:1px solid #edc9c1}
.fok{background:var(--primary-tint);color:var(--primary-dark);border:1px solid #cfe3d8}
.fgrp{margin-bottom:16px}
.flabel{display:block;font-size:12.5px;font-weight:600;margin-bottom:7px;color:#3c463e}
.finwrap{position:relative}
.finput{width:100%;padding:12px 15px;border:1.5px solid var(--border);border-radius:10px;font-size:14px;font-family:inherit;background:#fbfaf6;outline:none;transition:all .2s;color:var(--text)}
.finput:focus{border-color:var(--primary);background:#fff;box-shadow:0 0 0 3px var(--primary-tint)}
.feye{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted)}
.btn-submit{width:100%;padding:13px;margin-top:6px;background:var(--primary);color:#fff;border:none;border-radius:10px;font-size:14.5px;font-weight:600;font-family:inherit;cursor:pointer;transition:all .2s}
.btn-submit:hover{background:var(--primary-dark)}
.divider{display:flex;align-items:center;text-align:center;margin:24px 0;color:var(--muted);font-size:11.5px;font-weight:600;letter-spacing:.2px}
.divider::before,.divider::after{content:'';flex:1;border-bottom:1px solid var(--border)}
.divider::before{margin-right:10px}.divider::after{margin-left:10px}
.dbox{background:#fbfaf6;border:1px dashed var(--border);border-radius:12px;padding:14px;max-height:210px;overflow-y:auto}
.dgrid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.dpill{padding:9px 10px;background:var(--card);border:1px solid var(--border);border-radius:8px;cursor:pointer;text-align:left;transition:all .15s;font-family:inherit}
.dpill:hover{border-color:var(--gold-deep);box-shadow:0 2px 8px rgba(0,0,0,.05)}
.dpill strong{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--text)}
.dpill strong svg{width:14px;height:14px;color:var(--primary)}
.dpill span{font-size:11px;color:var(--muted)}
.reglink{text-align:center;font-size:12.5px;color:var(--muted);margin-top:22px}
.reglink b{color:var(--text)}
@media(max-width:900px){.wrap{flex-direction:column}.lft{width:100%;padding:36px 24px}.rgt{width:100%;padding:20px}.login-card{padding:30px 24px}.headline{font-size:32px}}
@media(max-width:480px){.lft{padding:26px 18px}.lft-bottom{flex-wrap:wrap;gap:14px}.lft-bottom div{flex:1 1 40%;padding-right:0}.headline{font-size:26px}.eyebrow-line{margin:26px 0 12px}.login-card{padding:24px 18px;border-radius:16px}.dgrid{grid-template-columns:1fr}.rgt{padding:14px}}
</style>
</head>
<body>
<div class="wrap">
  <div class="lft">
    <svg class="motif" viewBox="0 0 500 800" preserveAspectRatio="xMidYMid slice">
      <defs><g id="star8">
        <path d="M0,-26 L7,-7 L26,0 L7,7 L0,26 L-7,7 L-26,0 L-7,-7 Z" fill="none" stroke="#c9a35c" stroke-width="1"/>
      </g></defs>
      <use href="#star8" x="60" y="90" transform="scale(1.4)" transform-origin="60 90"/>
      <use href="#star8" x="430" y="60" transform="scale(1.1)" transform-origin="430 60"/>
      <use href="#star8" x="360" y="260" transform="scale(2.1)" transform-origin="360 260"/>
      <use href="#star8" x="60" y="420" transform="scale(1.6)" transform-origin="60 420"/>
      <use href="#star8" x="440" y="520" transform="scale(1.3)" transform-origin="440 520"/>
      <use href="#star8" x="150" y="650" transform="scale(1.8)" transform-origin="150 650"/>
      <use href="#star8" x="420" y="740" transform="scale(1.2)" transform-origin="420 740"/>
    </svg>
    <div class="lft-top">
      <div class="logo-badge-lg">
        <img src="logo-full.png" alt="Logo Perguruan Islam Al Syukro Universal">
        <div class="lb-text"><strong>Perguruan Islam<br>Al Syukro Universal</strong><span>Tangerang Selatan</span></div>
      </div>
    </div>
    <div class="lft-mid">
      <div class="eyebrow-line">Sistem Operasional Terpadu</div>
      <h1 class="headline">Satu ruang kerja untuk seluruh operasional yayasan.</h1>
      <p class="sub-txt">Al-SyukroSmart Ops menyatukan inventaris, kepegawaian, kegiatan, fasilitas, dan arsip — dengan alur persetujuan dan hak akses yang mengikuti struktur organisasi Anda.</p>
    </div>
    <div class="lft-bottom">
      <div><strong>5</strong><span>Modul Terintegrasi</span></div>
      <div><strong>17</strong><span>Peran Organisasi</span></div>
      <div><strong>4</strong><span>Unit Pendidikan</span></div>
    </div>
  </div>
  <div class="rgt">
    <div class="login-card">
      <div class="fwelcome"><h2>Masuk ke akun Anda</h2><p>Gunakan kredensial yang diberikan oleh Administrator.</p></div>
      <form method="POST">
        <?= csrf_field() ?>
        <div class="fgrp">
          <label class="flabel">Username atau Email</label>
          <div class="finwrap"><input class="finput form-control" type="text" name="username" id="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" placeholder="Masukkan username..." required autofocus></div>
        </div>
        <div class="fgrp">
          <label class="flabel">Password</label>
          <div class="finwrap">
            <input class="finput form-control" type="password" name="password" id="pw" placeholder="Masukkan password..." required>
            <button type="button" class="feye" id="eyeBtn"><i data-lucide="eye"></i></button>
          </div>
        </div>
        <button type="submit" class="btn-submit btn btn-success w-100">Masuk</button>
      </form>
      <div class="divider">MODE DEMO — PASSWORD: PASSWORD123</div>
      <div class="dbox">
        <div class="dgrid">
          <?php foreach ($demoRoles as $d): ?>
          <button type="button" class="dpill" data-u="<?= $d['u'] ?>" data-p="password123">
            <strong><i data-lucide="<?= $d['icon'] ?>"></i> <?= htmlspecialchars($d['label']) ?></strong>
            <span><?= $d['u'] ?></span>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
      <p class="reglink">Butuh akun baru? Hubungi <b>Administrator Sistem</b> untuk dibuatkan akun sesuai peran Anda.</p>
    </div>
  </div>
</div>
<script>
lucide.createIcons();
document.querySelectorAll('.dpill').forEach(b=>{
  b.addEventListener('click',()=>{document.getElementById('username').value=b.dataset.u;document.getElementById('pw').value=b.dataset.p});
});
const pw=document.getElementById('pw'),eye=document.getElementById('eyeBtn');
eye.addEventListener('click',()=>{
  const type = pw.type === 'password' ? 'text' : 'password';
  pw.type = type;
  eye.innerHTML = type === 'password' ? '<i data-lucide="eye"></i>' : '<i data-lucide="eye-off"></i>';
  lucide.createIcons({ root: eye });
});

<?php if ($error): ?>
Swal.fire({ title: 'Terjadi Kesalahan!', text: <?= json_encode($error) ?>, icon: 'error', confirmButtonText: 'OK', confirmButtonColor: '#145c3f' });
<?php endif; ?>
<?php if (isset($_GET['registered'])): ?>
Swal.fire({ title: 'Berhasil!', text: 'Registrasi berhasil! Silakan login.', icon: 'success', confirmButtonText: 'OK', confirmButtonColor: '#145c3f' });
<?php endif; ?>
</script>
</body>
</html>
