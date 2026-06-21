<?php
session_start();
include("../config/db.php");
include("../includes/auth.php");

require_admin(1); // depth=1 because this file is inside /admin/

// --- Summary stats ---

$totalSlots = $conn->query("SELECT COUNT(*) AS c FROM parking_slots")->fetch_assoc()['c'];
$occupiedSlots = $conn->query("SELECT COUNT(*) AS c FROM parking_slots WHERE status = 'occupied'")->fetch_assoc()['c'];
$freeSlots = $totalSlots - $occupiedSlots;

$totalCustomers = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'customer'")->fetch_assoc()['c'];

$todayRevenue = $conn->query("
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM payments
    WHERE status = 'paid' AND DATE(paid_at) = CURDATE()
")->fetch_assoc()['total'];

$totalRevenue = $conn->query("
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM payments
    WHERE status = 'paid'
")->fetch_assoc()['total'];

$pendingPayments = $conn->query("SELECT COUNT(*) AS c FROM payments WHERE status = 'pending'")->fetch_assoc()['c'];

// --- Recent activity (last 8 vehicle entries) ---
$recent = $conn->query("
    SELECT v.vehicle_number, v.entry_time, v.exit_time, v.status,
           s.slot_number, u.name AS customer_name
    FROM vehicles v
    JOIN parking_slots s ON v.slot_id = s.id
    JOIN users u ON v.user_id = u.id
    ORDER BY v.entry_time DESC
    LIMIT 8
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Parking System</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>

<div class="topbar">
    <div class="brand">SPACE FINDER — Admin</div>
    <div class="user-info">
        <span>Hi, <?php echo e($_SESSION['name']); ?></span>
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>
</div>

<div class="container">

    <h1 class="page-title">Dashboard Overview</h1>

    <div class="nav-tabs">
        <a href="dashboard.php" class="active">Dashboard</a>
        <a href="vehicles.php">Manage Vehicles</a>
        <a href="add_vehicle.php">Add Vehicle</a>
        <a href="customers.php">Manage Customers</a>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Slots</div>
            <div class="stat-value"><?php echo e($totalSlots); ?></div>
        </div>
        <div class="stat-card yellow">
            <div class="stat-label">Occupied Slots</div>
            <div class="stat-value"><?php echo e($occupiedSlots); ?></div>
        </div>
        <div class="stat-card green">
            <div class="stat-label">Free Slots</div>
            <div class="stat-value"><?php echo e($freeSlots); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Customers</div>
            <div class="stat-value"><?php echo e($totalCustomers); ?></div>
        </div>
        <div class="stat-card green">
            <div class="stat-label">Today's Revenue</div>
            <div class="stat-value">Rs. <?php echo e(number_format($todayRevenue, 2)); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Revenue</div>
            <div class="stat-value">Rs. <?php echo e(number_format($totalRevenue, 2)); ?></div>
        </div>
        <div class="stat-card red">
            <div class="stat-label">Pending Payments</div>
            <div class="stat-value"><?php echo e($pendingPayments); ?></div>
        </div>
    </div>

    <h2 class="page-title" style="font-size:18px;">Recent Activity</h2>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Vehicle No.</th>
                    <th>Customer</th>
                    <th>Slot</th>
                    <th>Entry Time</th>
                    <th>Exit Time</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recent->num_rows === 0): ?>
                    <tr><td colspan="6" class="empty-state">No vehicle activity yet.</td></tr>
                <?php else: ?>
                    <?php while ($row = $recent->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo e($row['vehicle_number']); ?></td>
                            <td><?php echo e($row['customer_name']); ?></td>
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

</div>

</body>
</html>