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
        if ($pass !== $confirm)                  $errors['conf']  = 'Passwords do not match.';
        if (!$agree)                             $errors[]        = 'You must accept the Terms of Service.';

        if (empty($errors)) {
            try {
                $existing = dbQueryOne(
                    'SELECT id FROM users WHERE LOWER(email) = LOWER(?)', [$email]
                );
                if ($existing) {
                    $errors['email'] = 'This email is already registered.';
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
                    flashSet('success', "Welcome aboard, $fname! 🚀");
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
    <title>Register — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&family=Syne:wght@400;500;600;700;800&family=Space+Grotesk:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/style.css">
</head>
<body>
    <div class="auth-wrap">
        <div class="auth-card">
            <div class="auth-top">
                <div class="auth-logo"><?= APP_NAME ?></div>
                <div class="auth-tagline">Smart expense tracking</div>
            </div>
            <div class="auth-body">
                <div class="auth-heading">Enlist Now</div>
                <div class="auth-sub">Join the fleet and take control</div>

                <?php if ($globalErrors): ?>
                    <?php foreach ($globalErrors as $err): ?>
                    <div class="auth-error">
                        <i class="bi bi-exclamation-triangle-fill"></i> <?= h($err) ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <form method="POST" action="<?= APP_BASE ?>/register.php">
                    <?= csrfField() ?>

                    <div class="g2" style="margin-bottom: 16px;">
                        <div class="form-group">
                            <label class="fc-label" for="fname">First name *</label>
                            <input type="text" name="fname" id="fname" class="fc <?= isset($errors['fname']) ? 'err' : '' ?>" 
                                   value="<?= h($fd['fname']) ?>" placeholder="Juan" required>
                            <?php if (isset($errors['fname'])): ?>
                                <div class="field-err" style="display: block;"><?= h($errors['fname']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="fc-label" for="lname">Last name</label>
                            <input type="text" name="lname" id="lname" class="fc" 
                                   value="<?= h($fd['lname']) ?>" placeholder="Dela Cruz">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label class="fc-label" for="email">Email *</label>
                        <input type="email" name="email" id="email" class="fc <?= isset($errors['email']) ? 'err' : '' ?>" 
                               value="<?= h($fd['email']) ?>" placeholder="you@example.com" required>
                        <?php if (isset($errors['email'])): ?>
                            <div class="field-err" style="display: block;"><?= h($errors['email']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label class="fc-label" for="password">Password *</label>
                        <div class="input-wrap">
                            <input type="password" name="password" id="password" class="fc has-eye <?= isset($errors['pass']) ? 'err' : '' ?>" 
                                   placeholder="Minimum 8 characters" required>
                            <button type="button" class="input-eye" onclick="togglePassword('password')">
                                <i class="bi bi-eye-slash"></i>
                            </button>
                        </div>
                        <?php if (isset($errors['pass'])): ?>
                            <div class="field-err" style="display: block;"><?= h($errors['pass']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label class="fc-label" for="confirm">Confirm password *</label>
                        <div class="input-wrap">
                            <input type="password" name="confirm" id="confirm" class="fc has-eye <?= isset($errors['conf']) ? 'err' : '' ?>" 
                                   placeholder="Repeat password" required>
                            <button type="button" class="input-eye" onclick="togglePassword('confirm')">
                                <i class="bi bi-eye-slash"></i>
                            </button>
                        </div>
                        <?php if (isset($errors['conf'])): ?>
                            <div class="field-err" style="display: block;"><?= h($errors['conf']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="check-row" style="margin-bottom: 24px;">
                        <label class="check-label" style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" name="agree" <?= isset($_POST['agree']) ? 'checked' : '' ?> required>
                            I agree to the <a href="#" style="color: var(--c-cyan);">Terms</a> and <a href="#" style="color: var(--c-cyan);">Privacy Policy</a>.
                        </label>
                    </div>
                </form>

                <div class="divider" style="margin: 24px 0 16px;">
                    <span>Already registered?</span>
                </div>

                <div style="text-align: center;">
                    <a href="<?= APP_BASE ?>/login.php" class="btn-outline" style="width: 100%; display: flex; justify-content: center;">
                        Sign In
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const btn = field.parentElement.querySelector('.input-eye i');
            if (field.type === 'password') {
                field.type = 'text';
                btn.classList.remove('bi-eye-slash');
                btn.classList.add('bi-eye');
            } else {
                field.type = 'password';
                btn.classList.remove('bi-eye');
                btn.classList.add('bi-eye-slash');
            }
        }
    </script>
</body>
</html>