<?php

require_once __DIR__ . '/config/auth.php';
requireGuest();

$errors = [];
$fd = ['fname' => '', 'lname' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (!checkRateLimit('register', 5, 300)) {
        $errors[] = 'Too many attempts. Please wait 5 minutes.';
    } else {
        $fname   = postStr('fname');
        $lname   = postStr('lname');
        $email   = postStr('email');
        $pass    = postStr('password');
        $confirm = postStr('confirm');
        $agree   = isset($_POST['agree']);
        $fd      = compact('fname', 'lname', 'email');

        if (!$fname)                             $errors['fname'] = 'First name is required.';
        if (!$email || !isValidEmail($email))    $errors['email'] = 'Enter a valid email address.';
        if (strlen($pass) < 8)                   $errors['pass']  = 'Minimum 8 characters required.';
        if ($pass !== $confirm)                  $errors['conf']  = 'Access codes do not match.';
        if (!$agree)                             $errors[]        = 'You must accept the Mission Terms.';

        if (empty($errors)) {
            try {
                $existing = dbQueryOne(
                    'SELECT id FROM users WHERE LOWER(email) = LOWER(?)', [$email]
                );
                if ($existing) {
                    $errors['email'] = 'This operator ID is already registered.';
                } else {
                    $initials = strtoupper(substr($fname,0,1).substr($lname ?: $fname,1,1));
                    $hash     = password_hash($pass, PASSWORD_ARGON2ID);
                    $userId   = dbInsert(
                        "INSERT INTO users (name,email,password_hash,avatar_initials,plan,currency)
                         VALUES (?,?,?,?,'free','PHP') RETURNING id",
                        [trim("$fname $lname"), $email, $hash, $initials]
                    );
                    dbExec(
                        "INSERT INTO categories (user_id,name,emoji,color,is_system,sort_order)
                         SELECT ?,name,emoji,color,FALSE,sort_order FROM categories WHERE user_id IS NULL",
                        [$userId]
                    );
                    dbExec('INSERT INTO audit_log(user_id,action,ip_address) VALUES(?,?,?)',
                        [$userId, 'register', $_SERVER['REMOTE_ADDR'] ?? '']);
                    session_regenerate_id(true);
                    $_SESSION['user_id']   = $userId;
                    $_SESSION['user_data'] = [
                        'id'             => $userId,
                        'name'           => trim("$fname $lname"),
                        'email'          => $email,
                        'plan'           => 'free',
                        'avatar_initials'=> $initials,
                        'currency'       => 'PHP',
                    ];
                    flashSet('success', "Enlisted! Welcome to the fleet, $fname! 🚀");
                    header('Location: '.APP_BASE.'/dashboard.php');
                    exit;
                }
            } catch (Exception $e) {
                error_log('Register error: '.$e->getMessage());
                $errors[] = 'System error. Please try again.';
            }
        }
    }
}

$globalErrors = array_filter($errors, 'is_int', ARRAY_FILTER_USE_KEY);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Enlist — <?= APP_NAME ?></title>

<!-- External fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;900&family=Exo+2:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600&display=swap">

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<!-- ★ EXTERNAL AUTH CSS ★ -->
<link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/auth.css">

<!-- Keyframes for dynamic elements -->
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

<!-- ══════════ LAYER 1: NEBULA (purple tint for register) ══════════ -->
<div class="nebula-layer">
  <div class="nebula neb2"></div>
  <div class="nebula neb3"></div>
  <div class="nebula neb4"></div>
  <!-- Extra alien-green cloud -->
  <div class="nebula" style="width:500px;height:500px;background:radial-gradient(circle,rgba(68,255,0,.1) 0%,transparent 65%);top:-10%;right:30%;animation-duration:24s;filter:blur(90px)"></div>
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

