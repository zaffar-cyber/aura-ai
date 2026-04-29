<?php
date_default_timezone_set('Asia/Kolkata');
require_once 'config.php';

// ── Find Active Window ────────────────────────────────────
$class_id_filter = trim($_GET['class_id'] ?? '');
$all_windows     = rj('attendance_windows.json');
$active_win      = null;
$class_id        = '';

foreach ($all_windows as $w) {
    if ($w['status'] === 'open') {
        if (!$class_id_filter || $w['class_id'] === $class_id_filter) {
            $active_win = $w;
            $class_id   = $w['class_id'];
            break;
        }
    }
}

// ── Load Students ─────────────────────────────────────────
$all_students = [];
$load_method  = '';

if ($class_id && $class_id !== 'DEFAULT') {
    // Try exact class match
    $all_students = find_many('students.json', 'class_id', $class_id);
    $load_method  = 'class';
}

// Fallback: if 0 students found (DEFAULT or empty class), load ALL students
if (empty($all_students)) {
    $all_students = rj('students.json');
    $load_method  = 'all';
    // If there's an active window, update its class to reflect reality
    if ($active_win && !empty($all_students)) {
        // Use first student's class as the effective class
        $class_id = $all_students[0]['class_id'] ?? 'ALL';
    }
}

// ── Build Face Data (only students with registered faces) ─
$face_data = array_values(array_filter(
    array_map(fn($s) => [
        'id'         => $s['id'],
        'name'       => $s['name'],
        'class_id'   => $s['class_id'],
        'descriptor' => $s['face_descriptor'] ?? null,
    ], $all_students),
    fn($s) => $s['descriptor'] !== null
));

// ── Today's Records ───────────────────────────────────────
$today      = date('Y-m-d');
$all_att    = rj('attendance.json');
$today_recs = array_filter($all_att, fn($a) => $a['date'] === $today);

// Build map: student_id => status for today
$marked_map = [];
foreach ($today_recs as $rec) {
    $marked_map[$rec['student_id']] = [
        'status'    => $rec['status'],
        'marked_at' => $rec['marked_at'],
        'method'    => $rec['method'],
    ];
}

// Pre-fill already marked present IDs (for JS)
$already_present = array_keys(array_filter($marked_map, fn($r) => $r['status'] === 'present'));

// ── Teacher Info ──────────────────────────────────────────
$teacher = $active_win ? find_one('teachers.json', 'id', $active_win['teacher_id']) : null;
$classes = rj('classes.json');

// Get class name
$class_name = 'All Students';
foreach ($classes as $cls) {
    if ($cls['id'] === $class_id) { $class_name = $cls['name'] . ' (Sem ' . $cls['semester'] . ')'; break; }
}

// Stats
$present_count = count(array_filter($marked_map, fn($r) => $r['status'] === 'present'));
$absent_count  = count(array_filter($marked_map, fn($r) => $r['status'] === 'absent'));
$pending_count = count($all_students) - count($marked_map);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Face Attendance — AuraAi</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<style>
:root {
  --primary:#4f46e5; --primary-dark:#3730a3;
  --bg:#0f172a; --surface:#1e293b; --surface2:#334155;
  --text:#e2e8f0; --muted:#94a3b8; --border:rgba(255,255,255,.07);
  --success:#10b981; --danger:#ef4444; --warning:#f59e0b;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);
     min-height:100vh;display:flex;flex-direction:column}

/* ── Topbar ── */
.topbar{background:var(--surface);border-bottom:1px solid var(--border);
        padding:.85rem 1.5rem;display:flex;align-items:center;
        justify-content:space-between;flex-shrink:0;gap:1rem;flex-wrap:wrap}
.topbar-left{display:flex;align-items:center;gap:1rem}
.logo-wrap{display:flex;align-items:center;gap:.5rem;text-decoration:none}
.logo-icon{width:30px;height:30px;background:var(--primary);border-radius:8px;
           display:grid;place-items:center}
