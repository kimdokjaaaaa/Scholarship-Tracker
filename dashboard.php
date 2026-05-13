<?php
// dashboard.php: Main dashboard with KPI stats
require 'config.php';
requireLogin();
$pageTitle    = 'Dashboard';
$pageSubtitle = 'Overview of all scholarship applications';

// Aggregation queries
$stats = $pdo->query("
    SELECT
        COUNT(DISTINCT s.scholarship_id)                         AS total_scholarships,
        COUNT(DISTINCT ap.applicant_id)                          AS total_applicants,
        COUNT(a.application_id)                                  AS total_applications,
        SUM(CASE WHEN a.status = 'approved'  THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN a.status = 'rejected'  THEN 1 ELSE 0 END) AS rejected_count,
        SUM(CASE WHEN a.status = 'submitted' OR a.status = 'under_review' THEN 1 ELSE 0 END) AS pending_count,
        AVG(ap.gpa)                                              AS avg_gpa,
        MIN(ap.gpa)                                              AS best_gpa,
        MAX(ap.gpa)                                              AS lowest_gpa,
        SUM(CASE WHEN a.status = 'approved' THEN s.amount ELSE 0 END) AS total_awarded
    FROM applications a
    JOIN applicants   ap ON a.applicant_id   = ap.applicant_id
    JOIN scholarships s  ON a.scholarship_id = s.scholarship_id
")->fetch();

// Applications by status (for bar chart)
$byStatus = $pdo->query("
    SELECT status, COUNT(*) AS cnt
    FROM applications
    GROUP BY status
    ORDER BY cnt DESC
")->fetchAll();

// Scholarship categories summary (JOIN 3 tables)
$byCategory = $pdo->query("
    SELECT s.category, COUNT(a.application_id) AS apps,
           SUM(CASE WHEN a.status='approved' THEN 1 ELSE 0 END) AS approved,
           SUM(s.amount * (CASE WHEN a.status='approved' THEN 1 ELSE 0 END)) AS awarded
    FROM scholarships s
    LEFT JOIN applications a ON s.scholarship_id = a.scholarship_id
    LEFT JOIN applicants ap  ON a.applicant_id   = ap.applicant_id
    GROUP BY s.category
    ORDER BY apps DESC
")->fetchAll();

// Recent applications (3-table JOIN)
$recent = $pdo->query("
    SELECT a.application_id, a.status, a.submitted_at, a.score,
           CONCAT(ap.first_name,' ',ap.last_name) AS applicant_name,
           s.title AS scholarship_title, s.amount
    FROM applications a
    JOIN applicants   ap ON a.applicant_id   = ap.applicant_id
    JOIN scholarships s  ON a.scholarship_id = s.scholarship_id
    ORDER BY a.submitted_at DESC
    LIMIT 8
")->fetchAll();

// Top scholarships by applicants (subquery)
$topScholarships = $pdo->query("
    SELECT s.title, s.amount, s.status AS sch_status,
           (SELECT COUNT(*) FROM applications WHERE scholarship_id = s.scholarship_id) AS app_count,
           (SELECT COUNT(*) FROM applications WHERE scholarship_id = s.scholarship_id AND status = 'approved') AS approved
    FROM scholarships s
    ORDER BY app_count DESC
    LIMIT 5
")->fetchAll();

$maxApps = max(array_column($byStatus, 'cnt') ?: [1]);

$categoryColors = [
    'academic'  => '#3b82f6',
    'merit'     => '#c9a227',
    'need-based'=> '#10b981',
    'athletic'  => '#ef4444',
    'community' => '#8b5cf6',
    'research'  => '#f59e0b',
];

include 'includes/header.php';
?>

<!-- KPI Cards -->
<div class="stats-grid">
  <div class="stat-card" style="--accent:#c9a227">
    <div class="stat-value"><?= number_format($stats['total_applications']) ?></div>
    <div class="stat-label">Total Applications</div>
    <div class="stat-sub">Across all scholarships</div>
  </div>
  <div class="stat-card" style="--accent:#10b981">
    <div class="stat-value"><?= number_format($stats['approved_count']) ?></div>
    <div class="stat-label">Approved</div>
    <div class="stat-sub">₱<?= number_format($stats['total_awarded'],0) ?> total awarded</div>
  </div>
  <div class="stat-card" style="--accent:#f59e0b">
    <div class="stat-value"><?= number_format($stats['pending_count']) ?></div>
    <div class="stat-label">Pending Review</div>
    <div class="stat-sub">Submitted or under review</div>
  </div>
  <div class="stat-card" style="--accent:#ef4444">
    <div class="stat-value"><?= number_format($stats['rejected_count']) ?></div>
    <div class="stat-label">Rejected</div>
    <div class="stat-sub">Did not qualify</div>
  </div>
  <div class="stat-card" style="--accent:#3b82f6">
    <div class="stat-value"><?= number_format($stats['total_scholarships']) ?></div>
    <div class="stat-label">Scholarships</div>
    <div class="stat-sub"><?= number_format($stats['total_applicants']) ?> unique applicants</div>
  </div>
  <div class="stat-card" style="--accent:#8b5cf6">
    <div class="stat-value"><?= $stats['avg_gpa'] ? number_format($stats['avg_gpa'],2) : 'N/A' ?></div>
    <div class="stat-label">Avg GPA</div>
    <div class="stat-sub">Best: <?= $stats['best_gpa'] ?? 'N/A' ?></div>
  </div>
</div>

<!-- Charts + Recent -->
<div class="two-col">
  <!-- Applications by Status -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Applications by Status</span>
    </div>
    <div class="chart-bar-wrap">
      <?php foreach ($byStatus as $row):
        $pct = $maxApps ? round($row['cnt'] / $maxApps * 100) : 0;
        $badgeClass = 'badge-' . $row['status'];
        $fillColors = [
          'submitted'=>'#3b82f6','under_review'=>'#f59e0b','shortlisted'=>'#8b5cf6',
          'approved'=>'#10b981','rejected'=>'#ef4444','withdrawn'=>'#94a3b8'
        ];
        $color = $fillColors[$row['status']] ?? '#94a3b8';
      ?>
      <div class="chart-bar-item">
        <span class="chart-bar-label"><span class="badge badge-<?= $row['status'] ?>"><?= $statusLabels[$row['status']] ?? $row['status'] ?></span></span>
        <div class="chart-bar-track">
          <div class="chart-bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>">
            <?= $pct > 15 ? $row['cnt'] : '' ?>
          </div>
        </div>
        <span class="chart-bar-count"><?= $row['cnt'] ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Top Scholarships -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Top Scholarships</span>
      <a href="scholarships.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <table>
      <thead><tr>
        <th>Scholarship</th><th>Apps</th><th>Approved</th>
      </tr></thead>
      <tbody>
        <?php foreach ($topScholarships as $row): ?>
        <tr>
          <td>
            <div style="font-weight:500"><?= e($row['title']) ?></div>
            <div class="text-muted text-sm">₱<?= number_format($row['amount'],0) ?></div>
          </td>
          <td><?= $row['app_count'] ?></td>
          <td><span class="badge badge-approved"><?= $row['approved'] ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Recent Applications -->
<div class="card section-gap">
  <div class="card-header">
    <span class="card-title">Recent Applications</span>
    <a href="applications.php" class="btn btn-outline btn-sm">View All</a>
  </div>
  <div class="table-responsive">
    <table>
      <thead><tr>
        <th>#</th><th>Applicant</th><th>Scholarship</th><th>Status</th><th>Score</th><th>Date</th>
      </tr></thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
        <tr>
          <td class="text-muted">#<?= $r['application_id'] ?></td>
          <td><strong><?= e($r['applicant_name']) ?></strong></td>
          <td><?= e($r['scholarship_title']) ?></td>
          <td><span class="badge badge-<?= $r['status'] ?>"><?= $statusLabels[$r['status']] ?? $r['status'] ?></span></td>
          <td><?= $r['score'] ? number_format($r['score'],1) : '<span class="text-muted">—</span>' ?></td>
          <td class="text-muted text-sm"><?= date('M d, Y', strtotime($r['submitted_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Category Breakdown -->
<div class="card section-gap">
  <div class="card-header">
    <span class="card-title">Applications by Category</span>
  </div>
  <div class="table-responsive">
    <table>
      <thead><tr>
        <th>Category</th><th>Applications</th><th>Approved</th><th>Amount Awarded</th>
      </tr></thead>
      <tbody>
        <?php foreach ($byCategory as $cat): $color = $categoryColors[$cat['category']] ?? '#6b7280'; ?>
        <tr>
          <td>
            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= $color ?>;margin-right:.5rem"></span>
            <?= ucfirst($cat['category']) ?>
          </td>
          <td><?= $cat['apps'] ?: 0 ?></td>
          <td><?= $cat['approved'] ?: 0 ?></td>
          <td>₱<?= number_format($cat['awarded'] ?: 0, 0) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
