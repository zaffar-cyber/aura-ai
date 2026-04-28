<?php
require_once 'config.php';

$success = $error = '';
$registered_id   = '';
$registered_role = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role         = $_POST['role']         ?? '';
    $name         = trim($_POST['name']    ?? '');
    $email        = trim($_POST['email']   ?? '');
    $phone        = trim($_POST['phone']   ?? '');
    $semester     = trim($_POST['semester'] ?? '1');
    $class_id     = trim($_POST['class_id'] ?? '');

    if (!$name || !$email || !$role) {
        $error = 'Please fill all required fields.';
    } elseif (find_one('students.json', 'email', $email) || find_one('teachers.json', 'email', $email)) {
        $error = 'This email is already registered.';
    } else {
        $id = gen_id($role === 'teacher' ? 'TCH' : 'STU');

        if ($role === 'teacher') {
            $password = $_POST['password'] ?? '';
            if (strlen($password) < 6) { $error = 'Password must be at least 6 characters.'; goto done; }
            add_item('teachers.json', [
                'id'              => $id,
                'name'            => $name,
                'email'           => $email,
                'phone'           => $phone,
                'password'        => password_hash($password, PASSWORD_DEFAULT),
                'class_id'        => $class_id,
                'semester'        => $semester,
                'face_descriptor' => null,
                'created_at'      => date('Y-m-d H:i:s'),
            ]);
        } else {
            $parent_email = trim($_POST['parent_email'] ?? '');
            add_item('students.json', [
                'id'              => $id,
                'name'            => $name,
                'email'           => $email,
                'phone'           => $phone,
                'parent_email'    => $parent_email,
                'semester'        => $semester,
                'class_id'        => $class_id,
                'face_descriptor' => null,
                'created_at'      => date('Y-m-d H:i:s'),
            ]);
        }

        $registered_id   = $id;
        $registered_role = $role;
        $success = "Registered! Your ID is <strong>{$id}</strong>. Now complete face capture below.";
    }
    done:
}

$classes = rj('classes.json');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register — AttendAI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<style>
:root {
  --primary: #4f46e5;
  --primary-dark: #3730a3;
  --primary-light: #e0e7ff;
  --bg: #f1f5f9;
  --surface: #ffffff;
  --text: #0f172a;
  --muted: #64748b;
  --border: rgba(15, 23, 42, 0.1);
  --success-bg: #ecfdf5;
  --success-border: #6ee7b7;
  --success-text: #065f46;
  --error-bg: #fef2f2;
  --error-border: #fecaca;
  --error-text: #dc2626;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Inter', sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
}
/* ── Top Bar ── */
.topbar {
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  padding: .85rem 1.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  position: sticky;
  top: 0;
  z-index: 50;
}
.logo { display: flex; align-items: center; gap: .5rem; text-decoration: none; }
.logo-icon {
  width: 32px; height: 32px;
  background: var(--primary);
  border-radius: 8px;
  display: grid; place-items: center;
}
.logo-text { font-family: 'Space Grotesk', sans-serif; font-size: 1rem; font-weight: 700; color: var(--text); }
.logo-text span { color: var(--primary); }
.topbar-title { font-size: .9rem; color: var(--muted); }
.back-link { margin-left: auto; color: var(--primary); text-decoration: none; font-size: .85rem; font-weight: 600; }

