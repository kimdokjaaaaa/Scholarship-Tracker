<?php
// reports.php: Analytics and reports
require 'config.php';
requireLogin();
$pageTitle    = 'Reports';
$pageSubtitle = 'Analytics, statistics and detailed reports';

// Report 1: Monthly application summary
$monthlyReport = $pdo->query("
    WITH monthly_summary AS (
        SELECT
            DATE_FORMAT(submitted_at, '%Y-%m')               AS ym,
            DATE_FORMAT(submitted_at, '%M %Y')               AS month_label,
            COUNT(application_id)                            AS total_apps,
            SUM(CASE WHEN status = 'approved'     THEN 1 ELSE 0 END) AS approved,
            SUM(CASE WHEN status = 'rejected'     THEN 1 ELSE 0 END) AS rejected,
            SUM(CASE WHEN status = 'submitted' OR status = 'under_review' THEN 1 ELSE 0 END) AS pending,
            AVG(score)                                       AS avg_score,
            MAX(score)                                       AS max_score,
            MIN(score)                                       AS min_score
        FROM applications
        GROUP BY DATE_FORMAT(submitted_at, '%Y-%m'), DATE_FORMAT(submitted_at, '%M %Y')
    )
    SELECT * FROM monthly_summary
    ORDER BY ym DESC
    LIMIT 12
")->fetchAll();

// Report 2: Scholarships with above-average applications
$aboveAvgScholarships = $pdo->query("
    SELECT s.title, s.category, s.amount,
           COUNT(a.application_id) AS app_count,
           SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) AS approved_count,
           AVG(a.score) AS avg_score
    FROM scholarships s
    JOIN applications a ON s.scholarship_id = a.scholarship_id
    GROUP BY s.scholarship_id
    HAVING COUNT(a.application_id) > (
        SELECT AVG(cnt) FROM (
            SELECT COUNT(*) AS cnt
            FROM applications
            GROUP BY scholarship_id
        ) AS sub_avg
    )
    ORDER BY app_count DESC
")->fetchAll();

// Report 3: Applicants with no applications
$noApplications = $pdo->query("
    SELECT ap.applicant_id, ap.first_name, ap.last_name, ap.email, ap.school, ap.gpa, ap.created_at
    FROM applicants ap
    WHERE ap.applicant_id NOT IN (
        SELECT DISTINCT applicant_id FROM applications
    )
    ORDER BY ap.created_at DESC
")->fetchAll();

// Report 4: Top scorer per scholarship
$topScorers = $pdo->query("
    SELECT s.title AS sch_title, s.amount, s.category,
           CONCAT(ap.first_name,' ',ap.last_name) AS applicant_name,
           ap.gpa, a.score, a.status
    FROM applications a
    JOIN applicants   ap ON a.applicant_id   = ap.applicant_id
    JOIN scholarships s  ON a.scholarship_id  = s.scholarship_id
    WHERE a.score IS NOT NULL
      AND a.score = (
          SELECT MAX(a2.score)
          FROM applications a2
          WHERE a2.scholarship_id = a.scholarship_id
          AND a2.score IS NOT NULL
      )
    ORDER BY a.score DESC
")->fetchAll();

// Report 5: Reviewer performance
$reviewerStats = $pdo->query("
    SELECT u.full_name, u.role,
           COUNT(a.application_id)                                AS total_reviewed,
           SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) AS approved,
           SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
           AVG(a.score)                                           AS avg_score_given,
           MAX(a.score)                                           AS highest_score,
           MIN(a.score)                                           AS lowest_score
    FROM users u
    JOIN applications a ON u.user_id = a.reviewer_id
    GROUP BY u.user_id
    ORDER BY total_reviewed DESC
")->fetchAll();

// Report 6: Document completion per application
$docCompletion = $pdo->query("
    SELECT
        CONCAT(ap.first_name,' ',ap.last_name) AS applicant_name,
        s.title AS sch_title,
        a.status,
        COUNT(d.document_id)                                           AS doc_count,
        SUM(d.verified)                                                AS verified_count,
        SUM(CASE WHEN d.verified = 0 THEN 1 ELSE 0 END)              AS pending_docs
    FROM applications a
    JOIN applicants   ap ON a.applicant_id   = ap.applicant_id
    JOIN scholarships s  ON a.scholarship_id  = s.scholarship_id
    LEFT JOIN documents d ON a.application_id = d.application_id
    WHERE a.status IN ('submitted','under_review','shortlisted')
    GROUP BY a.application_id
    HAVING doc_count > 0
    ORDER BY pending_docs DESC, doc_count DESC
")->fetchAll();

// Report 7: School performance
$schoolStats = $pdo->query("
    SELECT ap.school,
           COUNT(DISTINCT ap.applicant_id)                           AS students,
           COUNT(a.application_id)                                   AS applications,
           SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END)   AS approved,
           AVG(ap.gpa)                                               AS avg_gpa,
           MIN(ap.gpa)                                               AS best_gpa,
           SUM(CASE WHEN a.status = 'approved' THEN s.amount ELSE 0 END) AS total_awarded
    FROM applicants ap
    LEFT JOIN applications a ON ap.applicant_id = a.applicant_id
    LEFT JOIN scholarships s ON a.scholarship_id = s.scholarship_id
    WHERE ap.school IS NOT NULL AND ap.school != ''
    GROUP BY ap.school
    ORDER BY applications DESC
")->fetchAll();

$maxMonthlyApps = max(array_column($monthlyReport, 'total_apps') ?: [1]);

include 'includes/header.php';
?>

<!-- Print button -->
<div style="text-align:right;margin-bottom:1.5rem">
  <button onclick="window.print()" class="btn btn-outline">🖨 Print Report</button>
</div>

<!-- Report 1: Monthly application summary -->
<div class="report-section">
  <h2>📅 Monthly Application Summary</h2>
  <div class="card">
    <div class="table-responsive">
      <table>
        <thead><tr>
          <th>Month</th><th>Total</th><th>Approved</th><th>Rejected</th><th>Pending</th><th>Avg Score</th><th>Max Score</th><th>Min Score</th><th>Trend</th>
        </tr></thead>
        <tbody>
          <?php if (!$monthlyReport): ?>
          <tr><td colspan="9" class="no-data">No data available.</td></tr>
          <?php endif; ?>
          <?php foreach ($monthlyReport as $row):
            $pct = $maxMonthlyApps ? round($row['total_apps'] / $maxMonthlyApps * 100) : 0;
          ?>
          <tr>
            <td><strong><?= e($row['month_label']) ?></strong></td>
            <td><?= $row['total_apps'] ?></td>
            <td><span class="badge badge-approved"><?= $row['approved'] ?></span></td>
            <td><span class="badge badge-rejected"><?= $row['rejected'] ?></span></td>
            <td><span class="badge badge-under_review"><?= $row['pending'] ?></span></td>
            <td><?= $row['avg_score'] ? number_format($row['avg_score'],1) : '—' ?></td>
            <td><?= $row['max_score'] ? number_format($row['max_score'],1) : '—' ?></td>
            <td><?= $row['min_score'] ? number_format($row['min_score'],1) : '—' ?></td>
            <td>
              <div style="background:#f1f5f9;height:16px;border-radius:4px;overflow:hidden;min-width:80px">
                <div style="height:100%;background:var(--gold);width:<?= $pct ?>%;border-radius:4px"></div>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Report 2: Above-average scholarships -->
<div class="report-section">
  <h2>🏆 High-Demand Scholarships</h2>
  <div class="card">
    <div class="table-responsive">
      <table>
        <thead><tr>
          <th>Scholarship</th><th>Category</th><th>Amount</th><th>Applications</th><th>Approved</th><th>Avg Score</th>
        </tr></thead>
        <tbody>
          <?php if (!$aboveAvgScholarships): ?>
          <tr><td colspan="6" class="no-data">No data.</td></tr>
          <?php endif; ?>
          <?php foreach ($aboveAvgScholarships as $row): ?>
          <tr class="highlight-row">
            <td><strong><?= e($row['title']) ?></strong></td>
            <td><?= ucfirst($row['category']) ?></td>
            <td>₱<?= number_format($row['amount'],0) ?></td>
            <td><?= $row['app_count'] ?></td>
            <td><span class="badge badge-approved"><?= $row['approved_count'] ?></span></td>
            <td><?= $row['avg_score'] ? number_format($row['avg_score'],1) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Report 3: Applicants without applications -->
<div class="report-section">
  <h2>👤 Registered Applicants Without Applications</h2>
  <div class="card">
    <?php if (!$noApplications): ?>
    <p style="color:var(--green);font-weight:500;padding:.5rem 0">✅ All registered applicants have submitted at least one application.</p>
    <?php else: ?>
    <div class="table-responsive">
      <table>
        <thead><tr><th>#</th><th>Name</th><th>Email</th><th>School</th><th>GPA</th><th>Registered</th></tr></thead>
        <tbody>
          <?php foreach ($noApplications as $row): ?>
          <tr>
            <td class="text-muted"><?= $row['applicant_id'] ?></td>
            <td><?= e($row['first_name'].' '.$row['last_name']) ?></td>
            <td class="text-sm"><?= e($row['email']) ?></td>
            <td><?= e($row['school'] ?? '—') ?></td>
            <td><?= $row['gpa'] ?? '—' ?></td>
            <td class="text-sm text-muted"><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Report 4: Top scorer per scholarship -->
<div class="report-section">
  <h2>🥇 Top Scorers per Scholarship</h2>
  <div class="card">
    <div class="table-responsive">
      <table>
        <thead><tr>
          <th>Scholarship</th><th>Category</th><th>Amount</th><th>Top Applicant</th><th>GPA</th><th>Score</th><th>Status</th>
        </tr></thead>
        <tbody>
          <?php if (!$topScorers): ?>
          <tr><td colspan="7" class="no-data">No scored applications yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($topScorers as $row): ?>
          <tr>
            <td><strong><?= e($row['sch_title']) ?></strong></td>
            <td><?= ucfirst($row['category']) ?></td>
            <td>₱<?= number_format($row['amount'],0) ?></td>
            <td><?= e($row['applicant_name']) ?></td>
            <td><?= $row['gpa'] ?></td>
            <td><strong style="color:var(--gold)"><?= number_format($row['score'],1) ?></strong></td>
            <td><span class="badge badge-<?= $row['status'] ?>"><?= $statusLabels[$row['status']] ?? $row['status'] ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Report 5: Reviewer performance -->
<div class="report-section">
  <h2>👥 Reviewer Performance</h2>
  <div class="card">
    <div class="table-responsive">
      <table>
        <thead><tr>
          <th>Reviewer</th><th>Role</th><th>Reviewed</th><th>Approved</th><th>Rejected</th><th>Avg Score</th><th>High</th><th>Low</th>
        </tr></thead>
        <tbody>
          <?php if (!$reviewerStats): ?>
          <tr><td colspan="8" class="no-data">No reviewer data.</td></tr>
          <?php endif; ?>
          <?php foreach ($reviewerStats as $row): ?>
          <tr>
            <td><strong><?= e($row['full_name']) ?></strong></td>
            <td><?= ucfirst($row['role']) ?></td>
            <td><?= $row['total_reviewed'] ?></td>
            <td><span class="badge badge-approved"><?= $row['approved'] ?></span></td>
            <td><span class="badge badge-rejected"><?= $row['rejected'] ?></span></td>
            <td><?= $row['avg_score_given'] ? number_format($row['avg_score_given'],1) : '—' ?></td>
            <td><?= $row['highest_score'] ? number_format($row['highest_score'],1) : '—' ?></td>
            <td><?= $row['lowest_score']  ? number_format($row['lowest_score'],1)  : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Report 6: Document completion -->
<div class="report-section">
  <h2>📄 Document Completion Status</h2>
  <div class="card">
    <div class="table-responsive">
      <table>
        <thead><tr>
          <th>Applicant</th><th>Scholarship</th><th>Status</th><th>Docs</th><th>Verified</th><th>Pending</th>
        </tr></thead>
        <tbody>
          <?php if (!$docCompletion): ?>
          <tr><td colspan="6" class="no-data">No pending document reviews.</td></tr>
          <?php endif; ?>
          <?php foreach ($docCompletion as $row): ?>
          <tr class="<?= $row['pending_docs'] > 0 ? 'highlight-row' : '' ?>">
            <td><?= e($row['applicant_name']) ?></td>
            <td><?= e($row['sch_title']) ?></td>
            <td><span class="badge badge-<?= $row['status'] ?>"><?= $statusLabels[$row['status']] ?? $row['status'] ?></span></td>
            <td><?= $row['doc_count'] ?></td>
            <td style="color:var(--green)"><?= $row['verified_count'] ?></td>
            <td style="color:<?= $row['pending_docs'] > 0 ? 'var(--red)' : 'var(--green)' ?>">
              <?= $row['pending_docs'] ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Report 7: School performance -->
<div class="report-section">
  <h2>🏫 School / University Summary</h2>
  <div class="card">
    <div class="table-responsive">
      <table>
        <thead><tr>
          <th>School</th><th>Students</th><th>Applications</th><th>Approved</th><th>Avg GPA</th><th>Best GPA</th><th>Total Awarded</th>
        </tr></thead>
        <tbody>
          <?php foreach ($schoolStats as $row): ?>
          <tr>
            <td><strong><?= e($row['school']) ?></strong></td>
            <td><?= $row['students'] ?></td>
            <td><?= $row['applications'] ?></td>
            <td><span class="badge badge-approved"><?= $row['approved'] ?></span></td>
            <td><?= $row['avg_gpa'] ? number_format($row['avg_gpa'],2) : '—' ?></td>
            <td><?= $row['best_gpa'] ?? '—' ?></td>
            <td>₱<?= number_format($row['total_awarded'],0) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- SQL Reference -->
<div class="report-section">
  <h2>📋 SQL Features Reference</h2>
  <div class="card">
    <table>
      <thead><tr><th>Feature</th><th>Used In</th><th>Description</th></tr></thead>
      <tbody>
        <tr><td><strong>CTE (WITH)</strong></td><td>Monthly Summary</td><td>monthly_summary CTE computes per-month aggregates</td></tr>
        <tr><td><strong>Subquery #1</strong></td><td>High-Demand Report</td><td>AVG inside HAVING: HAVING COUNT > (SELECT AVG …)</td></tr>
        <tr><td><strong>Subquery #2</strong></td><td>High-Demand Report</td><td>Nested: SELECT COUNT GROUP BY inside Subquery #1</td></tr>
        <tr><td><strong>Subquery #3</strong></td><td>No Applications</td><td>WHERE applicant_id NOT IN (SELECT DISTINCT …)</td></tr>
        <tr><td><strong>Subquery #4</strong></td><td>Top Scorers</td><td>Correlated: WHERE score = (SELECT MAX per scholarship)</td></tr>
        <tr><td><strong>COUNT</strong></td><td>All reports</td><td>Counts applications, students, documents</td></tr>
        <tr><td><strong>SUM</strong></td><td>All reports</td><td>Totals approved/rejected counts, amounts awarded</td></tr>
        <tr><td><strong>AVG</strong></td><td>Monthly, Reviewer, School</td><td>Average scores and GPA</td></tr>
        <tr><td><strong>MAX</strong></td><td>Monthly, Reviewer</td><td>Highest score per month or reviewer</td></tr>
        <tr><td><strong>MIN</strong></td><td>Monthly, Reviewer, School</td><td>Lowest/best score and GPA</td></tr>
        <tr><td><strong>3-table JOINs</strong></td><td>Most reports</td><td>applications ↔ applicants ↔ scholarships ↔ users</td></tr>
      </tbody>
    </table>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