<!-- ══════════ LAYER 5: SPACESHIPS (register — more friendly ships) ══════════ -->
<div class="ships-layer">
  <!-- Two hero ships in formation -->
  <div class="hero-ship" style="left:8%;bottom:20%">
    <svg viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg">
      <polygon points="30,4 42,38 30,32 18,38" fill="#0a2a4a" stroke="#44ff00" stroke-width="1.2"/>
      <polygon points="42,28 58,48 42,38" fill="#061828" stroke="#44ff00" stroke-width="1"/>
      <polygon points="18,28 2,48 18,38" fill="#061828" stroke="#44ff00" stroke-width="1"/>
      <rect x="24" y="34" width="5" height="10" rx="2" fill="#003a5c" stroke="#44ff00" stroke-width=".8"/>
      <rect x="31" y="34" width="5" height="10" rx="2" fill="#003a5c" stroke="#44ff00" stroke-width=".8"/>
      <ellipse cx="30" cy="16" rx="6" ry="9" fill="#001e38" stroke="#44ff00" stroke-width=".8"/>
      <ellipse cx="30" cy="15" rx="3.5" ry="5.5" fill="#003a6e" opacity=".8"/>
      <circle cx="30" cy="28" r="3" fill="#44ff00" opacity=".7"/>
      <line x1="44" y1="32" x2="54" y2="44" stroke="#44ff00" stroke-width=".6" opacity=".6"/>
      <line x1="16" y1="32" x2="6" y2="44" stroke="#44ff00" stroke-width=".6" opacity=".6"/>
    </svg>
  </div>
  <div class="hero-engine" style="left:calc(8% + 28px);bottom:calc(20% - 18px)"></div>
  <!-- Wingman -->
  <div class="hero-ship" style="left:15%;bottom:12%;animation-delay:.8s;transform:scale(.75)">
    <svg viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg">
      <polygon points="30,4 42,38 30,32 18,38" fill="#0a2a4a" stroke="#00cfff" stroke-width="1.2"/>
      <polygon points="42,28 58,48 42,38" fill="#061828" stroke="#00cfff" stroke-width="1"/>
      <polygon points="18,28 2,48 18,38" fill="#061828" stroke="#00cfff" stroke-width="1"/>
      <rect x="24" y="34" width="5" height="10" rx="2" fill="#003a5c" stroke="#00cfff" stroke-width=".8"/>
      <rect x="31" y="34" width="5" height="10" rx="2" fill="#003a5c" stroke="#00cfff" stroke-width=".8"/>
      <ellipse cx="30" cy="16" rx="6" ry="9" fill="#001e38" stroke="#00cfff" stroke-width=".8"/>
      <circle cx="30" cy="28" r="3" fill="#00cfff" opacity=".7"/>
    </svg>
  </div>

  <!-- Enemy fleet -->
  <div class="enemy-fleet">
    <div class="enemy-ship">
      <svg viewBox="0 0 50 44" xmlns="http://www.w3.org/2000/svg">
        <ellipse cx="25" cy="18" rx="22" ry="10" fill="#1a0028" stroke="#ff3366" stroke-width="1.2"/>
        <ellipse cx="25" cy="14" rx="10" ry="6" fill="#2a0038" stroke="#ff3366" stroke-width=".8"/>
        <circle cx="25" cy="12" r="3.5" fill="#ff3366" opacity=".8"/>
        <ellipse cx="9" cy="22" rx="5" ry="3" fill="#1a0028" stroke="#ff3366" stroke-width=".8"/>
        <ellipse cx="41" cy="22" rx="5" ry="3" fill="#1a0028" stroke="#ff3366" stroke-width=".8"/>
        <circle cx="14" cy="19" r="2" fill="#ff3366" opacity=".6"/>
        <circle cx="36" cy="19" r="2" fill="#ff3366" opacity=".6"/>
      </svg>
    </div>
    <div class="enemy-ship">
      <svg viewBox="0 0 48 50" xmlns="http://www.w3.org/2000/svg">
        <polygon points="24,2 44,20 38,44 24,38 10,44 4,20" fill="#001a08" stroke="#44ff00" stroke-width="1.2"/>
        <circle cx="24" cy="22" r="5" fill="#44ff00" opacity=".5"/>
        <circle cx="24" cy="22" r="2.5" fill="#88ff44"/>
      </svg>
    </div>
    <div class="enemy-ship">
      <svg viewBox="0 0 52 42" xmlns="http://www.w3.org/2000/svg">
        <path d="M26 2 L48 18 L44 36 L26 30 L8 36 L4 18 Z" fill="#1c1000" stroke="#ffaa00" stroke-width="1.2"/>
        <ellipse cx="26" cy="17" rx="12" ry="8" fill="#2a1800" stroke="#ffaa00" stroke-width=".8"/>
        <circle cx="26" cy="16" r="4" fill="#ffaa00" opacity=".7"/>
      </svg>
    </div>
  </div>

  <!-- Saucer -->
  <div class="saucer">
    <svg viewBox="0 0 80 46" xmlns="http://www.w3.org/2000/svg">
      <ellipse cx="40" cy="30" rx="38" ry="13" fill="#001e0a" stroke="#44ff00" stroke-width="1.2"/>
      <ellipse cx="40" cy="28" rx="24" ry="9" fill="#002a10" stroke="#44ff00" stroke-width=".8"/>
      <ellipse cx="40" cy="20" rx="12" ry="8" fill="#003818" stroke="#44ff00" stroke-width=".8"/>
      <ellipse cx="40" cy="18" rx="6" ry="4" fill="#44ff00" opacity=".5"/>
      <circle cx="40" cy="17" r="3" fill="#88ff44" opacity=".8"/>
      <circle cx="22" cy="34" r="3" fill="#44ff00" opacity=".5"/>
      <circle cx="32" cy="37" r="3" fill="#44ff00" opacity=".5"/>
      <circle cx="40" cy="38" r="3.5" fill="#44ff00" opacity=".6"/>
      <circle cx="48" cy="37" r="3" fill="#44ff00" opacity=".5"/>
      <circle cx="58" cy="34" r="3" fill="#44ff00" opacity=".5"/>
    </svg>
  </div>
