<?php
// documents.php: CRUD for documents
require 'config.php';
requireLogin();
$pageTitle    = 'Documents';
$pageSubtitle = 'Manage submitted application documents';

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id']     ?? 0);
$appId  = (int)($_GET['app_id'] ?? 0);

// Delete document
if ($action === 'delete' && $id) {
    $pdo->prepare("DELETE FROM documents WHERE document_id = ?")->execute([$id]);
    flash('Document deleted.', 'error');
    header('Location: documents.php'); exit;
}

// Toggle verification status
if ($action === 'verify' && $id) {
    $pdo->prepare("UPDATE documents SET verified = NOT verified WHERE document_id = ?")->execute([$id]);
    flash('Document verification status updated.');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'documents.php')); exit;
}

// Save document
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'application_id' => (int)$_POST['application_id'],
        'doc_type'       => $_POST['doc_type'] ?? 'other',
        'filename'       => trim($_POST['filename'] ?? ''),
        'verified'       => isset($_POST['verified']) ? 1 : 0,
    ];

    if ($id) {
        $fields['document_id'] = $id;
        $pdo->prepare("UPDATE documents SET application_id=:application_id, doc_type=:doc_type, filename=:filename, verified=:verified WHERE document_id=:document_id")
            ->execute($fields);
        flash('Document updated!');
    } else {
        $pdo->prepare("INSERT INTO documents (application_id, doc_type, filename, verified) VALUES (:application_id,:doc_type,:filename,:verified)")
            ->execute($fields);
        flash('Document added!');
    }
    $back = $fields['application_id'] ? "applications.php?action=view&id={$fields['application_id']}" : 'documents.php';
    header("Location: $back"); exit;
}

// Edit form
$document = null;
if ($action === 'edit' && $id) {
    $s = $pdo->prepare("SELECT * FROM documents WHERE document_id=?"); $s->execute([$id]);
    $document = $s->fetch();
    if (!$document) { header('Location: documents.php'); exit; }
    $appId = $document['application_id'];
}

