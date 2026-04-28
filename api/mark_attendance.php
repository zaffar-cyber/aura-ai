<?php
require_once '../config.php';

$body = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$student_id = $body['student_id'] ?? '';
$class_id   = $body['class_id']   ?? '';
$window_id  = $body['window_id']  ?? '';
$method     = $body['method']     ?? 'face';
$status     = $body['status']     ?? 'present';
$confidence = (int)($body['confidence'] ?? 0);

if (!$student_id || !$class_id) {
    json_resp(['success' => false, 'error' => 'Missing student_id or class_id.'], 400);
}

$today = date('Y-m-d');

// ── Block duplicate ───────────────────────────────────────
foreach (rj('attendance.json') as $a) {
    if ($a['student_id'] === $student_id && $a['date'] === $today && $a['class_id'] === $class_id) {
        json_resp(['success' => false, 'error' => 'Already marked for today.', 'duplicate' => true]);
    }
}

// ── Save record ───────────────────────────────────────────
$att_id = gen_id('ATT');
add_item('attendance.json', [
    'id'         => $att_id,
    'student_id' => $student_id,
    'class_id'   => $class_id,
    'window_id'  => $window_id,
    'date'       => $today,
    'status'     => $status,
    'method'     => $method,      // 'face' or 'manual'
    'confidence' => $confidence,
    'marked_at'  => date('Y-m-d H:i:s'),
]);

json_resp(['success' => true, 'att_id' => $att_id, 'status' => $status]);
// Check duplicate
$all_att = rj('attendance.json');
foreach ($all_att as $a) {
    if ($a['student_id'] === $student_id && $a['date'] === $today) {
        json_resp(['success' => false, 'error' => 'Already marked for today.', 'duplicate' => true]);
    }
}