</div>

<!-- ══════════ LASERS ══════════ -->
<div class="lasers-layer">
  <div class="laser-hero lh1" style="left:calc(8% + 35px)"></div>
  <div class="laser-hero lh2" style="left:calc(8% + 28px)"></div>
  <div class="laser-hero lh3" style="left:calc(8% + 42px)"></div>
  <div class="plasma-bolt pb1"></div>
  <div class="plasma-bolt pb2"></div>
  <div class="plasma-bolt pb3"></div>
  <div class="alien-beam"></div>
  <div class="alien-beam alien-beam2"></div>
</div>

<!-- ══════════ DYNAMIC EXPLOSIONS ══════════ -->
<div class="explosions-layer" id="explosion-layer-el"></div>

<!-- ══════════ METEOR SHOWER ══════════ -->
<div class="meteors" id="meteor-field-el"></div>

<!-- ══════════ SCAN LINES ══════════ -->
<div class="scanlines-layer">
  <div class="scan-beam sb1"></div>
  <div class="scan-beam sb2"></div>
  <div class="scan-beam sb3"></div>
  <div class="scan-beam sb4"></div>
</div>

<div class="warn-flash"></div>

<!-- ══════════ HUD ══════════ -->
<div class="hud-layer">
  <div class="tac-corner tc-tl"></div>
  <div class="tac-corner tc-tr"></div>
  <div class="tac-corner tc-bl"></div>
  <div class="tac-corner tc-br"></div>
</div>
<div class="threat-hud">
  ENLISTMENT ACTIVE
  <div class="threat-bar">
    <div class="tbar-seg safe"></div><div class="tbar-seg safe"></div>
    <div class="tbar-seg safe"></div><div class="tbar-seg safe"></div>
    <div class="tbar-seg warn"></div><div class="tbar-seg warn"></div>
    <div class="tbar-seg danger"></div>
  </div>
</div>
<div style="position:fixed;top:18px;left:88px;z-index:10;font-family:'Orbitron',monospace;font-size:9px;color:rgba(68,255,0,.5);letter-spacing:2px;pointer-events:none;line-height:1.7">
  <div id="hud-l1">ENLISTING…</div>
  <div id="hud-l2">CLEARANCE: PENDING</div>
  <div id="hud-l3">FLEET: READY</div>
