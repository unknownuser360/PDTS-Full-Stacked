<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

if (!isset($_POST['id'])) {
    $_SESSION['error_message'] = "Invalid request.";
    header("Location: admin_page.php?section=tickets");
    exit();
}

$id = intval($_POST['id']);
$stmt = $conn->prepare("UPDATE complaints SET is_deleted=1, deleted_at=NOW() WHERE id=?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    $_SESSION['success_message'] = "Ticket moved to History.";
} else {
    $_SESSION['error_message'] = "Failed to delete ticket: " . $stmt->error;
}

$section = $_POST['section'] ?? 'tickets';
header("Location: admin_page.php?section=".$section);
exit();
