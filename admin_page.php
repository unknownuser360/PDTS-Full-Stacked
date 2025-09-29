<?php
session_start();
require_once 'config.php';

// Only admin can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ./index.php");
    exit();
}

// Prevent caching of authed pages (helps on logout/back issues)
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Feedback messages
$success = $_SESSION['success_message'] ?? '';
$error   = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);



function showMessage($msg, $type = 'success') {
    if (!empty($msg)) {
        $class = $type === 'success' ? 'toast-success' : 'toast-error';
        return "
        <div class='toast $class'>
            <span>$msg</span>
            <button type='button' class='close-btn'>&times;</button>
        </div>";
    }
    return '';
}



// Active section
$activeSection = $_GET['section'] ?? 'dashboard';


/* ===========================
   STATS (exclude soft-deleted)
   =========================== */
$total      = $conn->query("SELECT COUNT(*) AS c FROM complaints WHERE is_deleted=0")->fetch_assoc()['c'];
$pending    = $conn->query("SELECT COUNT(*) AS c FROM complaints WHERE is_deleted=0 AND status='Pending'")->fetch_assoc()['c'];
$inprogress = $conn->query("SELECT COUNT(*) AS c FROM complaints WHERE is_deleted=0 AND status='In Progress'")->fetch_assoc()['c'];
$completed  = $conn->query("SELECT COUNT(*) AS c FROM complaints WHERE is_deleted=0 AND status='Completed'")->fetch_assoc()['c'];
$rejected   = $conn->query("SELECT COUNT(*) AS c FROM complaints WHERE is_deleted=0 AND status='Rejected'")->fetch_assoc()['c'];

/* ===========================
   DASHBOARD CHART DATA
   =========================== */

