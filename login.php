<?php

ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);

session_start();
include("config/db.php");
include("includes/auth.php");

$error = "";
$email = "";

const MAX_LOGIN_ATTEMPTS = 5;
const LOCKOUT_SECONDS = 300; // 5 minutes

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
if (!isset($_SESSION['lockout_until'])) {
    $_SESSION['lockout_until'] = 0;
}

$isLockedOut = $_SESSION['lockout_until'] > time();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isLockedOut) {

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = "Invalid form submission. Please refresh the page and try again.";
    } else {

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $error = "Please enter both email and password.";
        } else {

            $stmt = $conn->prepare("SELECT id, name, password, role, status FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($password, $user['password'])) {

                if ($user['status'] === 'disabled') {

                    $error = "This account has been disabled. Please contact the administrator.";

                } else {

                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['name'] = $user['name'];

                    $_SESSION['login_attempts'] = 0;
                    $_SESSION['lockout_until'] = 0;

                    if ($user['role'] === 'admin') {
                        header("Location: admin/dashboard.php");
                    } else {
                        header("Location: customer/dashboard.php");
                    }
                    exit();
                }

            } else {
                $_SESSION['login_attempts']++;

                if ($_SESSION['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
                    $_SESSION['lockout_until'] = time() + LOCKOUT_SECONDS;
                    $_SESSION['login_attempts'] = 0;
                    $isLockedOut = true;
                    $error = "Too many login attempts. Please try again in " . ceil(LOCKOUT_SECONDS / 60) . " minutes.";
                } else {
                    $remaining = MAX_LOGIN_ATTEMPTS - $_SESSION['login_attempts'];
                    $error = "Invalid email or password! ({$remaining} attempt" . ($remaining === 1 ? "" : "s") . " remaining)";
                }
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLockedOut) {
    $minutesLeft = ceil(($_SESSION['lockout_until'] - time()) / 60);
    $error = "Too many login attempts. Please try again in {$minutesLeft} minute" . ($minutesLeft === 1 ? "" : "s") . ".";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SPACE FINDER</title>
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>

<div class="box">

    <h2>Login</h2>

    <?php if (isset($_GET['logged_out'])): ?>
        <p class="message message-success">You have been logged out successfully.</p>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <p class="message message-error"><?php echo e($error); ?></p>
    <?php endif; ?>

    <form method="POST" id="loginForm">

        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">

        <input type="email" name="email" placeholder="Email" value="<?php echo e($email); ?>" required <?php echo $isLockedOut ? 'disabled' : ''; ?>>

        <input type="password" name="password" placeholder="Password" required <?php echo $isLockedOut ? 'disabled' : ''; ?>>

        <button type="submit" name="login" id="loginBtn" <?php echo $isLockedOut ? 'disabled' : ''; ?>>
            <?php echo $isLockedOut ? 'Locked' : 'Login'; ?>
        </button>

    </form>

    <p class="bottom-link">
        Don't have an account? <a href="register.php">Register</a>
    </p>

</div>

<script src="assets/js/login.js"></script>

</body>
</html>