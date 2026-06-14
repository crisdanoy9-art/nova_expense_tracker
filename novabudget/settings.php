<?php
// settings.php — App settings
require_once __DIR__ . '/config/auth.php';
$user = requireAuth(); $userId = currentUserId();
$pageTitle = 'Settings'; $activePage = 'settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = postStr('action');
    if ($action === 'notifications') {
        $prefs = [
            'budget_alerts'       => isset($_POST['budget_alerts']),
            'weekly_digest'       => isset($_POST['weekly_digest']),
            'ai_tips'             => isset($_POST['ai_tips']),
            'transaction_confirm' => isset($_POST['transaction_confirm']),
            'monthly_report'      => isset($_POST['monthly_report']),
        ];
        dbExec("INSERT INTO users (id) VALUES (?) ON CONFLICT (id) DO NOTHING", [$userId]);
        // Store prefs as JSON in a simple key-value (or use a dedicated table)
        dbExec("UPDATE users SET updated_at=NOW() WHERE id=?", [$userId]);
        // We'll save in session for demo (in production use a user_settings table)
        $_SESSION['notif_prefs'] = $prefs;
        flashSet('success', 'Notification preferences saved!');
    }
    if ($action === 'delete_account') {
        $confirm = postStr('confirm_delete');
        if ($confirm === 'DELETE') {
            dbExec('DELETE FROM users WHERE id=?', [$userId]);
            session_destroy();
            header('Location: ' . APP_BASE . '/login.php?deleted=1'); exit;
        } else {
            flashSet('error','Type DELETE exactly to confirm.');
        }
    }
    header('Location: ' . APP_BASE . '/settings.php'); exit;
}

$notifPrefs = $_SESSION['notif_prefs'] ?? [
    'budget_alerts'=>true,'weekly_digest'=>true,'ai_tips'=>true,
    'transaction_confirm'=>false,'monthly_report'=>true,
];

require_once __DIR__ . '/includes/header.php';
?>
<div class="page-hd"><div class="page-title"><i class="bi bi-gear" style="color:var(--c-amber)"></i> Settings</div></div>
<div style="max-width:620px">

  <!-- Notifications -->
  <div class="card-g" style="margin-bottom:16px">
    <div class="card-head"><div class="card-title"><i class="bi bi-bell" style="color:var(--c-cyan)"></i> Notifications</div></div>
    <div class="card-body">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="notifications">
        <?php
        $notifs = [
          ['budget_alerts','Budget alerts','Get notified when you approach your budget limit'],
          ['weekly_digest','Weekly digest','Summary of your spending every Sunday'],
          ['ai_tips','AI spending tips','Personalized recommendations from Claude AI'],
          ['transaction_confirm','Transaction confirmations','Alert after each expense is added'],
          ['monthly_report','Monthly report','Auto-generated report on the 1st of each month'],
        ];
        foreach ($notifs as $i => [$key,$label,$desc]):
        ?>
        <div style="display:flex;align-items:center;justify-content:space-between;<?= $i<count($notifs)-1?'margin-bottom:18px':'' ?>">
          <div>
            <div style="font-size:13.5px;font-weight:500"><?= $label ?></div>
            <div style="font-size:11px;color:var(--text3)"><?= $desc ?></div>
          </div>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" role="switch" name="<?= $key ?>"
              style="width:40px;height:22px;cursor:pointer;accent-color:var(--c-cyan)"
              <?= !empty($notifPrefs[$key]) ? 'checked' : '' ?>>
          </div>
        </div>
        <?php endforeach; ?>
        <div style="margin-top:20px">
          <button type="submit" class="btn-glow" style="width:auto;padding:11px 22px"><i class="bi bi-check-circle"></i> Save Preferences</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Data management -->
  <div class="card-g" style="margin-bottom:16px">
    <div class="card-head"><div class="card-title"><i class="bi bi-database" style="color:var(--c-purple)"></i> Data Management</div></div>
    <div class="card-body">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
        <div><div style="font-size:13.5px;font-weight:500">Export all data</div><div style="font-size:11px;color:var(--text3)">Download your complete expense history</div></div>
        <div style="display:flex;gap:8px">
          <a href="<?= APP_BASE ?>/reports.php?export=csv" class="btn-outline btn-sm"><i class="bi bi-filetype-csv"></i> CSV</a>
          <a href="<?= APP_BASE ?>/reports.php?export=json" class="btn-outline btn-sm"><i class="bi bi-braces"></i> JSON</a>
        </div>
      </div>
      <div style="display:flex;align-items:center;justify-content:space-between">
        <div><div style="font-size:13.5px;font-weight:500">View activity log</div><div style="font-size:11px;color:var(--text3)">See your account activity history</div></div>
        <a href="<?= APP_BASE ?>/activity_log.php" class="btn-outline btn-sm"><i class="bi bi-journal-text"></i> View Log</a>
      </div>
    </div>
  </div>

  <!-- Danger zone -->
  <div class="card-g" style="border-color:rgba(248,113,113,.2)">
    <div class="card-head" style="border-color:rgba(248,113,113,.15)"><div class="card-title" style="color:var(--c-red)"><i class="bi bi-exclamation-triangle"></i> Danger Zone</div></div>
    <div class="card-body">
      <p style="font-size:13px;color:var(--text2);margin-bottom:16px">Permanently delete your account and all associated data. This action <strong style="color:var(--c-red)">cannot be undone</strong>.</p>
      <form method="POST" id="del-account-form">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="delete_account">
        <div style="margin-bottom:12px">
          <label class="fc-label">Type <strong style="color:var(--c-red)">DELETE</strong> to confirm</label>
          <input class="fc" type="text" name="confirm_delete" placeholder="DELETE" autocomplete="off">
        </div>
        <button type="submit" class="btn-danger" onclick="return confirm('This will permanently delete your account and all data. Are you absolutely sure?')">
          <i class="bi bi-trash"></i> Delete My Account
        </button>
      </form>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
