<?php
session_start();
require_once 'config.php';

// Only admin allowed
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

if (isset($_POST['name'], $_POST['email'], $_POST['password'], $_POST['role'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    // Insert into profile
    $stmt = $conn->prepare("INSERT INTO profile (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $password, $role);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Staff account created successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to create staff: " . $stmt->error;
    }
}

header("Location: admin_page.php");
exit();
