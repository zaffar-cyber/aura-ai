AuraAI — AI-Powered Smart Attendance System
Live Project: superdevs.co.in/test
Built by: SuperDevs
Tech Stack: PHP · Bootstrap 5 · JSON · Face-API.js · PHPMailer · IST Timezone

Overview
AuraAI is a face recognition–based student attendance management system designed for educational institutions. It eliminates manual roll calls by using AI-powered facial recognition directly in the browser — no mobile app or special hardware needed. Teachers open a timed attendance window, students scan their faces on a shared device, and absent students automatically trigger parent email notifications via PHPMailer.

Key Features
Feature	Description
🎓 Student Registration	Multi-angle face capture (Front, Left, Right) for high accuracy
👨‍🏫 Teacher Portal	Dedicated login, class management, attendance dashboard
📷 Face Recognition	Real-time face detection using Face-API.js + SSD MobileNet
🕐 Attendance Window	Teacher sets time slot (e.g. 10:00 AM – 11:00 AM)
📍 Location Lock	Teacher captures classroom GPS coordinates when opening window
📧 Parent Alerts	PHPMailer sends absence emails automatically
📊 Dashboard	7-day attendance bar chart, live roll call, KPI stats
🛡️ Admin Panel	Manage classes, semesters, teachers, and students
⏰ IST Timezone	All timestamps in Indian Standard Time (Asia/Kolkata)
Project Structure
text
attendai/
├── config.php                  ← Core config, timezone (IST), JSON helpers
├── index.php                   ← Login page (Teacher / Admin)
├── register.php                ← Student & Teacher registration + webcam capture
├── teacher_dashboard.php       ← Teacher home with stats & 7-day chart
├── attendance_window.php       ← Open/close attendance sessions + GPS
├── take_attendance.php         ← Live face scanner for students
├── admin.php                   ← Admin panel (classes, users, reports)
├── logout.php                  ← Session destroy
├── mailer.php                  ← PHPMailer wrapper for parent alerts
├── api/
│   ├── save_descriptor.php     ← AJAX: save face descriptor after registration
│   ├── mark_attendance.php     ← AJAX: mark present/absent via face match
│   └── set_window.php          ← AJAX: open / close attendance window
└── data/                       ← Auto-created JSON storage
    ├── students.json
    ├── teachers.json
    ├── classes.json
    ├── attendance.json
    ├── attendance_windows.json
    └── photos/                 ← Face capture thumbnails
Tech Stack
Layer	Technology
Backend	PHP 8+
Frontend	HTML5 · CSS3 · Bootstrap 5.3
AI / Face Recognition	Face-API.js v0.22 (SSD MobileNetV1)
Storage	JSON flat files (no MySQL required)
Email	PHPMailer (SMTP / Gmail)
Charts	Chart.js 4.4
Icons	Bootstrap Icons 1.11
Fonts	Inter + Space Grotesk (Google Fonts)
Timezone	Asia/Kolkata (IST)
How It Works
1. Registration Flow
Student or Teacher visits /register.php

Fills in details: name, email, semester, class, parent email (student) or password (teacher)

Webcam activates — captures 3 face angles: Front, Left, Right

Face-API.js computes a 128-dimension face descriptor per angle

The 3 descriptors are averaged into one master descriptor

Descriptor is saved to students.json / teachers.json via api/save_descriptor.php

2. Teacher Opens Attendance Window
Teacher logs in → teacher_dashboard.php

Clicks "Open Attendance Window" → attendance_window.php

Sets subject, time slot (From – To), and optionally captures classroom GPS

Window is saved to attendance_windows.json with status open

Dashboard banner turns green showing active window

3. Student Takes Attendance
Student opens take_attendance.php on a classroom device (shared laptop/tablet)

Face-API.js loads AI models (SSD MobileNet, Face Landmarks, Face Recognition)

Live video feed shows real-time face detection overlay

Student clicks "Scan My Face" (or auto-scan triggers every 3 seconds)

Captured frame is compared against all registered student descriptors

Euclidean distance threshold < 0.52 = match → attendance marked present

api/mark_attendance.php saves record to attendance.json

Side panel roll call updates in real time

4. Absent Student Email Alert
When the teacher closes the attendance window (or it expires), mailer.php is triggered:

All students in the class with no present record for that date = absent

PHPMailer sends an email to parent_email stored during registration

Email contains student name, date, subject, and time window

Installation
Requirements
PHP 8.0+

Composer (for PHPMailer)

Laragon / XAMPP / WAMP (local) or any PHP hosting

HTTPS recommended (required for webcam access in browsers)

Steps
bash
# 1. Clone / upload project
git clone https://github.com/yourrepo/auraai attendai

# 2. Install PHPMailer
cd attendai
composer require phpmailer/phpmailer

# 3. Configure settings in config.php
config.php — Required Changes
php
date_default_timezone_set('Asia/Kolkata');   // IST — already set

