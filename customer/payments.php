<?php
session_start();
include("../config/db.php");
include("../includes/auth.php");

require_customer(1);

$userId = $_SESSION['user_id'];
$error = "";
$success = "";

// --- Handle payment submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_now'])) {

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = "Invalid form submission. Please refresh and try again.";
    } else {

        $paymentId = (int) $_POST['payment_id'];

        // Ownership check: make sure this payment actually belongs to this customer.
        // Without this, a customer could pay (or worse, manipulate) someone else's payment
        // just by guessing/changing the payment_id in the form.
        $check = $conn->prepare("SELECT id, status FROM payments WHERE id = ? AND user_id = ?");
        $check->bind_param("ii", $paymentId, $userId);
        $check->execute();
        $payment = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$payment) {
            $error = "Payment not found.";
        } elseif ($payment['status'] === 'paid') {
            $error = "This payment has already been completed.";
        } else {
            $update = $conn->prepare("UPDATE payments SET status = 'paid', paid_at = NOW() WHERE id = ?");
            $update->bind_param("i", $paymentId);
            $update->execute();
            $update->close();

            $success = "Payment successful! Thank you.";
        }
    }
}

// --- Get all pending payments for this customer ---
$pending = $conn->prepare("
    SELECT p.id AS payment_id, p.amount, v.vehicle_number, s.slot_number, v.entry_time
    FROM payments p
    JOIN vehicles v ON p.vehicle_id = v.id
    JOIN parking_slots s ON v.slot_id = s.id
    WHERE p.user_id = ? AND p.status = 'pending'
    ORDER BY v.entry_time ASC
");
$pending->bind_param("i", $userId);
$pending->execute();
$pendingResult = $pending->get_result();

// If a specific payment was requested via ?pay=ID (from dashboard "Pay Now" link),
// pre-select it for a slightly smoother flow.
$preselectedId = isset($_GET['pay']) ? (int) $_GET['pay'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Payment - Parking System</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>

<div class="topbar">
    <div class="brand">SPACE FINDER</div>
    <div class="user-info">
        <span>Hi, <?php echo e($_SESSION['name']); ?></span>
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>
</div>

<div class="container">

    <h1 class="page-title">Make Payment</h1>

    <div class="nav-tabs">
        <a href="dashboard.php">Dashboard</a>
        <a href="payments.php" class="active">Make Payment</a>
        <a href="history.php">History</a>
    </div>

    <?php if ($error !== ""): ?>
        <p class="message message-error"><?php echo e($error); ?></p>
    <?php endif; ?>

    <?php if ($success !== ""): ?>
        <p class="message message-success"><?php echo e($success); ?></p>
    <?php endif; ?>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Vehicle No.</th>
                    <th>Slot</th>
                    <th>Entry Time</th>
                    <th>Amount</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($pendingResult->num_rows === 0): ?>
                    <tr><td colspan="5" class="empty-state">No pending payments. You're all settled up!</td></tr>
                <?php else: ?>
                    <?php while ($row = $pendingResult->fetch_assoc()): ?>
                        <tr<?php echo ($row['payment_id'] == $preselectedId) ? ' style="background:rgba(56,189,248,0.1);"' : ''; ?>>
                            <td><?php echo e($row['vehicle_number']); ?></td>
                            <td><?php echo e($row['slot_number']); ?></td>
                            <td><?php echo e($row['entry_time']); ?></td>
                            <td>Rs. <?php echo e(number_format($row['amount'], 2)); ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Confirm payment of Rs. <?php echo e(number_format($row['amount'], 2)); ?>?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                    <input type="hidden" name="payment_id" value="<?php echo e($row['payment_id']); ?>">
                                    <button type="submit" name="pay_now" class="btn btn-success">Pay Now</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <p style="color:#6b7280; font-size:13px; margin-top:10px;">
        ⓘ This is a simulated payment for demonstration purposes — no real transaction is processed.
    </p>

</div>

</body>
</html>