<?php
session_start();
include("../config/db.php");
include("../includes/auth.php");

require_admin(1);

$error = "";
$success = "";

// --- Handle disable/enable toggle ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = "Invalid form submission.";
    } else {
        $targetId = (int) $_POST['user_id'];
        $newStatus = $_POST['new_status'] === 'disabled' ? 'disabled' : 'active';

        // Never allow disabling your own admin account through this form —
        // that would lock the admin out with no way back in.
        if ($targetId === (int) $_SESSION['user_id']) {
            $error = "You cannot disable your own account.";
        } else {
            $update = $conn->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'customer'");
            $update->bind_param("si", $newStatus, $targetId);
            $update->execute();
            $update->close();

            $success = $newStatus === 'disabled' ? "Account disabled." : "Account re-enabled.";
        }
    }
}

// --- Handle delete ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = "Invalid form submission.";
    } else {
        $targetId = (int) $_POST['user_id'];

        if ($targetId === (int) $_SESSION['user_id']) {
            $error = "You cannot delete your own account.";
        } else {
            // ON DELETE CASCADE on vehicles/payments handles cleanup of related records.
            $del = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'customer'");
            $del->bind_param("i", $targetId);
            $del->execute();
            $del->close();

            $success = "Customer account deleted.";
        }
    }
}

// --- Search/filter ---
$searchTerm = trim($_GET['q'] ?? '');

if ($searchTerm !== '') {
    $likeTerm = '%' . $searchTerm . '%';
    $customers = $conn->prepare("
        SELECT id, name, email, status, created_at
        FROM users
        WHERE role = 'customer' AND (name LIKE ? OR email LIKE ?)
        ORDER BY created_at DESC
    ");
    $customers->bind_param("ss", $likeTerm, $likeTerm);
    $customers->execute();
    $customersResult = $customers->get_result();
} else {
    $customersResult = $conn->query("
        SELECT id, name, email, status, created_at
        FROM users
        WHERE role = 'customer'
        ORDER BY created_at DESC
    ");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Customers - Admin</title>
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

    <h1 class="page-title">Manage Customers</h1>

    <div class="nav-tabs">
        <a href="dashboard.php">Dashboard</a>
        <a href="vehicles.php">Manage Vehicles</a>
        <a href="add_vehicle.php">Add Vehicle</a>
        <a href="customers.php" class="active">Manage Customers</a>
    </div>

    <?php if ($error !== ""): ?>
        <p class="message message-error"><?php echo e($error); ?></p>
    <?php endif; ?>

    <?php if ($success !== ""): ?>
        <p class="message message-success"><?php echo e($success); ?></p>
    <?php endif; ?>

    <form method="GET" style="margin-bottom:20px;">
        <input
            type="text"
            name="q"
            placeholder="Search by name or email..."
            value="<?php echo e($searchTerm); ?>"
            style="width:100%; max-width:400px; padding:10px; border-radius:8px; border:none;"
        >
    </form>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($customersResult->num_rows === 0): ?>
                    <tr><td colspan="5" class="empty-state">No customers found.</td></tr>
                <?php else: ?>
                    <?php while ($u = $customersResult->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo e($u['name']); ?></td>
                            <td><?php echo e($u['email']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $u['status'] === 'active' ? 'free' : 'pending'; ?>">
                                    <?php echo e($u['status']); ?>
                                </span>
                            </td>
                            <td><?php echo e($u['created_at']); ?></td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('<?php echo $u['status'] === 'active' ? 'Disable' : 'Re-enable'; ?> this account?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                    <input type="hidden" name="user_id" value="<?php echo e($u['id']); ?>">
                                    <input type="hidden" name="new_status" value="<?php echo $u['status'] === 'active' ? 'disabled' : 'active'; ?>">
                                    <button type="submit" name="toggle_status" class="btn <?php echo $u['status'] === 'active' ? 'btn-danger' : 'btn-success'; ?>">
                                        <?php echo $u['status'] === 'active' ? 'Disable' : 'Enable'; ?>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Permanently delete this customer and all their records?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                    <input type="hidden" name="user_id" value="<?php echo e($u['id']); ?>">
                                    <button type="submit" name="delete_user" class="btn btn-secondary">Delete</button>
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