</div>

<div id="toast-box"></div>

<!-- ══════════ REGISTER CARD ══════════ -->
<div class="auth-page">
  <div class="auth-card">

    <!-- Banner -->
    <div class="card-banner">
      <div class="banner-lines"></div>
      <div class="banner-radar"></div>
      <div class="auth-logo">NovaBudget</div>
      <div class="auth-tagline">MY EXPENSE TRACKER</div>
      <!-- Progress steps -->
      <div class="enlist-steps">
        <div class="estep active" id="step1">
          <div class="estep-dot">1</div>
          <span>IDENTITY</span>
        </div>
        <div class="estep-line"></div>
        <div class="estep" id="step2">
          <div class="estep-dot">2</div>
          <span>ACCESS</span>
        </div>
        <div class="estep-line"></div>
        <div class="estep" id="step3">
          <div class="estep-dot">3</div>
          <span>LAUNCH</span>
        </div>
      </div>
    </div>

    <!-- Body -->
      <div style="text-align: center;">
        <div class="card-heading">REGISTER NOW</div>
      </div>
      <!-- Benefit pills -->
      <div class="benefit-row">
        <span class="bpill"><i class="bi bi-stars" style="color:var(--alien)"></i>  INSIGHTS</span>
        <span class="bpill"><i class="bi bi-shield-check" style="color:var(--shield)"></i> SECURE</span>
        <span class="bpill"><i class="bi bi-graph-up" style="color:var(--laser)"></i> ANALYTICS</span>
        <span class="bpill"><i class="bi bi-calendar3" style="color:var(--warning)"></i> CALENDAR</span>
      </div>

      <?php foreach ($globalErrors as $err): ?>
      <div class="err-box">
        <i class="bi bi-exclamation-triangle-fill" style="color:var(--plasma);font-size:15px"></i>
        <?= h($err) ?>
      </div>
      <?php endforeach; ?>

      <form method="POST" action="<?= APP_BASE ?>/register.php" novalidate id="auth-form">
        <?= csrfField() ?>

        <!-- Name row -->
        <div class="fg-row">
          <div>
            <label class="fc-label" for="fname-input">
              <i class="bi bi-person"></i> First Name *
            </label>
            <div class="input-wrap">
              <i class="bi bi-person input-icon"></i>
              <input class="fc-input <?= isset($errors['fname']) ? 'err-field' : '' ?>"
                type="text" name="fname" id="fname-input"
                value="<?= h($_POST['fname'] ?? '') ?>" placeholder="juan" required>
            </div>
            <?php if (isset($errors['fname'])): ?>
            <div class="ferr">⚠ <?= h($errors['fname']) ?></div>
            <?php endif; ?>
          </div>
          <div>
            <label class="fc-label" for="lname-input">
              <i class="bi bi-person"></i> Last Name
            </label>
            <div class="input-wrap">
              <i class="bi bi-person input-icon"></i>
              <input class="fc-input" type="text" name="lname" id="lname-input"
                value="<?= h($_POST['lname'] ?? '') ?>" placeholder="dela cruz">
            </div>
          </div>
        </div>

        <!-- Email -->
        <div class="fg">
          <label class="fc-label" for="email-input">
            <i class="bi bi-envelope"></i> EMAIL *
          </label>
          <div class="input-wrap">
            <i class="bi bi-envelope input-icon"></i>
            <input class="fc-input <?= isset($errors['email']) ? 'err-field' : '' ?>"
              type="email" name="email" id="email-input"
              value="<?= h($_POST['email'] ?? '') ?>" placeholder="Juandelacruz@gmail.com"
              autocomplete="email" required>
          </div>
          <?php if (isset($errors['email'])): ?>
          <div class="ferr">⚠ <?= h($errors['email']) ?></div>
          <?php endif; ?>
        </div>

        <!-- Password -->
        <div class="fg">
          <label class="fc-label" for="pw-input">
            <i class="bi bi-shield-lock"></i> PASSWORD*
          </label>
          <div class="input-wrap">
            <i class="bi bi-lock input-icon"></i>
            <input class="fc-input has-eye <?= isset($errors['pass']) ? 'err-field' : '' ?>"
              type="password" name="password" id="pw-input"
              placeholder="Min. 8 characters" autocomplete="new-password" required
              oninput="checkPwdStrength(this.value)">
            <button type="button" class="eye-btn" onclick="togglePwd('pw-input',this)">
              <i class="bi bi-eye-slash"></i>
            </button>
          </div>
          <div class="pwd-strength">
            <div class="pwd-track"><div class="pwd-fill" id="pwd-fill-bar"></div></div>
            <div class="pwd-label" id="pwd-strength-label"></div>
          </div>
          <?php if (isset($errors['pass'])): ?>
          <div class="ferr">⚠ <?= h($errors['pass']) ?></div>
          <?php endif; ?>
        </div>

        <!-- Confirm password -->
        <div class="fg">
          <label class="fc-label" for="cf-input">
            <i class="bi bi-shield-fill-check"></i> Confirm PASSWORD *
          </label>
          <div class="input-wrap">
            <i class="bi bi-lock-fill input-icon"></i>
            <input class="fc-input has-eye <?= isset($errors['conf']) ? 'err-field' : '' ?>"
              type="password" name="confirm" id="cf-input"
              placeholder="Repeat access code" autocomplete="new-password" required
              oninput="checkConfirmMatch()">
            <button type="button" class="eye-btn" onclick="togglePwd('cf-input',this)">
              <i class="bi bi-eye-slash"></i>
            </button>
          </div>
          <div id="match-msg" style="margin-top:4px;display:flex;align-items:center;gap:5px"></div>
          <?php if (isset($errors['conf'])): ?>
          <div class="ferr">⚠ <?= h($errors['conf']) ?></div>
          <?php endif; ?>
        </div>

        <!-- Agree -->
        <div class="agree-row">
          <input type="checkbox" name="agree" id="agree-check"
            <?= isset($_POST['agree']) ? 'checked' : '' ?>>
          <label for="agree-check">
            I accept the <a href="#">Mission Terms</a> and <a href="#">Privacy Protocol</a>.
          </label>
        </div>

        <button type="submit" class="btn-launch" id="submit-btn"
          data-original='<i class="bi bi-person-plus-fill"></i>&nbsp; ENLIST TO FLEET'>
          <i class="bi bi-person-plus-fill"></i>&nbsp; REGISTER NOW
        </button>
      </form>

      <div class="auth-foot">
        Already Register? &nbsp;<a href="<?= APP_BASE ?>/login.php">Login Here→</a>
      </div>
    </div>
  </div>
