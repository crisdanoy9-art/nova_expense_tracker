<?php
// profile.php — User profile
require_once __DIR__ . '/config/auth.php';
$user = requireAuth(); $userId = currentUserId();
$pageTitle = 'Profile'; $activePage = 'profile';
$errors = [];

// Load full user record
$dbUser = dbQueryOne('SELECT * FROM users WHERE id=?', [$userId]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = postStr('action');

    if ($action === 'profile') {
        $fname    = postStr('fname');
        $lname    = postStr('lname');
        $currency = postStr('currency');
        $timezone = postStr('timezone');
        if (!$fname) { $errors['fname'] = 'First name is required.'; }
        if (empty($errors)) {
            $name     = trim("$fname $lname");
            $initials = strtoupper(substr($fname,0,1).substr($lname ?: $fname,1,1));
            dbExec('UPDATE users SET name=?,avatar_initials=?,currency=?,timezone=?,updated_at=NOW() WHERE id=?',
                [$name,$initials,$currency,$timezone,$userId]);
            // Refresh session
            $_SESSION['user_data']['name']            = $name;
            $_SESSION['user_data']['avatar_initials'] = $initials;
            $_SESSION['user_data']['currency']        = $currency;
            flashSet('success','Profile updated!');
            header('Location: ' . APP_BASE . '/profile.php'); exit;
        }
    }

    if ($action === 'password') {
        $current = postStr('current_password');
        $newPass = postStr('new_password');
        $confirm = postStr('confirm_password');
        if (!$current)          { $errors['current'] = 'Current password is required.'; }
        elseif (!password_verify($current, $dbUser['password_hash'])) { $errors['current'] = 'Current password is incorrect.'; }
        if (strlen($newPass)<8) { $errors['new'] = 'New password must be at least 8 characters.'; }
        if ($newPass!==$confirm) { $errors['conf'] = 'Passwords do not match.'; }
        if (empty($errors)) {
            dbExec('UPDATE users SET password_hash=?,updated_at=NOW() WHERE id=?',
                [password_hash($newPass,PASSWORD_ARGON2ID),$userId]);
            dbExec('INSERT INTO audit_log (user_id,action) VALUES (?,?)',[$userId,'password_change']);
            flashSet('success','Password changed successfully!');
            header('Location: ' . APP_BASE . '/profile.php'); exit;
        }
    }
}

