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
$stmt = $conn->prepare("DELETE FROM complaints WHERE id=? AND is_deleted=1");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    $_SESSION['success_message'] = "Ticket permanently deleted.";
} else {
    $_SESSION['error_message'] = "Failed to purge ticket: " . $stmt->error;
}

$section = $_POST['section'] ?? 'history';
header("Location: admin_page.php?section=".$section);
exit();
