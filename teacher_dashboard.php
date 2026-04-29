<?php
require_once 'config.php';
require_role('teacher');

$teacher    = find_one('teachers.json', 'id', sess()['uid']);
$class_id   = $teacher['class_id'] ?? '';
$students   = find_many('students.json', 'class_id', $class_id);
$all_att    = rj('attendance.json');
$today      = date('Y-m-d');

// Stats
$today_att  = array_filter($all_att, fn($a) => $a['class_id'] === $class_id && $a['date'] === $today);
$present    = count(array_filter($today_att, fn($a) => $a['status'] === 'present'));
$absent     = count(array_filter($today_att, fn($a) => $a['status'] === 'absent'));
$total      = count($students);

// Active attendance window
$windows    = rj('attendance_windows.json');
$active_win = null;
foreach ($windows as $w) {
    if ($w['class_id'] === $class_id && $w['status'] === 'open') { $active_win = $w; break; }
}

// Recent attendance (last 7 days)
$week_labels = [];
$week_present = [];
for ($d = 6; $d >= 0; $d--) {
    $day = date('Y-m-d', strtotime("-$d days"));
    $week_labels[] = date('D', strtotime($day));
    $day_att = array_filter($all_att, fn($a) => $a['class_id'] === $class_id && $a['date'] === $day && $a['status'] === 'present');
    $week_present[] = count($day_att);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — AuraAi</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
  :root { --primary:#4f46e5; --primary-dark:#3730a3; --primary-light:#e0e7ff;
          --bg:#f1f5f9; --surface:#fff; --text:#0f172a; --muted:#64748b;
          --success:#10b981; --danger:#ef4444; --warning:#f59e0b;
          --border:rgba(15,23,42,.1); }
  body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--text); }
  /* Sidebar */
  .sidebar { width:240px; min-height:100vh; background:var(--text); position:fixed; left:0; top:0;
             padding:1.5rem 1rem; display:flex; flex-direction:column; z-index:100; }
  .sidebar-logo { display:flex; align-items:center; gap:.6rem; margin-bottom:2rem; padding:.5rem; }
  .logo-icon { width:36px; height:36px; background:var(--primary); border-radius:9px;
               display:grid; place-items:center; flex-shrink:0; }
  .logo-text { font-family:'Space Grotesk',sans-serif; font-size:1.1rem; font-weight:700; color:white; }
  .logo-text span { color:#a5b4fc; }
  .nav-section { font-size:.7rem; font-weight:700; color:#475569; text-transform:uppercase;
                 letter-spacing:.08em; margin:1rem .5rem .5rem; }
  .nav-link { display:flex; align-items:center; gap:.7rem; padding:.6rem .8rem; border-radius:10px;
              color:#94a3b8; font-size:.9rem; font-weight:500; text-decoration:none; transition:all .2s; }
  .nav-link:hover { background:rgba(255,255,255,.06); color:white; }
  .nav-link.active { background:var(--primary); color:white; }
  .nav-link i { font-size:1rem; width:16px; flex-shrink:0; }
  .sidebar-bottom { margin-top:auto; padding-top:1rem; border-top:1px solid rgba(255,255,255,.08); }
  .user-chip { display:flex; align-items:center; gap:.6rem; padding:.6rem .8rem; }
  .user-avatar { width:32px; height:32px; background:var(--primary); border-radius:50%;
                 display:grid; place-items:center; color:white; font-weight:700; font-size:.85rem; }
  .user-name { font-size:.85rem; color:#e2e8f0; font-weight:500; }
  .user-role { font-size:.7rem; color:#64748b; }
  /* Main */
  .main { margin-left:240px; padding:2rem; }
  .page-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:2rem; }
  .page-title { font-family:'Space Grotesk',sans-serif; font-size:1.4rem; font-weight:700; }
  .page-sub   { color:var(--muted); font-size:.9rem; margin-top:.2rem; }
  /* KPIs */
  .kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1rem; margin-bottom:2rem; }
  .kpi-card { background:var(--surface); border-radius:14px; padding:1.3rem;
              border:1px solid var(--border); }
  .kpi-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:.6rem; }
  .kpi-icon { width:40px; height:40px; border-radius:10px; display:grid; place-items:center; font-size:1.1rem; }
  .kpi-label { font-size:.75rem; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; }
  .kpi-value { font-family:'Space Grotesk',sans-serif; font-size:1.8rem; font-weight:700; }
  .kpi-sub   { font-size:.78rem; color:var(--muted); margin-top:.2rem; }
  /* Cards */
  .section-card { background:var(--surface); border-radius:14px; border:1px solid var(--border);
                  padding:1.5rem; margin-bottom:1.5rem; }
  .section-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem; }
  .section-title { font-family:'Space Grotesk',sans-serif; font-size:1rem; font-weight:700; }
  /* Table */
  .att-table { width:100%; border-collapse:collapse; font-size:.88rem; }
  .att-table th { padding:.7rem .8rem; text-align:left; font-size:.75rem; font-weight:700;
                  color:var(--muted); text-transform:uppercase; letter-spacing:.05em;
                  border-bottom:1px solid var(--border); }
  .att-table td { padding:.7rem .8rem; border-bottom:1px solid rgba(15,23,42,.04); }
  .att-table tr:last-child td { border-bottom:none; }
  .att-table tr:hover td { background:var(--bg); }
  .badge-present { background:#ecfdf5; color:#065f46; padding:.2rem .6rem;
                   border-radius:99px; font-size:.75rem; font-weight:600; }
  .badge-absent  { background:#fef2f2; color:#dc2626; padding:.2rem .6rem;
                   border-radius:99px; font-size:.75rem; font-weight:600; }
  .badge-pending { background:#fff7ed; color:#c2410c; padding:.2rem .6rem;
                   border-radius:99px; font-size:.75rem; font-weight:600; }
  /* Window status banner */
  .window-banner { border-radius:14px; padding:1rem 1.5rem; margin-bottom:1.5rem;
                   display:flex; align-items:center; justify-content:space-between; }
  .window-banner.open   { background:#ecfdf5; border:1.5px solid #6ee7b7; }
  .window-banner.closed { background:#fff7ed; border:1.5px solid #fed7aa; }
  .btn-sm-primary { background:var(--primary); border:none; color:white; border-radius:8px;
                    padding:.4rem 1rem; font-size:.85rem; font-weight:600; cursor:pointer; transition:background .2s; }
  .btn-sm-primary:hover { background:var(--primary-dark); }
  .btn-sm-danger  { background:#ef4444; border:none; color:white; border-radius:8px;
                    padding:.4rem 1rem; font-size:.85rem; font-weight:600; cursor:pointer; }
  .btn-sm-danger:hover { background:#dc2626; }
  @media(max-width:768px){ .sidebar{display:none} .main{margin-left:0} }
</style>
</head>
<body>

<!-- Sidebar -->
<nav class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon">
      <svg width="18" height="18" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">
        <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
      </svg>
    </div>
    <div class="logo-text">Attend<span>AI</span></div>
  </div>

  <div class="nav-section">Main</div>
  <a href="teacher_dashboard.php" class="nav-link active"><i class="bi bi-speedometer2"></i> Dashboard</a>
  <a href="attendance_window.php" class="nav-link"><i class="bi bi-clock-history"></i> Attendance Window</a>
  <a href="take_attendance.php" class="nav-link"><i class="bi bi-camera-video"></i> Face Scanner</a>

  <div class="nav-section">Management</div>
  <a href="teacher_dashboard.php?view=students" class="nav-link"><i class="bi bi-people"></i> Students</a>
  <a href="teacher_dashboard.php?view=history" class="nav-link"><i class="bi bi-journal-text"></i> History</a>

  <div class="sidebar-bottom">
    <div class="user-chip">
      <div class="user-avatar"><?= strtoupper(substr(sess()['name'], 0, 1)) ?></div>
      <div>
        <div class="user-name"><?= htmlspecialchars(sess()['name']) ?></div>
        <div class="user-role">Teacher</div>
      </div>
    </div>
    <a href="logout.php" class="nav-link mt-1"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </div>
</nav>

<!-- Main Content -->
<main class="main">
  <div class="page-head">
    <div>
      <div class="page-title">Good <?= date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening') ?>, <?= htmlspecialchars(explode(' ', sess()['name'])[0]) ?>!</div>
      <div class="page-sub"><?= date('l, F j, Y') ?> &mdash; <?= htmlspecialchars($teacher['semester'] ?? 'Semester ?') ?></div>
    </div>
    <a href="attendance_window.php" class="btn-sm-primary">
      <i class="bi bi-plus-circle me-1"></i>Open Attendance
    </a>
  </div>

  <!-- Attendance Window Banner -->
  <?php if ($active_win): ?>
  <div class="window-banner open">
    <div>
      <strong style="color:#065f46"><i class="bi bi-broadcast me-2"></i>Attendance is OPEN</strong>
      <div style="font-size:.85rem;color:#047857;margin-top:.2rem">
        Window: <?= $active_win['from_time'] ?> – <?= $active_win['to_time'] ?>
      </div>
    </div>
    <form method="POST" action="api/set_window.php">
      <input type="hidden" name="action" value="close">
      <input type="hidden" name="window_id" value="<?= $active_win['id'] ?>">
      <button type="submit" class="btn-sm-danger">
        <i class="bi bi-stop-circle me-1"></i>Close Window
      </button>
    </form>
  </div>
  <?php else: ?>
  <div class="window-banner closed">
    <div>
      <strong style="color:#c2410c"><i class="bi bi-clock me-2"></i>No Active Attendance Window</strong>
      <div style="font-size:.85rem;color:#9a3412;margin-top:.2rem">Students cannot mark attendance right now.</div>
    </div>
    <a href="attendance_window.php" class="btn-sm-primary">
      <i class="bi bi-play-circle me-1"></i>Open Now
    </a>
  </div>
  <?php endif; ?>

  <!-- KPIs -->
  <div class="kpi-grid">
    <div class="kpi-card">
      <div class="kpi-top">
        <div class="kpi-label">Total Students</div>
        <div class="kpi-icon" style="background:#e0e7ff;color:var(--primary)"><i class="bi bi-people-fill"></i></div>
      </div>
      <div class="kpi-value"><?= $total ?></div>
      <div class="kpi-sub">In your class</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-top">
        <div class="kpi-label">Present Today</div>
        <div class="kpi-icon" style="background:#ecfdf5;color:#10b981"><i class="bi bi-person-check-fill"></i></div>
      </div>
      <div class="kpi-value" style="color:#10b981"><?= $present ?></div>
      <div class="kpi-sub"><?= $total > 0 ? round($present/$total*100) : 0 ?>% attendance rate</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-top">
        <div class="kpi-label">Absent Today</div>
        <div class="kpi-icon" style="background:#fef2f2;color:#ef4444"><i class="bi bi-person-x-fill"></i></div>
      </div>
      <div class="kpi-value" style="color:#ef4444"><?= $absent ?></div>
      <div class="kpi-sub">Emails sent to parents</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-top">
        <div class="kpi-label">Pending</div>
        <div class="kpi-icon" style="background:#fff7ed;color:#f59e0b"><i class="bi bi-hourglass-split"></i></div>
      </div>
      <div class="kpi-value" style="color:#f59e0b"><?= max(0, $total - $present - $absent) ?></div>
      <div class="kpi-sub">Not yet scanned</div>
    </div>
  </div>

  <!-- Chart + Student List -->
  <div class="row g-3">
    <div class="col-lg-5">
      <div class="section-card">
        <div class="section-head">
          <div class="section-title">7-Day Attendance</div>
        </div>
        <canvas id="weekChart" height="180"></canvas>
      </div>
    </div>
    <div class="col-lg-7">
      <div class="section-card">
        <div class="section-head">
          <div class="section-title">Today's Attendance</div>
          <span style="font-size:.8rem;color:var(--muted)"><?= date('M j') ?></span>
        </div>
        <table class="att-table">
          <thead>
            <tr>
              <th>Student</th><th>Time</th><th>Status</th><th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $shown = 0;
            foreach ($students as $stu):
              $rec = null;
              foreach ($today_att as $a) { if ($a['student_id'] === $stu['id']) { $rec = $a; break; } }
              $status = $rec ? $rec['status'] : 'pending';
              $time   = $rec ? date('h:i A', strtotime($rec['marked_at'])) : '—';
              if ($shown++ >= 10) break;
            ?>
            <tr>
              <td>
                <div style="font-weight:600"><?= htmlspecialchars($stu['name']) ?></div>
                <div style="font-size:.75rem;color:var(--muted)"><?= htmlspecialchars($stu['id']) ?></div>
              </td>
              <td style="color:var(--muted)"><?= $time ?></td>
              <td>
                <?php if ($status === 'present'): ?>
                  <span class="badge-present"><i class="bi bi-check2 me-1"></i>Present</span>
                <?php elseif ($status === 'absent'): ?>
                  <span class="badge-absent"><i class="bi bi-x me-1"></i>Absent</span>
                <?php else: ?>
                  <span class="badge-pending"><i class="bi bi-clock me-1"></i>Pending</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($status !== 'present'): ?>
                <button onclick="manualMark('<?= $stu['id'] ?>', '<?= $class_id ?>')"
                        style="font-size:.75rem;color:var(--primary);background:none;border:none;cursor:pointer;font-weight:600">
                  Mark Present
                </button>
                <?php else: ?>
                <span style="font-size:.75rem;color:var(--muted)">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$students): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--muted);padding:2rem">
              No students in this class. <a href="admin.php">Add students →</a>
            </td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<script>
// Chart
new Chart(document.getElementById('weekChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($week_labels) ?>,
    datasets: [{
      label: 'Present',
      data: <?= json_encode($week_present) ?>,
      backgroundColor: '#4f46e5cc',
      borderRadius: 8,
      borderSkipped: false,
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, max: Math.max(<?= $total ?>, 5),
           grid: { color: 'rgba(0,0,0,.05)' }, ticks: { stepSize: 1 } },
      x: { grid: { display: false } }
    }
  }
});

// Manual mark present
async function manualMark(studentId, classId) {
  if (!confirm('Mark this student as present?')) return;
  const res = await fetch('api/mark_attendance.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ student_id: studentId, class_id: classId, method: 'manual', status: 'present' })
  });
  const d = await res.json();
  if (d.success) location.reload();
  else alert('Error: ' + (d.error || 'Unknown'));
}
</script>
</body>
</html>