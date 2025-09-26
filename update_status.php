<?php
session_start();
require_once 'config.php';

if (isset($_POST['id'], $_POST['status'])) {
    $id = intval($_POST['id']);
    $status = $_POST['status'];

    // Only allow valid statuses
    $allowed = ['pending','in-progress','completed','rejected'];
    if (!in_array($status, $allowed)) {
        $_SESSION['error_message'] = "Invalid status value.";
        header("Location: admin_page.php?section=complaints");
        exit();
    }

    // Update database
    $stmt = $conn->prepare("UPDATE complaints SET status=? WHERE id=?");
    $stmt->bind_param("si", $status, $id);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Status updated successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to update status: " . $stmt->error;
    }

    // Redirect back
    $section = $_POST['section'] ?? 'dashboard';
    header("Location: admin_page.php?section=" . $section);
    exit();
} else {
    $_SESSION['error_message'] = "Invalid request.";
    header("Location: admin_page.php?section=complaints");
    exit();
}
