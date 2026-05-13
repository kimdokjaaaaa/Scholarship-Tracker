<?php
// header.php: Navigation sidebar and page shell
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

$nav = [
    ['dashboard',    'dashboard',   '📊', 'Dashboard'],
    ['applications', 'applications','📋', 'Applications'],
    ['scholarships', 'scholarships','🏆', 'Scholarships'],
    ['applicants',   'applicants',  '👤', 'Applicants'],
    ['documents',    'documents',   '📄', 'Documents'],
    ['reports',      'reports',     '📈', 'Reports'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($pageTitle) ? e($pageTitle) . ' – ' : '' ?><?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <span class="brand-icon">🎓</span>
    <span class="brand-name"><?= APP_NAME ?></span>
  </div>

  <nav class="sidebar-nav">
    <?php foreach ($nav as [$page, $href, $icon, $label]): ?>
    <a href="<?= $href ?>.php"
       class="nav-item <?= $currentPage === $page ? 'active' : '' ?>">
      <span class="nav-icon"><?= $icon ?></span>
      <span class="nav-label"><?= $label ?></span>
    </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="user-info">
      <div class="user-avatar"><?= strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)) ?></div>
      <div>
        <div class="user-name"><?= e($_SESSION['full_name'] ?? '') ?></div>
        <div class="user-role"><?= ucfirst($_SESSION['role'] ?? '') ?></div>
      </div>
    </div>
    <a href="logout.php" class="btn-logout">Logout</a>
  </div>
</aside>

<!-- Main content -->
<main class="main-content">
  <div class="page-header">
    <h1 class="page-title"><?= isset($pageTitle) ? e($pageTitle) : '' ?></h1>
    <?php if (isset($pageSubtitle)): ?>
      <p class="page-subtitle"><?= e($pageSubtitle) ?></p>
    <?php endif; ?>
  </div>

  <?php
  $flash = getFlash();
  if ($flash): ?>
  <div class="alert alert-<?= $flash['type'] ?>">
    <?= e($flash['msg']) ?>
  </div>
  <?php endif; ?>

  <div class="page-body">
