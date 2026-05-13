<?php
// applicants.php: CRUD for applicants
require 'config.php';
requireLogin();
$pageTitle    = 'Applicants';
$pageSubtitle = 'Manage student applicant profiles';

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

// Delete applicant
if ($action === 'delete' && $id && isAdmin()) {
    $pdo->prepare("DELETE FROM applicants WHERE applicant_id = ?")->execute([$id]);
    flash('Applicant deleted.', 'error');
    header('Location: applicants.php'); exit;
}

// Save applicant
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'first_name'  => trim($_POST['first_name'] ?? ''),
        'last_name'   => trim($_POST['last_name']  ?? ''),
        'email'       => trim($_POST['email']      ?? ''),
        'phone'       => trim($_POST['phone']      ?? ''),
        'address'     => trim($_POST['address']    ?? ''),
        'birth_date'  => $_POST['birth_date']      ?: null,
        'gpa'         => $_POST['gpa'] !== '' ? (float)$_POST['gpa'] : null,
        'school'      => trim($_POST['school']     ?? ''),
        'year_level'  => $_POST['year_level']      ?? null,
        'course'      => trim($_POST['course']     ?? ''),
    ];

    if ($id) {
        $fields['applicant_id'] = $id;
        $pdo->prepare("UPDATE applicants SET first_name=:first_name, last_name=:last_name,
            email=:email, phone=:phone, address=:address, birth_date=:birth_date,
            gpa=:gpa, school=:school, year_level=:year_level, course=:course
            WHERE applicant_id=:applicant_id")->execute($fields);
        flash('Applicant updated successfully!');
    } else {
        $pdo->prepare("INSERT INTO applicants (first_name,last_name,email,phone,address,birth_date,gpa,school,year_level,course)
            VALUES (:first_name,:last_name,:email,:phone,:address,:birth_date,:gpa,:school,:year_level,:course)")
            ->execute($fields);
        flash('Applicant added successfully!');
    }
    header('Location: applicants.php'); exit;
}

// Edit form
$applicant = null;
if ($action === 'edit' && $id) {
    $s = $pdo->prepare("SELECT * FROM applicants WHERE applicant_id = ?");
    $s->execute([$id]);
    $applicant = $s->fetch();
    if (!$applicant) { header('Location: applicants.php'); exit; }
}

// View single applicant
$viewData = null;
if ($action === 'view' && $id) {
    $s = $pdo->prepare("SELECT * FROM applicants WHERE applicant_id = ?");
    $s->execute([$id]);
    $viewData = $s->fetch();

    // Get applicant applications
    $viewApps = $pdo->prepare("
        SELECT a.*, s.title AS sch_title, s.amount, s.category,
               u.full_name AS reviewer_name,
               (SELECT COUNT(*) FROM documents WHERE application_id = a.application_id) AS doc_count
        FROM applications a
        JOIN scholarships s ON a.scholarship_id = s.scholarship_id
        LEFT JOIN users u   ON a.reviewer_id    = u.user_id
        WHERE a.applicant_id = ?
        ORDER BY a.submitted_at DESC
    ");
    $viewApps->execute([$id]);
    $viewApps = $viewApps->fetchAll();
}

// List applicants with filters
$search    = trim($_GET['q']          ?? '');
$filterYr  = $_GET['year_level']      ?? '';
$filterGpa = $_GET['gpa_filter']      ?? '';
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 12;
$offset    = ($page - 1) * $perPage;

$where  = [];
$params = [];
if ($search) {
    $searchTerm = '%' . $search . '%';
    $where[]  = "(ap.first_name LIKE ? OR ap.last_name LIKE ? OR ap.email LIKE ? OR ap.school LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}
if ($filterYr) {
    $where[]  = "ap.year_level = ?";
    $params[] = $filterYr;
}
if ($filterGpa === 'honor') {
    $where[] = "ap.gpa <= 1.75";
} elseif ($filterGpa === 'good') {
    $where[] = "ap.gpa BETWEEN 1.76 AND 2.25";
} elseif ($filterGpa === 'pass') {
    $where[] = "ap.gpa > 2.25";
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT ap.*,
           (SELECT COUNT(*) FROM applications WHERE applicant_id = ap.applicant_id) AS app_count,
           (SELECT COUNT(*) FROM applications WHERE applicant_id = ap.applicant_id AND status = 'approved') AS approved_count
    FROM applicants ap $whereSQL
    ORDER BY ap.last_name, ap.first_name
    LIMIT ? OFFSET ?
");
$params[] = $perPage;
$params[] = $offset;
$stmt->execute($params);
$applicants = $stmt->fetchAll();

// Build params for COUNT query (exclude pagination params)
$countParams = array_slice($params, 0, count($params) - 2);
$cStmt = $pdo->prepare("SELECT COUNT(*) FROM applicants ap $whereSQL");
$cStmt->execute($countParams);
$totalRows  = $cStmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

include 'includes/header.php';

if ($action === 'view' && $viewData):
?>

<!-- VIEW single applicant -->
<div style="margin-bottom:1rem">
  <a href="applicants.php" class="btn btn-outline btn-sm">← Back to Applicants</a>
</div>

<div class="two-col">
  <div class="card">
    <div class="card-header">
      <span class="card-title">Applicant Profile</span>
      <a href="applicants.php?action=edit&id=<?= $viewData['applicant_id'] ?>" class="btn btn-gold btn-sm">Edit</a>
    </div>
    <table>
      <tbody>
        <tr><td style="width:40%;color:var(--text-muted)">Full Name</td><td><strong><?= e($viewData['first_name'].' '.$viewData['last_name']) ?></strong></td></tr>
        <tr><td style="color:var(--text-muted)">Email</td><td><?= e($viewData['email']) ?></td></tr>
        <tr><td style="color:var(--text-muted)">Phone</td><td><?= e($viewData['phone'] ?? '—') ?></td></tr>
        <tr><td style="color:var(--text-muted)">School</td><td><?= e($viewData['school'] ?? '—') ?></td></tr>
        <tr><td style="color:var(--text-muted)">Course</td><td><?= e($viewData['course'] ?? '—') ?></td></tr>
        <tr><td style="color:var(--text-muted)">Year Level</td><td><?= e($viewData['year_level'] ?? '—') ?></td></tr>
        <tr><td style="color:var(--text-muted)">GPA</td><td><strong style="color:var(--navy)"><?= $viewData['gpa'] ?? '—' ?></strong></td></tr>
        <tr><td style="color:var(--text-muted)">Date Registered</td><td><?= date('M d, Y', strtotime($viewData['created_at'])) ?></td></tr>
      </tbody>
    </table>
  </div>

  <div class="card">
    <div class="card-header">
      <span class="card-title">Application History</span>
    </div>
    <?php if (!$viewApps): ?>
      <p class="no-data">No applications yet.</p>
    <?php else: ?>
    <table>
      <thead><tr><th>Scholarship</th><th>Status</th><th>Score</th><th>Docs</th></tr></thead>
      <tbody>
        <?php foreach ($viewApps as $a): ?>
        <tr>
          <td>
            <div><?= e($a['sch_title']) ?></div>
            <div class="text-muted text-sm">₱<?= number_format($a['amount'],0) ?></div>
          </td>
          <td><span class="badge badge-<?= $a['status'] ?>"><?= $statusLabels[$a['status']] ?? $a['status'] ?></span></td>
          <td><?= $a['score'] ? number_format($a['score'],1) : '—' ?></td>
          <td><?= $a['doc_count'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($action === 'new' || $action === 'edit'): ?>

<!-- FORM -->
<div class="card">
  <div class="card-header">
    <span class="card-title"><?= $action === 'edit' ? 'Edit Applicant' : 'Add New Applicant' ?></span>
    <a href="applicants.php" class="btn btn-outline btn-sm">← Back</a>
  </div>
  <form method="POST">
    <div class="form-grid">
      <div class="form-group">
        <label>First Name</label>
        <input type="text" name="first_name" required value="<?= e($applicant['first_name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Last Name</label>
        <input type="text" name="last_name" required value="<?= e($applicant['last_name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" required value="<?= e($applicant['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Phone</label>
        <input type="text" name="phone" value="<?= e($applicant['phone'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Date of Birth</label>
        <input type="date" name="birth_date" value="<?= $applicant['birth_date'] ?? '' ?>">
      </div>
      <div class="form-group">
        <label>GPA</label>
        <input type="number" name="gpa" step="0.01" min="1.00" max="5.00" value="<?= $applicant['gpa'] ?? '' ?>" placeholder="1.00–5.00">
      </div>
      <div class="form-group">
        <label>School / University</label>
        <input type="text" name="school" value="<?= e($applicant['school'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Year Level</label>
        <select name="year_level">
          <option value="">Select…</option>
          <?php foreach (['1st Year','2nd Year','3rd Year','4th Year','Graduate','Others'] as $yr): ?>
          <option value="<?= $yr ?>" <?= ($applicant['year_level'] ?? '') === $yr ? 'selected' : '' ?>><?= $yr ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group full">
        <label>Course / Program</label>
        <input type="text" name="course" value="<?= e($applicant['course'] ?? '') ?>">
      </div>
      <div class="form-group full">
        <label>Address</label>
        <textarea name="address"><?= e($applicant['address'] ?? '') ?></textarea>
      </div>
    </div>
    <div class="form-actions">
      <a href="applicants.php" class="btn btn-outline">Cancel</a>
      <button type="submit" class="btn btn-gold"><?= $action === 'edit' ? 'Update Applicant' : 'Add Applicant' ?></button>
    </div>
  </form>
</div>

<?php else: ?>

<!-- LIST -->
<div class="card">
  <div class="card-header">
    <span class="card-title">All Applicants (<?= $totalRows ?>)</span>
    <a href="applicants.php?action=new" class="btn btn-gold">+ Add Applicant</a>
  </div>

  <div class="filter-bar">
    <form method="GET">
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search name, email, school…">
      <select name="year_level">
        <option value="">All Year Levels</option>
        <?php foreach (['1st Year','2nd Year','3rd Year','4th Year','Graduate','Others'] as $yr): ?>
        <option value="<?= $yr ?>" <?= $filterYr === $yr ? 'selected' : '' ?>><?= $yr ?></option>
        <?php endforeach; ?>
      </select>
      <select name="gpa_filter">
        <option value="">All GPA</option>
        <option value="honor" <?= $filterGpa === 'honor' ? 'selected' : '' ?>>Honor (≤1.75)</option>
        <option value="good"  <?= $filterGpa === 'good'  ? 'selected' : '' ?>>Good (1.76–2.25)</option>
        <option value="pass"  <?= $filterGpa === 'pass'  ? 'selected' : '' ?>>Passing (>2.25)</option>
      </select>
      <button type="submit" class="btn btn-primary">Filter</button>
      <a href="applicants.php" class="btn btn-outline">Reset</a>
    </form>
  </div>

  <div class="table-responsive">
    <table>
      <thead><tr>
        <th>#</th><th>Name</th><th>Email</th><th>School</th><th>Course</th><th>Year</th><th>GPA</th><th>Apps</th><th>Actions</th>
      </tr></thead>
      <tbody>
        <?php if (!$applicants): ?>
        <tr><td colspan="9" class="no-data">No applicants found.</td></tr>
        <?php endif; ?>
        <?php foreach ($applicants as $ap): ?>
        <tr>
          <td class="text-muted"><?= $ap['applicant_id'] ?></td>
          <td><strong><?= e($ap['first_name'].' '.$ap['last_name']) ?></strong></td>
          <td class="text-sm"><?= e($ap['email']) ?></td>
          <td><?= e($ap['school'] ?? '—') ?></td>
          <td class="text-sm"><?= e($ap['course'] ?? '—') ?></td>
          <td><?= e($ap['year_level'] ?? '—') ?></td>
          <td><strong><?= $ap['gpa'] ?? '—' ?></strong></td>
          <td><?= $ap['app_count'] ?> <span class="text-muted text-sm">(<?= $ap['approved_count'] ?> ✓)</span></td>
          <td>
            <div class="actions">
              <a href="applicants.php?action=view&id=<?= $ap['applicant_id'] ?>" class="btn btn-sm btn-success">View</a>
              <a href="applicants.php?action=edit&id=<?= $ap['applicant_id'] ?>" class="btn btn-sm btn-outline">Edit</a>
              <?php if (isAdmin()): ?>
              <a href="applicants.php?action=delete&id=<?= $ap['applicant_id'] ?>"
                 class="btn btn-sm btn-danger"
                 data-confirm="Delete this applicant and all their applications?">Del</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
      <?php $qStr = http_build_query(array_merge($_GET, ['page' => $p])); ?>
      <?php if ($p == $page): ?><span class="current"><?= $p ?></span>
      <?php else: ?><a href="?<?= $qStr ?>"><?= $p ?></a>
      <?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>