// Tickets by category
$catRows = $conn->query("SELECT category, COUNT(*) AS cnt
                         FROM complaints
                         WHERE is_deleted=0
                         GROUP BY category");
$chartCategoryLabels = [];
$chartCategoryCounts = [];
while ($r = $catRows->fetch_assoc()) {
    $chartCategoryLabels[] = $r['category'];
    $chartCategoryCounts[] = (int)$r['cnt'];
}

// Tickets by block split by gender (join to profile)
$blockGenderRows = $conn->query("
    SELECT p.block,
           SUM(CASE WHEN p.gender='male' THEN 1 ELSE 0 END) AS male_cnt,
           SUM(CASE WHEN p.gender='female' THEN 1 ELSE 0 END) AS female_cnt
    FROM complaints c
    JOIN profile p ON c.student_id = p.student_id
    WHERE c.is_deleted=0
    GROUP BY p.block
    ORDER BY p.block ASC
");
$blocks = []; $maleCounts = []; $femaleCounts = [];
while ($r = $blockGenderRows->fetch_assoc()) {
    $blocks[] = $r['block'] ?? 'N/A';
    $maleCounts[] = (int)$r['male_cnt'];
    $femaleCounts[] = (int)$r['female_cnt'];
}

/* ===========================
   TICKETS LIST (exclude soft-deleted) (with Pagination + Filters)
   =========================== */
$perPage = 20; // tickets per page
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;

// Distinct values for filters (for dropdowns)
$categoriesRes = $conn->query("SELECT DISTINCT category FROM complaints WHERE is_deleted=0 ORDER BY category");
$prioritiesRes = $conn->query("SELECT DISTINCT priority FROM complaints WHERE is_deleted=0 ORDER BY priority");
$blocksRes     = $conn->query("SELECT DISTINCT block FROM profile WHERE is_deleted=0 AND block <> '' AND block IS NOT NULL ORDER BY block");

// Capture filters from GET
$statusFilter     = $_GET['status']     ?? '';
$categoryFilter   = $_GET['category']   ?? '';
$priorityFilter   = $_GET['priority']   ?? '';
$blockFilter      = $_GET['block']      ?? '';
$techFilter       = $_GET['technician'] ?? '';
$dateFromFilter   = $_GET['from']       ?? '';
$dateToFilter     = $_GET['to']         ?? '';

// Build conditions
$conditions = ["c.is_deleted=0"];
if ($statusFilter)   $conditions[] = "c.status = '".$conn->real_escape_string($statusFilter)."'";
if ($categoryFilter) $conditions[] = "c.category = '".$conn->real_escape_string($categoryFilter)."'";
if ($priorityFilter) $conditions[] = "c.priority = '".$conn->real_escape_string($priorityFilter)."'";
if ($blockFilter)    $conditions[] = "p.block = '".$conn->real_escape_string($blockFilter)."'";
if ($techFilter)     $conditions[] = "c.assigned_to = ".intval($techFilter);
if ($dateFromFilter && $dateToFilter) {
    $conditions[] = "DATE(c.created_at) BETWEEN '".$conn->real_escape_string($dateFromFilter)."' AND '".$conn->real_escape_string($dateToFilter)."'";
}

$whereSql = implode(" AND ", $conditions);

// Count tickets with filters
$totalTickets = $conn->query("
    SELECT COUNT(*) AS c
    FROM complaints c
    JOIN profile p ON c.student_id = p.student_id
    WHERE $whereSql
")->fetch_assoc()['c'];
$totalPages = ceil($totalTickets / $perPage);

// Fetch tickets with filters
$tickets = $conn->query("
    SELECT c.*, p.name, p.block, p.room_number, t.name AS tech_name
    FROM complaints c
    JOIN profile p ON c.student_id = p.student_id
    LEFT JOIN profile t ON c.assigned_to = t.id
    WHERE $whereSql
    ORDER BY c.id DESC
    LIMIT $perPage OFFSET $offset
");


/* ===========================
   TECHNICIAN LIST FOR ASSIGNMENT
   =========================== */
$techList = $conn->query("
    SELECT id, name
    FROM profile
    WHERE role='technician' AND is_deleted=0
    ORDER BY name ASC
");

/* ===========================
   STAFF (exclude admins; exclude soft-deleted) with filters + pagination
   =========================== */
$staffPerPage = 10; // staff per page
$staffPage = isset($_GET['staff_page']) ? max(1, intval($_GET['staff_page'])) : 1;
$staffOffset = ($staffPage - 1) * $staffPerPage;

// Capture staff filters
$roleFilter   = $_GET['role']   ?? '';
$searchFilter = $_GET['search'] ?? '';

// Build conditions
$staffConditions = ["role IN ('penyelia','technician')", "is_deleted=0"];
if ($roleFilter) {
    $staffConditions[] = "role = '".$conn->real_escape_string($roleFilter)."'";
}
if ($searchFilter) {
    $s = $conn->real_escape_string($searchFilter);
    $staffConditions[] = "(name LIKE '%$s%' OR email LIKE '%$s%')";
}
$staffWhereSql = implode(" AND ", $staffConditions);

// Count staff for pagination
$totalStaff = $conn->query("SELECT COUNT(*) AS c FROM profile WHERE $staffWhereSql")->fetch_assoc()['c'];
$totalStaffPages = ceil($totalStaff / $staffPerPage);

// Fetch staff with filters + pagination
$staff = $conn->query("
    SELECT *
    FROM profile
    WHERE $staffWhereSql
    ORDER BY role, name
    LIMIT $staffPerPage OFFSET $staffOffset
");


/* Staff stats (for profile card): preload into an array keyed by staff id */
$staffStats = [];
$staffIds = [];
foreach ($staff as $s) { $staffIds[] = (int)$s['id']; }
$staff->data_seek(0); // rewind

if (!empty($staffIds)) {
    $idsCsv = implode(',', array_map('intval', $staffIds));

    // Workload: open = assigned to staff AND status not completed/rejected AND ticket not deleted
    $workloadRes = $conn->query("
        SELECT assigned_to, COUNT(*) AS open_cnt
        FROM complaints
        WHERE is_deleted=0
          AND assigned_to IN ($idsCsv)
          AND status NOT IN ('Completed','Rejected')
        GROUP BY assigned_to
    ");
    while ($r = $workloadRes->fetch_assoc()) {
        $staffStats[(int)$r['assigned_to']]['open'] = (int)$r['open_cnt'];
    }

    // Completed handled
    $handledRes = $conn->query("
        SELECT assigned_to, COUNT(*) AS done_cnt
        FROM complaints
        WHERE is_deleted=0
          AND assigned_to IN ($idsCsv)
          AND status='Completed'
        GROUP BY assigned_to
    ");
    while ($r = $handledRes->fetch_assoc()) {
        $staffStats[(int)$r['assigned_to']]['done'] = (int)$r['done_cnt'];
    }

    // Rejected handled
    $rejRes = $conn->query("
        SELECT assigned_to, COUNT(*) AS rej_cnt
        FROM complaints
        WHERE is_deleted=0
          AND assigned_to IN ($idsCsv)
          AND status='Rejected'
        GROUP BY assigned_to
    ");
    while ($r = $rejRes->fetch_assoc()) {
        $staffStats[(int)$r['assigned_to']]['rejected'] = (int)$r['rej_cnt'];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="admin.css">
    <!-- Chart.js (for dashboard charts) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="sidebar">
    <h2>Admin Panel</h2>
    <ul>
    <li>
        <a href="?section=dashboard" class="<?= $activeSection=='dashboard'?'active':'' ?>">
            📊 Dashboard
        </a>
    </li>
    <li>
        <a href="?section=tickets" class="<?= $activeSection=='tickets'?'active':'' ?>">
            🎫 Ticket Management
        </a>
    </li>
    <li>
        <a href="?section=staff" class="<?= $activeSection=='staff'?'active':'' ?>">
            👥 Staff
        </a>
    </li>
    <li>
        <a href="?section=history" class="<?= $activeSection=='history'?'active':'' ?>">
            🗄️ History
        </a>
    </li>
    <li>
        <form action="logout.php" method="post">
            <button type="submit" class="logout-btn">⏻ Logout</button>
        </form>
    </li>
</ul>


</div>

<div class="main-content">

    <!-- FEEDBACK -->
  <div id="toast-container">
    <?= showMessage($success, 'success'); ?>
    <?= showMessage($error, 'error'); ?>
</div>


    <!-- DASHBOARD -->
    <section id="dashboard" class="section <?= $activeSection=='dashboard'?'active':'' ?>">
        <h1>Dashboard Overview</h1>
        <div class="cards">
            <div class="card">Total Tickets <span><?= $total ?></span></div>
            <div class="card">Pending <span><?= $pending ?></span></div>
            <div class="card">In Progress <span><?= $inprogress ?></span></div>
            <div class="card">Completed <span><?= $completed ?></span></div>
            <div class="card">Rejected <span><?= $rejected ?></span></div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-top:20px;">
            <div class="card" style="text-align:left;">
                <h3>Tickets by Category</h3>
                <canvas id="chartCategory"></canvas>
            </div>
            <div class="card" style="text-align:left;">
                <h3>Tickets by Block (Male vs Female)</h3>
                <canvas id="chartBlockGender"></canvas>
            </div>
        </div>
    </section>

    <!-- TICKETS -->
    <section id="tickets" class="section <?= $activeSection=='tickets'?'active':'' ?>">
        <h1>Ticket Management</h1>
        <!-- FILTER BAR -->
    <form method="GET" class="filter-bar">
      <input type="hidden" name="section" value="tickets">

      <!-- status -->
      <select name="status">
        <option value="">-- All Status --</option>
        <?php foreach (['Pending','In Progress','Completed','Rejected'] as $s): ?>
          <option value="<?= $s ?>" <?= $statusFilter==$s?'selected':'' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>

      <!-- category -->
      <select name="category">
        <option value="">-- All Categories --</option>
        <?php $categoriesRes->data_seek(0); while ($r = $categoriesRes->fetch_assoc()): ?>
          <option value="<?= $r['category'] ?>" <?= $categoryFilter==$r['category']?'selected':'' ?>><?= $r['category'] ?></option>
        <?php endwhile; ?>
      </select>

      <!-- priority -->
      <select name="priority">
        <option value="">-- All Priorities --</option>
        <?php $prioritiesRes->data_seek(0); while ($r = $prioritiesRes->fetch_assoc()): ?>
          <option value="<?= $r['priority'] ?>" <?= $priorityFilter==$r['priority']?'selected':'' ?>><?= $r['priority'] ?></option>
        <?php endwhile; ?>
      </select>

      <!-- block -->
      <select name="block">
        <option value="">-- All Blocks --</option>
        <?php $blocksRes->data_seek(0); while ($r = $blocksRes->fetch_assoc()): ?>
          <option value="<?= $r['block'] ?>" <?= $blockFilter==$r['block']?'selected':'' ?>><?= $r['block'] ?></option>
        <?php endwhile; ?>
      </select>

      <!-- technician -->
      <select name="technician">
        <option value="">-- All Technicians --</option>
        <?php $techList->data_seek(0); while ($t = $techList->fetch_assoc()): ?>
          <option value="<?= $t['id'] ?>" <?= $techFilter==$t['id']?'selected':'' ?>><?= $t['name'] ?></option>
        <?php endwhile; ?>
      </select>

      <!-- date range -->
      <input type="date" name="from" value="<?= $dateFromFilter ?>">
      <input type="date" name="to" value="<?= $dateToFilter ?>">

      <!-- buttons -->
      <button type="submit">Filter</button>
      <a href="?section=tickets" class="reset-btn">Reset</a>
    </form>
        <table>
           
            <thead>
                <tr>
                    <th>No.</th><th>Student</th><th>Block</th><th>Room</th>
                    <th>Title</th><th>Category</th><th>Priority</th>
                    <th>Status</th><th>Assigned</th><th>Action</th>
                </tr>
            </thead>
            <tbody>

            <?php
            $seq = $offset + 1;
            $tickets->data_seek(0);
            while($row = $tickets->fetch_assoc()): ?>
                <tr>
                    <td><?= $seq++ ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= $row['block'] ?></td>
                    <td><?= $row['room_number'] ?></td>
                    <td><?= htmlspecialchars($row['title']) ?></td>
                    <td><?= $row['category'] ?></td>
                    <td><?= $row['priority'] ?></td>
                    <td>
                        <!-- Badge (space-friendly class) -->
                        <?php
                          $cls = 'status-'.strtolower(str_replace(' ', '-', $row['status']));
                          $label = ucwords($row['status']);
                        ?>
                        <span class="badge <?= $cls ?>"><?= $label ?></span>

                        <!-- Status dropdown -->
                        <form action="update_status.php" method="post" style="margin-top:6px;">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <input type="hidden" name="section" value="tickets">
                            <select name="status" onchange="this.form.submit()">
                                <option value="Pending"     <?= $row['status']=='Pending'?'selected':'' ?>>Pending</option>
                                <option value="In Progress" <?= $row['status']=='In Progress'?'selected':'' ?>>In Progress</option>
                                <option value="Completed"   <?= $row['status']=='Completed'?'selected':'' ?>>Completed</option>
                                <option value="Rejected"    <?= $row['status']=='Rejected'?'selected':'' ?>>Rejected</option>

                            </select>
                        </form>
                    </td>

                    <td>
                        <!-- Assign technician -->
                        <form action="assign_technician.php" method="post">
                            <input type="hidden" name="ticket_id" value="<?= $row['id'] ?>">
                            <input type="hidden" name="section" value="tickets">
                            <select name="technician_id" onchange="this.form.submit()">
                                <option value="">-- Unassigned --</option>
                                <?php
                                $techList->data_seek(0);
                                while($t = $techList->fetch_assoc()):
                                    $sel = ($row['assigned_to'] == $t['id']) ? 'selected' : '';
                                ?>
                                    <option value="<?= $t['id'] ?>" <?= $sel ?>><?= htmlspecialchars($t['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </form>
                        <?php if (!empty($row['tech_name'])): ?>
                            <div style="margin-top:6px; font-size:12px; color:#555;">→ <?= htmlspecialchars($row['tech_name']) ?></div>
                        <?php endif; ?>
                    </td>

                    <td>
                        <!-- Details -->
                        <button type="button"
                            class="details-btn"
                            data-title="<?= htmlspecialchars($row['title'], ENT_QUOTES) ?>"
                            data-category="<?= htmlspecialchars($row['category'], ENT_QUOTES) ?>"
                            data-priority="<?= htmlspecialchars($row['priority'], ENT_QUOTES) ?>"
                            data-status="<?= htmlspecialchars($row['status'], ENT_QUOTES) ?>"
                            data-submitted="<?= htmlspecialchars($row['created_at'] ?? '', ENT_QUOTES) ?>"
                            data-description="<?= htmlspecialchars($row['complaint'], ENT_QUOTES) ?>"
                            data-student="<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>"
                            data-block="<?= htmlspecialchars($row['block'], ENT_QUOTES) ?>"
                            data-room="<?= htmlspecialchars($row['room_number'], ENT_QUOTES) ?>"
                            data-attachment="<?= htmlspecialchars($row['attachment'] ?? '', ENT_QUOTES) ?>"
                            onclick="openDetails(this)">
                            Details
                        </button>

                        <!-- Soft delete ticket -->
                        <form action="delete_ticket.php" method="post" onsubmit="return confirm('Move ticket to History?');" style="margin-top:6px;">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <input type="hidden" name="section" value="tickets">
                            <button type="submit" class="danger-btn">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        
     <!-- Pagination (outside table) -->
<div class="pagination">
  <?php if ($page > 1): ?>
    <a href="?section=tickets&page=<?= $page-1 ?>" class="page-btn">Prev</a>
  <?php endif; ?>

  <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <a href="?section=tickets&page=<?= $i ?>" class="page-btn <?= $i==$page ? 'active' : '' ?>">
      <?= $i ?>
    </a>
  <?php endfor; ?>

  <?php if ($page < $totalPages): ?>
    <a href="?section=tickets&page=<?= $page+1 ?>" class="page-btn">Next</a>
  <?php endif; ?>
</div>
    </section>

    <!-- STAFF -->
    <section id="staff" class="section <?= $activeSection=='staff'?'active':'' ?>">
        <h1>Staff</h1>
            <!-- STAFF FILTER BAR -->
    <form method="GET" class="filter-bar staff-filter-bar">
  <input type="hidden" name="section" value="staff">

  <!-- Role -->
  <select name="role">
    <option value="">-- All Roles --</option>
    <option value="penyelia" <?= $roleFilter=='penyelia'?'selected':'' ?>>Penyelia</option>
    <option value="technician" <?= $roleFilter=='technician'?'selected':'' ?>>Technician</option>
  </select>

  <!-- Search -->
  <input type="text" name="search" placeholder="Search name/email"
         value="<?= htmlspecialchars($searchFilter) ?>">

  <!-- Buttons -->
  <button type="submit">Filter</button>
  <a href="?section=staff" class="reset-btn">Reset</a>
</form>
        <form action="create_staff.php" method="post" class="staff-form">
            <input type="hidden" name="section" value="staff">
            <input type="text" name="name" placeholder="Full Name" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <select name="role" required>
                <option value="">-- Select Role --</option>
                <option value="penyelia">Penyelia</option>
                <option value="technician">Technician</option>
            </select>
            <button type="submit">Create Staff</button>
        </form>

        <h2>Active Staff</h2>
        <table>
            <thead><tr><th>No.</th><th>Name</th><th>Email</th><th>Role</th><th>Workload</th><th>Handled</th><th>Rejected</th><th>Action</th></tr></thead>
            <tbody>
                <?php
                $staff->data_seek(0);
                $i=1; while($row=$staff->fetch_assoc()):
                    $sid = (int)$row['id'];
                    $open = $staffStats[$sid]['open'] ?? 0;
                    $done = $staffStats[$sid]['done'] ?? 0;
                    $rej  = $staffStats[$sid]['rejected'] ?? 0;
                    $perf = ($open + $done) > 0 ? round(($done / ($open + $done)) * 100) : 0;
                ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td>
                        <button type="button" class="linklike"
                                data-staffname="<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>"
                                data-role="<?= htmlspecialchars($row['role'], ENT_QUOTES) ?>"
                                data-open="<?= $open ?>" data-done="<?= $done ?>" data-rej="<?= $rej ?>" data-perf="<?= $perf ?>"
                                onclick="openStaffCard(this)">
                            <?= htmlspecialchars($row['name']) ?>
                        </button>
                    </td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= $row['role'] ?></td>
                    <td><?= $open ?></td>
                    <td><?= $done ?></td>
                    <td><?= $rej ?></td>
                    <td>
                        <form action="delete_staff.php" method="post" onsubmit="return confirm('Move staff to History?');">
                            <input type="hidden" name="id" value="<?= $sid ?>">
                            <input type="hidden" name="section" value="staff">
                            <button type="submit" class="danger-btn">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <!-- Staff Pagination -->
<div class="pagination">
  <?php if ($staffPage > 1): ?>
    <a href="?section=staff&staff_page=<?= $staffPage-1 ?>&role=<?= urlencode($roleFilter) ?>&search=<?= urlencode($searchFilter) ?>" class="page-btn">Prev</a>
  <?php endif; ?>

  <?php for ($i = 1; $i <= $totalStaffPages; $i++): ?>
    <a href="?section=staff&staff_page=<?= $i ?>&role=<?= urlencode($roleFilter) ?>&search=<?= urlencode($searchFilter) ?>"
       class="page-btn <?= $i==$staffPage ? 'active' : '' ?>">
       <?= $i ?>
    </a>
  <?php endfor; ?>

  <?php if ($staffPage < $totalStaffPages): ?>
    <a href="?section=staff&staff_page=<?= $staffPage+1 ?>&role=<?= urlencode($roleFilter) ?>&search=<?= urlencode($searchFilter) ?>" class="page-btn">Next</a>
  <?php endif; ?>
</div>
    </section>

    <!-- HISTORY -->
    <section id="history" class="section <?= $activeSection=='history'?'active':'' ?>">
        <h1>History (Soft-deleted)</h1>

        <h3 style="margin-top:10px;">Tickets</h3>
        <table>
            <thead><tr><th>No.</th><th>Title</th><th>Category</th><th>Deleted At</th><th>Action</th></tr></thead>
            <tbody>
                <?php
                $histTickets = $conn->query("SELECT id, title, category, deleted_at FROM complaints WHERE is_deleted=1 ORDER BY deleted_at DESC");
                $n=1; while($r=$histTickets->fetch_assoc()): ?>
                    <tr>
                        <td><?= $n++ ?></td>
                        <td><?= htmlspecialchars($r['title']) ?></td>
                        <td><?= htmlspecialchars($r['category']) ?></td>
                        <td><?= $r['deleted_at'] ?></td>
                        <td>
                            <form action="purge_ticket.php" method="post" onsubmit="return confirm('Permanently delete this ticket? This cannot be undone.');" style="display:inline;">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <input type="hidden" name="section" value="history">
                                <button type="submit" class="danger-btn">Purge</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <h3 style="margin-top:20px;">Staff</h3>
        <table>
            <thead><tr><th>No.</th><th>Name</th><th>Email</th><th>Role</th><th>Deleted At</th><th>Action</th></tr></thead>
            <tbody>
                <?php
                $histStaff = $conn->query("SELECT id, name, email, role, deleted_at FROM profile WHERE is_deleted=1 ORDER BY deleted_at DESC");
                $n=1; while($r=$histStaff->fetch_assoc()): ?>
                    <tr>
                        <td><?= $n++ ?></td>
                        <td><?= htmlspecialchars($r['name']) ?></td>
                        <td><?= htmlspecialchars($r['email']) ?></td>
                        <td><?= $r['role'] ?></td>
                        <td><?= $r['deleted_at'] ?></td>
                        <td>
                            <form action="purge_staff.php" method="post" onsubmit="return confirm('Permanently delete this staff? This cannot be undone.');" style="display:inline;">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <input type="hidden" name="section" value="history">
                                <button type="submit" class="danger-btn">Purge</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </section>

</div>

<!-- TICKET DETAILS MODAL -->
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

<!-- STAFF PROFILE CARD MODAL -->
<div id="staffModal" class="modal" style="display:none;">
    <div class="modal-content">
        <span class="close" onclick="closeStaff()">&times;</span>
        <h2 id="staffName"></h2>
        <p><strong>Role:</strong> <span id="staffRole"></span></p>
        <p><strong>Open Workload:</strong> <span id="staffOpen"></span></p>
        <p><strong>Tickets Completed:</strong> <span id="staffDone"></span></p>
        <p><strong>Rejected:</strong> <span id="staffRej"></span></p>
        <p><strong>Performance:</strong> <span id="staffPerf"></span>%</p>
    </div>
</div>

<script>
// Section toggler
function showSection(id){
    document.querySelectorAll('.section').forEach(s=>s.classList.remove('active'));
    document.getElementById(id).classList.add('active');
}

// Ticket details modal
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

// Staff modal
function openStaffCard(el){
    document.getElementById('staffName').innerText = el.dataset.staffname;
    document.getElementById('staffRole').innerText = el.dataset.role;
    document.getElementById('staffOpen').innerText = el.dataset.open;
    document.getElementById('staffDone').innerText = el.dataset.done;
    document.getElementById('staffRej').innerText = el.dataset.rej;
    document.getElementById('staffPerf').innerText = el.dataset.perf;
    document.getElementById('staffModal').style.display = 'block';
}
function closeStaff(){ document.getElementById('staffModal').style.display = 'none'; }

// Chart.js
const catLabels = <?= json_encode($chartCategoryLabels) ?>;
const catCounts = <?= json_encode($chartCategoryCounts) ?>;
const blockLabels = <?= json_encode($blocks) ?>;
const maleData = <?= json_encode($maleCounts) ?>;
const femaleData = <?= json_encode($femaleCounts) ?>;

window.addEventListener('DOMContentLoaded', () => {
    const ctx1 = document.getElementById('chartCategory');
    if (ctx1) new Chart(ctx1, {
        type: 'pie',
        data: {
            labels: catLabels,
            datasets: [{
                data: catCounts,
                backgroundColor: [
                    '#3498db', // Blue
                    '#e74c3c', // Red
                    '#2ecc71', // Green
                    '#f1c40f', // Yellow
                    '#9b59b6'  // Purple
                ]
            }]
        }
    });

    const ctx2 = document.getElementById('chartBlockGender');
    if (ctx2) new Chart(ctx2, {
        type: 'bar',
        data: {
            labels: blockLabels,
            datasets: [
                { label: 'Male', data: maleData, backgroundColor: '#3498db' },
                { label: 'Female', data: femaleData, backgroundColor: '#e74c3c' }
            ]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true } }
        }
    });
});

</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.toast').forEach(toast => {
    // Manual close
    toast.querySelector('.close-btn').addEventListener('click', () => {
      toast.classList.add('hide');
      setTimeout(() => toast.remove(), 500);
    });

    // Auto close
    setTimeout(() => {
      toast.classList.add('hide');
      setTimeout(() => toast.remove(), 500);
    }, 4000);
  });
});

</script>




</body>
</html>
