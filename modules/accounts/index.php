<?php
// modules/accounts/index.php

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../index.php");
    exit();
}

$user_role = isset($_SESSION['role']) ? (string)$_SESSION['role'] : '';

if (strpos($user_role, 'Admin') === false && strpos($user_role, 'Accounts') === false) {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body class="bg-light d-flex align-items-center justify-content-center" style="height: 100vh;">
        <div class="text-center p-5 bg-white shadow rounded border-top border-danger border-5" style="max-width: 500px;">
            <i class="fa-solid fa-shield-halved text-danger fa-4x mb-3"></i>
            <h2 class="fw-bold text-dark mb-2">Access Denied</h2>
            <p class="text-muted mb-4">You do not have the required permissions to view this module. If you believe this is an error, please contact the System Administrator.</p>
            <a href="/dashboard.php" class="btn btn-primary btn-lg fw-bold w-100 shadow-sm">
                <i class="fa-solid fa-arrow-left me-2"></i> Return to Dashboard
            </a>
        </div>
    </body>
    </html>';
    exit();
}

include '../../includes/db_connect.php';

$message = '';
$messageType = '';
$selected_visit_id = isset($_GET['visit_id']) ? intval($_GET['visit_id']) : null;
$patient_data = null;
$completed_visit_id = null; 

// Handle form submission (Processing Payment)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $visit_id = intval($_POST['visit_id']);
    $total_amount = floatval($_POST['total_amount']);
    $amount_paid = floatval($_POST['amount_paid']);
    
    // Determine the payment status
    $payment_status = 'Unpaid';
    if ($amount_paid >= $total_amount) {
        $payment_status = 'Paid';
    } elseif ($amount_paid > 0) {
        $payment_status = 'Partial';
    }

    $check_acct = $conn->query("SELECT id FROM accounts WHERE visit_id = $visit_id");
    
    if ($check_acct && $check_acct->num_rows > 0) {
        $sql_account = "UPDATE accounts SET total_amount = $total_amount, amount_paid = $amount_paid, payment_status = '$payment_status' WHERE visit_id = $visit_id";
    } else {
        $sql_account = "INSERT INTO accounts (visit_id, total_amount, amount_paid, payment_status) VALUES ($visit_id, $total_amount, $amount_paid, '$payment_status')";
    }

    if ($conn->query($sql_account) === TRUE) {
        if ($payment_status === 'Paid') {
            $sql_update_visit = "UPDATE visits SET current_stage = 'Cleared' WHERE id = $visit_id";
            $conn->query($sql_update_visit);
            $message = "Payment successful! Patient has been fully cleared.";
            $messageType = "success";
            $completed_visit_id = $visit_id; 
            $selected_visit_id = null;
        } else {
            $message = "Partial payment recorded. Patient still has an outstanding balance.";
            $messageType = "warning";
        }
    } else {
        $message = "Error processing payment: " . $conn->error;
        $messageType = "danger";
    }
}

// Fetch selected patient details
if ($selected_visit_id) {
    $patient_query = "SELECT p.first_name, p.last_name, v.id as visit_id 
                      FROM visits v 
                      JOIN patients p ON v.patient_id = p.id 
                      WHERE v.id = $selected_visit_id";
    $patient_result = $conn->query($patient_query);
    if ($patient_result && $patient_result->num_rows > 0) {
        $patient_data = $patient_result->fetch_assoc();
    }
}

// Fetch Billing Data
$services_data = [];
$services_res = $conn->query("SELECT * FROM hospital_services ORDER BY service_name ASC");
if ($services_res && $services_res->num_rows > 0) {
    while($row = $services_res->fetch_assoc()) $services_data[] = $row;
}

$pharma_data = [];
$pharma_res = $conn->query("SELECT item_name, unit_price FROM pharmacy_inventory ORDER BY item_name ASC");
if ($pharma_res && $pharma_res->num_rows > 0) {
    while($row = $pharma_res->fetch_assoc()) $pharma_data[] = $row;
}