// SMTP Mail Settings
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'youremail@gmail.com');  // ← your Gmail
define('SMTP_PASS', 'your_app_password');    // ← Gmail App Password (not your password)
define('SMTP_PORT', 587);
define('MAIL_FROM', 'youremail@gmail.com');

// Admin Login
define('ADMIN_EMAIL', 'admin@attendai.com');
define('ADMIN_PASS',  'admin123');           // ← change this!
Gmail App Password: Go to Google Account → Security → 2-Step Verification → App Passwords → Generate one for "Mail"

Database (JSON Files)
AuraAI uses flat JSON files — no MySQL setup needed.

students.json — Schema
json
{
  "id": "STU1A2B3C",
  "name": "Ayaan Khan",
  "email": "ayaan@email.com",
  "phone": "+91 9000000000",
  "parent_email": "parent@email.com",
  "semester": "3",
  "class_id": "CLS001",
  "face_descriptor": [0.023, -0.141, ...],
  "face_registered_at": "2026-04-28 10:30:00",
  "created_at": "2026-04-28 10:25:00"
}
attendance.json — Schema
json
{
  "id": "ATT4D5E6F",
  "student_id": "STU1A2B3C",
  "class_id": "CLS001",
  "window_id": "WIN7G8H9I",
  "date": "2026-04-28",
  "status": "present",
  "method": "face",
  "confidence": 91,
  "marked_at": "2026-04-28 10:42:15"
}
attendance_windows.json — Schema
json
{
  "id": "WIN7G8H9I",
  "class_id": "CLS001",
  "teacher_id": "TCHA1B2C3",
  "subject": "Mathematics",
  "date": "2026-04-28",
  "from_time": "10:00",
  "to_time": "11:00",
  "lat": 33.729789,
  "lng": 75.153846,
  "status": "open",
  "created_at": "2026-04-28 09:58:00"
}
Face Recognition — Technical Details
Algorithm
Model: SSD MobileNetV1 (lightweight, runs in-browser)

Descriptor: 128-dimensional Float32 vector per face

Matching: Euclidean distance between stored and live descriptor

Threshold: distance < 0.52 → recognized (lower = stricter)

Multi-angle averaging: 3 descriptors averaged at registration → better accuracy in varied lighting

Accuracy Tips
Situation	Recommendation
Poor lighting	Use good indoor lighting, avoid backlight
Glasses	Register with and without glasses
Low confidence	Retake registration, capture all 3 angles clearly
Wrong match	Lower threshold to 0.45 in take_attendance.php
Models not loading	Check internet connection — models load from CDN
API Endpoints
Endpoint	Method	Purpose
api/save_descriptor.php	POST (JSON)	Save face descriptor after registration
api/mark_attendance.php	POST (JSON)	Mark student present/absent
api/set_window.php	POST (form)	Open or close an attendance window
api/mark_attendance.php — Request Body
json
{
  "student_id": "STU1A2B3C",
  "class_id": "CLS001",
  "window_id": "WIN7G8H9I",
  "method": "face",
  "status": "present",
  "confidence": 91
}
User Roles
Role	Access	Login
Admin	Full system — classes, users, reports	admin@attendai.com + password
Teacher	Own class dashboard, open windows, view roll	Email + password set at registration
Student	Face scan page only (no login required)	Public page take_attendance.php
Troubleshooting
Students Showing 0 / "Class DEFAULT"
This happens when the attendance window's class_id is DEFAULT or doesn't match any student. Fix applied in take_attendance.php: if no students found for the window's class, system automatically falls back to loading all registered students.

To permanently fix: create a class in admin.php first, then assign both students and the teacher to that class before opening an attendance window.

Webcam Not Working
Browser requires HTTPS or localhost for camera access

Allow camera permission in browser settings

On shared devices, check no other tab is using the camera

Face Not Recognized
Ensure student completed all 3 angle captures during registration

Ensure face_descriptor is not null in students.json

Improve lighting — face must be clearly visible

Try adjusting THRESHOLD from 0.52 to 0.55 for more lenient matching

Emails Not Sending
Confirm Gmail App Password is correct (not your Gmail login password)

Enable "Less secure app access" OR use App Password

Check SMTP_PORT = 587 with STARTTLS (or 465 with SSL)

Roadmap
 MySQL database migration (for larger institutions)

 Mobile-responsive student scan page

 QR code backup attendance method

 Weekly/monthly attendance PDF export

 Multi-class teacher support

 Student login to view own attendance record

 Geofencing — only allow attendance from within classroom radius

Credits
Developed by SuperDevs
🌐 superdevs.co.in/test

Face AI: face-api.js by Vincent Mühler

Mail: PHPMailer

Charts: Chart.js

UI: Bootstrap 5

AuraAI — Smarter Attendance, Zero Paper.