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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 440px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 24px;
            padding: 40px 36px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            transition: transform 0.2s ease;
        }

        .login-card:hover {
            transform: translateY(-4px);
        }

        .logo {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo h1 {
            font-size: 32px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            letter-spacing: -0.5px;
        }

        .logo p {
            font-size: 14px;
            color: #64748b;
            margin-top: 6px;
        }

        .error-message {
            background: #fee2e2;
            color: #ef476f;
            border-left: 4px solid #ef476f;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 6px;
        }

        .form-group label i {
            font-size: 14px;
            color: #667eea;
        }

        .input-group {
            position: relative;
        }

        .input-group input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.2s ease;
            outline: none;
        }

        .input-group input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .input-group input::placeholder {
            color: #94a3b8;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #64748b;
            font-size: 16px;
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            margin-bottom: 24px;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            color: #1e293b;
        }

        .checkbox-label input {
            width: 16px;
            height: 16px;
            accent-color: #667eea;
        }

        .forgot-link {
            color: #667eea;
            text-decoration: none;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            padding: 14px;
            border-radius: 40px;
            font-size: 15px;
            font-weight: 600;
            color: white;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .signup-link {
            text-align: center;
            margin-top: 28px;
            font-size: 13px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
            padding-top: 24px;
        }

        .signup-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .signup-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 32px 24px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo">
                <h1><?= APP_NAME ?></h1>
                <p>Smart expense tracking</p>
            </div>

            <?php if ($error): ?>
            <div class="error-message">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?= h($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="<?= APP_BASE ?>/login.php">
                <?= csrfField() ?>

                <div class="form-group">
                    <label for="email"><i class="bi bi-envelope"></i> Email</label>
                    <div class="input-group">
                        <input type="email" name="email" id="email" class="form-control" 
                               value="<?= h($emailVal) ?>" placeholder="your@email.com" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password"><i class="bi bi-lock"></i> Password</label>
                    <div class="input-group">
                        <input type="password" name="password" id="password" placeholder="••••••••" required>
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="bi bi-eye-slash"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember"> Remember me
                    </label>
                    <a href="<?= APP_BASE ?>/forgot-password.php" class="forgot-link">Forgot password?</a>
                </div>

                <button type="submit" class="btn-login">
                    <i class="bi bi-box-arrow-in-right"></i> Sign In
                </button>
            </form>

            <div class="signup-link">
                Don't have an account? <a href="<?= APP_BASE ?>/register.php">Create account</a>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const field = document.getElementById('password');
            const btn = document.querySelector('.password-toggle i');
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