<?php

// place these at the very top
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer-master/src/Exception.php';
require __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
require __DIR__ . '/PHPMailer-master/src/SMTP.php';
session_start();
require_once 'config.php';

// (Optional in dev) show mysqli errors clearly:
// mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function back($form, $err = '', $ok = '') {
  $_SESSION['active_form'] = $form;               // 'login' or 'register'
  if ($err) $_SESSION[$form . '_error'] = $err;   // 'login_error' or 'register_error'
  if ($ok)  $_SESSION['register_success'] = $ok;
  header("Location: index.php");
  exit();
}

/* ===================== REGISTER (students only) ===================== */
if (isset($_POST['register'])) {
  $rawName     = trim($_POST['name'] ?? '');
  $name        = strtoupper($rawName);
  $email       = strtolower(trim($_POST['email'] ?? ''));
  $phone       = trim($_POST['phone'] ?? '');
  $gender      = $_POST['gender'] ?? '';
  $role        = 'student';
  $password    = $_POST['password'] ?? '';
  $confirm     = $_POST['confirm_password'] ?? '';
  $student_id  = strtoupper(trim($_POST['student_id'] ?? ''));
  $block       = trim($_POST['block'] ?? '');
  $room_number = (int)($_POST['room_number'] ?? 0);

  if ($name !== $rawName) back('register', "Name must be in UPPERCASE.");
  if ($password === '' || $password !== $confirm) back('register', "Passwords do not match.");

  $stmt = $conn->prepare("SELECT 1 FROM valid_student WHERE student_id=? AND block=? AND room_number=? LIMIT 1");
  $stmt->bind_param("ssi", $student_id, $block, $room_number);
  $stmt->execute();
  if (!$stmt->get_result()->fetch_row()) back('register', "Invalid Student record.");

  $stmt = $conn->prepare("SELECT 1 FROM profile WHERE student_id=? LIMIT 1");
  $stmt->bind_param("s", $student_id);
  $stmt->execute();
  if ($stmt->get_result()->fetch_row()) back('register', "This Student ID is already registered.");

  $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM profile WHERE room_number=? AND block=? AND is_deleted=0");
  $stmt->bind_param("is", $room_number, $block);
  $stmt->execute();
  $cnt = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
  if ($cnt >= 2) back('register', "Room $room_number in Block $block already has 2 students.");

  $stmt = $conn->prepare("SELECT 1 FROM profile WHERE email=? LIMIT 1");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  if ($stmt->get_result()->fetch_row()) back('register', "This email is already registered.");

  // ✅ Generate verification token
  $verification_token = bin2hex(random_bytes(32));
  $hash = password_hash($password, PASSWORD_DEFAULT);

 $stmt = $conn->prepare("
  INSERT INTO profile 
    (name, email, password, phone, gender, student_id, block, room_number, role, verified, verification_token)
  VALUES (?,?,?,?,?,?,?,?,?,0,?)
");
$stmt->bind_param("sssssssiss", $name, $email, $hash, $phone, $gender, $student_id, $block, $room_number, $role, $verification_token);
$stmt->execute();




// Create the mailer
  $mail = new PHPMailer(true); // true enables exceptions
  $mail->SMTPDebug = 0; // shows connection info
  $mail->Debugoutput = 'html';
  $mail->isSMTP();
  $mail->Host = 'smtp.gmail.com';
  $mail->SMTPAuth = true;
  $mail->Username = 'oscartuak@gmail.com'; // 🔒 use your email
  $mail->Password = 'vupc bjly nwdg cgkn';   // ⚠️ use App Password (not real one)
  $mail->SMTPSecure = 'tls';
  $mail->Port = 587;

  $mail->setFrom('yourgmail@gmail.com', 'Dormitory Ticketing System');
  $mail->addAddress($email, $name);
  $mail->isHTML(true);
  $mail->Subject = 'Verify your email address';
  $mail->Body = "
    <h3>Welcome, {$name}!</h3>
    <p>Click the link below to verify your email:</p>
    <a href='http://localhost/PDTS/verify.php?token={$verification_token}'>
      Verify My Email
    </a>
    <br><br>
    <small>If you didn’t sign up, ignore this email.</small>
  ";

  if ($mail->send()) {
    back('login', '', "Registration successful! Please check your email to verify your account.");
  } else {
    back('register', "Registration successful, but email failed to send. Try verifying manually.");
  }
}


/* ===================== LOGIN (all roles) ===================== */
if (isset($_POST['login'])) {
  $email = strtolower(trim($_POST['email'] ?? ''));
  $pass  = $_POST['password'] ?? '';

  if ($email === '' || $pass === '') {
    back('login', "Please enter email and password.");
  }

  // fetch by email; ignore soft-deleted accounts
  $stmt = $conn->prepare(
    "SELECT id, name, email, password, role, student_id, block, room_number,gender, is_deleted
     FROM profile
     WHERE email=? LIMIT 1"
  );
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $user = $stmt->get_result()->fetch_assoc();

  if (!$user || (int)$user['is_deleted'] === 1 || !password_verify($pass, $user['password'])) {
    back('login', "Incorrect email or password.");
  }

  // set sessions used by your pages
  $_SESSION['profile_id']  = (int)$user['id'];      // keep both keys if you already used 'user_id'
  $_SESSION['user_id']     = (int)$user['id'];
  $_SESSION['name']        = $user['name'];
  $_SESSION['email']       = $user['email'];
  $_SESSION['role']        = $user['role'];
  $_SESSION['block']       = $user['block'];
  $_SESSION['student_id']  = $user['student_id'];
  $_SESSION['room_number'] = $user['room_number'];
  $_SESSION['gender']      = strtolower(trim((string)($user['gender'] ?? ''))); 

  

  // redirect by role
  switch ($user['role']) {
    case 'ketua_penyelia':   header("Location: ketua_penyelia_page.php");   break;
    case 'penyelia':         header("Location: penyelia_page.php");         break;
    case 'technician':       header("Location: technician_page.php");       break;
    case 'student':          header("Location: student_page.php");          break;
    case 'admin':            header("Location: admin_page.php");            break;
    default:                 header("Location: index.php");                 break;
  }
  exit();
}

// Fallback if neither form was submitted:
header("Location: index.php");
exit();
