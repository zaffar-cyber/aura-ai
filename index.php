<?php
require_once 'config.php';

if (is_logged()) {
    header('Location: ' . (sess()['role'] === 'admin' ? 'admin.php' : 'teacher_dashboard.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password =      $_POST['password'] ?? '';
    $role     =      $_POST['role']     ?? 'teacher';

    if ($role === 'admin') {
        if ($email === ADMIN_EMAIL && $password === ADMIN_PASS) {
            $_SESSION['uid']  = 'ADMIN';
            $_SESSION['role'] = 'admin';
            $_SESSION['name'] = 'Administrator';
            header('Location: admin.php'); exit;
        } else { $error = 'Invalid admin credentials.'; }
    } elseif ($role === 'teacher') {
        $teacher = find_one('teachers.json', 'email', $email);
        if ($teacher && password_verify($password, $teacher['password'])) {
            $_SESSION['uid']      = $teacher['id'];
            $_SESSION['role']     = 'teacher';
            $_SESSION['name']     = $teacher['name'];
            $_SESSION['class_id'] = $teacher['class_id'];
            header('Location: teacher_dashboard.php'); exit;
        } else { $error = 'Invalid email or password.'; }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AttendAI — Smart Attendance System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
  :root {
    --primary: #4f46e5; --primary-dark: #3730a3; --primary-light: #e0e7ff;
    --surface: #ffffff; --bg: #f1f5f9; --text: #0f172a; --muted: #64748b;
    --border: rgba(15,23,42,.1); --radius: 12px; --shadow: 0 4px 24px rgba(79,70,229,.1);
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Inter', sans-serif; background: var(--bg); min-height: 100vh;
         display: flex; align-items: center; justify-content: center; }
  .login-wrap { width: 100%; max-width: 440px; padding: 1rem; }
  .logo { display: flex; align-items: center; gap: .6rem; margin-bottom: 2rem; justify-content: center; }
  .logo-icon { width: 44px; height: 44px; background: var(--primary);
               border-radius: 12px; display: grid; place-items: center; }
  .logo-icon svg { color: white; }
  .logo-text { font-family: 'Space Grotesk', sans-serif; font-size: 1.5rem;
               font-weight: 700; color: var(--text); }
  .logo-text span { color: var(--primary); }
  .card { background: var(--surface); border-radius: 20px; padding: 2rem;
          box-shadow: var(--shadow); border: 1px solid var(--border); }
  .card-title { font-family: 'Space Grotesk', sans-serif; font-size: 1.3rem;
                font-weight: 700; color: var(--text); margin-bottom: .3rem; }
  .card-sub { color: var(--muted); font-size: .9rem; margin-bottom: 1.5rem; }
  .role-tabs { display: flex; gap: .5rem; margin-bottom: 1.5rem; background: var(--bg);
               padding: .3rem; border-radius: 10px; }
  .role-tab { flex: 1; padding: .5rem; border: none; background: transparent;
              border-radius: 8px; font-size: .85rem; font-weight: 500; color: var(--muted);
              cursor: pointer; transition: all .2s; }
  .role-tab.active { background: white; color: var(--primary); box-shadow: 0 1px 8px rgba(0,0,0,.08); }
  .form-label { font-size: .85rem; font-weight: 600; color: var(--text); margin-bottom: .4rem; }
  .form-control { border: 1.5px solid var(--border); border-radius: 10px; padding: .65rem 1rem;
                  font-size: .9rem; transition: border-color .2s; }
  .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79,70,229,.1);
                        outline: none; }
  .btn-primary { background: var(--primary); border: none; border-radius: 10px;
                 padding: .7rem 1.5rem; font-weight: 600; font-size: .95rem; width: 100%;
                 transition: background .2s, transform .1s; }
  .btn-primary:hover { background: var(--primary-dark); }
  .btn-primary:active { transform: scale(.99); }
  .alert-danger { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626;
                  border-radius: 10px; padding: .7rem 1rem; font-size: .85rem; margin-bottom: 1rem; }
  .divider { text-align: center; color: var(--muted); font-size: .8rem; margin: 1.2rem 0;
             position: relative; }
  .divider::before, .divider::after { content: ''; position: absolute; top: 50%;
    width: 40%; height: 1px; background: var(--border); }
  .divider::before { left: 0; } .divider::after { right: 0; }
  .register-link { text-align: center; font-size: .85rem; color: var(--muted); margin-top: 1rem; }
  .register-link a { color: var(--primary); font-weight: 600; text-decoration: none; }
  .register-link a:hover { text-decoration: underline; }
  .hero-bg { position: fixed; inset: 0; z-index: -1; overflow: hidden; }
  .hero-blob { position: absolute; border-radius: 50%; filter: blur(80px); opacity: .15; }
  .blob1 { width: 500px; height: 500px; background: var(--primary); top: -100px; right: -100px; }
  .blob2 { width: 400px; height: 400px; background: #06b6d4; bottom: -100px; left: -100px; }
</style>
</head>
<body>
<div class="hero-bg">
  <div class="hero-blob blob1"></div>
  <div class="hero-blob blob2"></div>
</div>

<div class="login-wrap">
  <div class="logo">
    <div class="logo-icon">
      <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
        <path d="M17 3l1.5 1.5L21 2" stroke-linecap="round"/>
      </svg>
    </div>
    <div class="logo-text">Attend<span>AI</span></div>
  </div>

  <div class="card">
    <div class="card-title">Welcome back</div>
    <div class="card-sub">Sign in to your AttendAI account</div>

    <div class="role-tabs">
      <button class="role-tab active" onclick="setRole('teacher', this)">
        <i class="bi bi-person-badge me-1"></i> Teacher
      </button>
      <button class="role-tab" onclick="setRole('admin', this)">
        <i class="bi bi-shield-check me-1"></i> Admin
      </button>
    </div>

    <?php if ($error): ?>
      <div class="alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="role" id="roleInput" value="teacher">
      <div class="mb-3">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control" placeholder="you@school.edu" required
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="mb-4">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
      </button>
    </form>

    <div class="divider">or</div>
    <div class="register-link">
      New here? <a href="register.php">Register as Student or Teacher</a>
    </div>
    <div class="register-link mt-2">
      <a href="take_attendance.php" style="color: #06b6d4;">
        <i class="bi bi-camera me-1"></i>Take Attendance (Face Scan)
      </a>
    </div>
  </div>
</div>

<script>
function setRole(role, el) {
  document.getElementById('roleInput').value = role;
  document.querySelectorAll('.role-tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
}
</script>
</body>
</html>