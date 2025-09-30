<?php
session_start();
require_once 'config.php';

// Only allow technicians
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'technician') {
    header("Location: ./index.php");
    exit();
}

$techId = $_SESSION['user_id'];

// ===== Stats =====
$totalAssigned = $conn->query("SELECT COUNT(*) AS c FROM complaints WHERE assigned_to=$techId AND is_deleted=0")->fetch_assoc()['c'];
$pending       = $conn->query("SELECT COUNT(*) AS c FROM complaints WHERE assigned_to=$techId AND status='Pending' AND is_deleted=0")->fetch_assoc()['c'];
$inProgress    = $conn->query("SELECT COUNT(*) AS c FROM complaints WHERE assigned_to=$techId AND status='In Progress' AND is_deleted=0")->fetch_assoc()['c'];
$completed     = $conn->query("SELECT COUNT(*) AS c FROM complaints WHERE assigned_to=$techId AND status='Completed' AND is_deleted=0")->fetch_assoc()['c'];
$rejected      = $conn->query("SELECT COUNT(*) AS c FROM complaints WHERE assigned_to=$techId AND status='Rejected' AND is_deleted=0")->fetch_assoc()['c'];
$urgentCount   = $conn->query("SELECT COUNT(*) AS c FROM complaints WHERE assigned_to=$techId AND priority='Urgent' AND status NOT IN ('Completed','Rejected') AND is_deleted=0")->fetch_assoc()['c'];

