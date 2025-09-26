<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

if (!isset($_POST['id'])) {
    $_SESSION['error_message'] = "Invalid request.";
    header("Location: admin_page.php?section=staff");
    exit();
}

$id = intval($_POST['id']);

// Don't allow deleting admins
$stmt = $conn->prepare("UPDATE profile SET is_deleted=1, deleted_at=NOW() WHERE id=? AND role IN ('penyelia','technician')");
$stmt->bind_param("i", $id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    $_SESSION['success_message'] = "Staff moved to History.";
} else {
    $_SESSION['error_message'] = "Failed to delete staff.";
}

$section = $_POST['section'] ?? 'staff';
header("Location: admin_page.php?section=".$section);
exit();
