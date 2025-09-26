<?php
session_start();
require_once 'config.php';



// ========== REGISTER ==========
if (isset($_POST['register'])) {
    $rawName = trim($_POST['name']);
    $name = strtoupper($rawName); // Force uppercase
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $gender = $_POST['gender'];
    $role = 'student'; // Forced for security
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $student_id = $_POST['student_id'] ?? null;
    $room_number = $_POST['room_number'] ?? null;
    $block = $_POST['block'] ?? null;

    // ========== VALIDATION ==========
    // 1. Name must be uppercase
    if ($name !== $rawName) {
        $_SESSION['register_error'] = "Name must be in UPPERCASE.";
        $_SESSION['active_form'] = 'register';
        header("Location: index.php");
        exit();
    }

    // 2. Passwords must match
    if ($password !== $confirm_password) {
        $_SESSION['register_error'] = "Passwords do not match.";
        $_SESSION['active_form'] = 'register';
        header("Location: index.php");
        exit();
    }

    // 3. Validate student record from valid_student table
    $stmt = $conn->prepare("SELECT * FROM valid_student WHERE student_id = ? AND block = ? AND room_number = ?");
    $stmt->bind_param("sss", $student_id, $block, $room_number);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['register_error'] = "Invalid Student record. Make sure Student ID, Block, and Room Number match our records.";
        $_SESSION['active_form'] = 'register';
        header("Location: index.php");
        exit();
    }

    // 4. Check if Student ID already registered
    $stmt = $conn->prepare("SELECT * FROM profile WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $_SESSION['register_error'] = "This Student ID is already registered.";
        $_SESSION['active_form'] = 'register';
        header("Location: index.php");
        exit();
    }

    // 5. Check room capacity (max 2 students per room in same block)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM profile WHERE room_number = ? AND block = ?");
    $stmt->bind_param("ss", $room_number, $block);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['count'];
    if ($count >= 2) {
        $_SESSION['register_error'] = "Room $room_number in Block $block already has 2 students.";
        $_SESSION['active_form'] = 'register';
        header("Location: index.php");
        exit();
    }

    // 6. Check if email already used
    $stmt = $conn->prepare("SELECT * FROM profile WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $_SESSION['register_error'] = "This email is already registered.";
        $_SESSION['active_form'] = 'register';
        header("Location: index.php");
        exit();
    }

    // 7. Insert into profile
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO profile (name,email,password,phone,gender,student_id,block,room_number,role) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param("sssssssss", $name, $email, $hashedPassword, $phone, $gender, $student_id, $block, $room_number, $role);

    if ($stmt->execute()) {
        $_SESSION['register_success'] = "Registration successful! You can now log in.";
    } else {
        $_SESSION['register_error'] = "Registration failed (Database Error): " . $stmt->error;
        $_SESSION['active_form'] = 'register';
    }

    header("Location: index.php");
    exit();
}

// ========== LOGIN ==========
if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Fetch user by email
    $stmt = $conn->prepare("SELECT * FROM profile WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        // Save session (NOW includes block, room_number, student_id)
        $_SESSION['user_id']     = $user['id'];
        $_SESSION['name']        = $user['name'];
        $_SESSION['role']        = $user['role'];
        $_SESSION['student_id']  = $user['student_id'];
        $_SESSION['block']       = $user['block'];
        $_SESSION['room_number'] = $user['room_number'];

        // Redirect based on role
        switch ($user['role']) {
            case 'student': header("Location: student_page.php"); break;
            case 'penyelia': header("Location: penyelia_page.php"); break;
            case 'technician': header("Location: technician_page.php"); break;
            case 'admin': header("Location: admin_page.php"); break;
            default: header("Location: index.php"); break;
        }
        exit();
    }

    // Login failed
    $_SESSION['login_error'] = "Incorrect email or password.";
    $_SESSION['active_form'] = 'login';
    header("Location: index.php");
    exit();
}
?>
<link rel="stylesheet" href="style.css">