// Auto-bill Logic
$auto_bill_items = [];
if ($selected_visit_id) {
    $consults = $conn->query("SELECT id FROM consultations WHERE visit_id = $selected_visit_id LIMIT 1");
    if ($consults && $consults->num_rows > 0) {
        $srv = $conn->query("SELECT cost FROM hospital_services WHERE service_name LIKE '%Consultation%' LIMIT 1");
        $cost = ($srv && $srv->num_rows > 0) ? floatval($srv->fetch_assoc()['cost']) : 0;
        $auto_bill_items[] = ['name' => 'Doctor Consultation', 'price' => $cost, 'qty' => 1];
    }
    $labs = $conn->query("SELECT test_requested FROM lab_tests WHERE visit_id = $selected_visit_id");
    while ($lab = $labs->fetch_assoc()) {
        $test_name = $conn->real_escape_string(trim($lab['test_requested']));
        $srv = $conn->query("SELECT cost FROM hospital_services WHERE service_name = '$test_name' OR service_name LIKE '%$test_name%' LIMIT 1");
        $cost = ($srv && $srv->num_rows > 0) ? floatval($srv->fetch_assoc()['cost']) : 0;
        $auto_bill_items[] = ['name' => 'Lab: ' . $lab['test_requested'], 'price' => $cost, 'qty' => 1];
    }
    $rads = $conn->query("SELECT test_requested FROM radiology_tests WHERE visit_id = $selected_visit_id");
    while ($rad = $rads->fetch_assoc()) {
        $test_name = $conn->real_escape_string(trim($rad['test_requested']));
        $srv = $conn->query("SELECT cost FROM hospital_services WHERE service_name = '$test_name' OR service_name LIKE '%$test_name%' LIMIT 1");
        $cost = ($srv && $srv->num_rows > 0) ? floatval($srv->fetch_assoc()['cost']) : 0;
        $auto_bill_items[] = ['name' => 'Rad: ' . $rad['test_requested'], 'price' => $cost, 'qty' => 1];
    }
    $meds = $conn->query("SELECT medication_name, dose FROM prescriptions WHERE visit_id = $selected_visit_id AND dispensed = 1");
    while ($med = $meds->fetch_assoc()) {
        $med_name = $conn->real_escape_string(trim($med['medication_name']));
        $inv = $conn->query("SELECT unit_price FROM pharmacy_inventory WHERE item_name = '$med_name' OR generic_name = '$med_name' LIMIT 1");
        $cost = ($inv && $inv->num_rows > 0) ? floatval($inv->fetch_assoc()['unit_price']) : 0;
        $auto_bill_items[] = ['name' => 'Rx: ' . $med['medication_name'], 'price' => $cost, 'qty' => 1];
    }
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Billing & Accounts</h1>
</div>

<?php if ($completed_visit_id): ?>
    <div class="alert alert-success d-flex justify-content-between align-items-center shadow-sm" role="alert">
        <div><i class="fa-solid fa-check-circle me-2"></i> Payment successful! Patient has been cleared.</div>
        <a href="receipt.php?visit_id=<?php echo $completed_visit_id; ?>" target="_blank" class="btn btn-dark btn-sm fw-bold"><i class="fa-solid fa-file-pdf me-1"></i> Download Receipt</a>
    </div>
    <script>window.addEventListener('load', () => window.open('receipt.php?visit_id=<?php echo $completed_visit_id; ?>', '_blank'));</script>
<?php elseif ($message != ''): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show shadow-sm" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <button class="nav-link active fw-bold text-dark" data-bs-toggle="tab" data-bs-target="#activeQueue"><i class="fa-solid fa-list-check me-2 text-primary"></i> Active Queue</button>
    </li>
    <li class="nav-item">
        <button class="nav-link fw-bold text-dark" data-bs-toggle="tab" data-bs-target="#pendingBills"><i class="fa-solid fa-clock-rotate-left me-2 text-danger"></i> Pending Bills</button>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="activeQueue">
        <div class="row">
            <div class="col-md-3">
                <div class="card shadow-sm h-100 border-primary">
                    <div class="card-header bg-primary text-white fw-bold"><i class="fa-solid fa-file-invoice-dollar me-2"></i> Awaiting Checkout</div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php
                            $queue_query = "SELECT v.id AS visit_id, p.first_name, p.last_name, v.visit_date FROM visits v JOIN patients p ON v.patient_id = p.id WHERE v.current_stage = 'Accounts' ORDER BY v.visit_date ASC";
                            $queue_result = $conn->query($queue_query);
                            while ($row = $queue_result->fetch_assoc()) {
                                $active = ($selected_visit_id == $row['visit_id']) ? 'active bg-primary border-primary' : '';
                                echo "<a href='index.php?visit_id={$row['visit_id']}' class='list-group-item list-group-item-action {$active}'><i class='fa-solid fa-wallet me-2'></i>{$row['first_name']} {$row['last_name']}</a>";
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-9">
                <?php if ($selected_visit_id): ?>
                    <div class="card shadow-sm border-dark">
                        <div class="card-header bg-dark text-white fw-bold">Invoice Details (Visit #<?php echo $selected_visit_id; ?>)</div>
                        <div class="card-body">
                            <h5 class="fw-bold mb-4">Patient: <span class="text-primary"><?php echo $patient_data['first_name'] . ' ' . $patient_data['last_name']; ?></span></h5>
                            <form method="POST" action="index.php">
                                <input type="hidden" name="visit_id" value="<?php echo $selected_visit_id; ?>">
                                <div class="bg-light p-3 rounded border mb-4" id="invoice_rows">
                                    <?php foreach($auto_bill_items as $item): ?>
                                        <div class="row gx-1 mb-2 align-items-center invoice-row">
                                            <div class="col-4"><input type="text" class="form-control form-control-sm" name="item_name[]" value="<?php echo htmlspecialchars($item['name']); ?>" readonly></div>
                                            <div class="col-2"><input type="number" class="form-control form-control-sm qty-input" name="item_qty[]" value="<?php echo $item['qty']; ?>" readonly></div>
                                            <div class="col-2"><input type="number" step="0.01" class="form-control form-control-sm unit-price" name="unit_price[]" value="<?php echo number_format($item['price'], 2, '.', ''); ?>" readonly></div>
                                            <div class="col-3"><input type="number" step="0.01" class="form-control form-control-sm row-price" value="<?php echo number_format($item['price'] * $item['qty'], 2, '.', ''); ?>" readonly></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="row align-items-center mb-3">
                                    <div class="col-md-6 text-end fw-bold fs-5">Total Bill Amount (KES):</div>
                                    <div class="col-md-6"><input type="number" step="0.01" class="form-control form-control-lg border-primary fw-bold" id="grand_total" name="total_amount" readonly></div>
                                </div>
                                <div class="row align-items-center mb-4">
                                    <div class="col-md-6 text-end fw-bold fs-5">Amount Received (KES):</div>
                                    <div class="col-md-6"><input type="number" step="0.01" class="form-control form-control-lg border-success fw-bold" name="amount_paid" required></div>
                                </div>
                                <button type="submit" class="btn btn-success btn-lg w-100"><i class="fa-solid fa-check-circle me-2"></i> Process Payment</button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card p-5 text-center text-muted bg-light border-0"><h5>Select a patient from the queue to process their bill.</h5></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="pendingBills">
        <div class="card shadow-sm border-danger">
            <div class="card-header bg-danger text-white fw-bold"><i class="fa-solid fa-clock-rotate-left me-2"></i> Outstanding Balances</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Patient</th>
                            <th>Visit ID</th>
                            <th>Total Bill</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $pending_sql = "SELECT a.total_amount, a.amount_paid, a.payment_status, p.first_name, p.last_name, v.id as visit_id 
                                        FROM accounts a 
                                        JOIN visits v ON a.visit_id = v.id 
                                        JOIN patients p ON v.patient_id = p.id 
                                        WHERE a.payment_status IN ('Unpaid', 'Partial') 
                                        ORDER BY v.visit_date DESC";
                        $pending_res = $conn->query($pending_sql);
                        if ($pending_res && $pending_res->num_rows > 0) {
                            while ($bill = $pending_res->fetch_assoc()) {
                                $balance = $bill['total_amount'] - $bill['amount_paid'];
                                echo "<tr>
                                    <td>{$bill['first_name']} {$bill['last_name']}</td>
                                    <td>#{$bill['visit_id']}</td>
                                    <td>KES " . number_format($bill['total_amount'], 2) . "</td>
                                    <td>KES " . number_format($bill['amount_paid'], 2) . "</td>
                                    <td class='fw-bold text-danger'>KES " . number_format($balance, 2) . "</td>
                                    <td><span class='badge bg-warning text-dark'>{$bill['payment_status']}</span></td>
                                    <td><a href='index.php?visit_id={$bill['visit_id']}' class='btn btn-sm btn-outline-dark'>Settle</a></td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='7' class='text-center py-3 text-muted'>No pending bills found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    let total = 0;
    document.querySelectorAll('.row-price').forEach(input => {
        total += parseFloat(input.value) || 0;
    });
    document.getElementById('grand_total').value = total.toFixed(2);
});
</script>

<?php include '../../includes/footer.php'; ?>