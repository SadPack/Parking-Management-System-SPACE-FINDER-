<?php
session_start();
include("config/db.php");
include("includes/auth.php");

$error = "";
$success = "";
$name = "";
$email = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = "Invalid form submission. Please refresh the page and try again.";
    } else {

        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($name === '' || $email === '' || $password === '') {
            $error = "All fields are required!";
        }
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address!";
        }
        elseif (
            strlen($password) < 8 ||
            !preg_match('/[A-Z]/', $password) ||
            !preg_match('/[a-z]/', $password) ||
            !preg_match('/[0-9]/', $password)
        ) {
            $error = "Weak password! Must contain an uppercase letter, lowercase letter, number, and be 8+ characters.";
        }
        else {

            // Check for duplicate email (prepared statement = SQL injection safe)
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $result = $check->get_result();

            if ($result->num_rows > 0) {
                $error = "Email already exists!";
            }
            else {
                // Customers ALWAYS register as 'customer'. Admin accounts are
                // never created through this public form — that would let
                // anyone grant themselves admin access.
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

                $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'customer')");
                $stmt->bind_param("sss", $name, $email, $hashedPassword);

                if ($stmt->execute()) {
                    $success = "Registration successful! Redirecting to login...";
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // rotate token after use
                    $name = "";
                    $email = "";
                } else {
                    $error = "Registration failed. Please try again.";
                }

                $stmt->close();
            }

            $check->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - SPACE FINDER</title>
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="assets/css/register.css">
</head>
<body>

<div class="box">

    <h2>Register</h2>

    <?php if ($error !== ""): ?>
        <p class="message message-error"><?php echo e($error); ?></p>
    <?php endif; ?>

    <?php if ($success !== ""): ?>
        <p class="message message-success"><?php echo e($success); ?></p>
    <?php endif; ?>

    <form method="POST" id="registerForm" novalidate>

        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">

        <input type="text" name="name" placeholder="Full Name" value="<?php echo e($name); ?>" required>

        <input type="email" name="email" placeholder="Email" value="<?php echo e($email); ?>" required>

        <input type="password" name="password" placeholder="Password" required minlength="8">
        <small class="hint">Min 8 characters, with uppercase, lowercase &amp; a number.</small>

        <button type="submit" id="regBtn" name="register">Register</button>

    </form>

    <p class="bottom-link">
        Already have an account? <a href="login.php">Login</a>
    </p>

</div>

<?php if ($success !== ""): ?>
    <script>
        setTimeout(function () {
            window.location.href = "login.php";
        }, 1500);
    </script>
<?php endif; ?>

<script src="assets/js/register.js"></script>

</body>
</html>