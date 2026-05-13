<?php
// scholarships.php: CRUD for scholarships
require 'config.php';
requireLogin();
$pageTitle    = 'Scholarships';
$pageSubtitle = 'Manage scholarship programs';

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

// Delete scholarship
if ($action === 'delete' && $id && isAdmin()) {
    $pdo->prepare("DELETE FROM scholarships WHERE scholarship_id = ?")->execute([$id]);
    flash('Scholarship deleted.', 'error');
    header('Location: scholarships.php'); exit;
}

// Save scholarship
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'title'           => trim($_POST['title'] ?? ''),
        'provider'        => trim($_POST['provider'] ?? ''),
        'description'     => trim($_POST['description'] ?? ''),
        'amount'          => (float)($_POST['amount'] ?? 0),
        'slots_available' => (int)($_POST['slots_available'] ?? 1),
        'min_gpa'         => (float)($_POST['min_gpa'] ?? 1.0),
        'deadline'        => $_POST['deadline'] ?? '',
        'category'        => $_POST['category'] ?? 'merit',
        'status'          => $_POST['status'] ?? 'open',
    ];

    if ($id) {
        $sql = "UPDATE scholarships SET title=:title, provider=:provider, description=:description,
                amount=:amount, slots_available=:slots_available, min_gpa=:min_gpa,
                deadline=:deadline, category=:category, status=:status
                WHERE scholarship_id=:scholarship_id";
        $fields['scholarship_id'] = $id;
        $pdo->prepare($sql)->execute($fields);
        flash('Scholarship updated successfully!');
    } else {
        $fields['created_by'] = $_SESSION['user_id'];
        $sql = "INSERT INTO scholarships (title,provider,description,amount,slots_available,min_gpa,deadline,category,status,created_by)
                VALUES (:title,:provider,:description,:amount,:slots_available,:min_gpa,:deadline,:category,:status,:created_by)";
        $pdo->prepare($sql)->execute($fields);
        flash('Scholarship added successfully!');
    }
    header('Location: scholarships.php'); exit;
}

// Load scholarship for edit form
$scholarship = null;
if ($action === 'edit' && $id) {
    $scholarship = $pdo->prepare("SELECT * FROM scholarships WHERE scholarship_id = ?");
    $scholarship->execute([$id]);
    $scholarship = $scholarship->fetch();
    if (!$scholarship) { header('Location: scholarships.php'); exit; }
}

// List with search and filter
$search   = trim($_GET['q']        ?? '');
$filterCat= $_GET['category']     ?? '';
$filterSt = $_GET['status']        ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 10;
$offset   = ($page - 1) * $perPage;

$where  = [];
$params = [];

