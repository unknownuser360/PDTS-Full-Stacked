<?php
session_start();
require_once 'config.php';

// Only admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$ticketId = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
$techId   = isset($_POST['technician_id']) && $_POST['technician_id'] !== '' ? intval($_POST['technician_id']) : null;

if ($ticketId <= 0) {
    $_SESSION['error_message'] = "Invalid ticket.";
    header("Location: admin_page.php?section=tickets");
    exit();
}

if ($techId !== null) {
    // Verify technician exists and active
    $chk = $conn->prepare("SELECT id FROM profile WHERE id=? AND role='technician' AND is_deleted=0");
    $chk->bind_param("i", $techId);
    $chk->execute();
    $exists = $chk->get_result()->num_rows > 0;
    if (!$exists) {
        $_SESSION['error_message'] = "Technician not found.";
        header("Location: admin_page.php?section=tickets");
        exit();
    }
}

$stmt = $conn->prepare("UPDATE complaints SET assigned_to=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
if ($techId === null) {
    // unassign
    $null = null;
    $stmt->bind_param("ii", $null, $ticketId);
} else {
    $stmt->bind_param("ii", $techId, $ticketId);
}

if ($stmt->execute()) {
    $_SESSION['success_message'] = $techId ? "Technician assigned." : "Ticket unassigned.";
} else {
    $_SESSION['error_message'] = "Failed to assign: " . $stmt->error;
}

$section = $_POST['section'] ?? 'tickets';
header("Location: admin_page.php?section=".$section);
exit();
