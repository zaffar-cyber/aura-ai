<?php
// download_models.php — Run ONCE to download face-api.js models locally
// Visit: http://localhost/AuraAi/download_models.php

set_time_limit(300);  // 5 min timeout for large downloads

$models_dir = __DIR__ . '/models/';
if (!is_dir($models_dir)) mkdir($models_dir, 0755, true);

// All model files needed — from GitHub raw (most reliable source)
$base = 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/';

$files = [
    // Tiny Face Detector (fast & small — replaces heavy ssd_mobilenetv1)
    'tiny_face_detector_model-weights_manifest.json',
    'tiny_face_detector_model-shard1',

    // Face Landmarks (tiny version — faster)
    'face_landmark_68_tiny_model-weights_manifest.json',
    'face_landmark_68_tiny_model-shard1',

    // Face Recognition — needed for 128D descriptors (no tiny version)
    'face_recognition_model-weights_manifest.json',
    'face_recognition_model-shard1',
    'face_recognition_model-shard2',
];

echo '<html><head>';
echo '<style>body{font-family:monospace;background:#0f172a;color:#e2e8f0;padding:2rem}
      .ok{color:#34d399} .err{color:#f87171} .skip{color:#fbbf24}
      h2{color:#818cf8} progress{width:100%;margin:.5rem 0}</style>';
echo '</head><body>';
echo '<h2>AuraAi — Downloading face-api.js Models</h2>';
echo '<p style="color:#94a3b8">Downloading ' . count($files) . ' model files. Do not close this tab...</p><hr style="border-color:#334155;margin:1rem 0">';

$success = 0;
$failed  = 0;

foreach ($files as $filename) {
    $dest = $models_dir . $filename;

    // Skip if already downloaded
    if (file_exists($dest) && filesize($dest) > 100) {
        echo "<div class='skip'>⏭  SKIP   {$filename} (" . round(filesize($dest)/1024) . " KB already exists)</div>";
        $success++;
        flush();
        continue;
    }

    echo "<div>⬇  Downloading  <strong>{$filename}</strong>... ";
    flush();

    $context = stream_context_create([
        'http' => [
            'timeout'    => 120,
            'user_agent' => 'Mozilla/5.0 AuraAi-Model-Downloader/1.0',
            'follow_location' => true,
        ]
    ]);

    $data = @file_get_contents($base . $filename, false, $context);

    if ($data !== false && strlen($data) > 100) {
        file_put_contents($dest, $data);
        $kb = round(strlen($data) / 1024);
        echo "<span class='ok'>✅ OK ({$kb} KB)</span></div>";
        $success++;
    } else {
        echo "<span class='err'>❌ FAILED — check internet / GitHub access</span></div>";
        $failed++;
    }
    flush();
    ob_flush();
}

echo '<hr style="border-color:#334155;margin:1.5rem 0">';
if ($failed === 0) {
    echo "<h3 class='ok'>✅ All {$success} model files downloaded successfully!</h3>";
    echo "<p style='color:#94a3b8'>You can now delete this file and register faces.</p>";
    echo "<p><a href='register.php' style='color:#818cf8'>→ Go to Registration</a></p>";
} else {
    echo "<h3 class='err'>⚠️ {$failed} file(s) failed. Try running again or check internet.</h3>";
}

echo '</body></html>';