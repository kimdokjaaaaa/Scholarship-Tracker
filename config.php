<?php
// config.php: Database connection & session bootstrap
session_start();

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');                   // default XAMPP password
define('DB_NAME', 'scholarship_tracker');
define('APP_NAME', 'ScholarTrack');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('<div style="font-family:monospace;padding:2rem;background:#1a1a2e;color:#e94560;border-left:4px solid #e94560;">
        <strong>Database Connection Failed</strong><br><br>
        ' . htmlspecialchars($e->getMessage()) . '<br><br>
        <small>Make sure XAMPP MySQL is running and you have imported <strong>setup.sql</strong> into phpMyAdmin.</small>
    </div>');
}

// Auth helpers
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function isAdmin(): bool {
    return ($_SESSION['role'] ?? '') === 'admin';
}

function flash(string $msg, string $type = 'success'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$statusColors = [
    'submitted'    => '#3b82f6',
    'under_review' => '#f59e0b',
    'shortlisted'  => '#8b5cf6',
    'approved'     => '#10b981',
    'rejected'     => '#ef4444',
    'withdrawn'    => '#6b7280',
];

$statusLabels = [
    'submitted'    => 'Submitted',
    'under_review' => 'Under Review',
    'shortlisted'  => 'Shortlisted',
    'approved'     => 'Approved',
    'rejected'     => 'Rejected',
    'withdrawn'    => 'Withdrawn',
];
