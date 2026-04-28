<?php
require_once '../config.php';

// Safe mailer load — won't crash if PHPMailer not installed
$mailerAvailable = file_exists(__DIR__ . '/../vendor/autoload.php');
if ($mailerAvailable) {
    require_once '../mailer.php';
}

// Works via both POST form and JSON body
$body      = !empty($_POST) ? $_POST : (json_decode(file_get_contents('php://input'), true) ?? []);
$action    = trim($body['action']    ?? '');
$window_id = trim($body['window_id'] ?? '');

// Validate
if (!$action || !$window_id) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
        header('Location: ../teacher_dashboard.php?err=missing_params'); exit;
    }
    json_resp(['success' => false, 'error' => 'Missing action or window_id.'], 400);
}

$windows  = rj('attendance_windows.json');
$found    = false;
$class_id = '';

foreach ($windows as &$w) {
    if ($w['id'] !== $window_id) continue;

    $found    = true;
    $class_id = $w['class_id'];

    if ($action === 'close') {
        $w['status']    = 'closed';
        $w['closed_at'] = date('Y-m-d H:i:s');

        // Auto-mark absent + send parent emails
        $students = find_many('students.json', 'class_id', $class_id);
        $today    = date('Y-m-d');
        $all_att  = rj('attendance.json');

        foreach ($students as $stu) {
            // Check if already has a record today
            $already = false;
            foreach ($all_att as $a) {
                if ($a['student_id'] === $stu['id']
                    && $a['date']       === $today
                    && $a['class_id']   === $class_id) {
                    $already = true;
                    break;
                }
            }

            if (!$already) {
                // Mark absent
                add_item('attendance.json', [
                    'id'         => gen_id('ATT'),
                    'student_id' => $stu['id'],
                    'class_id'   => $class_id,
                    'window_id'  => $window_id,
                    'date'       => $today,
                    'status'     => 'absent',
                    'method'     => 'auto',
                    'confidence' => 0,
                    'marked_at'  => date('Y-m-d H:i:s'),
                ]);

                // Send parent email — safely
                if ($mailerAvailable && !empty($stu['parent_email'])) {
                    try {
                        send_absent_mail(
                            $stu['parent_email'],
                            $stu['name'],
                            $w['subject']   ?? 'Class',
                            $w['from_time'] ?? '',
                            $w['to_time']   ?? '',
                            $today
                        );
                    } catch (Exception $e) {
                        error_log('Mail failed for ' . $stu['parent_email'] . ': ' . $e->getMessage());
                        // Don't crash — continue marking others absent
                    }
                }
            }
        }

    } elseif ($action === 'open') {
        $w['status'] = 'open';
    }

    break;
}
unset($w); // important: release reference after foreach &$w

// Save changes
wj('attendance_windows.json', $windows);

if (!$found) {
    if (!empty($_POST)) {
        header('Location: ../teacher_dashboard.php?err=window_not_found'); exit;
    }
    json_resp(['success' => false, 'error' => 'Window not found.'], 404);
}

// Redirect form POSTs, return JSON for AJAX
if (!empty($_POST)) {
    header('Location: ../teacher_dashboard.php?msg=window_' . $action . 'd'); exit;
}

json_resp(['success' => true, 'action' => $action]);