<?php
session_start();
require_once 'config.php';

// Only allow logged-in students
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit();
}

$studentId = $_SESSION['student_id'] ?? null;

if (!$studentId) {
    $_SESSION['error_message'] = "Session expired. Please login again.";
    header("Location: index.php");
    exit();
}

// Collect POST data
$title      = trim($_POST['title'] ?? '');
$category   = $_POST['category'] ?? '';
$complaint  = trim($_POST['complaint'] ?? '');
$priority   = $_POST['priority'] ?? 'Low';
$attachmentPath = null;

// ========== FILE UPLOAD ==========
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['attachment'];
    $maxSize = 30 * 1024 * 1024; // 30 MB
    $allowedTypes = ['image/jpeg','image/png','image/gif','video/mp4','video/avi','video/mov','video/mkv'];

    if ($file['size'] > $maxSize) {
        $_SESSION['error_message'] = "File size exceeds 30MB limit.";
        header("Location: student_page.php");
        exit();
    }

    if (!in_array($file['type'], $allowedTypes)) {
        $_SESSION['error_message'] = "Invalid file type. Only images and videos allowed.";
        header("Location: student_page.php");
        exit();
    }

    // Create uploads folder if not exist
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = time() . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    $targetPath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $attachmentPath = 'uploads/' . $filename;
    } else {
        $_SESSION['error_message'] = "Failed to upload file.";
        header("Location: student_page.php");
        exit();
    }
}

// ========== INSERT INTO DATABASE ==========
$stmt = $conn->prepare("INSERT INTO complaints (student_id, title, category, complaint, priority, attachment, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
$stmt->bind_param("ssssss", $studentId, $title, $category, $complaint, $priority, $attachmentPath);

if ($stmt->execute()) {
    $_SESSION['success_message'] = "Ticket submitted successfully!";
} else {
    $_SESSION['error_message'] = "Failed to submit ticket: " . $stmt->error;
}

header("Location: student_page.php");
exit();
?>
