<?php
require_once 'config.php';
require_role('teacher');

$teacher  = find_one('teachers.json', 'id', sess()['uid']);
$success  = '';
$error    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $from_time = $_POST['from_time'] ?? '';
    $to_time   = $_POST['to_time']   ?? '';
    $subject   = trim($_POST['subject']  ?? 'Class Session');
    $class_id  = trim($_POST['class_id'] ?? $teacher['class_id'] ?? '');
    $lat       = (float)($_POST['lat']   ?? 0);
    $lng       = (float)($_POST['lng']   ?? 0);

    if (!$class_id) {
        $error = 'Please select a class.';
        goto done;
    }
    if (!$from_time || !$to_time) {
        $error = 'Please set both from and to time.';
        goto done;
    }

    // Close any existing open windows for this class
    $windows = rj('attendance_windows.json');
    foreach ($windows as &$w) {
        if ($w['class_id'] === $class_id && $w['status'] === 'open') {
            $w['status']    = 'closed';
            $w['closed_at'] = date('Y-m-d H:i:s');
        }
    }
    unset($w);
    wj('attendance_windows.json', $windows);

    // Create new window
    $win_id = gen_id('WIN');
    add_item('attendance_windows.json', [
        'id'         => $win_id,
        'class_id'   => $class_id,
        'teacher_id' => sess()['uid'],
        'subject'    => $subject,
        'date'       => date('Y-m-d'),
        'from_time'  => $from_time,
        'to_time'    => $to_time,
        'lat'        => $lat,
        'lng'        => $lng,
        'status'     => 'open',
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    // Also update teacher's class_id if they had DEFAULT
    if (($teacher['class_id'] ?? '') === 'DEFAULT' || empty($teacher['class_id'])) {
        update_item('teachers.json', 'id', sess()['uid'], ['class_id' => $class_id]);
        $_SESSION['class_id'] = $class_id;
    }

    $student_count = count(find_many('students.json', 'class_id', $class_id));
    $success = "✅ Attendance window opened for <strong>$subject</strong> ($from_time – $to_time). 
                <strong>$student_count students</strong> in this class.";
    done:
}

$all_classes = rj('classes.json');

// Add DEFAULT as option if no classes exist
if (!$all_classes) {
    $all_classes = [['id' => 'DEFAULT', 'name' => 'Default Class', 'semester' => '1']];
}

// Recent windows
$all_windows   = rj('attendance_windows.json');
$my_windows    = array_filter($all_windows, fn($w) => $w['teacher_id'] === sess()['uid']);
$recent_windows = array_slice(array_reverse(array_values($my_windows)), 0, 10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Attendance Window — AuraAi</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root {
  --primary:#4f46e5; --primary-dark:#3730a3; --primary-light:#e0e7ff;
  --bg:#f1f5f9; --surface:#fff; --text:#0f172a; --muted:#64748b; --border:rgba(15,23,42,.1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text)}
.topbar{background:var(--surface);border-bottom:1px solid var(--border);
        padding:.85rem 1.5rem;display:flex;align-items:center;gap:1rem;
        position:sticky;top:0;z-index:50}
.logo{display:flex;align-items:center;gap:.5rem;text-decoration:none}
.logo-icon{width:30px;height:30px;background:var(--primary);border-radius:8px;display:grid;place-items:center}
.logo-text{font-family:'Space Grotesk',sans-serif;font-size:.95rem;font-weight:700;color:var(--text)}
.logo-text span{color:var(--primary)}
.back-link{margin-left:auto;color:var(--primary);text-decoration:none;font-size:.85rem;font-weight:600}
.main{max-width:780px;margin:2rem auto;padding:0 1rem}
.card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:1.8rem;margin-bottom:1.5rem;
      box-shadow:0 1px 8px rgba(79,70,229,.05)}
.card-title{font-family:'Space Grotesk',sans-serif;font-size:1.05rem;font-weight:700;
            margin-bottom:1.4rem;display:flex;align-items:center;gap:.5rem}
.form-label{font-size:.83rem;font-weight:600;margin-bottom:.3rem;display:block}
.form-control,.form-select{border:1.5px solid var(--border);border-radius:10px;
  padding:.62rem 1rem;font-size:.9rem;width:100%;outline:none;font-family:'Inter',sans-serif;
  transition:border-color .2s,box-shadow .2s}
.form-control:focus,.form-select:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(79,70,229,.1)}
.btn-primary{background:var(--primary);border:none;border-radius:10px;padding:.7rem 2rem;
             font-weight:700;font-size:.95rem;color:white;cursor:pointer;
             transition:background .2s;display:inline-flex;align-items:center;gap:.5rem}
.btn-primary:hover{background:var(--primary-dark)}
.btn-danger{background:#ef4444;border:none;border-radius:8px;padding:.4rem 1rem;
            font-size:.82rem;font-weight:600;color:white;cursor:pointer}
.btn-danger:hover{background:#dc2626}
.alert{border-radius:10px;padding:.85rem 1rem;font-size:.88rem;margin-bottom:1.2rem;
       display:flex;align-items:flex-start;gap:.5rem}
.alert-success{background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46}
.alert-danger{background:#fef2f2;border:1px solid #fecaca;color:#dc2626}
.loc-box{background:var(--bg);border-radius:12px;padding:1rem;margin-top:1rem}
.loc-status{font-size:.85rem;font-weight:600;display:flex;align-items:center;gap:.4rem;margin-top:.5rem}
.loc-status.ok{color:#10b981} .loc-status.err{color:#ef4444} .loc-status.warn{color:#f59e0b}
.window-row{display:flex;align-items:center;justify-content:space-between;
            padding:.85rem 0;border-bottom:1px solid var(--border)}
.window-row:last-child{border-bottom:none}
.pill{padding:.2rem .7rem;border-radius:99px;font-size:.72rem;font-weight:700}
.pill-open{background:#ecfdf5;color:#065f46}
.pill-closed{background:#f1f5f9;color:var(--muted)}
.student-count-badge{background:var(--primary-light);color:var(--primary);
  font-size:.78rem;font-weight:700;padding:.2rem .7rem;border-radius:99px;margin-left:.5rem}
.class-preview{background:var(--primary-light);border:1px solid rgba(79,70,229,.2);
  border-radius:12px;padding:1rem;margin-top:1rem;display:none}
.class-preview.show{display:block}
</style>
</head>
<body>

<div class="topbar">
  <a href="index.php" class="logo">
    <div class="logo-icon">
      <svg width="15" height="15" fill="none" stroke="white" stroke-width="2.5" viewBox="0 0 24 24">
        <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
      </svg>
    </div>
    <div class="logo-text">Attend<span>AI</span></div>
  </a>
  <span style="color:var(--muted);font-size:.9rem">/ Attendance Window</span>
  <a href="teacher_dashboard.php" class="back-link">
    <i class="bi bi-arrow-left me-1"></i>Dashboard
  </a>
</div>

<div class="main">

  <?php if ($error): ?>
    <div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success">
      <i class="bi bi-check-circle-fill"></i>
      <div>
        <?= $success ?>
        <br><a href="take_attendance.php" style="font-weight:700;color:#065f46">
          <i class="bi bi-camera-video me-1"></i>Open Face Scanner →
        </a>
      </div>
    </div>
  <?php endif; ?>

  <!-- Open Window Form -->
  <div class="card">
    <div class="card-title">
      <i class="bi bi-play-circle" style="color:var(--primary)"></i>
      Open Attendance Window
    </div>

    <form method="POST" id="windowForm">
      <input type="hidden" name="lat" id="latField" value="0">
      <input type="hidden" name="lng" id="lngField" value="0">

      <div class="row g-3">

        <!-- Class Selector — THE KEY FIX -->
        <div class="col-12">
          <label class="form-label">
            Select Class *
            <span id="studentCountBadge" class="student-count-badge" style="display:none"></span>
          </label>
          <select name="class_id" id="classSelect" class="form-select" onchange="onClassChange(this)" required>
            <option value="">-- Select a class --</option>
            <?php foreach ($all_classes as $cls):
              $stu_in_class = count(find_many('students.json', 'class_id', $cls['id']));
              $isTeacherClass = ($cls['id'] === ($teacher['class_id'] ?? ''));
            ?>
              <option value="<?= $cls['id'] ?>"
                      data-students="<?= $stu_in_class ?>"
                      <?= $isTeacherClass ? 'selected' : '' ?>>
                <?= htmlspecialchars($cls['name']) ?> — Sem <?= $cls['semester'] ?>
                (<?= $stu_in_class ?> students)
              </option>
            <?php endforeach; ?>
          </select>

          <!-- Preview of students in selected class -->
          <div class="class-preview" id="classPreview">
            <div id="classPreviewContent"></div>
          </div>

          <?php if (!$all_classes || count($all_classes) <= 1 && $all_classes[0]['id'] === 'DEFAULT'): ?>
            <p style="font-size:.8rem;color:#c2410c;margin-top:.4rem">
              <i class="bi bi-exclamation-triangle me-1"></i>
              No classes created yet.
              <a href="admin.php" style="font-weight:700">Create a class in Admin Panel →</a>
            </p>
          <?php endif; ?>
        </div>

        <div class="col-md-5">
          <label class="form-label">Subject / Session Name</label>
          <input type="text" name="subject" class="form-control"
                 placeholder="e.g. Mathematics, Lab Session" value="Class Session">
        </div>
        <div class="col-md-3">
          <label class="form-label">From Time</label>
          <input type="time" name="from_time" class="form-control"
                 value="<?= date('H:i') ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">To Time</label>
          <input type="time" name="to_time" class="form-control"
                 value="<?= date('H:i', strtotime('+1 hour')) ?>" required>
        </div>
      </div>

      <!-- Location -->
      <div class="loc-box">
        <div style="display:flex;align-items:center;justify-content:space-between">
          <span style="font-size:.88rem;font-weight:700">
            <i class="bi bi-geo-alt-fill me-1" style="color:var(--primary)"></i>
            Classroom Location (optional)
          </span>
          <button type="button" onclick="captureLocation()" id="locBtn"
                  style="background:var(--primary);border:none;border-radius:8px;
                         color:white;padding:.4rem 1rem;font-size:.82rem;font-weight:600;cursor:pointer">
            <i class="bi bi-crosshair me-1"></i>Capture Location
          </button>
        </div>
        <div class="loc-status warn" id="locStatus">
          <i class="bi bi-info-circle"></i>
          Click to capture GPS location of classroom.
        </div>
      </div>

      <div class="mt-4">
        <button type="submit" class="btn-primary">
          <i class="bi bi-broadcast"></i>Open Attendance Window
        </button>
      </div>
    </form>
  </div>

  <!-- Recent Windows -->
  <div class="card">
    <div class="card-title">
      <i class="bi bi-journal-text" style="color:var(--primary)"></i>
      Recent Windows
    </div>

    <?php if (!$recent_windows): ?>
      <p style="color:var(--muted);font-size:.9rem">No attendance windows created yet.</p>
    <?php else: ?>
      <?php foreach ($recent_windows as $w):
        $stu_count_w = count(find_many('students.json', 'class_id', $w['class_id']));
        $present_w   = count(array_filter(rj('attendance.json'), fn($a) =>
          $a['window_id'] === $w['id'] && $a['status'] === 'present'));
      ?>
      <div class="window-row">
        <div>
          <div style="font-weight:600">
            <?= htmlspecialchars($w['subject']) ?>
            <span class="student-count-badge"><?= $present_w ?>/<?= $stu_count_w ?> present</span>
          </div>
          <div style="font-size:.8rem;color:var(--muted);margin-top:.2rem">
            <?= $w['date'] ?> &nbsp;|&nbsp;
            <?= $w['from_time'] ?> – <?= $w['to_time'] ?> &nbsp;|&nbsp;
            Class: <strong><?= htmlspecialchars($w['class_id']) ?></strong>
            <?php if ($w['lat'] ?? 0): ?>
              &nbsp;<i class="bi bi-geo-alt-fill" style="color:var(--primary)"></i>Location set
            <?php endif; ?>
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:.6rem">
          <span class="pill pill-<?= $w['status'] ?>"><?= ucfirst($w['status']) ?></span>
          <?php if ($w['status'] === 'open'): ?>
            <a href="take_attendance.php?class_id=<?= urlencode($w['class_id']) ?>"
               style="background:#4f46e5;border-radius:8px;color:white;padding:.35rem .8rem;
                      font-size:.78rem;font-weight:700;text-decoration:none">
              <i class="bi bi-camera-video me-1"></i>Scanner
            </a>
            <form method="POST" action="api/set_window.php" style="display:inline">
              <input type="hidden" name="action"    value="close">
              <input type="hidden" name="window_id" value="<?= $w['id'] ?>">
              <button type="submit" class="btn-danger">
                <i class="bi bi-stop-circle me-1"></i>Close
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>

<script>
// Show student count when class is selected
function onClassChange(sel) {
  const opt     = sel.options[sel.selectedIndex];
  const count   = opt.dataset.students || 0;
  const badge   = document.getElementById('studentCountBadge');
  const preview = document.getElementById('classPreview');
  const content = document.getElementById('classPreviewContent');

  if (sel.value && sel.value !== '') {
    badge.textContent = count + ' students';
    badge.style.display = 'inline';

    if (count == 0) {
      preview.className = 'class-preview show';
      content.innerHTML = `<span style="color:#c2410c;font-size:.85rem">
        <i class="bi bi-exclamation-triangle me-1"></i>
        No students in this class yet. 
        <a href="register.php" style="font-weight:700;color:#c2410c">Register students →</a>
      </span>`;
    } else {
      preview.className = 'class-preview show';
      content.innerHTML = `<span style="color:var(--primary);font-size:.85rem;font-weight:600">
        <i class="bi bi-people-fill me-1"></i>
        ${count} student(s) will be tracked in this session.
      </span>`;
    }
  } else {
    badge.style.display = 'none';
    preview.className   = 'class-preview';
  }
}

// Trigger on page load to show count for pre-selected class
window.addEventListener('load', () => {
  const sel = document.getElementById('classSelect');
  if (sel.value) onClassChange(sel);
});

// Location capture
function captureLocation() {
  const btn = document.getElementById('locBtn');
  btn.textContent = 'Getting...';
  btn.disabled    = true;

  if (!navigator.geolocation) {
    setLocStatus('Geolocation not supported.', 'err');
    btn.disabled = false; return;
  }

  navigator.geolocation.getCurrentPosition(
    pos => {
      const { latitude: lat, longitude: lng, accuracy } = pos.coords;
      document.getElementById('latField').value = lat;
      document.getElementById('lngField').value = lng;
      setLocStatus(`✓ Location captured! Accuracy: ±${Math.round(accuracy)}m`, 'ok');
      btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Location Set';
      btn.style.background = '#10b981';
    },
    err => {
      setLocStatus('Could not get location: ' + err.message, 'err');
      btn.disabled = false;
      btn.textContent = 'Retry';
    },
    { enableHighAccuracy: true, timeout: 10000 }
  );
}

function setLocStatus(msg, type) {
  const el  = document.getElementById('locStatus');
  const icons = { ok: 'bi-check-circle-fill', err: 'bi-x-circle', warn: 'bi-info-circle' };
  el.className  = 'loc-status ' + type;
  el.innerHTML  = `<i class="bi ${icons[type]}"></i> ${msg}`;
}
</script>
</body>
</html>