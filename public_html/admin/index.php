<?php
/**
 * Admin Login Page
 */

require_once __DIR__ . '/config/config.php';

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: ' . ADMIN_BASE_URL . '/dashboard.php');
    exit;
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        try {
            $pdo = get_db_connection();

            // Find admin by email
            $stmt = $pdo->prepare('SELECT * FROM admins WHERE email = ? AND is_active = 1');
            $stmt->execute([$email]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                // Login successful
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['last_activity'] = time();

                // Update last login
                $stmt = $pdo->prepare('UPDATE admins SET last_login = NOW() WHERE id = ?');
                $stmt->execute([$admin['id']]);

                // Regenerate session ID for security
                session_regenerate_id(true);

                set_flash('success', 'Welcome back, ' . $admin['name'] . '!');
                header('Location: ' . ADMIN_BASE_URL . '/dashboard.php');
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            error_log('Login error: ' . $e->getMessage());
            $error = 'An error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo ADMIN_SITE_NAME; ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            padding: 2rem;
            background: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .login-logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .login-logo i {
            font-size: 3rem;
            color: #667eea;
        }

        .login-logo h1 {
            font-size: 1.5rem;
            margin-top: 0.5rem;
            color: #333;
        }

        .form-floating > label {
            color: #6c757d;
        }

        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 0.75rem;
            font-weight: 600;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #5a6fd6 0%, #6a4190 100%);
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-logo">
            <i class="bi bi-file-earmark-pdf-fill"></i>
            <h1><?php echo ADMIN_SITE_NAME; ?></h1>
        </div>

        <?php
        // Display flash messages
        $flash_messages = get_flash_messages();
        foreach ($flash_messages as $flash): ?>
            <div class="alert alert-<?php echo e($flash['type']); ?> alert-dismissible fade show" role="alert">
                <?php echo e($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo e($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php echo csrf_field(); ?>

            <div class="form-floating mb-3">
                <input type="email" class="form-control" id="email" name="email"
                       placeholder="name@example.com" required autofocus
                       value="<?php echo e($_POST['email'] ?? ''); ?>">
                <label for="email">Email address</label>
            </div>

            <div class="form-floating mb-4">
                <input type="password" class="form-control" id="password" name="password"
                       placeholder="Password" required>
                <label for="password">Password</label>
            </div>

            <button type="submit" class="btn btn-primary btn-login w-100">
                <i class="bi bi-box-arrow-in-right"></i> Sign In
            </button>
        </form>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
