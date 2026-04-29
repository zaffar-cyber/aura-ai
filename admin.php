<?php
require_once 'config.php';
if (!is_logged() || sess()['role'] !== 'admin') { header('Location: index.php'); exit; }

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    // Add Class
    if ($act === 'add_class') {
        $cname    = trim($_POST['class_name'] ?? '');
        $semester = trim($_POST['semester']   ?? '');
        if ($cname) {
            add_item('classes.json', [
                'id'       => gen_id('CLS'),
                'name'     => $cname,
                'semester' => $semester,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
    // Delete class
    if ($act === 'del_class') {
        delete_item('classes.json', 'id', $_POST['id'] ?? '');
    }
    // Delete student
    if ($act === 'del_student') {
        delete_item('students.json', 'id', $_POST['id'] ?? '');
    }
    // Delete teacher
    if ($act === 'del_teacher') {
        delete_item('teachers.json', 'id', $_POST['id'] ?? '');
    }
    // Reset student face
    if ($act === 'reset_face') {
        update_item('students.json', 'id', $_POST['id'] ?? '', ['face_descriptor' => null]);
    }
    header('Location: admin.php'); exit;
}

$classes  = rj('classes.json');
$students = rj('students.json');
$teachers = rj('teachers.json');
$all_att  = rj('attendance.json');
$today    = date('Y-m-d');
$today_att = array_filter($all_att, fn($a) => $a['date'] === $today);

// Build student attendance % map
$att_map = [];
foreach ($students as $s) {
    $total   = count(array_filter($all_att, fn($a) => $a['student_id'] === $s['id']));
    $present = count(array_filter($all_att, fn($a) => $a['student_id'] === $s['id'] && $a['status'] === 'present'));
    $att_map[$s['id']] = $total > 0 ? round($present / $total * 100) : 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Panel — AuraAi</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
  :root { --primary:#4f46e5; --primary-dark:#3730a3; --primary-light:#e0e7ff;
          --bg:#f1f5f9; --surface:#fff; --text:#0f172a; --muted:#64748b; --border:rgba(15,23,42,.1); }
  body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--text); }
  /* Sidebar same as teacher */
  .sidebar { width:220px; min-height:100vh; background:var(--text); position:fixed;
             left:0; top:0; padding:1.5rem 1rem; display:flex; flex-direction:column; z-index:100; }
  .sidebar-logo { display:flex; align-items:center; gap:.6rem; margin-bottom:2rem; }
  .logo-icon { width:34px; height:34px; background:var(--primary); border-radius:9px;
               display:grid; place-items:center; flex-shrink:0; }
  .logo-text { font-family:'Space Grotesk',sans-serif; font-size:1rem; font-weight:700; color:white; }
  .logo-text span { color:#a5b4fc; }
  .nav-section { font-size:.68rem; font-weight:700; color:#475569; text-transform:uppercase;
                 letter-spacing:.08em; margin:1rem .4rem .4rem; }
  .nav-link { display:flex; align-items:center; gap:.6rem; padding:.55rem .8rem; border-radius:8px;
              color:#94a3b8; font-size:.88rem; font-weight:500; text-decoration:none; transition:all .2s; }
  .nav-link:hover { background:rgba(255,255,255,.07); color:white; }
  .nav-link.active { background:var(--primary); color:white; }
  .nav-link i { font-size:.95rem; width:16px; flex-shrink:0; }
  .sidebar-bottom { margin-top:auto; padding-top:1rem; border-top:1px solid rgba(255,255,255,.08); }
  /* Main */
  .main { margin-left:220px; padding:2rem; }
  .page-title { font-family:'Space Grotesk',sans-serif; font-size:1.4rem; font-weight:700; margin-bottom:2rem; }
  /* Tabs */
  .tab-bar { display:flex; gap:.5rem; margin-bottom:1.5rem; background:white;
             padding:.3rem; border-radius:12px; border:1px solid var(--border); width:fit-content; }
  .tab-btn { padding:.5rem 1.2rem; border:none; background:transparent; border-radius:8px;
             font-size:.85rem; font-weight:600; color:var(--muted); cursor:pointer; transition:all .2s; }
  .tab-btn.active { background:var(--primary); color:white; }
  /* Cards */
  .card { background:var(--surface); border-radius:14px; border:1px solid var(--border); padding:1.5rem; margin-bottom:1.5rem; }
  .card-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem; }
  .card-title { font-family:'Space Grotesk',sans-serif; font-size:1rem; font-weight:700; }
  /* Forms */
  .form-inline { display:flex; gap:.6rem; flex-wrap:wrap; align-items:flex-end; }
  .form-label { font-size:.82rem; font-weight:600; margin-bottom:.3rem; display:block; }
  .form-control, .form-select { border:1.5px solid var(--border); border-radius:8px;
                                padding:.5rem .9rem; font-size:.88rem; }
  .form-control:focus, .form-select:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(79,70,229,.1); outline:none; }
  .btn-primary { background:var(--primary); border:none; border-radius:8px; padding:.5rem 1.2rem;
                 font-size:.88rem; font-weight:600; color:white; cursor:pointer; transition:background .2s; }
  .btn-primary:hover { background:var(--primary-dark); }
  .btn-danger  { background:#ef4444; border:none; border-radius:6px; padding:.3rem .7rem;
                 font-size:.78rem; font-weight:600; color:white; cursor:pointer; }
  .btn-warning { background:#f59e0b; border:none; border-radius:6px; padding:.3rem .7rem;
                 font-size:.78rem; font-weight:600; color:white; cursor:pointer; }
  /* Table */
  .data-table { width:100%; border-collapse:collapse; font-size:.86rem; }
  .data-table th { padding:.65rem .8rem; text-align:left; font-size:.73rem; font-weight:700;
                   color:var(--muted); text-transform:uppercase; letter-spacing:.05em;
                   border-bottom:1px solid var(--border); }
  .data-table td { padding:.65rem .8rem; border-bottom:1px solid rgba(15,23,42,.04); }
  .data-table tr:last-child td { border-bottom:none; }
  .data-table tr:hover td { background:#f8fafc; }
  /* Badges */
  .badge-face-ok  { background:#ecfdf5; color:#065f46; padding:.2rem .6rem;
                    border-radius:99px; font-size:.73rem; font-weight:600; }
  .badge-face-no  { background:#fef2f2; color:#dc2626; padding:.2rem .6rem;
                    border-radius:99px; font-size:.73rem; font-weight:600; }
  .att-bar { height:6px; border-radius:99px; background:#e2e8f0; overflow:hidden; margin-top:3px; }
  .att-fill { height:100%; border-radius:99px; }
  /* KPI row */
  .kpi-row { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin-bottom:2rem; }
  .kpi { background:var(--surface); border-radius:12px; border:1px solid var(--border);
         padding:1.2rem; }
  .kpi-val { font-family:'Space Grotesk',sans-serif; font-size:1.8rem; font-weight:700; }
  .kpi-lbl { font-size:.75rem; color:var(--muted); margin-top:.2rem; }
  /* Tab panels */
  .tab-panel { display:none; }
  .tab-panel.active { display:block; }
  @media(max-width:768px){ .sidebar{display:none} .main{margin-left:0} .kpi-row{grid-template-columns:repeat(2,1fr)} }
</style>
</head>
<body>

<!-- Sidebar -->
<nav class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon">
      <svg width="16" height="16" fill="none" stroke="white" stroke-width="2.5" viewBox="0 0 24 24">
        <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
      </svg>
    </div>
    <div class="logo-text">Attend<span>AI</span></div>
  </div>
  <div class="nav-section">Admin</div>
  <a href="admin.php" class="nav-link active"><i class="bi bi-grid-1x2"></i> Overview</a>
  <div class="nav-section">Manage</div>
  <a href="#" onclick="showTab('classes')" class="nav-link"><i class="bi bi-building"></i> Classes</a>
  <a href="#" onclick="showTab('students')" class="nav-link"><i class="bi bi-mortarboard"></i> Students</a>
  <a href="#" onclick="showTab('teachers')" class="nav-link"><i class="bi bi-person-badge"></i> Teachers</a>
  <a href="#" onclick="showTab('attendance')" class="nav-link"><i class="bi bi-journal-check"></i> Attendance</a>
  <div class="sidebar-bottom">
    <a href="logout.php" class="nav-link"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
</nav>

<main class="main">
  <div class="page-title"><i class="bi bi-shield-check me-2" style="color:var(--primary)"></i>Admin Panel</div>

  <!-- KPIs -->
  <div class="kpi-row">
    <div class="kpi">
      <div class="kpi-val"><?= count($classes) ?></div>
      <div class="kpi-lbl"><i class="bi bi-building me-1"></i>Classes</div>
    </div>
    <div class="kpi">
      <div class="kpi-val"><?= count($students) ?></div>
      <div class="kpi-lbl"><i class="bi bi-mortarboard me-1"></i>Students</div>
    </div>
    <div class="kpi">
      <div class="kpi-val"><?= count($teachers) ?></div>
      <div class="kpi-lbl"><i class="bi bi-person-badge me-1"></i>Teachers</div>
    </div>
    <div class="kpi">
      <div class="kpi-val"><?= count($today_att) ?></div>
      <div class="kpi-lbl"><i class="bi bi-calendar-check me-1"></i>Today's Records</div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="tab-bar">
    <button class="tab-btn active" onclick="showTab('classes',this)">Classes</button>
    <button class="tab-btn" onclick="showTab('students',this)">Students</button>
    <button class="tab-btn" onclick="showTab('teachers',this)">Teachers</button>
    <button class="tab-btn" onclick="showTab('attendance',this)">Attendance Log</button>
  </div>

  <!-- ── Classes Tab ── -->
  <div class="tab-panel active" id="tab-classes">
    <div class="card">
      <div class="card-head">
        <div class="card-title"><i class="bi bi-plus-circle me-2" style="color:var(--primary)"></i>Add Class</div>
      </div>
      <form method="POST" class="form-inline">
        <input type="hidden" name="act" value="add_class">
        <div>
          <label class="form-label">Class Name</label>
          <input type="text" name="class_name" class="form-control" placeholder="e.g. CS-A" required style="width:160px">
        </div>
        <div>
          <label class="form-label">Semester</label>
          <select name="semester" class="form-select" style="width:130px">
            <?php for($s=1;$s<=8;$s++): ?>
              <option value="<?= $s ?>">Semester <?= $s ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div style="padding-top:1.4rem">
          <button type="submit" class="btn-primary"><i class="bi bi-plus me-1"></i>Add Class</button>
        </div>
      </form>
    </div>
    <div class="card">
      <div class="card-title" style="margin-bottom:1rem">All Classes (<?= count($classes) ?>)</div>
      <table class="data-table">
        <thead><tr><th>ID</th><th>Name</th><th>Semester</th><th>Students</th><th>Created</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($classes as $cls):
            $stu_count = count(find_many('students.json', 'class_id', $cls['id']));
          ?>
          <tr>
            <td style="font-family:monospace;font-size:.8rem;color:var(--muted)"><?= $cls['id'] ?></td>
            <td style="font-weight:600"><?= htmlspecialchars($cls['name']) ?></td>
            <td>Sem <?= $cls['semester'] ?></td>
            <td><?= $stu_count ?> students</td>
            <td style="color:var(--muted)"><?= date('M j, Y', strtotime($cls['created_at'])) ?></td>
            <td>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete this class?')">
                <input type="hidden" name="act" value="del_class">
                <input type="hidden" name="id"  value="<?= $cls['id'] ?>">
                <button type="submit" class="btn-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$classes): ?>
          <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem">No classes yet. Add one above.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── Students Tab ── -->
  <div class="tab-panel" id="tab-students">
    <div class="card">
      <div class="card-title" style="margin-bottom:1rem">All Students (<?= count($students) ?>)</div>
      <table class="data-table">
        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Class</th><th>Face</th><th>Attendance</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($students as $stu):
            $pct = $att_map[$stu['id']] ?? 0;
            $color = $pct >= 75 ? '#10b981' : ($pct >= 50 ? '#f59e0b' : '#ef4444');
          ?>
          <tr>
            <td style="font-family:monospace;font-size:.78rem;color:var(--muted)"><?= $stu['id'] ?></td>
            <td style="font-weight:600"><?= htmlspecialchars($stu['name']) ?></td>
            <td style="color:var(--muted)"><?= htmlspecialchars($stu['email']) ?></td>
            <td><?= htmlspecialchars($stu['class_id']) ?></td>
            <td>
              <?php if ($stu['face_descriptor']): ?>
                <span class="badge-face-ok"><i class="bi bi-check2 me-1"></i>Registered</span>
              <?php else: ?>
                <span class="badge-face-no"><i class="bi bi-x me-1"></i>Not Set</span>
              <?php endif; ?>
            </td>
            <td style="min-width:90px">
              <div style="font-size:.78rem;font-weight:700;color:<?= $color ?>"><?= $pct ?>%</div>
              <div class="att-bar"><div class="att-fill" style="width:<?= $pct ?>%;background:<?= $color ?>"></div></div>
            </td>
            <td style="display:flex;gap:.4rem">
              <?php if ($stu['face_descriptor']): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="act" value="reset_face">
                <input type="hidden" name="id"  value="<?= $stu['id'] ?>">
                <button type="submit" class="btn-warning" title="Reset Face"><i class="bi bi-camera-video-off"></i></button>
              </form>
              <?php endif; ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete student?')">
                <input type="hidden" name="act" value="del_student">
                <input type="hidden" name="id"  value="<?= $stu['id'] ?>">
                <button type="submit" class="btn-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$students): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem">No students registered yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── Teachers Tab ── -->
  <div class="tab-panel" id="tab-teachers">
    <div class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
        <div class="card-title">All Teachers (<?= count($teachers) ?>)</div>
        <a href="register.php" class="btn-primary" style="text-decoration:none;font-size:.85rem">
          <i class="bi bi-person-plus me-1"></i>Register Teacher
        </a>
      </div>
      <table class="data-table">
        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Class</th><th>Semester</th><th>Face</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($teachers as $tch): ?>
          <tr>
            <td style="font-family:monospace;font-size:.78rem;color:var(--muted)"><?= $tch['id'] ?></td>
            <td style="font-weight:600"><?= htmlspecialchars($tch['name']) ?></td>
            <td style="color:var(--muted)"><?= htmlspecialchars($tch['email']) ?></td>
            <td><?= htmlspecialchars($tch['class_id']) ?></td>
            <td>Sem <?= $tch['semester'] ?></td>
            <td>
              <?php if ($tch['face_descriptor']): ?>
                <span class="badge-face-ok"><i class="bi bi-check2 me-1"></i>Set</span>
              <?php else: ?>
                <span class="badge-face-no"><i class="bi bi-x me-1"></i>None</span>
              <?php endif; ?>
            </td>
            <td>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete teacher?')">
                <input type="hidden" name="act" value="del_teacher">
                <input type="hidden" name="id"  value="<?= $tch['id'] ?>">
                <button type="submit" class="btn-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$teachers): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem">No teachers yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── Attendance Log Tab ── -->
  <div class="tab-panel" id="tab-attendance">
    <div class="card">
      <div class="card-title" style="margin-bottom:1rem">Full Attendance Log (<?= count($all_att) ?> records)</div>
      <table class="data-table">
        <thead>
          <tr><th>Date</th><th>Student</th><th>Class</th><th>Status</th><th>Method</th><th>Confidence</th><th>Time</th></tr>
        </thead>
        <tbody>
          <?php
          $log = array_reverse($all_att);
          foreach (array_slice($log, 0, 100) as $rec):
            $stu = find_one('students.json', 'id', $rec['student_id']);
          ?>
          <tr>
            <td style="color:var(--muted)"><?= $rec['date'] ?></td>
            <td style="font-weight:600"><?= htmlspecialchars($stu['name'] ?? $rec['student_id']) ?></td>
            <td><?= htmlspecialchars($rec['class_id']) ?></td>
            <td>
              <?php if ($rec['status'] === 'present'): ?>
                <span class="badge-face-ok">Present</span>
              <?php else: ?>
                <span class="badge-face-no">Absent</span>
              <?php endif; ?>
            </td>
            <td style="text-transform:capitalize;color:var(--muted)"><?= $rec['method'] ?></td>
            <td>
              <?php if ($rec['confidence'] > 0): ?>
                <span style="color:#4f46e5;font-weight:600"><?= $rec['confidence'] ?>%</span>
              <?php else: ?>
                <span style="color:var(--muted)">—</span>
              <?php endif; ?>
            </td>
            <td style="color:var(--muted);font-size:.8rem"><?= date('h:i A', strtotime($rec['marked_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$all_att): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem">No attendance records yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<script>
function showTab(name, btn) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  if (btn) btn.classList.add('active');
}
</script>
</body>
</html>