/* ── Steps ── */
.steps-wrap { max-width: 680px; margin: 2rem auto 0; padding: 0 1rem; }
.steps { display: flex; gap: .5rem; align-items: center; margin-bottom: 2rem; }
.step-item {
  flex: 1; background: var(--surface); border: 1.5px solid var(--border);
  border-radius: 12px; padding: .9rem 1rem;
  text-align: center; transition: all .3s;
}
.step-item.active { border-color: var(--primary); background: var(--primary-light); }
.step-item.done   { border-color: #10b981; background: #ecfdf5; }
.step-num {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 1.3rem; font-weight: 700; display: block;
  color: var(--muted);
}
.step-item.active .step-num { color: var(--primary); }
.step-item.done   .step-num { color: #10b981; }
.step-label { font-size: .75rem; font-weight: 600; color: var(--muted); margin-top: .2rem; }
.step-item.active .step-label { color: var(--primary); }
.step-item.done   .step-label { color: #10b981; }
.step-arrow { color: var(--border); font-size: 1.2rem; flex-shrink: 0; }

/* ── Card ── */
.card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 2rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 1px 8px rgba(79,70,229,.05);
}
.card-title {
  font-family: 'Space Grotesk', sans-serif;
  font-size: 1.05rem; font-weight: 700;
  margin-bottom: 1.5rem;
  display: flex; align-items: center; gap: .5rem;
}
/* ── Role Switch ── */
.role-switch {
  display: flex; gap: .4rem; background: var(--bg);
  padding: .3rem; border-radius: 10px;
  margin-bottom: 1.5rem;
}
.role-btn {
  flex: 1; padding: .55rem .8rem; border: none; background: transparent;
  border-radius: 8px; font-size: .85rem; font-weight: 600;
  color: var(--muted); cursor: pointer; transition: all .2s;
}
.role-btn.active { background: white; color: var(--primary); box-shadow: 0 1px 6px rgba(0,0,0,.08); }
/* ── Form ── */
.form-label { font-size: .82rem; font-weight: 600; margin-bottom: .3rem; display: block; }
.form-control, .form-select {
  border: 1.5px solid var(--border); border-radius: 10px;
  padding: .6rem 1rem; font-size: .9rem;
  width: 100%; outline: none;
  transition: border-color .2s, box-shadow .2s;
  font-family: 'Inter', sans-serif;
}
.form-control:focus, .form-select:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(79,70,229,.1);
}
.btn-primary {
  background: var(--primary); border: none; border-radius: 10px;
  padding: .7rem 2rem; font-weight: 700; font-size: .95rem;
  color: white; cursor: pointer; transition: background .2s, transform .1s;
  display: inline-flex; align-items: center; gap: .5rem;
}
.btn-primary:hover { background: var(--primary-dark); }
.btn-primary:active { transform: scale(.99); }
.btn-primary:disabled { background: #94a3b8; cursor: not-allowed; transform: none; }
.btn-outline {
  background: transparent; border: 1.5px solid var(--border);
  border-radius: 10px; padding: .7rem 1.5rem;
  font-weight: 600; color: var(--text); cursor: pointer;
  transition: all .2s; font-size: .9rem;
  display: inline-flex; align-items: center; gap: .5rem;
}
.btn-outline:hover { border-color: var(--primary); color: var(--primary); }
.alert {
  border-radius: 10px; padding: .8rem 1rem;
  font-size: .88rem; margin-bottom: 1.2rem;
  display: flex; align-items: flex-start; gap: .5rem;
}
.alert-success { background: var(--success-bg); border: 1px solid var(--success-border); color: var(--success-text); }
.alert-danger  { background: var(--error-bg);   border: 1px solid var(--error-border);   color: var(--error-text); }

/* ── Camera ── */
.camera-wrap {
  display: flex; flex-direction: column; align-items: center; gap: 1rem;
}
.cam-frame {
  position: relative; border-radius: 16px; overflow: hidden;
  border: 3px solid var(--primary-light);
  box-shadow: 0 4px 24px rgba(79,70,229,.12);
  background: #1e293b;
}
#videoEl {
  width: 400px; height: 300px; object-fit: cover; display: block;
}
#overlayCanvas {
  position: absolute; top: 0; left: 0;
  width: 400px; height: 300px;
  pointer-events: none;
}
#captureCanvas { display: none; }
.cam-status {
  font-size: .88rem; font-weight: 600; text-align: center;
  padding: .5rem 1rem; border-radius: 99px; min-height: 2rem;
  display: flex; align-items: center; justify-content: center; gap: .4rem;
}
.status-warn { background: #fff7ed; color: #c2410c; }
.status-ok   { background: #ecfdf5; color: #065f46; }
.status-err  { background: #fef2f2; color: #dc2626; }
.status-info { background: #eff6ff; color: #1d4ed8; }

/* ── Progress ── */
.progress-wrap { width: 100%; max-width: 400px; }
.progress-label { font-size: .78rem; color: var(--muted); margin-bottom: .4rem; text-align: center; }
.progress-bar-bg { background: var(--bg); border-radius: 99px; height: 8px; overflow: hidden; }
.progress-bar-fill { height: 100%; background: var(--primary); border-radius: 99px; transition: width .4s ease; width: 0%; }

/* ── Photo Strip ── */
.photo-strip { display: flex; gap: .8rem; }
.photo-slot-wrap { text-align: center; }
.angle-lbl { font-size: .72rem; font-weight: 700; color: var(--muted); margin-bottom: .3rem; text-transform: uppercase; letter-spacing: .05em; }
.photo-slot {
  width: 90px; height: 68px; border-radius: 10px;
  background: var(--bg); border: 2px dashed var(--border);
  display: grid; place-items: center;
  overflow: hidden; transition: border-color .3s;
}
.photo-slot.captured { border: 2px solid #10b981; }
.photo-slot img { width: 100%; height: 100%; object-fit: cover; }
.photo-slot i { font-size: 1.3rem; color: #cbd5e1; }

/* ── Success Card ── */
.success-card {
  text-align: center; padding: 2.5rem;
}
.success-icon { font-size: 4rem; margin-bottom: 1rem; }
.success-title { font-family: 'Space Grotesk', sans-serif; font-size: 1.4rem; font-weight: 700; margin-bottom: .5rem; }
.success-sub { color: var(--muted); margin-bottom: 1.5rem; font-size: .95rem; }
</style>
</head>
<body>

<!-- Top Bar -->
<div class="topbar">
  <a href="index.php" class="logo">
    <div class="logo-icon">
      <svg width="16" height="16" fill="none" stroke="white" stroke-width="2.5" viewBox="0 0 24 24">
        <circle cx="12" cy="8" r="4"/>
        <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
      </svg>
    </div>
    <div class="logo-text">Attend<span>AI</span></div>
  </a>
  <span class="topbar-title">/ Registration</span>
  <a href="index.php" class="back-link"><i class="bi bi-arrow-left me-1"></i>Back to Login</a>
</div>

<div class="steps-wrap">

  <!-- Step Indicators -->
  <div class="steps">
    <div class="step-item <?= $registered_id ? 'done' : 'active' ?>" id="stepEl1">
      <span class="step-num"><?= $registered_id ? '✓' : '1' ?></span>
      <div class="step-label">Fill Details</div>
    </div>
    <span class="step-arrow">›</span>
    <div class="step-item <?= $registered_id ? 'active' : '' ?>" id="stepEl2">
      <span class="step-num">2</span>
      <div class="step-label">Capture Face</div>
    </div>
    <span class="step-arrow">›</span>
    <div class="step-item" id="stepEl3">
      <span class="step-num">3</span>
      <div class="step-label">Done!</div>
    </div>
  </div>

  <!-- ════ STEP 1: REGISTRATION FORM ════ -->
  <div id="sectionForm" <?= $registered_id ? 'style="display:none"' : '' ?>>
    <div class="card">
      <div class="card-title">
        <i class="bi bi-person-plus" style="color:var(--primary)"></i>
        Create Your Account
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger">
          <i class="bi bi-x-circle-fill"></i>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
      <?php endif; ?>

      <!-- Role Switch -->
      <div class="role-switch">
        <button type="button" class="role-btn active" onclick="switchRole('student', this)">
          <i class="bi bi-mortarboard me-1"></i>Student
        </button>
        <button type="button" class="role-btn" onclick="switchRole('teacher', this)">
          <i class="bi bi-person-badge me-1"></i>Teacher
        </button>
      </div>

      <form method="POST" id="regForm" onsubmit="return validateForm()">
        <input type="hidden" name="role" id="roleField" value="student">

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Full Name *</label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Ayaan Khan" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Email Address *</label>
            <input type="email" name="email" class="form-control" placeholder="you@email.com" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Phone Number</label>
            <input type="text" name="phone" class="form-control" placeholder="+91 9000000000">
          </div>
          <div class="col-md-3">
            <label class="form-label">Semester</label>
            <select name="semester" class="form-select">
              <?php for ($s = 1; $s <= 8; $s++): ?>
                <option value="<?= $s ?>">Sem <?= $s ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Class</label>
            <select name="class_id" class="form-select">
              <option value="">Select Class</option>
              <?php foreach ($classes as $cls): ?>
                <option value="<?= $cls['id'] ?>">
                  <?= htmlspecialchars($cls['name']) ?> (Sem <?= $cls['semester'] ?>)
                </option>
              <?php endforeach; ?>
              <?php if (!$classes): ?>
                <option value="DEFAULT">Default Class</option>
              <?php endif; ?>
            </select>
          </div>

          <!-- Student only -->
          <div class="col-md-6" id="wrapParent">
            <label class="form-label">Parent Email (for absence alerts)</label>
            <input type="email" name="parent_email" class="form-control" placeholder="parent@email.com">
          </div>

          <!-- Teacher only -->
          <div class="col-md-6 d-none" id="wrapPassword">
            <label class="form-label">Password *</label>
            <input type="password" name="password" id="pwdField" class="form-control" placeholder="Min 6 characters">
          </div>
        </div>

        <div class="d-flex gap-2 mt-4 flex-wrap">
          <button type="submit" class="btn-primary">
            <i class="bi bi-arrow-right-circle"></i>Save & Go to Face Capture
          </button>
          <a href="index.php" class="btn-outline">
            <i class="bi bi-x"></i>Cancel
          </a>
        </div>
      </form>
    </div>
  </div>

  <!-- ════ STEP 2: FACE CAPTURE ════ -->
  <div id="sectionFace" <?= !$registered_id ? 'style="display:none"' : '' ?>>

    <?php if ($success): ?>
      <div class="alert alert-success">
        <i class="bi bi-check-circle-fill"></i>
        <span><?= $success ?></span>
      </div>
    <?php endif; ?>

    <div class="card" id="cameraCard">
      <div class="card-title">
        <i class="bi bi-camera-video" style="color:var(--primary)"></i>
        Capture Your Face — 3 Angles
      </div>

      <p style="color:var(--muted);font-size:.88rem;margin-bottom:1.5rem;line-height:1.6">
        We need <strong>3 photos</strong>: <strong>Front</strong>, <strong>Left</strong>, and <strong>Right</strong>.
        Each angle improves recognition accuracy in different lighting conditions.
      </p>

      <div class="camera-wrap">

        <!-- Video + Overlay -->
        <div class="cam-frame">
          <video id="videoEl" autoplay muted playsinline></video>
          <canvas id="overlayCanvas"></canvas>
        </div>
        <canvas id="captureCanvas" width="400" height="300"></canvas>

        <!-- Status -->
        <div class="cam-status status-info" id="camStatus">
          <i class="bi bi-hourglass-split"></i>
          <span>Initialising camera...</span>
        </div>

        <!-- Progress -->
        <div class="progress-wrap">
          <div class="progress-label" id="progressLabel">0 of 3 angles captured</div>
          <div class="progress-bar-bg">
            <div class="progress-bar-fill" id="progressFill"></div>
          </div>
        </div>

        <!-- Photo Thumbnails -->
        <div class="photo-strip">
          <div class="photo-slot-wrap">
            <div class="angle-lbl">Front</div>
            <div class="photo-slot" id="slot0"><i class="bi bi-camera"></i></div>
          </div>
          <div class="photo-slot-wrap">
            <div class="angle-lbl">Left</div>
            <div class="photo-slot" id="slot1"><i class="bi bi-camera"></i></div>
          </div>
          <div class="photo-slot-wrap">
            <div class="angle-lbl">Right</div>
            <div class="photo-slot" id="slot2"><i class="bi bi-camera"></i></div>
          </div>
        </div>

        <!-- Buttons -->
        <div class="d-flex gap-2 flex-wrap justify-content-center">
          <button class="btn-primary" id="captureBtn" disabled onclick="captureAngle()">
            <i class="bi bi-camera-fill" id="captureBtnIcon"></i>
            <span id="captureBtnText">Capture Front</span>
          </button>
          <button class="btn-outline" id="retakeBtn" style="display:none" onclick="resetCapture()">
            <i class="bi bi-arrow-counterclockwise"></i>Retake All
          </button>
        </div>

        <p style="font-size:.78rem;color:var(--muted)">
          Photo <strong id="angleNum">1</strong> of 3 &mdash;
          <span id="angleInstruction">Look straight at the camera</span>
        </p>
      </div>
    </div>

    <!-- Success after all 3 done -->
    <div class="card" id="doneCard" style="display:none">
      <div class="success-card">
        <div class="success-icon">🎉</div>
        <div class="success-title">Registration Complete!</div>
        <div class="success-sub">
          Your face has been registered successfully.<br>
          <?php if ($registered_role === 'teacher'): ?>
            You can now log in with your email and password.
          <?php else: ?>
            Your teacher can now take your attendance using face recognition.
          <?php endif; ?>
        </div>
        <?php if ($registered_role === 'teacher'): ?>
          <a href="index.php" class="btn-primary" style="text-decoration:none">
            <i class="bi bi-box-arrow-in-right"></i>Go to Login
          </a>
        <?php else: ?>
          <a href="register.php" class="btn-primary" style="text-decoration:none;margin-right:.5rem">
            <i class="bi bi-person-plus"></i>Register Another
          </a>
          <a href="index.php" class="btn-outline" style="text-decoration:none">
            <i class="bi bi-house"></i>Home
          </a>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- end sectionFace -->
</div><!-- end steps-wrap -->

<script>
// ── Config ────────────────────────────────────────────────
const MODEL_URL       = './models';   // local models folder
const REGISTERED_ID   = '<?= addslashes($registered_id) ?>';
const REGISTERED_ROLE = '<?= addslashes($registered_role) ?>';
const ANGLES = [
  { label: 'Front',      instruction: 'Look straight at the camera' },
  { label: 'Left Side',  instruction: 'Slowly turn your face to the LEFT' },
  { label: 'Right Side', instruction: 'Now turn your face to the RIGHT' },
];

// ── DOM refs ──────────────────────────────────────────────
const videoEl       = document.getElementById('videoEl');
const overlayCanvas = document.getElementById('overlayCanvas');
const captureCanvas = document.getElementById('captureCanvas');
const captureBtn    = document.getElementById('captureBtn');
const retakeBtn     = document.getElementById('retakeBtn');
const camStatus     = document.getElementById('camStatus');
const progressFill  = document.getElementById('progressFill');
const progressLabel = document.getElementById('progressLabel');

// ── State ─────────────────────────────────────────────────
let modelsLoaded         = false;
let currentAngleIndex    = 0;
let capturedDescriptors  = [];
let cameraStream         = null;
let detectionLoopId      = null;

// ── Role Switch ───────────────────────────────────────────
function switchRole(role, el) {
  document.getElementById('roleField').value = role;
  document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('active'));
  el.classList.add('active');
  const isTeacher = role === 'teacher';
  document.getElementById('wrapPassword').classList.toggle('d-none', !isTeacher);
  document.getElementById('wrapParent').classList.toggle('d-none', isTeacher);
  document.getElementById('pwdField').required = isTeacher;
}

function validateForm() {
  const role = document.getElementById('roleField').value;
  if (role === 'teacher') {
    const pwd = document.getElementById('pwdField').value;
    if (pwd.length < 6) { alert('Password must be at least 6 characters.'); return false; }
  }
  return true;
}

// ── Camera Init ───────────────────────────────────────────
async function initCamera() {
  if (!REGISTERED_ID) return;
  setStatus('Requesting camera access...', 'info');
  try {
    cameraStream = await navigator.mediaDevices.getUserMedia({
      video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' }
    });
    videoEl.srcObject = cameraStream;
    await new Promise(r => videoEl.onloadedmetadata = r);
    await videoEl.play();
    await loadModels();
  } catch (err) {
    setStatus('❌ Camera access denied. Please allow camera and refresh.', 'err');
    console.error(err);
  }
}

// ── Load face-api.js Models ───────────────────────────────
async function loadModels() {
  setStatus('Loading AI models (may take 5–15s on first load)...', 'warn');
  try {
    await Promise.all([
      faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
      faceapi.nets.faceLandmark68TinyNet.loadFromUri(MODEL_URL),
      faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
    ]);
    modelsLoaded = true;
    setStatus('✅ Ready! ' + ANGLES[0].instruction, 'ok');
    captureBtn.disabled = false;
    updateAngleUI();
    startDetectionLoop();
  } catch (err) {
    console.error('Model load error:', err);
    setStatus('❌ Models not found. Run download_models.php first, then refresh.', 'err');
  }
}

// ── Live Detection Overlay ────────────────────────────────
function startDetectionLoop() {
  const ctx = overlayCanvas.getContext('2d');
  detectionLoopId = setInterval(async () => {
    if (!modelsLoaded || videoEl.paused || videoEl.ended) return;
    try {
      const detections = await faceapi
        .detectAllFaces(videoEl, new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.4 }))
        .withFaceLandmarks(true);

      // Sync canvas size to video
      overlayCanvas.width  = videoEl.videoWidth  || 400;
      overlayCanvas.height = videoEl.videoHeight || 300;
      ctx.clearRect(0, 0, overlayCanvas.width, overlayCanvas.height);

      if (detections.length > 0) {
        faceapi.draw.drawDetections(overlayCanvas, detections);
        faceapi.draw.drawFaceLandmarks(overlayCanvas, detections);
      }
    } catch (_) {}
  }, 250);
}

// ── Capture One Angle ─────────────────────────────────────
async function captureAngle() {
  if (!modelsLoaded || currentAngleIndex >= 3) return;
  captureBtn.disabled = true;
  setStatus('🔍 Detecting face...', 'warn');

  // Draw current video frame onto hidden canvas
  const ctx = captureCanvas.getContext('2d');
  ctx.drawImage(videoEl, 0, 0, captureCanvas.width, captureCanvas.height);

  try {
    const detection = await faceapi
      .detectSingleFace(captureCanvas, new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.4 }))
      .withFaceLandmarks(true)
      .withFaceDescriptor();

    if (!detection) {
      setStatus('⚠️ No face detected! Make sure your face is well-lit and fully visible.', 'err');
      captureBtn.disabled = false;
      return;
    }

    // ✅ Face found — save descriptor
    capturedDescriptors.push(Array.from(detection.descriptor));

    // Show thumbnail in slot
    const slot = document.getElementById('slot' + currentAngleIndex);
    slot.innerHTML = '';
    slot.classList.add('captured');
    const thumb = new Image();
    thumb.src = captureCanvas.toDataURL('image/jpeg', 0.75);
    slot.appendChild(thumb);

    currentAngleIndex++;

    // Update progress bar
    const pct = (currentAngleIndex / 3) * 100;
    progressFill.style.width = pct + '%';
    progressLabel.textContent = currentAngleIndex + ' of 3 angles captured';

    if (currentAngleIndex < 3) {
      retakeBtn.style.display = 'inline-flex';
      captureBtn.disabled = false;
      updateAngleUI();
      setStatus('✅ Angle ' + currentAngleIndex + ' saved! ' + ANGLES[currentAngleIndex].instruction, 'ok');
    } else {
      // All 3 angles done
      setStatus('✅ All 3 angles captured! Saving to server...', 'ok');
      captureBtn.style.display = 'none';
      retakeBtn.style.display = 'none';
      await saveDescriptors();
    }

  } catch (err) {
    console.error(err);
    setStatus('❌ Detection error: ' + err.message, 'err');
    captureBtn.disabled = false;
  }
}

// ── Save Average Descriptor to Server ────────────────────
async function saveDescriptors() {
  // Average the 3 descriptors for better accuracy
  const avgDescriptor = capturedDescriptors[0].map((_, i) =>
    capturedDescriptors.reduce((sum, d) => sum + d[i], 0) / capturedDescriptors.length
  );

  try {
    const res = await fetch('api/save_descriptor.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        id:         REGISTERED_ID,
        role:       REGISTERED_ROLE,
        descriptor: avgDescriptor,
      }),
    });

    const data = await res.json();

    if (data.success) {
      // Stop camera
      if (cameraStream) cameraStream.getTracks().forEach(t => t.stop());
      clearInterval(detectionLoopId);

      // Show success card
      document.getElementById('cameraCard').style.display = 'none';
      document.getElementById('doneCard').style.display   = 'block';

      // Update steps
      document.getElementById('stepEl2').className = 'step-item done';
      document.getElementById('stepEl2').querySelector('.step-num').textContent = '✓';
      document.getElementById('stepEl3').className = 'step-item active';
    } else {
      setStatus('❌ Server error: ' + (data.error || 'Unknown. Check api/save_descriptor.php'), 'err');
      captureBtn.style.display = 'inline-flex';
      captureBtn.disabled = false;
    }
  } catch (err) {
    setStatus('❌ Network error saving descriptor. Check your server.', 'err');
    captureBtn.style.display = 'inline-flex';
    captureBtn.disabled = false;
  }
}

