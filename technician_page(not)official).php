<?php
session_start();
require_once 'config.php';

// Only allow technicians
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'technician') {
    header("Location: ./index.php");
    exit();
}

$techId = $_SESSION['user_id']; // make sure you store technician id in session

// Stats
$totalAssigned = $conn->query("SELECT COUNT(*) AS c FROM complaints WHERE assigned_to=$techId AND is_deleted=0")->fetch_assoc()['c'];
$inProgress    = $conn->query("SELECT COUNT(*) AS c FROM complaints WHERE assigned_to=$techId AND status='In Progress' AND is_deleted=0")->fetch_assoc()['c'];
$completed     = $conn->query("SELECT COUNT(*) AS c FROM complaints WHERE assigned_to=$techId AND status='Completed' AND is_deleted=0")->fetch_assoc()['c'];

// Tickets assigned to this technician
$tickets = $conn->query("
    SELECT c.*, p.name AS student_name, p.block, p.room_number
    FROM complaints c
    JOIN profile p ON c.student_id = p.student_id
    WHERE c.assigned_to=$techId AND c.is_deleted=0
    ORDER BY c.id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Technician Dashboard</title>
  <link rel="stylesheet" href="admin.css"> <!-- reuse admin css -->
  <style>
    /* Modal styling */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0; top: 0;
      width: 100%; height: 100%;
      background: rgba(0,0,0,0.5);
      padding-top: 60px;
    }
    .modal-content {
      background: #fff;
      margin: auto;
      padding: 20px;
      border-radius: 10px;
      width: 80%;
      max-width: 600px;
    }
    .close {
      float: right;
      font-size: 22px;
      font-weight: bold;
      cursor: pointer;
    }
  </style>
</head>
<body>

<div class="sidebar">
  <h2>Technician</h2>
  <ul>
    <li><a href="?section=dashboard" class="active">📊 Dashboard</a></li>
    <li><a href="?section=mytickets">🎫 My Tickets</a></li>
    <li>
      <form action="logout.php" method="post">
        <button type="submit" class="logout-btn">⏻ Logout</button>
      </form>
    </li>
  </ul>
</div>

<div class="main-content">
  <h1>Welcome, Technician</h1>

  <!-- Overview Cards -->
  <div class="cards">
    <div class="card">Assigned <span><?= $totalAssigned ?></span></div>
    <div class="card">In Progress <span><?= $inProgress ?></span></div>
    <div class="card">Completed <span><?= $completed ?></span></div>
  </div>

  <!-- Tickets -->
  <h2 style="margin-top:20px;">My Tickets</h2>
  <table>
    <thead>
      <tr>
        <th>No.</th><th>Student</th><th>Block</th><th>Room</th>
        <th>Title</th><th>Category</th><th>Priority</th>
        <th>Status</th><th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php $i=1; while($row=$tickets->fetch_assoc()): ?>
      <tr>
        <td><?= $i++ ?></td>
        <td><?= htmlspecialchars($row['student_name']) ?></td>
        <td><?= $row['block'] ?></td>
        <td><?= $row['room_number'] ?></td>
        <td><?= htmlspecialchars($row['title']) ?></td>
        <td><?= $row['category'] ?></td>
        <td><?= $row['priority'] ?></td>
        <td>
          <?php
            $cls = 'status-'.strtolower(str_replace(' ', '-', $row['status']));
            $label = ucwords($row['status']);
          ?>
          <span class="badge <?= $cls ?>"><?= $label ?></span>

          <!-- Status dropdown -->
          <form action="update_status.php" method="post" style="margin-top:6px;">
              <input type="hidden" name="id" value="<?= $row['id'] ?>">
              <input type="hidden" name="section" value="mytickets">
              <select name="status" onchange="this.form.submit()">
                  <option value="Pending"     <?= $row['status']=='Pending'?'selected':'' ?>>Pending</option>
                  <option value="In Progress" <?= $row['status']=='In Progress'?'selected':'' ?>>In Progress</option>
                  <option value="Completed"   <?= $row['status']=='Completed'?'selected':'' ?>>Completed</option>
                  <option value="Rejected"    <?= $row['status']=='Rejected'?'selected':'' ?>>Rejected</option>
              </select>
          </form>
        </td>
        <td>
          <!-- Details button -->
          <button type="button"
            class="details-btn"
            data-title="<?= htmlspecialchars($row['title'], ENT_QUOTES) ?>"
            data-category="<?= htmlspecialchars($row['category'], ENT_QUOTES) ?>"
            data-priority="<?= htmlspecialchars($row['priority'], ENT_QUOTES) ?>"
            data-status="<?= htmlspecialchars($row['status'], ENT_QUOTES) ?>"
            data-submitted="<?= htmlspecialchars($row['created_at'] ?? '', ENT_QUOTES) ?>"
            data-description="<?= htmlspecialchars($row['complaint'], ENT_QUOTES) ?>"
            data-student="<?= htmlspecialchars($row['student_name'], ENT_QUOTES) ?>"
            data-block="<?= htmlspecialchars($row['block'], ENT_QUOTES) ?>"
            data-room="<?= htmlspecialchars($row['room_number'], ENT_QUOTES) ?>"
            data-attachment="<?= htmlspecialchars($row['attachment'] ?? '', ENT_QUOTES) ?>"
            onclick="openDetails(this)">
            Details
          </button>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<!-- Ticket Details Modal -->
<div id="detailsModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeDetails()">&times;</span>
    <h2 id="modalTitle"></h2>
    <p><strong>Category:</strong> <span id="modalCategory"></span></p>
    <p><strong>Priority:</strong> <span id="modalPriority"></span></p>
    <p><strong>Status:</strong> <span id="modalStatus"></span></p>
    <p><strong>Submitted:</strong> <span id="modalSubmitted"></span></p>
    <p><strong>Description:</strong> <span id="modalDescription"></span></p>
    <hr>
    <p><strong>Student:</strong> <span id="modalStudent"></span></p>
    <p><strong>Block:</strong> <span id="modalBlock"></span></p>
    <p><strong>Room:</strong> <span id="modalRoom"></span></p>
    <p><strong>Attachment:</strong> <a id="modalAttachment" href="#" target="_blank">View File</a></p>
  </div>
</div>

<script>
function openDetails(btn) {
  document.getElementById("modalTitle").innerText = btn.dataset.title;
  document.getElementById("modalCategory").innerText = btn.dataset.category;
  document.getElementById("modalPriority").innerText = btn.dataset.priority;
  document.getElementById("modalStatus").innerText = btn.dataset.status;
  document.getElementById("modalSubmitted").innerText = btn.dataset.submitted;
  document.getElementById("modalDescription").innerText = btn.dataset.description;
  document.getElementById("modalStudent").innerText = btn.dataset.student;
  document.getElementById("modalBlock").innerText = btn.dataset.block;
  document.getElementById("modalRoom").innerText = btn.dataset.room;

  const link = document.getElementById("modalAttachment");
  if (btn.dataset.attachment) {
    link.href = btn.dataset.attachment;
    link.style.display = "inline";
  } else {
    link.style.display = "none";
  }
  document.getElementById("detailsModal").style.display = "block";
}

function closeDetails() {
  document.getElementById("detailsModal").style.display = "none";
}
</script>

</body>
</html>
