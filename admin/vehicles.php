<?php
session_start();
include("../config/db.php");
include("../includes/auth.php");

require_admin(1);

$error = "";
$success = "";

// --- Handle "Mark Exit" (UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_exit'])) {

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = "Invalid form submission.";
    } else {
        $vehicleId = (int) $_POST['vehicle_id'];

        $conn->begin_transaction();
        try {
            // Get the slot tied to this vehicle so we can free it
            $get = $conn->prepare("SELECT slot_id FROM vehicles WHERE id = ? AND status = 'parked'");
            $get->bind_param("i", $vehicleId);
            $get->execute();
            $row = $get->get_result()->fetch_assoc();
            $get->close();

            if (!$row) {
                $error = "Vehicle not found or already exited.";
            } else {
                $update = $conn->prepare("UPDATE vehicles SET exit_time = NOW(), status = 'exited' WHERE id = ?");
                $update->bind_param("i", $vehicleId);
                $update->execute();
                $update->close();

                $freeSlot = $conn->prepare("UPDATE parking_slots SET status = 'free' WHERE id = ?");
                $freeSlot->bind_param("i", $row['slot_id']);
                $freeSlot->execute();
                $freeSlot->close();

                $conn->commit();
                $success = "Vehicle marked as exited. Slot is now free.";
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Failed to update vehicle.";
        }
    }
}

// --- Handle DELETE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_vehicle'])) {

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = "Invalid form submission.";
    } else {
        $vehicleId = (int) $_POST['vehicle_id'];

        $conn->begin_transaction();
        try {
            // Free the slot if the vehicle was still parked
            $get = $conn->prepare("SELECT slot_id, status FROM vehicles WHERE id = ?");
            $get->bind_param("i", $vehicleId);
            $get->execute();
            $row = $get->get_result()->fetch_assoc();
            $get->close();

            if ($row) {
                if ($row['status'] === 'parked') {
                    $freeSlot = $conn->prepare("UPDATE parking_slots SET status = 'free' WHERE id = ?");
                    $freeSlot->bind_param("i", $row['slot_id']);
                    $freeSlot->execute();
                    $freeSlot->close();
                }

                // payments row is removed automatically via ON DELETE CASCADE
                $del = $conn->prepare("DELETE FROM vehicles WHERE id = ?");
                $del->bind_param("i", $vehicleId);
                $del->execute();
                $del->close();

                $conn->commit();
                $success = "Vehicle record deleted.";
            } else {
                $conn->rollback();
                $error = "Vehicle not found.";
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Failed to delete vehicle.";
        }
    }
}

// --- READ all vehicles ---
$vehicles = $conn->query("
    SELECT v.id, v.vehicle_number, v.entry_time, v.exit_time, v.status,
           s.slot_number, u.name AS customer_name,
           p.status AS payment_status, p.amount
    FROM vehicles v
    JOIN parking_slots s ON v.slot_id = s.id
    JOIN users u ON v.user_id = u.id
    LEFT JOIN payments p ON p.vehicle_id = v.id
    ORDER BY v.entry_time DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Vehicles - Admin</title>
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

    <h1 class="page-title">Manage Vehicles</h1>

    <div class="nav-tabs">
        <a href="dashboard.php">Dashboard</a>
        <a href="vehicles.php" class="active">Manage Vehicles</a>
        <a href="add_vehicle.php">Add Vehicle</a>
        <a href="customers.php">Manage Customers</a>
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
                    <th>Customer</th>
                    <th>Slot</th>
                    <th>Entry</th>
                    <th>Exit</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($vehicles->num_rows === 0): ?>
                    <tr><td colspan="8" class="empty-state">No vehicles recorded yet. <a href="add_vehicle.php" style="color:#38bdf8;">Add one &rarr;</a></td></tr>
                <?php else: ?>
                    <?php while ($v = $vehicles->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo e($v['vehicle_number']); ?></td>
                            <td><?php echo e($v['customer_name']); ?></td>
                            <td><?php echo e($v['slot_number']); ?></td>
                            <td><?php echo e($v['entry_time']); ?></td>
                            <td><?php echo $v['exit_time'] ? e($v['exit_time']) : '—'; ?></td>
                            <td><span class="badge badge-<?php echo e($v['status']); ?>"><?php echo e($v['status']); ?></span></td>
                            <td>
                                <?php if ($v['payment_status']): ?>
                                    <span class="badge badge-<?php echo e($v['payment_status']); ?>">
                                        <?php echo e($v['payment_status']); ?> (Rs. <?php echo e(number_format($v['amount'], 2)); ?>)
                                    </span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($v['status'] === 'parked'): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Mark this vehicle as exited?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                        <input type="hidden" name="vehicle_id" value="<?php echo e($v['id']); ?>">
                                        <button type="submit" name="mark_exit" class="btn btn-success">Mark Exit</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this vehicle record permanently?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                    <input type="hidden" name="vehicle_id" value="<?php echo e($v['id']); ?>">
                                    <button type="submit" name="delete_vehicle" class="btn btn-danger">Delete</button>
                                </form>
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