<?php
session_start();
include("../config/db.php");
include("../includes/auth.php");

require_admin(1);

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = "Invalid form submission. Please refresh and try again.";
    } else {

        $userId = (int) ($_POST['user_id'] ?? 0);
        $slotId = (int) ($_POST['slot_id'] ?? 0);
        $vehicleNumber = trim($_POST['vehicle_number'] ?? '');

        if ($userId <= 0 || $slotId <= 0 || $vehicleNumber === '') {
            $error = "Please fill in all fields.";
        } else {

            // Confirm the slot is still free (avoid double-booking race condition)
            $slotCheck = $conn->prepare("SELECT status FROM parking_slots WHERE id = ?");
            $slotCheck->bind_param("i", $slotId);
            $slotCheck->execute();
            $slotRow = $slotCheck->get_result()->fetch_assoc();
            $slotCheck->close();

            if (!$slotRow) {
                $error = "Selected slot does not exist.";
            } elseif ($slotRow['status'] !== 'free') {
                $error = "That slot is already occupied. Please choose another.";
            } else {

                // Confirm user exists, is a customer, and is active (not disabled)
                $userCheck = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'customer' AND status = 'active'");
                $userCheck->bind_param("i", $userId);
                $userCheck->execute();
                $userExists = $userCheck->get_result()->num_rows > 0;
                $userCheck->close();

                if (!$userExists) {
                    $error = "Selected customer does not exist.";
                } else {

                    $conn->begin_transaction();
                    try {
                        $stmt = $conn->prepare("
                            INSERT INTO vehicles (user_id, slot_id, vehicle_number, entry_time, status)
                            VALUES (?, ?, ?, NOW(), 'parked')
                        ");
                        $stmt->bind_param("iis", $userId, $slotId, $vehicleNumber);
                        $stmt->execute();
                        $vehicleId = $conn->insert_id;
                        $stmt->close();

                        $updateSlot = $conn->prepare("UPDATE parking_slots SET status = 'occupied' WHERE id = ?");
                        $updateSlot->bind_param("i", $slotId);
                        $updateSlot->execute();
                        $updateSlot->close();

                        // Create the pending payment record for this visit (flat rate, LKR)
                        $createPayment = $conn->prepare("
                            INSERT INTO payments (vehicle_id, user_id, amount, status)
                            VALUES (?, ?, 500.00, 'pending')
                        ");
                        $createPayment->bind_param("ii", $vehicleId, $userId);
                        $createPayment->execute();
                        $createPayment->close();

                        $conn->commit();
                        $success = "Vehicle added successfully and assigned to slot.";
                    } catch (Exception $e) {
                        $conn->rollback();
                        $error = "Failed to add vehicle. Please try again.";
                    }
                }
            }
        }
    }
}

// Data for the slot dropdown (customer list is fetched separately for the JS search box, further down)
$freeSlots = $conn->query("SELECT id, slot_number FROM parking_slots WHERE status = 'free' ORDER BY slot_number");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Vehicle - Admin</title>
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

    <h1 class="page-title">Add Vehicle</h1>

    <div class="nav-tabs">
        <a href="dashboard.php">Dashboard</a>
        <a href="vehicles.php">Manage Vehicles</a>
        <a href="add_vehicle.php" class="active">Add Vehicle</a>
        <a href="customers.php">Manage Customers</a>
    </div>

    <?php if ($error !== ""): ?>
        <p class="message message-error"><?php echo e($error); ?></p>
    <?php endif; ?>

    <?php if ($success !== ""): ?>
        <p class="message message-success"><?php echo e($success); ?> <a href="vehicles.php" style="color:#86efac;">View vehicles &rarr;</a></p>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST">

            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">

            <label for="customer_search">Customer</label>
            <input
                type="text"
                id="customer_search"
                placeholder="Type a name or email to search..."
                autocomplete="off"
            >
            <div id="customer_results" class="search-results"></div>

            <!-- Hidden field that actually gets submitted -->
            <input type="hidden" name="user_id" id="user_id" required>
            <small class="hint" id="selected_customer_label"></small>

            <label for="vehicle_number">Vehicle Number</label>
            <input type="text" name="vehicle_number" id="vehicle_number" placeholder="e.g. ABC-1234" required>

            <label for="slot_id">Parking Slot (free only)</label>
            <select name="slot_id" id="slot_id" required>
                <option value="">-- Select slot --</option>
                <?php if ($freeSlots->num_rows === 0): ?>
                    <option value="" disabled>No free slots available</option>
                <?php else: ?>
                    <?php while ($s = $freeSlots->fetch_assoc()): ?>
                        <option value="<?php echo e($s['id']); ?>"><?php echo e($s['slot_number']); ?></option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>

            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:18px; padding:12px;">
                Add Vehicle
            </button>

        </form>
    </div>

</div>

    <script>
        // Customer list passed from PHP, used for the simple search-as-you-type below.
        const customers = [
            <?php
            $customersForJs = $conn->query("SELECT id, name, email FROM users WHERE role = 'customer' AND status = 'active' ORDER BY name");
            while ($c = $customersForJs->fetch_assoc()):
            ?>
            { id: <?php echo (int) $c['id']; ?>, name: <?php echo json_encode($c['name']); ?>, email: <?php echo json_encode($c['email']); ?> },
            <?php endwhile; ?>
        ];

        const searchInput = document.getElementById('customer_search');
        const resultsBox = document.getElementById('customer_results');
        const hiddenUserId = document.getElementById('user_id');
        const selectedLabel = document.getElementById('selected_customer_label');

        searchInput.addEventListener('input', function () {
            const query = this.value.trim().toLowerCase();

            // Clear the actual selection whenever the user edits the search text,
            // so they can't accidentally submit a stale customer after retyping.
            hiddenUserId.value = '';

            resultsBox.innerHTML = '';

            if (query === '') {
                resultsBox.style.display = 'none';
                return;
            }

            const matches = customers.filter(function (c) {
                return c.name.toLowerCase().includes(query) || c.email.toLowerCase().includes(query);
            }).slice(0, 8); // cap results so the list stays short and usable

            if (matches.length === 0) {
                resultsBox.innerHTML = '<div class="search-result-item search-no-match">No matching customers</div>';
                resultsBox.style.display = 'block';
                return;
            }

            matches.forEach(function (c) {
                const item = document.createElement('div');
                item.className = 'search-result-item';
                item.textContent = c.name + ' (' + c.email + ')';
                item.addEventListener('click', function () {
                    hiddenUserId.value = c.id;
                    searchInput.value = c.name;
                    selectedLabel.textContent = 'Selected: ' + c.name + ' (' + c.email + ')';
                    resultsBox.innerHTML = '';
                    resultsBox.style.display = 'none';
                });
                resultsBox.appendChild(item);
            });

            resultsBox.style.display = 'block';
        });

        // Hide the results list if the admin clicks elsewhere on the page
        document.addEventListener('click', function (e) {
            if (e.target !== searchInput) {
                resultsBox.style.display = 'none';
            }
        });

        // Stop accidental form submit if no customer was actually selected
        document.querySelector('form').addEventListener('submit', function (e) {
            if (!hiddenUserId.value) {
                e.preventDefault();
                alert('Please select a customer from the search results.');
                searchInput.focus();
            }
        });
    </script>

</body>
</html>