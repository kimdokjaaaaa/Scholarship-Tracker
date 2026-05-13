<?php
// applications.php: Core CRUD + status workflow
require 'config.php';
requireLogin();
$pageTitle    = 'Applications';
$pageSubtitle = 'Track and manage scholarship applications';

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

// Delete application
if ($action === 'delete' && $id && isAdmin()) {
    $pdo->prepare("DELETE FROM applications WHERE application_id = ?")->execute([$id]);
    flash('Application deleted.', 'error');
    header('Location: applications.php'); exit;
}

// Update application status
if ($action === 'status' && $id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus = $_POST['status'] ?? '';
    $notes     = trim($_POST['notes'] ?? '');
    $score     = $_POST['score'] !== '' ? (float)$_POST['score'] : null;
    $remarks   = trim($_POST['remarks'] ?? '');

    // Track old status for history
    $old = $pdo->prepare("SELECT status FROM applications WHERE application_id = ?");
    $old->execute([$id]);
    $oldStatus = $old->fetchColumn();

    $pdo->prepare("UPDATE applications SET status=?, score=?, remarks=?, reviewer_id=? WHERE application_id=?")
        ->execute([$newStatus, $score, $remarks ?: null, $_SESSION['user_id'], $id]);

    // Log status change
    $pdo->prepare("INSERT INTO status_history (application_id, changed_by, old_status, new_status, notes)
                   VALUES (?,?,?,?,?)")
        ->execute([$id, $_SESSION['user_id'], $oldStatus, $newStatus, $notes ?: null]);

    flash('Application status updated to ' . ($statusLabels[$newStatus] ?? $newStatus));
    header("Location: applications.php?action=view&id=$id"); exit;
}

// Save application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'new' || $action === 'edit')) {
    $fields = [
        'applicant_id'   => (int)$_POST['applicant_id'],
        'scholarship_id' => (int)$_POST['scholarship_id'],
        'essay'          => trim($_POST['essay'] ?? ''),
        'status'         => 'submitted',
    ];

    if ($action === 'edit' && $id) {
        $fields['application_id'] = $id;
        $pdo->prepare("UPDATE applications SET applicant_id=:applicant_id, scholarship_id=:scholarship_id, essay=:essay WHERE application_id=:application_id")
            ->execute($fields);
        flash('Application updated!');
        header("Location: applications.php?action=view&id=$id"); exit;
    } else {
        // Prevent duplicate applications
        $dup = $pdo->prepare("SELECT 1 FROM applications WHERE applicant_id=? AND scholarship_id=?");
        $dup->execute([$fields['applicant_id'], $fields['scholarship_id']]);
        if ($dup->fetchColumn()) {
            flash('This applicant has already applied for that scholarship.', 'error');
            header('Location: applications.php?action=new'); exit;
        }
        $pdo->prepare("INSERT INTO applications (applicant_id, scholarship_id, essay, status)
                       VALUES (:applicant_id, :scholarship_id, :essay, :status)")
            ->execute($fields);
        $newId = $pdo->lastInsertId();
        flash('Application submitted successfully!');
        header("Location: applications.php?action=view&id=$newId"); exit;
    }
}

// View single application
$viewApp = null;
if ($action === 'view' && $id) {
    // Get application with related data
    $stmt = $pdo->prepare("
        SELECT a.*,
               CONCAT(ap.first_name,' ',ap.last_name) AS applicant_name,
               ap.email AS applicant_email, ap.gpa, ap.school, ap.course, ap.year_level,
               s.title AS sch_title, s.amount, s.deadline, s.category, s.provider,
               u.full_name AS reviewer_name
        FROM applications a
        JOIN applicants   ap ON a.applicant_id   = ap.applicant_id
        JOIN scholarships s  ON a.scholarship_id  = s.scholarship_id
        LEFT JOIN users   u  ON a.reviewer_id     = u.user_id
        WHERE a.application_id = ?
    ");
    $stmt->execute([$id]);
    $viewApp = $stmt->fetch();
    if (!$viewApp) { header('Location: applications.php'); exit; }

    // Get application documents
    $docs = $pdo->prepare("SELECT * FROM documents WHERE application_id = ? ORDER BY uploaded_at DESC");
    $docs->execute([$id]);
    $docs = $docs->fetchAll();

    // Get status change history
    $history = $pdo->prepare("
        SELECT sh.*, u.full_name AS changed_by_name
        FROM status_history sh
        LEFT JOIN users u ON sh.changed_by = u.user_id
        WHERE sh.application_id = ?
        ORDER BY sh.changed_at DESC
    ");
    $history->execute([$id]);
    $history = $history->fetchAll();
}

// Load dropdown options
if ($action === 'new' || $action === 'edit') {
    $allApplicants  = $pdo->query("SELECT applicant_id, CONCAT(first_name,' ',last_name,' (',email,')') AS label FROM applicants ORDER BY last_name")->fetchAll();
    $allScholarships = $pdo->query("SELECT scholarship_id, title, amount FROM scholarships WHERE status='open' ORDER BY title")->fetchAll();
    $editApp = null;
    if ($action === 'edit' && $id) {
        $s = $pdo->prepare("SELECT * FROM applications WHERE application_id=?"); $s->execute([$id]); $editApp = $s->fetch();
    }
}

// List applications with filters
$search    = trim($_GET['q']     ?? '');
$filterSt  = $_GET['status']     ?? '';
$filterSch = (int)($_GET['sch']  ?? 0);
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 12;
$offset    = ($page - 1) * $perPage;

$where  = [];
$params = [];
if ($search) {
    $searchTerm = '%' . $search . '%';
    $where[]  = "(ap.first_name LIKE ? OR ap.last_name LIKE ? OR s.title LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}
if ($filterSt) { $where[] = "a.status = ?"; $params[] = $filterSt; }
if ($filterSch) { $where[] = "a.scholarship_id = ?"; $params[] = $filterSch; }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// 3-table JOIN with aggregation
$stmt = $pdo->prepare("
    SELECT a.application_id, a.status, a.score, a.submitted_at,
           CONCAT(ap.first_name,' ',ap.last_name) AS applicant_name,
           ap.gpa, ap.school,
           s.title AS sch_title, s.amount, s.category,
           (SELECT COUNT(*) FROM documents WHERE application_id = a.application_id) AS doc_count
    FROM applications a
    JOIN applicants   ap ON a.applicant_id   = ap.applicant_id
    JOIN scholarships s  ON a.scholarship_id = s.scholarship_id
    $whereSQL
    ORDER BY a.submitted_at DESC
    LIMIT ? OFFSET ?
");
$params[] = $perPage;
$params[] = $offset;
$stmt->execute($params);
$applications = $stmt->fetchAll();

// Build params for COUNT query (exclude pagination params)
$countParams = array_slice($params, 0, count($params) - 2);
$cStmt = $pdo->prepare("SELECT COUNT(*) FROM applications a JOIN applicants ap ON a.applicant_id=ap.applicant_id JOIN scholarships s ON a.scholarship_id=s.scholarship_id $whereSQL");
$cStmt->execute($countParams);
$totalRows  = $cStmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$schList = $pdo->query("SELECT scholarship_id, title FROM scholarships ORDER BY title")->fetchAll();

include 'includes/header.php';

// View application details
if ($action === 'view' && $viewApp):
?>

<div style="margin-bottom:1rem; display:flex; gap:.75rem; align-items:center">
  <a href="applications.php" class="btn btn-outline btn-sm">← Back</a>
  <span class="badge badge-<?= $viewApp['status'] ?>" style="font-size:.9rem;padding:.35rem 1rem"><?= $statusLabels[$viewApp['status']] ?? $viewApp['status'] ?></span>
</div>

<div class="two-col">
  <!-- Application details -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Application #<?= $id ?></span>
      <?php if ($viewApp['status'] !== 'approved' && $viewApp['status'] !== 'rejected'): ?>
      <button onclick="document.getElementById('status-modal').style.display='flex'" class="btn btn-gold btn-sm">Update Status</button>
      <?php endif; ?>
    </div>
    <table>
      <tbody>
        <tr><td style="color:var(--text-muted);width:40%">Applicant</td><td><strong><?= e($viewApp['applicant_name']) ?></strong></td></tr>
        <tr><td style="color:var(--text-muted)">Email</td><td><?= e($viewApp['applicant_email']) ?></td></tr>
        <tr><td style="color:var(--text-muted)">GPA</td><td><?= $viewApp['gpa'] ?? '—' ?></td></tr>
        <tr><td style="color:var(--text-muted)">School</td><td><?= e($viewApp['school'] ?? '—') ?></td></tr>
        <tr><td style="color:var(--text-muted)">Scholarship</td><td><strong><?= e($viewApp['sch_title']) ?></strong></td></tr>
        <tr><td style="color:var(--text-muted)">Amount</td><td>₱<?= number_format($viewApp['amount'],0) ?></td></tr>
        <tr><td style="color:var(--text-muted)">Category</td><td><?= ucfirst($viewApp['category']) ?></td></tr>
        <tr><td style="color:var(--text-muted)">Score</td><td><?= $viewApp['score'] ? number_format($viewApp['score'],2) : '—' ?></td></tr>
        <tr><td style="color:var(--text-muted)">Reviewer</td><td><?= e($viewApp['reviewer_name'] ?? '—') ?></td></tr>
        <tr><td style="color:var(--text-muted)">Submitted</td><td><?= date('M d, Y g:i A', strtotime($viewApp['submitted_at'])) ?></td></tr>
        <?php if ($viewApp['remarks']): ?>
        <tr><td style="color:var(--text-muted)">Remarks</td><td><?= e($viewApp['remarks']) ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Documents + History -->
  <div style="display:flex;flex-direction:column;gap:1.25rem">
    <div class="card">
      <div class="card-header">
        <span class="card-title">Documents (<?= count($docs) ?>)</span>
        <a href="documents.php?action=new&app_id=<?= $id ?>" class="btn btn-sm btn-outline">+ Add</a>
      </div>
      <?php if (!$docs): ?>
        <p class="text-muted text-sm">No documents uploaded yet.</p>
      <?php else: ?>
      <table>
        <thead><tr><th>Type</th><th>File</th><th>Verified</th></tr></thead>
        <tbody>
          <?php foreach ($docs as $d): ?>
          <tr>
            <td><?= ucfirst($d['doc_type']) ?></td>
            <td class="text-sm"><?= e($d['filename']) ?></td>
            <td><?= $d['verified'] ? '<span style="color:var(--green)">✔ Yes</span>' : '<span style="color:var(--text-muted)">—</span>' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-header"><span class="card-title">Status History</span></div>
      <?php if (!$history): ?>
        <p class="text-muted text-sm">No history yet.</p>
      <?php else: ?>
      <?php foreach ($history as $h): ?>
      <div style="display:flex;align-items:flex-start;gap:.75rem;padding:.6rem 0;border-bottom:1px solid var(--border)">
        <div style="min-width:80px">
          <span class="badge badge-<?= $h['new_status'] ?>"><?= $statusLabels[$h['new_status']] ?? $h['new_status'] ?></span>
        </div>
        <div>
          <div class="text-sm"><?= e($h['changed_by_name'] ?? 'System') ?> — <?= date('M d, Y g:i A', strtotime($h['changed_at'])) ?></div>
          <?php if ($h['notes']): ?><div class="text-sm text-muted"><?= e($h['notes']) ?></div><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Status modal -->
<div id="status-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
  <div class="card" style="width:460px;max-width:90vw">
    <div class="card-header">
      <span class="card-title">Update Application Status</span>
      <button onclick="document.getElementById('status-modal').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:var(--text-muted)">×</button>
    </div>
    <form method="POST" action="applications.php?action=status&id=<?= $id ?>">
      <div class="form-group mb-2">
        <label>New Status</label>
        <select name="status" required>
          <?php foreach ($statusLabels as $val => $lbl): ?>
          <option value="<?= $val ?>" <?= $viewApp['status'] === $val ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group mb-2">
        <label>Score (optional)</label>
        <input type="number" name="score" step="0.01" min="0" max="100" value="<?= $viewApp['score'] ?? '' ?>">
      </div>
      <div class="form-group mb-2">
        <label>Remarks</label>
        <textarea name="remarks"><?= e($viewApp['remarks'] ?? '') ?></textarea>
      </div>
      <div class="form-group mb-2">
        <label>Change Notes</label>
        <input type="text" name="notes" placeholder="Reason for status change…">
      </div>
      <div class="form-actions">
        <button type="button" onclick="document.getElementById('status-modal').style.display='none'" class="btn btn-outline">Cancel</button>
        <button type="submit" class="btn btn-gold">Save Status</button>
      </div>
    </form>
  </div>
</div>

<?php elseif ($action === 'new' || $action === 'edit'): ?>

<!-- FORM -->
<div class="card">
  <div class="card-header">
    <span class="card-title"><?= $action === 'edit' ? 'Edit Application' : 'New Application' ?></span>
    <a href="applications.php" class="btn btn-outline btn-sm">← Back</a>
  </div>
  <form method="POST" action="applications.php?action=<?= $action ?><?= $id ? '&id='.$id : '' ?>">
    <div class="form-grid">
      <div class="form-group">
        <label>Applicant</label>
        <select name="applicant_id" required>
          <option value="">Select applicant…</option>
          <?php foreach ($allApplicants as $ap): ?>
          <option value="<?= $ap['applicant_id'] ?>" <?= ($editApp['applicant_id'] ?? 0) == $ap['applicant_id'] ? 'selected' : '' ?>><?= e($ap['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Scholarship</label>
        <select name="scholarship_id" required>
          <option value="">Select scholarship…</option>
          <?php foreach ($allScholarships as $s): ?>
          <option value="<?= $s['scholarship_id'] ?>" <?= ($editApp['scholarship_id'] ?? 0) == $s['scholarship_id'] ? 'selected' : '' ?>><?= e($s['title']) ?> (₱<?= number_format($s['amount'],0) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group full">
        <label>Essay / Personal Statement</label>
        <textarea name="essay" rows="6"><?= e($editApp['essay'] ?? '') ?></textarea>
      </div>
    </div>
    <div class="form-actions">
      <a href="applications.php" class="btn btn-outline">Cancel</a>
      <button type="submit" class="btn btn-gold"><?= $action === 'edit' ? 'Update' : 'Submit Application' ?></button>
    </div>
  </form>
</div>

<?php else: ?>

<!-- LIST -->
<div class="card">
  <div class="card-header">
    <span class="card-title">All Applications (<?= $totalRows ?>)</span>
    <a href="applications.php?action=new" class="btn btn-gold">+ New Application</a>
  </div>

  <div class="filter-bar">
    <form method="GET">
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search applicant or scholarship…">
      <select name="status">
        <option value="">All Status</option>
        <?php foreach ($statusLabels as $val => $lbl): ?>
        <option value="<?= $val ?>" <?= $filterSt === $val ? 'selected' : '' ?>><?= $lbl ?></option>
        <?php endforeach; ?>
      </select>
      <select name="sch">
        <option value="">All Scholarships</option>
        <?php foreach ($schList as $s): ?>
        <option value="<?= $s['scholarship_id'] ?>" <?= $filterSch == $s['scholarship_id'] ? 'selected' : '' ?>><?= e($s['title']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary">Filter</button>
      <a href="applications.php" class="btn btn-outline">Reset</a>
    </form>
  </div>

  <div class="table-responsive">
    <table>
      <thead><tr>
        <th>#</th><th>Applicant</th><th>Scholarship</th><th>GPA</th><th>Score</th><th>Status</th><th>Docs</th><th>Submitted</th><th>Actions</th>
      </tr></thead>
      <tbody>
        <?php if (!$applications): ?>
        <tr><td colspan="9" class="no-data">No applications found.</td></tr>
        <?php endif; ?>
        <?php foreach ($applications as $a): ?>
        <tr class="<?= $a['status'] === 'approved' ? 'highlight-row' : '' ?>">
          <td class="text-muted">#<?= $a['application_id'] ?></td>
          <td>
            <strong><?= e($a['applicant_name']) ?></strong>
            <div class="text-sm text-muted"><?= e($a['school'] ?? '') ?></div>
          </td>
          <td>
            <?= e($a['sch_title']) ?>
            <div class="text-sm text-muted">₱<?= number_format($a['amount'],0) ?></div>
          </td>
          <td><?= $a['gpa'] ?? '—' ?></td>
          <td><?= $a['score'] ? number_format($a['score'],1) : '—' ?></td>
          <td><span class="badge badge-<?= $a['status'] ?>"><?= $statusLabels[$a['status']] ?? $a['status'] ?></span></td>
          <td><?= $a['doc_count'] ?></td>
          <td class="text-sm text-muted"><?= date('M d, Y', strtotime($a['submitted_at'])) ?></td>
          <td>
            <div class="actions">
              <a href="applications.php?action=view&id=<?= $a['application_id'] ?>" class="btn btn-sm btn-success">View</a>
              <?php if (isAdmin()): ?>
              <a href="applications.php?action=delete&id=<?= $a['application_id'] ?>"
                 class="btn btn-sm btn-danger" data-confirm="Delete this application?">Del</a>
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
