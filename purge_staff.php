<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

if (!isset($_POST['id'])) {
    $_SESSION['error_message'] = "Invalid request.";
    header("Location: admin_page.php?section=history");
    exit();
}

$id = intval($_POST['id']);
$stmt = $conn->prepare("DELETE FROM profile WHERE id=? AND role IN ('penyelia','technician') AND is_deleted=1");
$stmt->bind_param("i", $id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    $_SESSION['success_message'] = "Staff permanently deleted.";
} else {
    $_SESSION['error_message'] = "Failed to purge staff.";
}

$section = $_POST['section'] ?? 'history';
header("Location: admin_page.php?section=".$section);
exit();