// ===== Tickets assigned =====
$tickets = $conn->query("
    SELECT c.*, p.name AS student_name, p.block, p.room_number
    FROM complaints c
    JOIN profile p ON c.student_id = p.student_id
    WHERE c.assigned_to=$techId AND c.is_deleted=0
    ORDER BY c.id DESC
");

// ===== History =====
$historyTickets = $conn->query("
    SELECT c.*, p.name AS student_name, p.block, p.room_number
    FROM complaints c
    JOIN profile p ON c.student_id = p.student_id
    WHERE c.assigned_to=$techId 
      AND c.is_deleted=0 
      AND c.status IN ('Completed','Rejected')
    ORDER BY c.id DESC
");

// ===== Profile =====
$profileRes = $conn->query("SELECT name,email FROM profile WHERE id=$techId LIMIT 1");
$profile = $profileRes->fetch_assoc();
$techName = $profile['name'] ?? '';
$techEmail = $profile['email'] ?? '';

// ===== Recent Activity =====
$recentActivity = $conn->query("
    SELECT c.title, c.status, c.updated_at
    FROM complaints c
    WHERE c.assigned_to=$techId AND c.is_deleted=0
    ORDER BY c.updated_at DESC
    LIMIT 5
");

// ===== Notifications =====
$notifications = $conn->query("
    SELECT title, status, updated_at
    FROM complaints
    WHERE assigned_to=$techId AND is_deleted=0
    ORDER BY updated_at DESC
    LIMIT 5
");
$notifCount = $notifications->num_rows;

$section = $_GET['section'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Technician Dashboard</title>
  <link rel="stylesheet" href="admin.css"> <!-- reuse admin css -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { font-family: "Segoe UI", sans-serif; margin:0; background:#f8f9fb; }
    .main-content { margin-left:240px; padding:20px; }

    /* Header */
    .header { display:flex; justify-content:space-between; align-items:center; }
    .header h1 { margin:0; font-size:24px; color:#2c3e50; }

    /* Notification Bell */
    .notif { position:relative; cursor:pointer; }
    .notif .badge {
      position:absolute; top:-6px; right:-6px;
      background:red; color:white; border-radius:50%;
      padding:2px 6px; font-size:12px;
    }
    .notif-dropdown {
      display:none; position:absolute; right:0; top:30px;
      background:#fff; border:1px solid #ddd;
      border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.15);
      width:250px; z-index:1000;
    }
    .notif-dropdown ul { list-style:none; margin:0; padding:0; }
    .notif-dropdown li {
      padding:10px; border-bottom:1px solid #eee; font-size:14px;
    }
    .notif-dropdown li:last-child { border-bottom:none; }
    .notif-dropdown li:hover { background:#f7f9fc; }

    /* Cards */
    .cards { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px,1fr)); gap:20px; margin-top:20px; }
    .card {
      background:#fff; padding:20px; border-radius:12px;
      box-shadow:0 2px 6px rgba(0,0,0,0.1);
      text-align:center; font-size:16px;
    }
    .card span { display:block; font-size:28px; margin-top:8px; font-weight:bold; }
    .card-urgent { color:#c0392b; border:2px solid #e74c3c; }

    /* Recent Activity */
    .recent-activity {
      margin-top:30px; background:#fff; padding:20px;
      border-radius:12px; box-shadow:0 2px 6px rgba(0,0,0,0.1);
    }
    .recent-activity h3 { margin:0 0 15px; color:#34495e; }
    .recent-activity table { width:100%; border-collapse:collapse; }
    .recent-activity th, .recent-activity td {
      padding:10px; border-bottom:1px solid #eee; font-size:14px;
    }
    .recent-activity tr:hover { background:#f9f9f9; }

    /* Chart */
    .chart-card {
      margin-top:30px; background:#fff; padding:20px;
      border-radius:12px; box-shadow:0 2px 6px rgba(0,0,0,0.1);
    }

    /* Status badges */
    .badge-status { padding:4px 8px; border-radius:6px; font-size:12px; color:#fff; }
    .status-pending { background:#bdc3c7; }
    .status-in-progress { background:#3498db; }
    .status-completed { background:#2ecc71; }
    .status-rejected { background:#e74c3c; }
  </style>
</head>
<body>

<div class="sidebar">
  <h2>Technician</h2>
  <ul>
    <li><a href="?section=dashboard" class="<?= $section=='dashboard'?'active':'' ?>">📊 Dashboard</a></li>
    <li><a href="?section=mytickets" class="<?= $section=='mytickets'?'active':'' ?>">🎫 My Tickets</a></li>
    <li><a href="?section=history" class="<?= $section=='history'?'active':'' ?>">🗄 History</a></li>
    <li><a href="?section=profile" class="<?= $section=='profile'?'active':'' ?>">👤 Profile</a></li>
    <li>
      <form action="logout.php" method="post"><button type="submit" class="logout-btn">⏻ Logout</button></form>
    </li>
  </ul>
</div>

<div class="main-content">

<?php if ($section == 'dashboard'): ?>

  <!-- Header -->
  <div class="header">
    <h1>Welcome, <?= htmlspecialchars($techName) ?> 👋</h1>
    <div class="notif" onclick="toggleNotif()">
      🔔
      <?php if ($notifCount > 0): ?><span class="badge"><?= $notifCount ?></span><?php endif; ?>
      <div class="notif-dropdown" id="notifDropdown">
        <ul>
          <?php if ($notifications->num_rows > 0): ?>
            <?php while($n = $notifications->fetch_assoc()): ?>
              <li>
                <strong><?= htmlspecialchars($n['title']) ?></strong><br>
                <span class="badge-status status-<?= strtolower(str_replace(' ','-',$n['status'])) ?>">
                  <?= $n['status'] ?>
                </span>
                <small><?= $n['updated_at'] ? date("d M Y, h:i A", strtotime($n['updated_at'])) : '' ?></small>

              </li>
            <?php endwhile; ?>
          <?php else: ?>
            <li>No notifications</li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>

  <!-- Cards -->
  <div class="cards">
    <div class="card">⏳ Pending <span><?= $pending ?></span></div>
    <div class="card">🔧 In Progress <span><?= $inProgress ?></span></div>
    <div class="card">✅ Completed <span><?= $completed ?></span></div>
    <div class="card">❌ Rejected <span><?= $rejected ?></span></div>
    <div class="card card-urgent">⚡ Urgent <span><?= $urgentCount ?></span></div>
  </div>

  <!-- Recent Activity -->
  <div class="recent-activity">
    <h3>📰 Recent Activity</h3>
    <table>
      <thead><tr><th>Title</th><th>Status</th><th>Updated At</th></tr></thead>
      <tbody>
        <?php if ($recentActivity->num_rows > 0): ?>
          <?php while($r=$recentActivity->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($r['title']) ?></td>
              <td><span class="badge-status status-<?= strtolower(str_replace(' ','-',$r['status'])) ?>"><?= $r['status'] ?></span></td>
             <td><?= $r['updated_at'] ? date("d M Y, h:i A", strtotime($r['updated_at'])) : '-' ?></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="3">No recent updates</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Chart -->
  <div class="chart-card">
    <h3>📊 Tickets by Status</h3>
    <canvas id="statusChart"></canvas>
  </div>

<?php elseif ($section == 'mytickets'): ?>

  <h1>My Tickets</h1>
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
          <span class="badge-status <?= $cls ?>"><?= $label ?></span>
          <form action="update_status.php" method="post" style="margin-top:6px;">
              <input type="hidden" name="id" value="<?= $row['id'] ?>">
              <input type="hidden" name="section" value="mytickets">
              <select name="status" onchange="this.form.submit()">
                  <?php if ($row['status']=='Pending'): ?>
                      <option value="Pending" selected disabled>Pending</option>
                  <?php endif; ?>
                  <option value="In Progress" <?= $row['status']=='In Progress'?'selected':'' ?>>In Progress</option>
                  <option value="Completed"   <?= $row['status']=='Completed'?'selected':'' ?>>Completed</option>
                  <option value="Rejected"    <?= $row['status']=='Rejected'?'selected':'' ?>>Rejected</option>
              </select>
          </form>
        </td>
        <td>
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
            onclick="openDetails(this)">Details</button>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

<?php elseif ($section == 'history'): ?>

  <h1>History</h1>
  <table>
    <thead>
      <tr>
        <th>No.</th><th>Student</th><th>Block</th><th>Room</th>
        <th>Title</th><th>Status</th><th>Completed At</th>
      </tr>
    </thead>
    <tbody>
      <?php $j=1; while($row=$historyTickets->fetch_assoc()): ?>
      <tr>
        <td><?= $j++ ?></td>
        <td><?= htmlspecialchars($row['student_name']) ?></td>
        <td><?= $row['block'] ?></td>
        <td><?= $row['room_number'] ?></td>
        <td><?= htmlspecialchars($row['title']) ?></td>
        <td><span class="badge-status status-<?= strtolower(str_replace(' ', '-', $row['status'])) ?>"><?= $row['status'] ?></span></td>
        <td><?= $row['updated_at'] ? date("d M Y, h:i A", strtotime($row['updated_at'])) : '-' ?></td>

      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

<?php elseif ($section == 'profile'): ?>

  <h1>My Profile</h1>
  <form method="post" action="update_profile.php">
    <label>Name</label>
    <input type="text" name="name" value="<?= htmlspecialchars($techName) ?>" required>
    <label>Email</label>
    <input type="email" name="email" value="<?= htmlspecialchars($techEmail) ?>" required>
    <label>New Password</label>
    <input type="password" name="password" placeholder="Leave blank if no change">
    <button type="submit">Update</button>
  </form>

<?php endif; ?>
</div>

<!-- Ticket Details Modal -->
<div id="detailsModal" class="modal" style="display:none;">
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
function toggleNotif(){
  const dropdown = document.getElementById("notifDropdown");
  dropdown.style.display = dropdown.style.display === "block" ? "none" : "block";
}

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
function closeDetails() { document.getElementById("detailsModal").style.display = "none"; }

// Chart.js
const ctx = document.getElementById('statusChart');
if(ctx){
  new Chart(ctx, {
    type: 'pie',
    data: {
      labels: ['Pending','In Progress','Completed','Rejected'],
      datasets: [{
               data: [<?= $pending ?>, <?= $inProgress ?>, <?= $completed ?>, <?= $rejected ?>],
        backgroundColor: ['#bdc3c7','#3498db','#2ecc71','#e74c3c']
      }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
  });
}
</script>

</body>
</html>
