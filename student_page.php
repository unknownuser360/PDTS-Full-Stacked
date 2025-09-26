<?php
session_start();

// If not logged in or not a student, redirect
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit();
}

// Pull session data safely
$studentName   = $_SESSION['name']        ?? 'Unknown';
$studentId     = $_SESSION['student_id']  ?? 'N/A';
$studentBlock  = $_SESSION['block']       ?? 'N/A';
$studentRoom   = $_SESSION['room_number'] ?? 'N/A';

require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="student.css">
</head>
<body>
    <!-- HEADER -->
    <div class="header">
        <h1>Politeknik Dormitory Ticketing System</h1>
        <div class="profile" onclick="toggleProfileDropdown()">
            <span class="profile-logo">👤</span>
            <span><?= htmlspecialchars($studentName) ?></span>
            <span class="profile-arrow">▼</span>

            <!-- Dropdown -->
            <div id="profileDropdown" class="profile-dropdown">
                <p><strong>ID:</strong> <?= htmlspecialchars($studentId) ?></p>
                <p><strong>Block:</strong> <?= htmlspecialchars($studentBlock) ?></p>
                <p><strong>Room:</strong> <?= htmlspecialchars($studentRoom) ?></p>
                <hr>
                <form action="logout.php" method="post" class="logout-form">
                    <button type="submit">
                        <span class="logout-icon">⏻</span> Logout
                 </button>
            </form>


            </div>
        </div>
    </div>

    <!-- DASHBOARD CONTENT -->
    <div class="dashboard-container">
        <!-- Welcome Card -->
        <div class="card welcome-card">
            <h2 class="highlight">Welcome, <?= htmlspecialchars($studentName) ?></h2>
            <p class="subtitle">Submit and track your dormitory maintenance tickets</p>
        </div>

        <!-- Tickets Table -->
        <div class="card">
            <div class="card-header">
                <h3>Your Tickets</h3>
                <button class="small-btn" onclick="openModal('complaintModal')">+ New Ticket</button>
            </div>

            <?php
            $stmt = $conn->prepare("SELECT * FROM complaints WHERE student_id = ? ORDER BY id DESC");
            $stmt->bind_param("s", $studentId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0): ?>
                <table class="complaint-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $count = 1; while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $count ?></td>
                            <td><?= htmlspecialchars($row['title']) ?></td>
                            <td><?= htmlspecialchars($row['category']) ?></td>
                            <td><span class="badge priority-<?= strtolower($row['priority']) ?>"><?= $row['priority'] ?></span></td>
                            <td><span class="badge status-<?= strtolower(str_replace(' ', '-', $row['status'])) ?>"><?= $row['status'] ?></span></td>
                            <td>
                                <button class="details-btn"
                                    data-id="<?= $row['id'] ?>"
                                    data-title="<?= htmlspecialchars($row['title']) ?>"
                                    data-category="<?= htmlspecialchars($row['category']) ?>"
                                    data-priority="<?= $row['priority'] ?>"
                                    data-status="<?= $row['status'] ?>"
                                    data-submitted="<?= $row['created_at'] ?>"
                                    data-description="<?= htmlspecialchars($row['complaint']) ?>"
                                    data-attachment="<?= $row['attachment'] ?? '' ?>"
                                    onclick="openDetails(this)">
                                    Details
                                </button>
                            </td>
                        </tr>
                        <?php $count++; endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No tickets submitted yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- SUBMIT TICKET MODAL -->
    <div id="complaintModal" class="modal">
        <div class="modal-content card">
            <span class="close" onclick="closeModal('complaintModal')">&times;</span>
            <h3>Submit Ticket</h3>
            <form action="submit_complaint.php" method="post" enctype="multipart/form-data">
                <input type="text" name="title" placeholder="Ticket Title" required>
                <select name="category" required>
                    <option value="">-- Select Category --</option>
                    <option value="Electrical">Electrical</option>
                    <option value="Plumbing">Plumbing</option>
                    <option value="Furniture">Furniture</option>
                    <option value="Others">Others</option>
                </select>
                <textarea name="complaint" placeholder="Describe your issue..." required></textarea>
                <select name="priority" required>
                    <option value="Low">Low</option>
                    <option value="Medium">Medium</option>
                    <option value="High">High</option>
                    <option value="Urgent">Urgent</option>
                </select>
                <input type="file" name="attachment" accept="image/*,video/*" />
                <button type="submit">Submit Ticket</button>
            </form>
        </div>
    </div>

    <!-- DETAILS MODAL -->
    <div id="detailsModal" class="modal">
        <div class="modal-content card ticket-details">
            <span class="close" onclick="closeDetails()">&times;</span>
            <h3 id="modalTitle">Ticket Details</h3>
            <p><strong>Category:</strong> <span id="modalCategory"></span></p>
            <p><strong>Priority:</strong> <span id="modalPriority"></span></p>
            <p><strong>Status:</strong> <span id="modalStatus"></span></p>
            <p><strong>Submitted:</strong> <span id="modalSubmitted"></span></p>
            <p><strong>Description:</strong> <span id="modalDescription"></span></p>
            <p><strong>Attachment:</strong> <a href="#" target="_blank" id="modalAttachment">View</a></p>
        </div>
    </div>

<script src="script.js"></script>
</body>
</html>
