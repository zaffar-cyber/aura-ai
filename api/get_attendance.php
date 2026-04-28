<?php
require_once '../config.php';

$class_id = $_GET['class_id'] ?? '';
$date     = $_GET['date']     ?? date('Y-m-d');

if (!$class_id) json_resp(['success' => false, 'error' => 'class_id required'], 400);

$records  = array_values(array_filter(rj('attendance.json'),
    fn($a) => $a['class_id'] === $class_id && $a['date'] === $date
));

$students = find_many('students.json', 'class_id', $class_id);
$total    = count($students);
$present  = count(array_filter($records, fn($r) => $r['status'] === 'present'));
$absent   = count(array_filter($records, fn($r) => $r['status'] === 'absent'));

json_resp([
    'success'  => true,
    'total'    => $total,
    'present'  => $present,
    'absent'   => $absent,
    'pending'  => $total - $present - $absent,
    'records'  => $records,
]);