<?php
// Place this in the root of AuraAi/
// Requires: composer require phpmailer/phpmailer
// OR manually place PHPMailer src/ files in vendor/phpmailer/phpmailer/src/

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Send absent notification email to parent
 */
function send_absent_mail(
    string $parent_email,
    string $student_name,
    string $subject,
    string $from_time,
    string $to_time,
    string $date
): bool {
    $mail = new PHPMailer(true);
    try {
        // ── SMTP Config ───────────────────────────────────
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        // ── From / To ─────────────────────────────────────
        $mail->setFrom(MAIL_FROM, MAIL_NAME);
        $mail->addAddress($parent_email);
        $mail->isHTML(true);

        $formatted_date = date('l, F j, Y', strtotime($date));
        $mail->Subject = "⚠️ Absence Alert: {$student_name} missed class on {$formatted_date}";
        $mail->Body    = build_absent_email($student_name, $subject, $from_time, $to_time, $formatted_date);
        $mail->AltBody = "Dear Parent, {$student_name} was absent during {$subject} ({$from_time}–{$to_time}) on {$formatted_date}. Please contact the school if needed.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail failed for {$parent_email}: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Beautiful HTML email template
 */
function build_absent_email(
    string $name,
    string $subject,
    string $from_time,
    string $to_time,
    string $date
): string {
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',Arial,sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0">
    <tr><td align="center" style="padding:40px 20px">
      <table width="560" cellpadding="0" cellspacing="0"
             style="background:#fff;border-radius:16px;overflow:hidden;
                    box-shadow:0 4px 24px rgba(0,0,0,.08)">
        <!-- Header -->
        <tr>
          <td style="background:linear-gradient(135deg,#4f46e5,#7c3aed);
                     padding:32px;text-align:center">
            <div style="font-size:2rem;margin-bottom:8px">⚠️</div>
            <h1 style="margin:0;color:#fff;font-size:22px;font-weight:700">
              Attendance Alert
            </h1>
            <p style="margin:6px 0 0;color:#c4b5fd;font-size:14px">
              AuraAi Smart Attendance System
            </p>
          </td>
        </tr>
        <!-- Body -->
        <tr>
          <td style="padding:32px">
            <p style="margin:0 0 16px;color:#374151;font-size:15px">
              Dear Parent/Guardian,
            </p>
            <p style="margin:0 0 24px;color:#374151;font-size:15px;line-height:1.6">
              This is an automated notification to inform you that
              <strong style="color:#0f172a">{$name}</strong>
              was <strong style="color:#dc2626">absent</strong>
              from the following class session:
            </p>
            <!-- Info Box -->
            <table width="100%" cellpadding="0" cellspacing="0"
                   style="background:#f8fafc;border:1px solid #e2e8f0;
                          border-radius:12px;margin-bottom:24px">
              <tr>
                <td style="padding:20px">
                  <table width="100%" cellpadding="6" cellspacing="0">
                    <tr>
                      <td style="color:#64748b;font-size:13px;font-weight:600;
                                 text-transform:uppercase;letter-spacing:.05em;width:40%">
                        Student
                      </td>
                      <td style="color:#0f172a;font-size:14px;font-weight:600">
                        {$name}
                      </td>
                    </tr>
                    <tr>
                      <td style="color:#64748b;font-size:13px;font-weight:600;
                                 text-transform:uppercase;letter-spacing:.05em">
                        Subject
                      </td>
                      <td style="color:#0f172a;font-size:14px">{$subject}</td>
                    </tr>
                    <tr>
                      <td style="color:#64748b;font-size:13px;font-weight:600;
                                 text-transform:uppercase;letter-spacing:.05em">
                        Date
                      </td>
                      <td style="color:#0f172a;font-size:14px">{$date}</td>
                    </tr>
                    <tr>
                      <td style="color:#64748b;font-size:13px;font-weight:600;
                                 text-transform:uppercase;letter-spacing:.05em">
                        Time Slot
                      </td>
                      <td style="color:#0f172a;font-size:14px">
                        {$from_time} – {$to_time}
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
            <p style="margin:0 0 8px;color:#374151;font-size:14px;line-height:1.6">
              If you believe this is an error or your child was present, please contact
              the school or the class teacher immediately.
            </p>
            <p style="margin:0;color:#374151;font-size:14px">
              Regards,<br>
              <strong>AuraAi System</strong>
            </p>
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td style="background:#f8fafc;padding:16px 32px;
                     border-top:1px solid #e2e8f0;text-align:center">
            <p style="margin:0;color:#94a3b8;font-size:12px">
              This is an automated message from AuraAi &mdash; Do not reply directly.<br>
              Powered by AI Face Recognition Technology.
            </p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}