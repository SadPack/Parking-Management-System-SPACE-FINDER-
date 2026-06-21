<?php
session_start();
include("../config/db.php");
include("../includes/auth.php");

require_customer(1);

$userId = $_SESSION['user_id'];

// Full vehicle history (parked + exited)
$vehicleHistory = $conn->prepare("
    SELECT v.vehicle_number, v.entry_time, v.exit_time, v.status, s.slot_number
    FROM vehicles v
    JOIN parking_slots s ON v.slot_id = s.id
    WHERE v.user_id = ?
    ORDER BY v.entry_time DESC
");
$vehicleHistory->bind_param("i", $userId);
$vehicleHistory->execute();
$vehicleHistoryResult = $vehicleHistory->get_result();

// Full payment history
$paymentHistory = $conn->prepare("
    SELECT p.amount, p.status, p.paid_at, v.vehicle_number, s.slot_number
    FROM payments p
    JOIN vehicles v ON p.vehicle_id = v.id
    JOIN parking_slots s ON v.slot_id = s.id
    WHERE p.user_id = ?
    ORDER BY v.entry_time DESC
");
$paymentHistory->bind_param("i", $userId);
$paymentHistory->execute();
$paymentHistoryResult = $paymentHistory->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History - Parking System</title>
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

    <h1 class="page-title">My History</h1>

    <div class="nav-tabs">
        <a href="dashboard.php">Dashboard</a>
        <a href="payments.php">Make Payment</a>
        <a href="history.php" class="active">History</a>
    </div>

    <h2 class="page-title" style="font-size:18px;">Parking History</h2>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Vehicle No.</th>
                    <th>Slot</th>
                    <th>Entry Time</th>
                    <th>Exit Time</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($vehicleHistoryResult->num_rows === 0): ?>
                    <tr><td colspan="5" class="empty-state">No parking history yet.</td></tr>
                <?php else: ?>
                    <?php while ($row = $vehicleHistoryResult->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo e($row['vehicle_number']); ?></td>
                            <td><?php echo e($row['slot_number']); ?></td>
                            <td><?php echo e($row['entry_time']); ?></td>
                            <td><?php echo $row['exit_time'] ? e($row['exit_time']) : '—'; ?></td>
                            <td><span class="badge badge-<?php echo e($row['status']); ?>"><?php echo e($row['status']); ?></span></td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <h2 class="page-title" style="font-size:18px;">Payment History</h2>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Vehicle No.</th>
                    <th>Slot</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Paid At</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($paymentHistoryResult->num_rows === 0): ?>
                    <tr><td colspan="5" class="empty-state">No payment history yet.</td></tr>
                <?php else: ?>
                    <?php while ($row = $paymentHistoryResult->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo e($row['vehicle_number']); ?></td>
                            <td><?php echo e($row['slot_number']); ?></td>
                            <td>Rs. <?php echo e(number_format($row['amount'], 2)); ?></td>
                            <td><span class="badge badge-<?php echo e($row['status']); ?>"><?php echo e($row['status']); ?></span></td>
                            <td><?php echo $row['paid_at'] ? e($row['paid_at']) : '—'; ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>