.logo-text{font-family:'Space Grotesk',sans-serif;font-size:.95rem;font-weight:700;color:var(--text)}
.logo-text span{color:#a5b4fc}
.topbar-sub{font-size:.82rem;color:var(--muted)}
.win-pill{padding:.32rem .85rem;border-radius:99px;font-size:.76rem;font-weight:700;
          display:inline-flex;align-items:center;gap:.35rem}
.pill-open  {background:rgba(16,185,129,.15);color:#34d399;border:1px solid rgba(16,185,129,.25)}
.pill-closed{background:rgba(239,68,68,.15); color:#f87171;border:1px solid rgba(239,68,68,.25)}

/* ── Layout ── */
.body-wrap{flex:1;display:grid;grid-template-columns:1fr 340px;overflow:hidden}

/* ── Camera Panel ── */
.cam-panel{display:flex;flex-direction:column;align-items:center;
           justify-content:center;padding:2rem;gap:1.1rem}

/* No window */
.no-win-box{text-align:center;padding:2rem}
.no-win-icon{font-size:3.5rem;color:#334155;margin-bottom:1rem}
.no-win-title{font-family:'Space Grotesk',sans-serif;font-size:1.3rem;font-weight:700;margin-bottom:.5rem}
.no-win-sub{color:var(--muted);font-size:.9rem;margin-bottom:1.5rem;line-height:1.6}

/* Open windows list */
.open-wins{width:100%;max-width:500px;margin-top:1rem}
.open-win-item{background:var(--surface);border:1px solid var(--border);border-radius:12px;
               padding:1rem;display:flex;align-items:center;justify-content:space-between;
               margin-bottom:.6rem}
.open-win-item a{background:var(--primary);color:white;border-radius:8px;
                 padding:.4rem 1rem;text-decoration:none;font-size:.82rem;font-weight:700}

/* Camera frame */
.cam-frame{position:relative;border-radius:20px;overflow:hidden;
           border:3px solid rgba(79,70,229,.4);
           box-shadow:0 0 60px rgba(79,70,229,.2);background:#020617}
#videoEl{width:520px;height:390px;object-fit:cover;display:block;transform:scaleX(-1)}
#overlayCanvas{position:absolute;top:0;left:0;width:520px;height:390px;
               pointer-events:none;transform:scaleX(-1)}
#captureCanvas{display:none}

/* Corners */
.cam-corners{position:absolute;inset:0;pointer-events:none}
.corner{position:absolute;width:24px;height:24px;border-color:#4f46e5;border-style:solid;opacity:.7}
.corner.tl{top:12px;left:12px;border-width:3px 0 0 3px;border-radius:4px 0 0 0}
.corner.tr{top:12px;right:12px;border-width:3px 3px 0 0;border-radius:0 4px 0 0}
.corner.bl{bottom:12px;left:12px;border-width:0 0 3px 3px;border-radius:0 0 0 4px}
.corner.br{bottom:12px;right:12px;border-width:0 3px 3px 0;border-radius:0 0 4px 0}

/* Scan line */
.scan-line{position:absolute;left:0;right:0;height:2px;
  background:linear-gradient(90deg,transparent,#4f46e5,#818cf8,#4f46e5,transparent);
  animation:scanDown 2.5s linear infinite;opacity:0;transition:opacity .3s}
.scan-line.on{opacity:1}
@keyframes scanDown{0%{top:0}100%{top:100%}}

/* Buttons */
.scan-btn{background:var(--primary);border:none;border-radius:14px;color:white;
          padding:.9rem 2.5rem;font-size:1rem;font-weight:700;cursor:pointer;
          transition:all .2s;box-shadow:0 4px 20px rgba(79,70,229,.4);
          display:inline-flex;align-items:center;gap:.6rem}
.scan-btn:hover:not(:disabled){background:var(--primary-dark);transform:translateY(-2px);
  box-shadow:0 6px 28px rgba(79,70,229,.5)}
.scan-btn:disabled{background:#334155;box-shadow:none;cursor:not-allowed}

/* Status */
.scan-status{font-size:.88rem;font-weight:600;text-align:center;padding:.5rem 1.2rem;
             border-radius:99px;min-height:2.2rem;display:flex;align-items:center;gap:.4rem}
.s-ok  {background:rgba(16,185,129,.12);color:#34d399}
.s-err {background:rgba(239,68,68,.12); color:#f87171}
.s-warn{background:rgba(245,158,11,.12);color:#fbbf24}
.s-info{background:rgba(79,70,229,.12); color:#a5b4fc}

.auto-tag{font-size:.72rem;color:#475569;display:flex;align-items:center;gap:.35rem}
.pulse-dot{width:6px;height:6px;background:#4f46e5;border-radius:50%;
           animation:pulse 1.5s ease-in-out infinite}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(.8)}}

/* Warning banners */
.warn-banner{background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);
             color:#fbbf24;border-radius:10px;padding:.7rem 1rem;
             font-size:.82rem;max-width:520px;width:100%;text-align:center}
.warn-banner a{color:#fbbf24;font-weight:700}

/* ── Side Panel ── */
.side-panel{background:var(--surface);border-left:1px solid var(--border);
            display:flex;flex-direction:column;overflow:hidden}
.side-head{padding:1.1rem 1.3rem;border-bottom:1px solid var(--border);flex-shrink:0}
.side-title{font-family:'Space Grotesk',sans-serif;font-size:.95rem;font-weight:700}
.side-sub{font-size:.75rem;color:var(--muted);margin-top:.2rem}

/* Class selector in side panel */
.class-sel-wrap{padding:.7rem;border-bottom:1px solid var(--border);flex-shrink:0}
.class-sel{width:100%;background:var(--surface2);border:1px solid var(--border);
           color:var(--text);border-radius:8px;padding:.45rem .8rem;
           font-size:.82rem;font-family:'Inter',sans-serif;outline:none}
.class-sel:focus{border-color:var(--primary)}

/* Roll list */
.roll-list{flex:1;overflow-y:auto;padding:.5rem}
.roll-list::-webkit-scrollbar{width:4px}
.roll-list::-webkit-scrollbar-thumb{background:var(--surface2);border-radius:99px}

.roll-item{display:flex;align-items:center;gap:.7rem;padding:.6rem .8rem;
           border-radius:10px;margin-bottom:.2rem;transition:background .15s}
.roll-item.present{background:rgba(16,185,129,.07)}
.roll-item.absent {background:rgba(239,68,68,.06)}
.roll-item:hover  {background:rgba(255,255,255,.03)}

.roll-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.roll-dot.present{background:var(--success)}
.roll-dot.absent {background:var(--danger)}
.roll-dot.pending{background:#475569}

.roll-avatar{width:34px;height:34px;border-radius:50%;background:var(--surface2);
             display:grid;place-items:center;font-weight:700;font-size:.85rem;
             color:#a5b4fc;flex-shrink:0}
.roll-name{font-size:.85rem;font-weight:600;flex:1;line-height:1.2}
.roll-meta{font-size:.72rem;color:var(--muted)}
.roll-badge{font-size:.7rem;font-weight:700;padding:.15rem .5rem;border-radius:99px;flex-shrink:0}
.b-present{background:rgba(16,185,129,.15);color:#34d399}
.b-absent {background:rgba(239,68,68,.15); color:#f87171}
.b-pending{background:rgba(71,85,105,.3);  color:#94a3b8}

/* Stats */
.side-stats{display:grid;grid-template-columns:repeat(3,1fr);
            border-top:1px solid var(--border);flex-shrink:0}
.stat-item{padding:.8rem;text-align:center;border-right:1px solid var(--border)}
.stat-item:last-child{border-right:none}
.stat-val{font-family:'Space Grotesk',sans-serif;font-size:1.4rem;font-weight:700}
.stat-lbl{font-size:.68rem;color:var(--muted);margin-top:.1rem}

/* ── Match Popup ── */
.match-popup{position:fixed;bottom:1.5rem;left:50%;
  transform:translateX(-50%) translateY(100px);
  background:var(--surface);border-radius:20px;padding:1.3rem 2rem;
  text-align:center;min-width:280px;
  box-shadow:0 16px 48px rgba(0,0,0,.6);z-index:999;
  border:1.5px solid var(--border);
  transition:transform .35s cubic-bezier(.34,1.56,.64,1),opacity .3s;
  opacity:0;pointer-events:none}
.match-popup.show{transform:translateX(-50%) translateY(0);opacity:1}
.match-popup.m-ok {border-color:rgba(16,185,129,.4)}
.match-popup.m-err{border-color:rgba(239,68,68,.4)}
.popup-icon{font-size:2.5rem;margin-bottom:.4rem}
.popup-name{font-family:'Space Grotesk',sans-serif;font-size:1.15rem;font-weight:700;margin-bottom:.2rem}
.popup-sub {font-size:.8rem;color:var(--muted)}
.popup-conf{font-size:.78rem;color:#a5b4fc;margin-top:.3rem}

/* Time display */
.live-time{font-size:.78rem;color:var(--muted)}

@media(max-width:900px){
  .body-wrap{grid-template-columns:1fr}
  .side-panel{display:none}
  #videoEl,#overlayCanvas{width:100%;height:auto}
  .cam-frame{width:100%}
}
</style>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
  <div class="topbar-left">
    <a href="index.php" class="logo-wrap">
      <div class="logo-icon">
        <svg width="15" height="15" fill="none" stroke="white" stroke-width="2.5" viewBox="0 0 24 24">
          <circle cx="12" cy="8" r="4"/>
          <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
        </svg>
      </div>
      <div class="logo-text">Attend<span>AI</span></div>
    </a>
    <span class="topbar-sub">/ Face Scanner</span>
  </div>

  <div style="display:flex;align-items:center;gap:.8rem;flex-wrap:wrap">
    <?php if ($teacher): ?>
      <span style="font-size:.8rem;color:var(--muted)">
        <i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($teacher['name']) ?>
      </span>
    <?php endif; ?>
    <?php if ($active_win): ?>
      <span style="font-size:.8rem;color:var(--muted)">
        <i class="bi bi-building me-1"></i><?= htmlspecialchars($class_name) ?>
      </span>
    <?php endif; ?>
    <span class="win-pill <?= $active_win ? 'pill-open' : 'pill-closed' ?>">
      <i class="bi bi-<?= $active_win ? 'broadcast' : 'stop-circle' ?>"></i>
      <?= $active_win
        ? 'Window Open: ' . $active_win['from_time'] . ' – ' . $active_win['to_time']
        : 'No Active Window' ?>
    </span>
    <span class="live-time" id="liveClock"><?= date('h:i:s A') ?> IST</span>
  </div>
</div>

<!-- Body -->
<div class="body-wrap">

  <!-- ── Camera Panel ── -->
  <div class="cam-panel">

    <?php if (!$active_win): ?>
    <!-- No window open — show all open windows or message -->
    <div class="no-win-box">
      <div class="no-win-icon"><i class="bi bi-clock-history"></i></div>
      <div class="no-win-title">No Active Attendance Window</div>
      <div class="no-win-sub">
        Your teacher needs to open an attendance window first.<br>
        If you are the teacher, open one from the dashboard.
      </div>

      <?php
      // Show any open windows from ANY class
      $any_open = array_filter($all_windows, fn($w) => $w['status'] === 'open');
      ?>

      <?php if ($any_open): ?>
        <p style="color:#a5b4fc;font-size:.85rem;font-weight:600;margin-bottom:.8rem">
          Open windows found — click to use:
        </p>
        <div class="open-wins">
          <?php foreach ($any_open as $ow):
            $ow_class = '';
            foreach ($classes as $cls) { if ($cls['id'] === $ow['class_id']) { $ow_class = $cls['name']; break; } }
            $ow_stucount = count(find_many('students.json', 'class_id', $ow['class_id']));
          ?>
          <div class="open-win-item">
            <div>
              <div style="font-weight:700;font-size:.9rem">
                <?= htmlspecialchars($ow['subject']) ?>
              </div>
              <div style="font-size:.78rem;color:var(--muted)">
                <?= htmlspecialchars($ow['class_id']) ?>
                <?= $ow_class ? '(' . htmlspecialchars($ow_class) . ')' : '' ?>
                · <?= $ow['from_time'] ?>–<?= $ow['to_time'] ?>
                · <?= $ow_stucount ?> students
              </div>
            </div>
            <a href="take_attendance.php?class_id=<?= urlencode($ow['class_id']) ?>">
              <i class="bi bi-camera-video me-1"></i>Use This
            </a>
          </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <a href="attendance_window.php"
           style="background:var(--primary);border-radius:10px;color:white;
                  padding:.7rem 1.5rem;text-decoration:none;font-weight:700;
                  display:inline-flex;align-items:center;gap:.5rem">
          <i class="bi bi-play-circle"></i>Open Attendance Window
        </a>
      <?php endif; ?>

      <div style="margin-top:1.5rem">
        <button onclick="location.reload()"
                style="background:transparent;border:1px solid var(--border);border-radius:8px;
                       color:var(--muted);padding:.5rem 1.2rem;cursor:pointer;font-size:.85rem">
          <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
      </div>
    </div>

    <?php else: ?>

    <!-- ── Active window — show camera ── -->

    <?php if ($load_method === 'all' && $class_id !== 'DEFAULT'): ?>
      <div class="warn-banner">
        <i class="bi bi-info-circle me-1"></i>
        Class <strong><?= htmlspecialchars($active_win['class_id']) ?></strong> has no students —
        showing <strong>all <?= count($all_students) ?> registered students</strong> instead.
        <a href="admin.php">Fix class assignment →</a>
      </div>
    <?php endif; ?>

    <?php if (empty($face_data)): ?>
      <div class="warn-banner">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <?= count($all_students) > 0
          ? count($all_students) . ' students found but <strong>none have registered their face</strong>. Ask them to go to <a href="register.php">register.php</a> and complete face capture.'
          : 'No students found at all. <a href="register.php">Register students →</a>' ?>
      </div>
    <?php endif; ?>

    <div class="cam-frame" id="camFrame">
      <video id="videoEl" autoplay muted playsinline></video>
      <canvas id="overlayCanvas"></canvas>
      <canvas id="captureCanvas" width="520" height="390"></canvas>
      <div class="scan-line" id="scanLine"></div>
      <div class="cam-corners">
        <div class="corner tl"></div><div class="corner tr"></div>
        <div class="corner bl"></div><div class="corner br"></div>
      </div>
    </div>

    <button class="scan-btn" id="scanBtn" disabled onclick="runScan(false)">
      <i class="bi bi-camera-fill"></i>
      <span>Scan My Face</span>
    </button>

    <div class="scan-status s-info" id="scanStatus">
      <i class="bi bi-hourglass-split"></i>
      <span>Loading AI models...</span>
    </div>

    <div class="auto-tag">
      <div class="pulse-dot"></div>
      Auto-scanning every 3 seconds &mdash;
      <?= count($face_data) ?> face(s) loaded
    </div>

    <?php endif; ?>
  </div>

  <!-- ── Side Panel ── -->
  <div class="side-panel">
    <div class="side-head">
      <div class="side-title">
        <i class="bi bi-journal-check me-1" style="color:#a5b4fc"></i>
        Attendance Roll
      </div>
      <div class="side-sub">
        <?= count($all_students) ?> students &middot;
        <?= htmlspecialchars($class_name) ?> &middot;
        <?= date('d M Y') ?>
      </div>
    </div>

    <!-- Class filter dropdown -->
    <?php if ($classes): ?>
    <div class="class-sel-wrap">
      <select class="class-sel" onchange="switchClass(this.value)">
        <option value="">All Students</option>
        <?php foreach ($classes as $cls): ?>
          <option value="<?= $cls['id'] ?>"
                  <?= $cls['id'] === ($active_win['class_id'] ?? '') ? 'selected' : '' ?>>
            <?= htmlspecialchars($cls['name']) ?> — Sem <?= $cls['semester'] ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>

    <!-- Roll -->
    <div class="roll-list" id="rollList">
      <?php if (empty($all_students)): ?>
        <div style="padding:2rem;text-align:center;color:var(--muted);font-size:.85rem">
          <i class="bi bi-people" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>
          No students registered yet.<br>
          <a href="register.php" style="color:#a5b4fc">Register students →</a>
        </div>
      <?php else: ?>
        <?php foreach ($all_students as $stu):
          $rec    = $marked_map[$stu['id']] ?? null;
          $status = $rec ? $rec['status'] : 'pending';
          $time   = $rec ? date('h:i A', strtotime($rec['marked_at'])) : '';
          $method = $rec ? $rec['method'] : '';
          $has_face = !empty($stu['face_descriptor']);
        ?>
        <div class="roll-item <?= $status ?>" id="rollItem-<?= $stu['id'] ?>">
          <div class="roll-dot <?= $status ?>" id="rollDot-<?= $stu['id'] ?>"></div>
          <div class="roll-avatar"><?= strtoupper(substr($stu['name'], 0, 2)) ?></div>
          <div style="flex:1;min-width:0">
            <div class="roll-name"><?= htmlspecialchars($stu['name']) ?></div>
            <div class="roll-meta" id="rollMeta-<?= $stu['id'] ?>">
              <?php if ($time): ?>
                <?= $time ?> IST
                <?= $method === 'face' ? '· <i class="bi bi-camera-fill"></i> Face' : ($method === 'manual' ? '· Manual' : '') ?>
              <?php else: ?>
                <?= htmlspecialchars($stu['id']) ?>
                <?= !$has_face ? ' · <span style="color:#f87171">No face</span>' : '' ?>
              <?php endif; ?>
            </div>
          </div>
          <span class="roll-badge b-<?= $status ?>" id="rollBadge-<?= $stu['id'] ?>">
            <?= ucfirst($status) ?>
          </span>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Stats -->
    <div class="side-stats">
      <div class="stat-item">
        <div class="stat-val" id="statPresent" style="color:var(--success)"><?= $present_count ?></div>
        <div class="stat-lbl">Present</div>
      </div>
      <div class="stat-item">
        <div class="stat-val" id="statAbsent" style="color:var(--danger)"><?= $absent_count ?></div>
        <div class="stat-lbl">Absent</div>
      </div>
      <div class="stat-item">
        <div class="stat-val" id="statPending" style="color:var(--muted)"><?= $pending_count ?></div>
        <div class="stat-lbl">Pending</div>
      </div>
    </div>
  </div>

</div><!-- end body-wrap -->

<!-- Match Popup -->
<div class="match-popup" id="matchPopup">
  <div class="popup-icon" id="popupIcon">✅</div>
  <div class="popup-name"  id="popupName">—</div>
  <div class="popup-sub"   id="popupSub">—</div>
  <div class="popup-conf"  id="popupConf"></div>
</div>

<script>
// ── Constants ─────────────────────────────────────────────
const MODEL_URL   = './models';
const CLASS_ID    = '<?= addslashes($class_id) ?>';
const WIN_ID      = '<?= $active_win ? addslashes($active_win["id"]) : "" ?>';
const HAS_WINDOW  = <?= $active_win ? 'true' : 'false' ?>;
const FACE_DATA   = <?= json_encode($face_data) ?>;
const THRESHOLD   = 0.50;

// ── State ─────────────────────────────────────────────────
let modelsLoaded = false;
let isScanning   = false;
let alreadyMarked = new Set([<?= implode(',', array_map(fn($id) => '"' . addslashes($id) . '"', $already_present)) ?>]);
let cameraStream  = null;
let autoInterval  = null;
let popupTimer    = null;

// ── Live Clock (IST) ──────────────────────────────────────
function updateClock() {
  const now = new Date();
  const ist = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Kolkata' }));
  const h   = String(ist.getHours()).padStart(2,'0');
  const m   = String(ist.getMinutes()).padStart(2,'0');
  const s   = String(ist.getSeconds()).padStart(2,'0');
  const ampm = ist.getHours() >= 12 ? 'PM' : 'AM';
  const h12 = ist.getHours() % 12 || 12;
  const el  = document.getElementById('liveClock');
  if (el) el.textContent = `${String(h12).padStart(2,'0')}:${m}:${s} ${ampm} IST`;
}
setInterval(updateClock, 1000);
updateClock();

// ── Camera Init ───────────────────────────────────────────
async function initCamera() {
  if (!HAS_WINDOW) return;
  setStatus('Requesting camera access...', 'info');
  try {
    cameraStream = await navigator.mediaDevices.getUserMedia({
      video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' }
    });
    document.getElementById('videoEl').srcObject = cameraStream;
    await new Promise(r => document.getElementById('videoEl').onloadedmetadata = r);
    await document.getElementById('videoEl').play();
    await loadModels();
  } catch (e) {
    setStatus('❌ Camera access denied. Allow camera and refresh.', 'err');
  }
}

// ── Load Models ───────────────────────────────────────────
async function loadModels() {
  setStatus('Loading AI models... (5–15 seconds first time)', 'warn');
  try {
    await Promise.all([
      faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
      faceapi.nets.faceLandmark68TinyNet.loadFromUri(MODEL_URL),
      faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
    ]);
    modelsLoaded = true;
    document.getElementById('scanBtn').disabled = false;
    setStatus(`✅ Ready! ${FACE_DATA.length} face(s) loaded. Auto-scanning active.`, 'ok');
    startOverlay();
    autoInterval = setInterval(() => { if (!isScanning) runScan(true); }, 3000);
  } catch (e) {
    console.error(e);
    setStatus('❌ Models not found! Run download_models.php first, then refresh.', 'err');
  }
}

// ── Live Detection Overlay ────────────────────────────────
function startOverlay() {
  const video  = document.getElementById('videoEl');
  const canvas = document.getElementById('overlayCanvas');
  const ctx    = canvas.getContext('2d');
  const line   = document.getElementById('scanLine');
  setInterval(async () => {
    if (!modelsLoaded || video.paused) return;
    try {
      const dets = await faceapi
        .detectAllFaces(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.4 }))
        .withFaceLandmarks(true);
      canvas.width  = video.videoWidth  || 520;
      canvas.height = video.videoHeight || 390;
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      if (dets.length > 0) {
        faceapi.draw.drawDetections(canvas, dets);
        faceapi.draw.drawFaceLandmarks(canvas, dets);
        line.classList.add('on');
      } else {
        line.classList.remove('on');
      }
    } catch (_) {}
  }, 250);
}

// ── Main Scan ─────────────────────────────────────────────
async function runScan(auto = false) {
  if (!modelsLoaded || isScanning || !HAS_WINDOW) return;
  if (FACE_DATA.length === 0) {
    setStatus('⚠️ No face data loaded. Register students first.', 'warn');
    return;
  }
  isScanning = true;
  if (!auto) document.getElementById('scanBtn').disabled = true;

  const video  = document.getElementById('videoEl');
  const canvas = document.getElementById('captureCanvas');
  const ctx    = canvas.getContext('2d');
  ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

  try {
    const det = await faceapi
      .detectSingleFace(canvas, new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.4 }))
      .withFaceLandmarks(true)
      .withFaceDescriptor();

    if (!det) {
      if (!auto) setStatus('⚠️ No face detected. Move closer to camera.', 'warn');
      else setStatus('👁 Scanning for faces...', 'info');
      isScanning = false;
      if (!auto) document.getElementById('scanBtn').disabled = false;
      return;
    }

    const scanned = Array.from(det.descriptor);
    let bestMatch = null, bestDist = Infinity;

    for (const s of FACE_DATA) {
      if (alreadyMarked.has(s.id)) continue;
      const d = euclidean(scanned, s.descriptor);
      if (d < bestDist) { bestDist = d; bestMatch = s; }
    }

    if (bestMatch && bestDist < THRESHOLD) {
      const conf = Math.round((1 - bestDist) * 100);
      setStatus(`✅ Recognized: ${bestMatch.name} — ${conf}% match`, 'ok');
      await markPresent(bestMatch.id, bestMatch.name, conf);
    } else {
      if (!auto) {
        setStatus(`❌ Face not recognized (score: ${bestDist.toFixed(3)})`, 'err');
        showPopup(false, 'Not Recognized', 'Try again or register face', '');
      } else {
        setStatus('👁 Scanning for faces...', 'info');
      }
    }
  } catch (e) {
    console.error(e);
    setStatus('❌ Error: ' + e.message, 'err');
  }

  isScanning = false;
  if (!auto) document.getElementById('scanBtn').disabled = false;
}

// ── Mark Present ──────────────────────────────────────────
async function markPresent(studentId, studentName, confidence) {
  if (alreadyMarked.has(studentId)) {
    setStatus(`⚠️ ${studentName} already marked present today.`, 'warn');
    return;
  }
  try {
    const res = await fetch('api/mark_attendance.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        student_id: studentId,
        class_id:   CLASS_ID,
        window_id:  WIN_ID,
        method:     'face',
        status:     'present',
        confidence: confidence,
      }),
    });
    const data = await res.json();
    if (data.success || data.duplicate) {
      alreadyMarked.add(studentId);
      updateRoll(studentId, 'present', confidence);
      updateStats();
      showPopup(true, studentName, 'Marked Present ✓', confidence + '% confidence');
    }
  } catch (e) {
    console.error(e);
  }
}

// ── Update Roll Item ──────────────────────────────────────
function updateRoll(id, status, conf) {
  const item  = document.getElementById('rollItem-'  + id);
  const dot   = document.getElementById('rollDot-'   + id);
  const badge = document.getElementById('rollBadge-' + id);
  const meta  = document.getElementById('rollMeta-'  + id);
  if (!item) return;

  item.className  = 'roll-item ' + status;
  if (dot)   { dot.className   = 'roll-dot ' + status; }
  if (badge) { badge.className = 'roll-badge b-' + status; badge.textContent = ucFirst(status); }
  if (meta) {
    const now = new Date();
    const ist = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Kolkata' }));
    const time = ist.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });
    meta.innerHTML = `${time} IST &middot; <i class="bi bi-camera-fill"></i> Face &middot; ${conf}%`;
  }
  item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// ── Update Stats ──────────────────────────────────────────
function updateStats() {
  const items = document.querySelectorAll('.roll-item');
  let p = 0, a = 0, n = 0;
  items.forEach(el => {
    if (el.classList.contains('present'))     p++;
    else if (el.classList.contains('absent')) a++;
    else                                       n++;
  });
  document.getElementById('statPresent').textContent = p;
  document.getElementById('statAbsent').textContent  = a;
  document.getElementById('statPending').textContent = n;
}

// ── Popup ─────────────────────────────────────────────────
function showPopup(ok, name, sub, conf) {
  const p = document.getElementById('matchPopup');
  document.getElementById('popupIcon').textContent = ok ? '✅' : '❌';
  document.getElementById('popupName').textContent = name;
  document.getElementById('popupSub').textContent  = sub;
  document.getElementById('popupConf').textContent = conf;
  p.className = 'match-popup show ' + (ok ? 'm-ok' : 'm-err');
  clearTimeout(popupTimer);
  popupTimer = setTimeout(() => { p.className = 'match-popup'; }, 3500);
}

// ── Class Switcher ────────────────────────────────────────
function switchClass(classId) {
  const url = classId
    ? `take_attendance.php?class_id=${encodeURIComponent(classId)}`
    : 'take_attendance.php';
  window.location.href = url;
}

// ── Helpers ───────────────────────────────────────────────
function euclidean(d1, d2) {
  let s = 0;
  for (let i = 0; i < d1.length; i++) s += (d1[i] - d2[i]) ** 2;
  return Math.sqrt(s);
}
function setStatus(msg, type) {
  const icons = { ok:'bi-check-circle-fill', err:'bi-exclamation-triangle-fill',
                  warn:'bi-exclamation-circle-fill', info:'bi-broadcast' };
  const el = document.getElementById('scanStatus');
  if (!el) return;
  el.className = 'scan-status s-' + type;
  el.innerHTML = `<i class="bi ${icons[type]||'bi-info-circle'}"></i><span>${msg}</span>`;
}
function ucFirst(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

window.addEventListener('load', initCamera);
</script>
</body>
</html>