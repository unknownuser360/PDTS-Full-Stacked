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

    // Name must be uppercase
    if ($name !== $rawName) {
        $_SESSION['register_error'] = "Name must be in UPPERCASE.";
        $_SESSION['active_form'] = 'register';
        header("Location: index.php");
        exit();
    }

    // Confirm password
    if ($password !== $confirm_password) {
        $_SESSION['register_error'] = "Passwords do not match!";
        $_SESSION['active_form'] = 'register';
        header("Location: index.php");
        exit();
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Step 1: Verify student_id exists in valid_student table
    $stmt = $conn->prepare("SELECT * FROM valid_student WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['register_error'] = "Invalid Student ID. Please contact admin.";
        $_SESSION['active_form'] = 'register';
        header("Location: index.php");
        exit();
    }

    // Step 2: Check if student_id already registered
    $stmt = $conn->prepare("SELECT * FROM profile WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $_SESSION['register_error'] = "This Student ID is already registered!";
        $_SESSION['active_form'] = 'register';
        header("Location: index.php");
        exit();
    }

    // Step 3: Check room limit (max 2)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM profile WHERE room_number = ?");
    $stmt->bind_param("s", $room_number);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['count'];
    if ($count >= 2) {
        $_SESSION['register_error'] = "Room $room_number already has 2 students!";
        $_SESSION['active_form'] = 'register';
        header("Location: index.php");
        exit();
    }

    // Step 4: Check email uniqueness
    $stmt = $conn->prepare("SELECT * FROM profile WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $_SESSION['register_error'] = "Email already registered!";
        $_SESSION['active_form'] = 'register';
        header("Location: index.php");
        exit();
    }

    // Step 5: Insert new student
    $stmt = $conn->prepare("INSERT INTO profile (name,email,password,phone,gender,student_id,room_number,role) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->bind_param("ssssssss", $name, $email, $hashedPassword, $phone, $gender, $student_id, $room_number, $role);

    if ($stmt->execute()) {
        $_SESSION['register_success'] = "Registration successful! You can now log in.";
    } else {
        $_SESSION['register_error'] = "Registration failed: " . $stmt->error;
        $_SESSION['active_form'] = 'register';
    }

    header("Location: index.php");
    exit();
}

// ========== LOGIN ==========
if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM profile WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];

        // Role-based redirection
        switch ($user['role']) {
            case 'student': header("Location: student_page.php"); break;
            case 'penyelia': header("Location: penyelia_page.php"); break;
            case 'technician': header("Location: technician_page.php"); break;
            case 'admin': header("Location: admin_page.php"); break;
            default: header("Location: index.php"); break;
        }
        exit();
    }

    $_SESSION['login_error'] = "Incorrect email or password!";
    $_SESSION['active_form'] = 'login';
    header("Location: index.php");
    exit();
}
?>
