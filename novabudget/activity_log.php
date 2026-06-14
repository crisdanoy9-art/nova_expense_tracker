<?php
// activity_log.php — Audit/activity log
require_once __DIR__ . '/config/auth.php';
$user = requireAuth(); $userId = currentUserId();
$pageTitle = 'Activity Log'; $activePage = 'activity_log';

$page = max(1, getInt('page',1)); $limit = 20;
$offset = ($page-1)*$limit;
$logs = dbQuery(
    "SELECT action, table_name, ip_address, created_at
     FROM audit_log WHERE user_id=?
     ORDER BY created_at DESC LIMIT ? OFFSET ?",
    [$userId,$limit,$offset]
);
$total = dbQueryOne('SELECT COUNT(*) AS n FROM audit_log WHERE user_id=?',[$userId])['n'];
$pages = max(1,ceil($total/$limit));

$actionIcons = [
    'login'           => ['bi-box-arrow-in-right','var(--c-green)'],
    'logout'          => ['bi-box-arrow-left','var(--text3)'],
    'register'        => ['bi-person-plus','var(--c-cyan)'],
    'insert'          => ['bi-plus-circle','var(--c-cyan)'],
    'update'          => ['bi-pencil','var(--c-amber)'],
    'delete'          => ['bi-trash','var(--c-red)'],
    'password_change' => ['bi-shield-lock','var(--c-purple)'],
];

require_once __DIR__ . '/includes/header.php';
?>
<div class="page-hd">
  <div class="page-title"><i class="bi bi-journal-text" style="color:var(--c-teal)"></i> Activity Log</div>
</div>
<div class="card-g">
  <div class="card-head">
    <div class="card-title">Account Activity</div>
    <span class="tag" style="background:rgba(255,255,255,.06);color:var(--text2)"><?= $total ?> events</span>
  </div>
  <div class="card-body" style="padding:0 18px">
    <?php if (empty($logs)): ?>
    <div style="padding:30px;text-align:center;color:var(--text3)">No activity recorded yet.</div>
    <?php else: foreach ($logs as $log):
      [$icon,$color] = $actionIcons[$log['action']] ?? ['bi-circle','var(--text3)'];
      $labels = ['login'=>'Signed in','logout'=>'Signed out','register'=>'Account created','insert'=>'Added '.$log['table_name'],'update'=>'Updated '.$log['table_name'],'delete'=>'Deleted from '.$log['table_name'],'password_change'=>'Password changed'];
    ?>
    <div class="activity-item">
      <div class="activity-ico" style="background:<?= $color ?>18">
        <i class="bi <?= $icon ?>" style="color:<?= $color ?>"></i>
      </div>
      <div class="activity-body">
        <div class="activity-action"><?= $labels[$log['action']] ?? ucfirst(h($log['action'])) ?></div>
        <div class="activity-meta">
          <i class="bi bi-clock" style="font-size:11px"></i> <?= date('M j, Y H:i', strtotime($log['created_at'])) ?>
          <?php if ($log['ip_address']): ?> &nbsp;·&nbsp; <i class="bi bi-geo" style="font-size:11px"></i> <?= h($log['ip_address']) ?><?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
  <div class="pg-wrap">
    <span class="pg-info">Page <?= $page ?> of <?= $pages ?></span>
    <div class="pg-btns">
      <button class="pg-btn" <?= $page<=1?'disabled':'' ?> onclick="location='?page=<?= $page-1 ?>'">← Prev</button>
      <button class="pg-btn" <?= $page>=$pages?'disabled':'' ?> onclick="location='?page=<?= $page+1 ?>'">Next →</button>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