if ($search) {
    $searchTerm = '%' . $search . '%';
    $where[]  = "(s.title LIKE ? OR s.provider LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}
if ($filterCat) {
    $where[]  = "s.category = ?";
    $params[] = $filterCat;
}
if ($filterSt) {
    $where[]  = "s.status = ?";
    $params[] = $filterSt;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get scholarships with application counts
$listSQL = "
    SELECT s.*,
           (SELECT COUNT(*) FROM applications WHERE scholarship_id = s.scholarship_id) AS app_count,
           (SELECT COUNT(*) FROM applications WHERE scholarship_id = s.scholarship_id AND status = 'approved') AS approved_count
    FROM scholarships s
    $whereSQL
    ORDER BY s.created_at DESC
    LIMIT ? OFFSET ?
";
$params[] = $perPage;
$params[] = $offset;
$stmt = $pdo->prepare($listSQL);
$stmt->execute($params);
$scholarships = $stmt->fetchAll();

$countParams = array_slice($params, 0, count($params) - 2);
$countSQL = "SELECT COUNT(*) FROM scholarships s $whereSQL";
$cStmt = $pdo->prepare($countSQL);
$cStmt->execute($countParams);
$totalRows = $cStmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

include 'includes/header.php';

if ($action === 'new' || $action === 'edit'):
?>

<!-- Form for adding/editing scholarship -->
<div class="card">
  <div class="card-header">
    <span class="card-title"><?= $action === 'edit' ? 'Edit Scholarship' : 'Add New Scholarship' ?></span>
    <a href="scholarships.php" class="btn btn-outline btn-sm">← Back</a>
  </div>
  <form method="POST">
    <div class="form-grid">
      <div class="form-group full">
        <label>Scholarship Title</label>
        <input type="text" name="title" required value="<?= e($scholarship['title'] ?? '') ?>" placeholder="e.g. Presidential Excellence Award">
      </div>
      <div class="form-group">
        <label>Provider / Sponsor</label>
        <input type="text" name="provider" required value="<?= e($scholarship['provider'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Amount (₱)</label>
        <input type="number" name="amount" step="0.01" min="0" required value="<?= $scholarship['amount'] ?? '' ?>">
      </div>
      <div class="form-group">
        <label>Slots Available</label>
        <input type="number" name="slots_available" min="1" required value="<?= $scholarship['slots_available'] ?? 1 ?>">
      </div>
      <div class="form-group">
        <label>Minimum GPA Required</label>
        <input type="number" name="min_gpa" step="0.01" min="1.00" max="5.00" value="<?= $scholarship['min_gpa'] ?? '1.00' ?>">
      </div>
      <div class="form-group">
        <label>Application Deadline</label>
        <input type="date" name="deadline" required value="<?= $scholarship['deadline'] ?? '' ?>">
      </div>
      <div class="form-group">
        <label>Category</label>
        <select name="category">
          <?php foreach (['academic','athletic','need-based','merit','community','research'] as $cat): ?>
          <option value="<?= $cat ?>" <?= ($scholarship['category'] ?? '') === $cat ? 'selected' : '' ?>><?= ucfirst($cat) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Status</label>
        <select name="status">
          <?php foreach (['open','closed','paused'] as $s): ?>
          <option value="<?= $s ?>" <?= ($scholarship['status'] ?? 'open') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group full">
        <label>Description</label>
        <textarea name="description"><?= e($scholarship['description'] ?? '') ?></textarea>
      </div>
    </div>
    <div class="form-actions">
      <a href="scholarships.php" class="btn btn-outline">Cancel</a>
      <button type="submit" class="btn btn-gold"><?= $action === 'edit' ? 'Update Scholarship' : 'Add Scholarship' ?></button>
    </div>
  </form>
</div>

<?php else: ?>

<!-- List all scholarships -->
<div class="card">
  <div class="card-header">
    <span class="card-title">All Scholarships (<?= $totalRows ?>)</span>
    <a href="scholarships.php?action=new" class="btn btn-gold">+ Add Scholarship</a>
  </div>

  <!-- Filter Bar -->
  <div class="filter-bar">
    <form method="GET">
      <input type="hidden" name="action" value="list">
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search title or provider…">
      <select name="category">
        <option value="">All Categories</option>
        <?php foreach (['academic','athletic','need-based','merit','community','research'] as $cat): ?>
        <option value="<?= $cat ?>" <?= $filterCat === $cat ? 'selected' : '' ?>><?= ucfirst($cat) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status">
        <option value="">All Status</option>
        <?php foreach (['open','closed','paused'] as $s): ?>
        <option value="<?= $s ?>" <?= $filterSt === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary">Filter</button>
      <a href="scholarships.php" class="btn btn-outline">Reset</a>
    </form>
  </div>

  <div class="table-responsive">
    <table>
      <thead><tr>
        <th>#</th><th>Title</th><th>Provider</th><th>Amount</th><th>Slots</th><th>Deadline</th><th>Category</th><th>Status</th><th>Apps</th><th>Actions</th>
      </tr></thead>
      <tbody>
        <?php if (!$scholarships): ?>
        <tr><td colspan="10" class="no-data">No scholarships found.</td></tr>
        <?php endif; ?>
        <?php foreach ($scholarships as $s): ?>
        <tr>
          <td class="text-muted"><?= $s['scholarship_id'] ?></td>
          <td><strong><?= e($s['title']) ?></strong></td>
          <td><?= e($s['provider']) ?></td>
          <td>₱<?= number_format($s['amount'],0) ?></td>
          <td><?= $s['slots_available'] ?></td>
          <td><?= date('M d, Y', strtotime($s['deadline'])) ?></td>
          <td><?= ucfirst($s['category']) ?></td>
          <td><span class="badge badge-<?= $s['status'] ?>"><?= ucfirst($s['status']) ?></span></td>
          <td><?= $s['app_count'] ?> <span class="text-muted text-sm">(<?= $s['approved_count'] ?> approved)</span></td>
          <td>
            <div class="actions">
              <a href="scholarships.php?action=edit&id=<?= $s['scholarship_id'] ?>" class="btn btn-sm btn-outline">Edit</a>
              <?php if (isAdmin()): ?>
              <a href="scholarships.php?action=delete&id=<?= $s['scholarship_id'] ?>"
                 class="btn btn-sm btn-danger"
                 data-confirm="Delete this scholarship? All related applications will also be removed.">Delete</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
      <?php
      $qStr = http_build_query(array_merge($_GET, ['page' => $p]));
      ?>
      <?php if ($p == $page): ?>
        <span class="current"><?= $p ?></span>
      <?php else: ?>
        <a href="?<?= $qStr ?>"><?= $p ?></a>
      <?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>