</div>

<!-- ★ EXTERNAL BATTLE JS ★ -->
<script src="<?= APP_BASE ?>/assets/js/auth-battle.js"></script>
<script>
  setLoadingState('auth-form', 'submit-btn', 'ENLISTING…');

  /* Animate step dots while filling the form */
  (function() {
    const steps = [
      { fields: ['fname-input','lname-input','email-input'], stepId: 'step1' },
      { fields: ['pw-input','cf-input'],                     stepId: 'step2' },
      { fields: ['agree-check'],                             stepId: 'step3' },
    ];
    function updateSteps() {
      let lastDone = -1;
      steps.forEach((s, i) => {
        const allFilled = s.fields.every(id => {
          const el = document.getElementById(id);
          if (!el) return false;
          return el.type === 'checkbox' ? el.checked : el.value.trim().length > 0;
        });
        const el = document.getElementById(s.stepId);
        if (!el) return;
        el.classList.remove('active', 'done');
        if (allFilled) { el.classList.add('done'); lastDone = i; }
      });
      const nextStep = lastDone + 1;
      if (nextStep < steps.length) {
        const el = document.getElementById(steps[nextStep].stepId);
        if (el) el.classList.add('active');
      }
    }
    document.querySelectorAll('.fc-input, #agree-check').forEach(el => {
      el.addEventListener('input', updateSteps);
      el.addEventListener('change', updateSteps);
    });
  })();
</script>
</body>
</html>