$nameParts = explode(' ', $dbUser['name']??'', 2);
$stats = dbQueryOne("SELECT COUNT(*) AS txn_count, COALESCE(SUM(amount),0) AS total FROM expenses WHERE user_id=? AND status='completed'",[$userId]);
$joinDays = (new DateTime($dbUser['created_at']))->diff(new DateTime())->days;
require_once __DIR__ . '/includes/header.php';
?>
<div class="page-hd"><div class="page-title"><i class="bi bi-person-circle" style="color:var(--c-purple)"></i> Profile</div></div>
<div style="max-width:620px">

  <!-- Avatar & Stats -->
  <div class="card-g" style="margin-bottom:16px">
    <div class="card-body" style="padding:22px">
      <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px">
        <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,var(--c-purple),var(--c-cyan));display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:800;flex-shrink:0">
          <?= h($dbUser['avatar_initials'] ?? strtoupper(substr($dbUser['name']??'U',0,2))) ?>
        </div>
        <div>
          <div style="font-family:'Syne',sans-serif;font-size:19px;font-weight:700"><?= h($dbUser['name']) ?></div>
          <div style="color:var(--text3);font-size:12px"><?= h($dbUser['email']) ?></div>
          <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">
            <span class="tag" style="background:rgba(0,229,255,.1);color:var(--c-cyan)"><?= ucfirst(h($dbUser['plan'])) ?> Plan</span>
            <span class="tag" style="background:rgba(255,255,255,.06);color:var(--text2)">Member for <?= $joinDays ?> days</span>
          </div>
        </div>
      </div>
      <div class="g3">
        <div style="text-align:center;padding:10px;background:var(--bg2);border-radius:var(--r-sm)">
          <div style="font-size:11px;color:var(--text3);margin-bottom:3px">Transactions</div>
          <div style="font-family:'Syne',sans-serif;font-size:20px;font-weight:700;color:var(--c-cyan)"><?= number_format($stats['txn_count']) ?></div>
        </div>
        <div style="text-align:center;padding:10px;background:var(--bg2);border-radius:var(--r-sm)">
          <div style="font-size:11px;color:var(--text3);margin-bottom:3px">Total Spent</div>
          <div style="font-family:'Syne',sans-serif;font-size:20px;font-weight:700;color:var(--c-purple)">₱<?= number_format((float)$stats['total'],0) ?></div>
        </div>
        <div style="text-align:center;padding:10px;background:var(--bg2);border-radius:var(--r-sm)">
          <div style="font-size:11px;color:var(--text3);margin-bottom:3px">Currency</div>
          <div style="font-family:'Syne',sans-serif;font-size:20px;font-weight:700;color:var(--c-green)"><?= h($dbUser['currency']) ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Edit Profile -->
  <div class="card-g" style="margin-bottom:16px">
    <div class="card-head"><div class="card-title">Personal Information</div></div>
    <div class="card-body">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="profile">
        <div class="g2 mb-3">
          <div>
            <label class="fc-label">First Name *</label>
            <input class="fc <?= isset($errors['fname'])?'err':'' ?>" type="text" name="fname" value="<?= h($nameParts[0]??'') ?>">
            <?php if(isset($errors['fname'])): ?><div class="field-err" style="display:block"><?= h($errors['fname']) ?></div><?php endif; ?>
          </div>
          <div>
            <label class="fc-label">Last Name</label>
            <input class="fc" type="text" name="lname" value="<?= h($nameParts[1]??'') ?>">
          </div>
        </div>
        <div class="mb-3">
          <label class="fc-label">Email <small style="color:var(--text3)">(contact support to change)</small></label>
          <input class="fc" type="email" value="<?= h($dbUser['email']) ?>" disabled style="opacity:.5;cursor:not-allowed">
        </div>
        <div class="g2 mb-3">
          <div>
            <label class="fc-label">Currency</label>
            <select class="fc" name="currency">
              <?php foreach(['PHP'=>'PHP (₱)','EUR'=>'EUR (€)','GBP'=>'GBP (£)','JPY'=>'JPY (¥)','AUD'=>'AUD (A$)','CAD'=>'CAD (C$)','SGD'=>'SGD (S$)','PHP'=>'PHP (₱)'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($dbUser['currency']??'PHP')===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="fc-label">Timezone</label>
            <select class="fc" name="timezone">
              <?php foreach(['UTC'=>'UTC','America/New_York'=>'Eastern (UTC-5)','America/Chicago'=>'Central (UTC-6)','America/Denver'=>'Mountain (UTC-7)','America/Los_Angeles'=>'Pacific (UTC-8)','Europe/London'=>'London (UTC+0)','Europe/Paris'=>'Paris (UTC+1)','Asia/Tokyo'=>'Tokyo (UTC+9)','Asia/Singapore'=>'Singapore (UTC+8)','Asia/Manila'=>'Manila (UTC+8)','Australia/Sydney'=>'Sydney (UTC+11)'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($dbUser['timezone']??'UTC')===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <button type="submit" class="btn-glow" style="width:auto;padding:11px 22px"><i class="bi bi-check-circle"></i> Save Changes</button>
      </form>
    </div>
  </div>

  <!-- Change Password -->
  <div class="card-g">
    <div class="card-head"><div class="card-title"><i class="bi bi-shield-lock" style="color:var(--c-amber)"></i> Change Password</div></div>
    <div class="card-body">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="password">
        <div class="mb-3">
          <label class="fc-label">Current Password</label>
          <div class="input-wrap">
            <input class="fc <?= isset($errors['current'])?'err':'' ?>" type="password" name="current_password" id="cp1" placeholder="••••••••">
            <button type="button" class="input-eye" onclick="togglePwd('cp1',this)"><i class="bi bi-eye-slash"></i></button>
          </div>
          <?php if(isset($errors['current'])): ?><div class="field-err" style="display:block"><?= h($errors['current']) ?></div><?php endif; ?>
        </div>
        <div class="g2 mb-3">
          <div>
            <label class="fc-label">New Password</label>
            <div class="input-wrap">
              <input class="fc <?= isset($errors['new'])?'err':'' ?>" type="password" name="new_password" id="cp2" placeholder="Min. 8 characters">
              <button type="button" class="input-eye" onclick="togglePwd('cp2',this)"><i class="bi bi-eye-slash"></i></button>
            </div>
            <?php if(isset($errors['new'])): ?><div class="field-err" style="display:block"><?= h($errors['new']) ?></div><?php endif; ?>
          </div>
          <div>
            <label class="fc-label">Confirm New Password</label>
            <div class="input-wrap">
              <input class="fc <?= isset($errors['conf'])?'err':'' ?>" type="password" name="confirm_password" id="cp3" placeholder="Repeat password">
              <button type="button" class="input-eye" onclick="togglePwd('cp3',this)"><i class="bi bi-eye-slash"></i></button>
            </div>
            <?php if(isset($errors['conf'])): ?><div class="field-err" style="display:block"><?= h($errors['conf']) ?></div><?php endif; ?>
          </div>
        </div>
        <button type="submit" class="btn-glow" style="width:auto;padding:11px 22px"><i class="bi bi-shield-lock"></i> Update Password</button>
      </form>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