// Load application dropdown data
$allApps = $pdo->query("
    SELECT a.application_id,
           CONCAT('#',a.application_id,' – ',ap.first_name,' ',ap.last_name,' → ',s.title) AS label
    FROM applications a
    JOIN applicants ap ON a.applicant_id = ap.applicant_id
    JOIN scholarships s ON a.scholarship_id = s.scholarship_id
    ORDER BY a.application_id DESC
")->fetchAll();

// List documents with filters
$search    = trim($_GET['q']       ?? '');
$filterType= $_GET['doc_type']     ?? '';
$filterVer = $_GET['verified']     ?? '';
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 15;
$offset    = ($page - 1) * $perPage;

$where  = [];
$params = [];
if ($search) {
    $searchTerm = '%' . $search . '%';
    $where[] = "(d.filename LIKE ? OR ap.first_name LIKE ? OR ap.last_name LIKE ? OR s.title LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}
if ($filterType) { $where[] = "d.doc_type = ?"; $params[] = $filterType; }
if ($filterVer !== '')  { $where[] = "d.verified = ?"; $params[] = (int)$filterVer; }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get documents with related data
$stmt = $pdo->prepare("
    SELECT d.*,
           a.status AS app_status,
           CONCAT(ap.first_name,' ',ap.last_name) AS applicant_name,
           s.title AS sch_title
    FROM documents d
    JOIN applications a  ON d.application_id = a.application_id
    JOIN applicants   ap ON a.applicant_id    = ap.applicant_id
    JOIN scholarships s  ON a.scholarship_id  = s.scholarship_id
    $whereSQL
    ORDER BY d.uploaded_at DESC
    LIMIT ? OFFSET ?
");
$params[] = $perPage;
$params[] = $offset;
$stmt->execute($params);
$documents = $stmt->fetchAll();

// Build params for COUNT query (exclude pagination params)
$countParams = array_slice($params, 0, count($params) - 2);
$cStmt = $pdo->prepare("SELECT COUNT(*) FROM documents d JOIN applications a ON d.application_id=a.application_id JOIN applicants ap ON a.applicant_id=ap.applicant_id JOIN scholarships s ON a.scholarship_id=s.scholarship_id $whereSQL");
$cStmt->execute($countParams);
$totalRows  = $cStmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

include 'includes/header.php';

if ($action === 'new' || $action === 'edit'):
?>

<div class="card">
  <div class="card-header">
    <span class="card-title"><?= $action === 'edit' ? 'Edit Document' : 'Add Document' ?></span>
    <a href="documents.php" class="btn btn-outline btn-sm">← Back</a>
  </div>
  <form method="POST">
    <div class="form-grid">
      <div class="form-group full">
        <label>Application</label>
        <select name="application_id" required>
          <option value="">Select application…</option>
          <?php foreach ($allApps as $app): ?>
          <option value="<?= $app['application_id'] ?>" <?= ($document['application_id'] ?? $appId) == $app['application_id'] ? 'selected' : '' ?>>
            <?= e($app['label']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Document Type</label>
        <select name="doc_type" required>
          <?php foreach (['transcript','recommendation','essay','id','certificate','financial','other'] as $t): ?>
          <option value="<?= $t ?>" <?= ($document['doc_type'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Filename / Reference</label>
        <input type="text" name="filename" required value="<?= e($document['filename'] ?? '') ?>" placeholder="e.g. transcript_2024.pdf">
      </div>
      <div class="form-group">
        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
          <input type="checkbox" name="verified" style="width:auto" <?= ($document['verified'] ?? 0) ? 'checked' : '' ?>>
          Mark as Verified
        </label>
      </div>
    </div>
    <div class="form-actions">
      <a href="documents.php" class="btn btn-outline">Cancel</a>
      <button type="submit" class="btn btn-gold"><?= $action === 'edit' ? 'Update Document' : 'Add Document' ?></button>
    </div>
  </form>
</div>

<?php else: ?>

<div class="card">
  <div class="card-header">
    <span class="card-title">All Documents (<?= $totalRows ?>)</span>
    <a href="documents.php?action=new" class="btn btn-gold">+ Add Document</a>
  </div>

  <div class="filter-bar">
    <form method="GET">
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search applicant or scholarship…">
      <select name="doc_type">
        <option value="">All Types</option>
        <?php foreach (['transcript','recommendation','essay','id','certificate','financial','other'] as $t): ?>
        <option value="<?= $t ?>" <?= $filterType === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="verified">
        <option value="">All</option>
        <option value="1" <?= $filterVer === '1' ? 'selected' : '' ?>>Verified</option>
        <option value="0" <?= $filterVer === '0' ? 'selected' : '' ?>>Unverified</option>
      </select>
      <button type="submit" class="btn btn-primary">Filter</button>
      <a href="documents.php" class="btn btn-outline">Reset</a>
    </form>
  </div>

  <div class="table-responsive">
    <table>
      <thead><tr>
        <th>#</th><th>Applicant</th><th>Scholarship</th><th>Type</th><th>Filename</th><th>Verified</th><th>Uploaded</th><th>Actions</th>
      </tr></thead>
      <tbody>
        <?php if (!$documents): ?>
        <tr><td colspan="8" class="no-data">No documents found.</td></tr>
        <?php endif; ?>
        <?php foreach ($documents as $d): ?>
        <tr>
          <td class="text-muted"><?= $d['document_id'] ?></td>
          <td><?= e($d['applicant_name']) ?></td>
          <td class="text-sm"><?= e($d['sch_title']) ?></td>
          <td><?= ucfirst($d['doc_type']) ?></td>
          <td class="text-sm"><?= e($d['filename']) ?></td>
          <td>
            <a href="documents.php?action=verify&id=<?= $d['document_id'] ?>" class="badge <?= $d['verified'] ? 'badge-approved' : 'badge-withdrawn' ?>" style="text-decoration:none;cursor:pointer">
              <?= $d['verified'] ? '✔ Verified' : 'Unverified' ?>
            </a>
          </td>
          <td class="text-sm text-muted"><?= date('M d, Y', strtotime($d['uploaded_at'])) ?></td>
          <td>
            <div class="actions">
              <a href="documents.php?action=edit&id=<?= $d['document_id'] ?>" class="btn btn-sm btn-outline">Edit</a>
              <a href="documents.php?action=delete&id=<?= $d['document_id'] ?>"
                 class="btn btn-sm btn-danger" data-confirm="Delete this document record?">Del</a>
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
