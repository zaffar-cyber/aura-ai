<?php
require_once '../config.php';

$body = json_decode(file_get_contents('php://input'), true);
$id         = $body['id']         ?? '';
$role       = $body['role']       ?? '';
$descriptor = $body['descriptor'] ?? [];

if (!$id || !$role || !$descriptor) {
    json_resp(['success' => false, 'error' => 'Missing fields.'], 400);
}

$file = $role === 'teacher' ? 'teachers.json' : 'students.json';
$user = find_one($file, 'id', $id);

if (!$user) {
    json_resp(['success' => false, 'error' => 'User not found.'], 404);
}

update_item($file, 'id', $id, [
    'face_descriptor' => $descriptor,
    'face_registered_at' => date('Y-m-d H:i:s'),
]);

json_resp(['success' => true, 'message' => 'Face descriptor saved.']);