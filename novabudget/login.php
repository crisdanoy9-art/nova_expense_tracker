<?php
/* ─────────────────────────────────────────
   login.php — NovaBudget Secure Login
   CSS: assets/css/auth.css
   JS:  assets/js/auth-battle.js
───────────────────────────────────────── */
require_once __DIR__ . '/config/auth.php';
requireGuest();

$error = '';
$emailVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (!checkRateLimit('login', 10, 60)) {
        $error = 'Too many login attempts. Please wait one minute.';
    } else {
        $emailVal = postStr('email');
        $password = postStr('password');
        $remember = isset($_POST['remember']);

        if (!$emailVal || !isValidEmail($emailVal)) {
            $error = 'Please enter a valid email address.';
        } elseif (!$password) {
            $error = 'Password is required.';
        } else {
            try {
                $user = dbQueryOne(
                    'SELECT * FROM users WHERE LOWER(email) = LOWER(?) AND is_active = TRUE',
                    [$emailVal]
                );
                if (!$user || !password_verify($password, $user['password_hash'])) {
                    $error = 'Invalid credentials. Access denied.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['user_data'] = [
                        'id'             => $user['id'],
                        'name'           => $user['name'],
                        'email'          => $user['email'],
                        'plan'           => $user['plan'],
                        'avatar_initials'=> $user['avatar_initials'] ?? strtoupper(substr($user['name'], 0, 2)),
                        'currency'       => $user['currency'],
                    ];
                    $token = bin2hex(random_bytes(32));
                    dbExec(
                        "INSERT INTO sessions (user_id,token_hash,ip_address,user_agent,expires_at)
                         VALUES (?,?,?,?,NOW()+INTERVAL '30 days')",
                        [$user['id'], hash('sha256',$token), $_SERVER['REMOTE_ADDR']??'', $_SERVER['HTTP_USER_AGENT']??'']
                    );
                    if ($remember) {
                        setcookie('nb_token', $token, time() + SESSION_LIFETIME, '/', '',
                            (bool)($_SERVER['HTTPS'] ?? false), true);
                    }
                    dbExec('UPDATE users SET updated_at=NOW() WHERE id=?', [$user['id']]);
                    dbExec('INSERT INTO audit_log(user_id,action,ip_address) VALUES(?,?,?)',
                        [$user['id'], 'login', $_SERVER['REMOTE_ADDR'] ?? '']);
                    flashSet('success', 'Welcome back, '.explode(' ',$user['name'])[0].'! 👋');
                    header('Location: '.APP_BASE.'/dashboard.php');
                    exit;
                }
            } catch (Exception $e) {
                error_log('Login error: '.$e->getMessage());
                $error = 'System error. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Access Portal — <?= APP_NAME ?></title>

<!-- External fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;900&family=Exo+2:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600&display=swap">

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<!-- ★ EXTERNAL AUTH CSS ★ -->
<link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/auth.css">

<!-- Keyframes needed inline (only animation @keyframes not in auth.css) -->
<style>
@keyframes explodeOut { 0%,35%{opacity:0;transform:scale(0)} 40%{opacity:1;transform:scale(1)} 70%{opacity:.6} 100%{opacity:0;transform:scale(1.4)} }
@keyframes ringExpand { 0%{transform:scale(0);opacity:1} 100%{transform:scale(3.5);opacity:0} }
@keyframes debrisFly  { 0%{transform:translate(0,0) scale(1);opacity:1} 100%{transform:translate(var(--dx),var(--dy)) scale(0);opacity:0} }
@keyframes meteorFall { 0%{opacity:0;transform:translateY(-80px) rotate(var(--angle,30deg)) scaleY(0)} 5%{opacity:1;transform:translateY(-80px) rotate(var(--angle,30deg)) scaleY(1)} 100%{opacity:0;transform:translateY(120vh) translateX(-70vw) rotate(var(--angle,30deg)) scaleY(.4)} }
</style>
</head>
<body>

<!-- ══════════ LAYER 0: STAR FIELD CANVAS ══════════ -->
<canvas id="star-canvas"></canvas>

<!-- ══════════ LAYER 1: NEBULA CLOUDS ══════════ -->
<div class="nebula-layer">
  <div class="nebula neb1"></div>
  <div class="nebula neb2"></div>
  <div class="nebula neb3"></div>
  <div class="nebula neb4"></div>
</div>

<!-- ══════════ LAYER 2: BATTLE GRID ══════════ -->
<div class="battle-grid"></div>

<!-- ══════════ LAYER 3: PLANET + MOON ══════════ -->
<div class="planet-wrap">
  <div class="planet"></div>
  <div class="planet-ring"></div>
</div>
<div class="moon"><div class="moon-body"></div></div>

<!-- ══════════ LAYER 4: PARTICLE FIELD ══════════ -->
<div class="particle-field" id="particle-field-el"></div>

<!-- ══════════ LAYER 5: SPACESHIPS ══════════ -->
<div class="ships-layer">
  <!-- Hero ship (friendly) -->
  <div class="hero-ship">
    <svg viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg">
      <!-- Main hull -->
      <polygon points="30,4 42,38 30,32 18,38" fill="#0a2a4a" stroke="#00cfff" stroke-width="1.2"/>
      <!-- Wing right -->
      <polygon points="42,28 58,48 42,38" fill="#061828" stroke="#00cfff" stroke-width="1"/>
      <!-- Wing left -->
      <polygon points="18,28 2,48 18,38" fill="#061828" stroke="#00cfff" stroke-width="1"/>
      <!-- Engine pods -->
      <rect x="24" y="34" width="5" height="10" rx="2" fill="#003a5c" stroke="#00cfff" stroke-width=".8"/>
      <rect x="31" y="34" width="5" height="10" rx="2" fill="#003a5c" stroke="#00cfff" stroke-width=".8"/>
      <!-- Cockpit -->
      <ellipse cx="30" cy="16" rx="6" ry="9" fill="#001e38" stroke="#00cfff" stroke-width=".8"/>
      <ellipse cx="30" cy="15" rx="3.5" ry="5.5" fill="#003a6e" opacity=".8"/>
      <!-- Center glow -->
      <circle cx="30" cy="28" r="3" fill="#00cfff" opacity=".7"/>
      <!-- Wing details -->
      <line x1="44" y1="32" x2="54" y2="44" stroke="#00cfff" stroke-width=".6" opacity=".6"/>
      <line x1="16" y1="32" x2="6" y2="44" stroke="#00cfff" stroke-width=".6" opacity=".6"/>
    </svg>
  </div>
  <div class="hero-engine"></div>

  <!-- Enemy alien fleet -->
  <div class="enemy-fleet">
    <!-- Alien fighter type A -->
    <div class="enemy-ship">
      <svg viewBox="0 0 50 44" xmlns="http://www.w3.org/2000/svg">
        <ellipse cx="25" cy="18" rx="22" ry="10" fill="#1a0028" stroke="#ff3366" stroke-width="1.2"/>
        <ellipse cx="25" cy="14" rx="10" ry="6" fill="#2a0038" stroke="#ff3366" stroke-width=".8"/>
        <circle cx="25" cy="12" r="3.5" fill="#ff3366" opacity=".8"/>
        <ellipse cx="9"  cy="22" rx="5" ry="3" fill="#1a0028" stroke="#ff3366" stroke-width=".8"/>
        <ellipse cx="41" cy="22" rx="5" ry="3" fill="#1a0028" stroke="#ff3366" stroke-width=".8"/>
        <circle cx="14" cy="19" r="2" fill="#ff3366" opacity=".6"/>
        <circle cx="36" cy="19" r="2" fill="#ff3366" opacity=".6"/>
        <line x1="25" y1="28" x2="20" y2="36" stroke="#ff3366" stroke-width="1" opacity=".7"/>
        <line x1="25" y1="28" x2="30" y2="36" stroke="#ff3366" stroke-width="1" opacity=".7"/>
        <rect x="22" y="34" width="6" height="4" rx="1" fill="#3a0050" stroke="#ff3366" stroke-width=".6"/>
      </svg>
    </div>
    <!-- Alien fighter type B -->
    <div class="enemy-ship">
      <svg viewBox="0 0 48 50" xmlns="http://www.w3.org/2000/svg">
        <polygon points="24,2 44,20 38,44 24,38 10,44 4,20" fill="#001a08" stroke="#44ff00" stroke-width="1.2"/>
        <polygon points="24,8 36,20 30,36 24,32 18,36 12,20" fill="#002a10" stroke="#44ff00" stroke-width=".7" opacity=".8"/>
        <circle cx="24" cy="22" r="5" fill="#44ff00" opacity=".6"/>
        <circle cx="24" cy="22" r="2.5" fill="#88ff44"/>
        <circle cx="16" cy="18" r="2" fill="#44ff00" opacity=".5"/>
        <circle cx="32" cy="18" r="2" fill="#44ff00" opacity=".5"/>
        <line x1="4"  y1="20" x2="0"  y2="28" stroke="#44ff00" stroke-width="1" opacity=".6"/>
        <line x1="44" y1="20" x2="48" y2="28" stroke="#44ff00" stroke-width="1" opacity=".6"/>
      </svg>
    </div>
    <!-- Alien fighter type C -->
    <div class="enemy-ship">
      <svg viewBox="0 0 52 42" xmlns="http://www.w3.org/2000/svg">
        <path d="M26 2 L48 18 L44 36 L26 30 L8 36 L4 18 Z" fill="#1c1000" stroke="#ffaa00" stroke-width="1.2"/>
        <ellipse cx="26" cy="17" rx="12" ry="8" fill="#2a1800" stroke="#ffaa00" stroke-width=".8"/>
        <circle cx="26" cy="16" r="4" fill="#ffaa00" opacity=".7"/>
        <rect x="22" y="26" width="8" height="6" rx="2" fill="#2a1800" stroke="#ffaa00" stroke-width=".7"/>
        <circle cx="14" cy="20" r="1.8" fill="#ffaa00" opacity=".5"/>
        <circle cx="38" cy="20" r="1.8" fill="#ffaa00" opacity=".5"/>
      </svg>
    </div>
    <!-- Alien fighter type D -->
    <div class="enemy-ship">
      <svg viewBox="0 0 50 46" xmlns="http://www.w3.org/2000/svg">
        <ellipse cx="25" cy="20" rx="20" ry="12" fill="#14001e" stroke="#cc44ff" stroke-width="1.2"/>
        <ellipse cx="25" cy="16" rx="8" ry="6" fill="#200030" stroke="#cc44ff" stroke-width=".8"/>
        <circle cx="25" cy="14" r="3" fill="#cc44ff" opacity=".8"/>
        <polygon points="25,32 18,44 25,40 32,44" fill="#14001e" stroke="#cc44ff" stroke-width=".8"/>
        <circle cx="14" cy="22" r="2" fill="#cc44ff" opacity=".5"/>
        <circle cx="36" cy="22" r="2" fill="#cc44ff" opacity=".5"/>
        <line x1="5" y1="16" x2="1" y2="24" stroke="#cc44ff" stroke-width="1" opacity=".6"/>
        <line x1="45" y1="16" x2="49" y2="24" stroke="#cc44ff" stroke-width="1" opacity=".6"/>
      </svg>
    </div>
  </div>

  <!-- Bomber -->
  <div class="bomber">
    <svg viewBox="0 0 70 55" xmlns="http://www.w3.org/2000/svg">
      <polygon points="35,2 65,35 55,52 35,44 15,52 5,35" fill="#001800" stroke="#44ff00" stroke-width="1.5"/>
      <polygon points="35,8 56,30 48,46 35,38 22,46 14,30" fill="#002800" stroke="#44ff00" stroke-width=".8" opacity=".7"/>
      <circle cx="35" cy="26" r="7" fill="#44ff00" opacity=".5"/>
      <circle cx="35" cy="26" r="3.5" fill="#88ff44" opacity=".9"/>
      <circle cx="20" cy="22" r="3" fill="#44ff00" opacity=".4"/>
      <circle cx="50" cy="22" r="3" fill="#44ff00" opacity=".4"/>
      <rect x="31" y="42" width="8" height="7" rx="2" fill="#002000" stroke="#44ff00" stroke-width=".8"/>
      <line x1="5" y1="35" x2="-2" y2="48" stroke="#44ff00" stroke-width="1.2" opacity=".6"/>
      <line x1="65" y1="35" x2="72" y2="48" stroke="#44ff00" stroke-width="1.2" opacity=".6"/>
    </svg>
  </div>

  <!-- Flying saucer -->
  <div class="saucer">
    <svg viewBox="0 0 80 46" xmlns="http://www.w3.org/2000/svg">
      <ellipse cx="40" cy="30" rx="38" ry="13" fill="#001e0a" stroke="#44ff00" stroke-width="1.2"/>
      <ellipse cx="40" cy="28" rx="24" ry="9" fill="#002a10" stroke="#44ff00" stroke-width=".8"/>
      <ellipse cx="40" cy="20" rx="12" ry="8" fill="#003818" stroke="#44ff00" stroke-width=".8"/>
      <ellipse cx="40" cy="18" rx="6" ry="4" fill="#44ff00" opacity=".5"/>
      <circle cx="40" cy="17" r="3" fill="#88ff44" opacity=".8"/>
      <!-- Underside glow portals -->
      <circle cx="22" cy="34" r="3" fill="#44ff00" opacity=".5"/>
      <circle cx="32" cy="37" r="3" fill="#44ff00" opacity=".5"/>
      <circle cx="40" cy="38" r="3.5" fill="#44ff00" opacity=".6"/>
      <circle cx="48" cy="37" r="3" fill="#44ff00" opacity=".5"/>
      <circle cx="58" cy="34" r="3" fill="#44ff00" opacity=".5"/>
    </svg>
  </div>
</div>

<!-- ══════════ LAYER 6: LASER BEAMS ══════════ -->
<div class="lasers-layer">
  <div class="laser-hero lh1"></div>
  <div class="laser-hero lh2"></div>
  <div class="laser-hero lh3"></div>
  <div class="plasma-bolt pb1"></div>
  <div class="plasma-bolt pb2"></div>
  <div class="plasma-bolt pb3"></div>
  <div class="plasma-bolt pb4"></div>
  <div class="alien-beam"></div>
  <div class="alien-beam alien-beam2"></div>
</div>

<!-- ══════════ LAYER 7: DYNAMIC EXPLOSIONS ══════════ -->
<div class="explosions-layer" id="explosion-layer-el"></div>

<!-- ══════════ LAYER 8: METEOR SHOWER ══════════ -->
<div class="meteors" id="meteor-field-el"></div>

<!-- ══════════ LAYER 9: SCAN LINES ══════════ -->
<div class="scanlines-layer">
  <div class="scan-beam sb1"></div>
  <div class="scan-beam sb2"></div>
  <div class="scan-beam sb3"></div>
  <div class="scan-beam sb4"></div>
</div>

<!-- ══════════ LAYER 10: WARNING FLASH ══════════ -->
<div class="warn-flash"></div>

<!-- ══════════ HUD LAYER ══════════ -->
<div class="hud-layer">
  <div class="tac-corner tc-tl"></div>
  <div class="tac-corner tc-tr"></div>
  <div class="tac-corner tc-bl"></div>
  <div class="tac-corner tc-br"></div>
</div>
<div class="threat-hud">
  THREAT LEVEL: HIGH
  <div class="threat-bar">
    <div class="tbar-seg danger"></div><div class="tbar-seg danger"></div>
    <div class="tbar-seg danger"></div><div class="tbar-seg warn"></div>
    <div class="tbar-seg warn"></div><div class="tbar-seg safe"></div>
    <div class="tbar-seg safe"></div>
  </div>
</div>

<!-- HUD top corner -->
<div style="position:fixed;top:18px;left:88px;z-index:10;font-family:'Orbitron',monospace;font-size:9px;color:rgba(0,207,255,.5);letter-spacing:2px;pointer-events:none;line-height:1.7">
  <div id="hud-l1">SECTOR: ALPHA-7</div>
  <div id="hud-l2">SHIELDS: 87%</div>
  <div id="hud-l3">SYSTEM: ONLINE</div>
</div>

<!-- ══════════ TOAST ══════════ -->
<div id="toast-box"></div>

<!-- ══════════ AUTH CARD ══════════ -->
<div class="auth-page">
  <div class="auth-card">

    <!-- Banner -->
    <div class="card-banner">
      <div class="banner-lines"></div>
      <div class="banner-radar"></div>
      <div class="auth-logo">NovaBudget</div>
      <div class="auth-tagline">MY EXPENSE TRACKER</div>
      <div class="battle-status">
        <div class="bstat"><div class="bstat-led"></div>SECURE</div>
        <div class="bstat"><div class="bstat-led"></div>ENCRYPTED</div>
        <div class="bstat"><div class="bstat-led"></div> ACTIVE</div>
        <div class="bstat"><div class="bstat-led"></div>₱ PHP</div>
      </div>
    </div>

    <!-- Body -->
    <div class="card-body">
      <div class="card-heading" style="text-align: center;">Access Portal</div>

      <?php if ($error): ?>
      <div class="err-box">
        <i class="bi bi-shield-exclamation" style="color:var(--plasma);font-size:16px"></i>
        <?= h($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" action="<?= APP_BASE ?>/login.php" novalidate id="auth-form">
        <?= csrfField() ?>

        <div class="fg">
          <label class="fc-label" for="email-input">
            <i class="bi bi-person-badge"></i> Gmail
          </label>
          <div class="input-wrap">
            <i class="bi bi-envelope input-icon"></i>
            <input class="fc-input" type="email" name="email" id="email-input"
              value="<?= h($emailVal) ?>" placeholder="your@gmail.com"
              autocomplete="email" required>
          </div>
        </div>

        <div class="fg">
          <label class="fc-label" for="pw-input">
            <i class="bi bi-shield-lock"></i> Password
          </label>
          <div class="input-wrap">
            <i class="bi bi-lock input-icon"></i>
            <input class="fc-input has-eye" type="password" name="password" id="pw-input"
              placeholder="••••••••" autocomplete="current-password" required>
            <button type="button" class="eye-btn" onclick="togglePwd('pw-input',this)">
              <i class="bi bi-eye-slash"></i>
            </button>
          </div>
        </div>

        <div class="check-row">
          <label class="check-label">
            <input type="checkbox" name="remember"> Remember  Me?
          </label>
          <a class="link-dim" href="<?= APP_BASE ?>/login.php">Forget Password?</a>
        </div>

        <button type="submit" class="btn-launch" id="submit-btn" data-original='<i class="bi bi-box-arrow-in-right"></i> &nbsp;AUTHENTICATE'>
          <i class="bi bi-box-arrow-in-right"></i>&nbsp; Login 
        </button>
      </form>


      <div class="auth-foot">
        No account? &nbsp;<a href="<?= APP_BASE ?>/register.php">Create Account →</a>
      </div>
    </div>
  </div>
</div>

<!-- ★ EXTERNAL BATTLE JS ★ -->
<script src="<?= APP_BASE ?>/assets/js/auth-battle.js"></script>
<script>
  setLoadingState('auth-form','submit-btn','AUTHENTICATING…');
  document.getElementById('email-input').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') document.getElementById('pw-input').focus();
  });
</script>
</body>
</html>
