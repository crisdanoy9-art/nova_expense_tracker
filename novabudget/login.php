<?php
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
    <title>Login — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&family=Syne:wght@400;500;600;700;800&family=Space+Grotesk:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= APP_BASE ?>/assets/css/style.css">
    <style>
        .auth-heading {
            text-align: center;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .auth-sub {
            text-align: center;
            font-size: 14px;
            margin-bottom: 24px;
        }
    </style>
</head>
<body>
    <div class="auth-wrap">
        <div class="auth-card">
            <div class="auth-top">
                <div class="auth-logo"><?= APP_NAME ?></div>
                <div class="auth-tagline">Smart expense tracking</div>
            </div>
            <div class="auth-body">
                <div class="auth-heading">Access Portal</div>
                <div class="auth-sub">Sign in to continue</div>

                <?php if ($error): ?>
                <div class="auth-error">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?= h($error) ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="<?= APP_BASE ?>/login.php">
                    <?= csrfField() ?>

                    <div class="form-group" style="margin-bottom: 18px;">
                        <label class="fc-label" for="email">Email</label>
                        <div class="input-wrap">
                            <i class="bi bi-envelope input-icon"></i>
                            <input type="email" name="email" id="email" class="fc" 
                                   value="<?= h($emailVal) ?>" placeholder="your@email.com" required autofocus>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 22px;">
                        <label class="fc-label" for="password">Password</label>
                        <div class="input-wrap">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" name="password" id="password" class="fc has-eye" 
                                   placeholder="••••••••" required>
                            <button type="button" class="input-eye" onclick="togglePassword()">
                                <i class="bi bi-eye-slash"></i>
                            </button>
                        </div>
                    </div>

                    <div class="check-row" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                        <label class="check-label" style="display: flex; align-items: center; gap: 8px; font-size: 12.5px;">
                            <input type="checkbox" name="remember"> Remember me
                        </label>
                        <a href="<?= APP_BASE ?>/forgot-password.php" style="color: var(--c-cyan); font-size: 12.5px;">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn-glow" style="width: 100%; padding: 12px;">
                        <i class="bi bi-box-arrow-in-right"></i> Sign In
                    </button>
                </form>

                <div class="divider" style="margin: 24px 0 16px;">
                    <span>New here?</span>
                </div>

                <div style="text-align: center;">
                    <a href="<?= APP_BASE ?>/register.php" class="btn-outline" style="width: 100%; display: flex; justify-content: center;">
                        Create Account
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const field = document.getElementById('password');
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