// ── Reset / Retake ────────────────────────────────────────
function resetCapture() {
  currentAngleIndex   = 0;
  capturedDescriptors = [];
  progressFill.style.width     = '0%';
  progressLabel.textContent    = '0 of 3 angles captured';
  captureBtn.style.display     = 'inline-flex';
  captureBtn.disabled          = false;
  retakeBtn.style.display      = 'none';

  for (let i = 0; i < 3; i++) {
    const slot = document.getElementById('slot' + i);
    slot.innerHTML = '<i class="bi bi-camera"></i>';
    slot.classList.remove('captured');
  }

  updateAngleUI();
  setStatus('✅ Reset! ' + ANGLES[0].instruction, 'ok');
}

// ── Helpers ───────────────────────────────────────────────
function updateAngleUI() {
  const a = ANGLES[currentAngleIndex] || ANGLES[0];
  document.getElementById('angleNum').textContent         = currentAngleIndex + 1;
  document.getElementById('captureBtnText').textContent   = 'Capture ' + a.label;
  document.getElementById('angleInstruction').textContent = a.instruction;
}

function setStatus(msg, type) {
  const icons = { ok: 'bi-check-circle-fill', err: 'bi-exclamation-triangle-fill',
                  warn: 'bi-hourglass-split', info: 'bi-info-circle-fill' };
  camStatus.className = 'cam-status status-' + type;
  camStatus.innerHTML = `<i class="bi ${icons[type] || 'bi-info-circle'}"></i><span>${msg}</span>`;
}

// ── Boot ──────────────────────────────────────────────────
window.addEventListener('load', initCamera);
</script>

</body>
</html>