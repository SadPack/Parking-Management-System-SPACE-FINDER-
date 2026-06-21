<?php
session_start();
include("../config/db.php");
include("../includes/auth.php");

require_customer(1);

$userId = $_SESSION['user_id'];

// Currently parked vehicles for this customer
$parked = $conn->prepare("
    SELECT v.id, v.vehicle_number, v.entry_time, s.slot_number,
           p.status AS payment_status, p.amount, p.id AS payment_id
    FROM vehicles v
    JOIN parking_slots s ON v.slot_id = s.id
    LEFT JOIN payments p ON p.vehicle_id = v.id
    WHERE v.user_id = ? AND v.status = 'parked'
    ORDER BY v.entry_time DESC
");
$parked->bind_param("i", $userId);
$parked->execute();
$parkedResult = $parked->get_result();

// Quick stats
$totalVisits = $conn->prepare("SELECT COUNT(*) AS c FROM vehicles WHERE user_id = ?");
$totalVisits->bind_param("i", $userId);
$totalVisits->execute();
$totalVisitsCount = $totalVisits->get_result()->fetch_assoc()['c'];

$pendingDue = $conn->prepare("
    SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE user_id = ? AND status = 'pending'
");
$pendingDue->bind_param("i", $userId);
$pendingDue->execute();
$pendingDueAmount = $pendingDue->get_result()->fetch_assoc()['total'];

$totalPaid = $conn->prepare("
    SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE user_id = ? AND status = 'paid'
");
$totalPaid->bind_param("i", $userId);
$totalPaid->execute();
$totalPaidAmount = $totalPaid->get_result()->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - Parking System</title>
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

    <h1 class="page-title">My Dashboard</h1>

    <div class="nav-tabs">
        <a href="dashboard.php" class="active">Dashboard</a>
        <a href="payments.php">Make Payment</a>
        <a href="history.php">History</a>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Currently Parked</div>
            <div class="stat-value"><?php echo e($parkedResult->num_rows); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Visits</div>
            <div class="stat-value"><?php echo e($totalVisitsCount); ?></div>
        </div>
        <div class="stat-card red">
            <div class="stat-label">Pending Due</div>
            <div class="stat-value">Rs. <?php echo e(number_format($pendingDueAmount, 2)); ?></div>
        </div>
        <div class="stat-card green">
            <div class="stat-label">Total Paid</div>
            <div class="stat-value">Rs. <?php echo e(number_format($totalPaidAmount, 2)); ?></div>
        </div>
    </div>

    <h2 class="page-title" style="font-size:18px;">Currently Parked Vehicles</h2>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Vehicle No.</th>
                    <th>Slot</th>
                    <th>Entry Time</th>
                    <th>Payment</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($parkedResult->num_rows === 0): ?>
                    <tr><td colspan="5" class="empty-state">No vehicles currently parked.</td></tr>
                <?php else: ?>
                    <?php while ($row = $parkedResult->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo e($row['vehicle_number']); ?></td>
                            <td><?php echo e($row['slot_number']); ?></td>
                            <td><?php echo e($row['entry_time']); ?></td>
                            <td>
                                <?php if ($row['payment_status']): ?>
                                    <span class="badge badge-<?php echo e($row['payment_status']); ?>">
                                        <?php echo e($row['payment_status']); ?> (Rs. <?php echo e(number_format($row['amount'], 2)); ?>)
                                    </span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['payment_status'] === 'pending'): ?>
                                    <a href="payments.php?pay=<?php echo e($row['payment_id']); ?>" class="btn btn-primary">Pay Now</a>
                                <?php else: ?>
                                    <span style="color:#6b7280; font-size:13px;">Paid</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>