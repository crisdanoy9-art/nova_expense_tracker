<?php
// includes/header.php — Shared header + sidebar
// Variables expected: $pageTitle, $activePage
$user   = currentUser();
$flash  = flashGet();
$initials = $user['avatar_initials'] ?? strtoupper(substr($user['name'] ?? 'U', 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle ?? 'Dashboard') ?> — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Space+Grotesk:wght@300;400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/style.css">
</head>
<body>

<!-- Sidebar overlay (mobile) -->
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

<!-- ── SIDEBAR ── -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="logo-txt"><?= APP_NAME ?></div>
    <div class="logo-sub">AI Expense Intelligence</div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-group">Main</div>
    <a href="<?= APP_BASE ?>/dashboard.php"      class="nav-item <?= $activePage==='dashboard'?'active':'' ?>"><i class="bi bi-grid-1x2"></i> Dashboard</a>
    <a href="<?= APP_BASE ?>/add_expense.php"    class="nav-item <?= $activePage==='add_expense'?'active':'' ?>"><i class="bi bi-plus-circle"></i> Add Expense</a>
    <a href="<?= APP_BASE ?>/calendar.php"       class="nav-item <?= $activePage==='calendar'?'active':'' ?>"><i class="bi bi-calendar3"></i> Calendar <span class="nav-badge">New</span></a>
    <a href="<?= APP_BASE ?>/categories.php"     class="nav-item <?= $activePage==='categories'?'active':'' ?>"><i class="bi bi-tags"></i> Categories</a>
    <div class="nav-group">Insights</div>
    <a href="<?= APP_BASE ?>/reports.php"        class="nav-item <?= $activePage==='reports'?'active':'' ?>"><i class="bi bi-bar-chart-line"></i> Reports</a>
    <a href="<?= APP_BASE ?>/analytics.php"      class="nav-item <?= $activePage==='analytics'?'active':'' ?>"><i class="bi bi-graph-up-arrow"></i> Analytics</a>
    <a href="<?= APP_BASE ?>/budget.php"         class="nav-item <?= $activePage==='budget'?'active':'' ?>"><i class="bi bi-wallet2"></i> Monthly Budget</a>
    <a href="<?= APP_BASE ?>/recurring.php"      class="nav-item <?= $activePage==='recurring'?'active':'' ?>"><i class="bi bi-arrow-repeat"></i> Recurring</a>
    <div class="nav-group">Account</div>
    <a href="<?= APP_BASE ?>/profile.php"        class="nav-item <?= $activePage==='profile'?'active':'' ?>"><i class="bi bi-person-circle"></i> Profile</a>
    <a href="<?= APP_BASE ?>/activity_log.php"   class="nav-item <?= $activePage==='activity_log'?'active':'' ?>"><i class="bi bi-journal-text"></i> Activity Log</a>
    <a href="<?= APP_BASE ?>/settings.php"       class="nav-item <?= $activePage==='settings'?'active':'' ?>"><i class="bi bi-gear"></i> Settings</a>
    <a href="<?= APP_BASE ?>/logout.php"         class="nav-item" style="color:#f87171;margin-top:4px"><i class="bi bi-box-arrow-left"></i> Logout</a>
  </nav>
  <div class="sidebar-foot">
    <div class="user-av"><?= h($initials) ?></div>
    <div>
      <div class="user-name"><?= h($user['name'] ?? 'User') ?></div>
      <div class="user-plan"><?= ucfirst(h($user['plan'] ?? 'free')) ?> Plan</div>
    </div>
  </div>
</aside>

<!-- ── MAIN WRAPPER ── -->
<div class="main-wrap">

  <!-- TOPBAR -->
  <div class="topbar">
    <button class="hamburger" onclick="openSidebar()"><i class="bi bi-list"></i></button>
    <div class="topbar-title"><?= h($pageTitle ?? 'Dashboard') ?></div>
    <form class="topbar-search" action="<?= APP_BASE ?>/dashboard.php" method="get">
      <i class="bi bi-search"></i>
      <input type="text" name="q" placeholder="Search expenses…" value="<?= h(getStr('q')) ?>" autocomplete="off">
    </form>
    <div class="topbar-actions">
      <!-- Notifications Bell -->
      <div class="notif-wrap" id="notif-wrap">
        <button class="topbar-icon-btn" onclick="toggleNotifPanel()" id="notif-btn">
          <i class="bi bi-bell"></i>
          <span class="notif-dot" id="notif-dot" style="display:none"></span>
        </button>
        <div class="notif-panel" id="notif-panel" style="display:none">
          <div class="notif-head"><span>Notifications</span><button onclick="markAllRead()" style="background:none;border:none;color:var(--c-cyan);font-size:12px;cursor:pointer">Mark all read</button></div>
          <div id="notif-list"><div style="padding:20px;text-align:center;color:var(--text3);font-size:13px">No notifications</div></div>
        </div>
      </div>
      <a href="<?= APP_BASE ?>/reports.php" class="btn-outline btn-sm"><i class="bi bi-download"></i> Export</a>
      <a href="<?= APP_BASE ?>/add_expense.php" class="btn-glow btn-sm"><i class="bi bi-plus"></i> Add</a>
    </div>
  </div>

  <!-- FLASH MESSAGES -->
  <?php foreach ($flash as $f): ?>
  <div class="flash-msg flash-<?= h($f['type']) ?>">
    <i class="bi <?= $f['type']==='success'?'bi-check-circle':($f['type']==='error'?'bi-exclamation-circle':'bi-info-circle') ?>"></i>
    <?= h($f['msg']) ?>
    <button onclick="this.parentElement.remove()" style="background:none;border:none;margin-left:auto;cursor:pointer;color:inherit;font-size:16px">×</button>
  </div>
  <?php endforeach; ?>

  <!-- PAGE CONTENT STARTS -->
  <div class="page-content">
