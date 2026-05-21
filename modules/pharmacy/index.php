<?php
// modules/pharmacy/index.php

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../index.php");
    exit();
}
if (strpos($_SESSION['role'], 'Admin') === false && strpos($_SESSION['role'], 'Pharmacy') === false) {
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
$completed_sale_visit_id = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // ACTION 1: Dispense from Doctor's Queue
    if (isset($_POST['action']) && $_POST['action'] == 'dispense') {
        $visit_id = intval($_POST['visit_id']);
        
        $sql_update_rx = "UPDATE prescriptions SET dispensed = 1 WHERE visit_id = $visit_id";

        if ($conn->query($sql_update_rx) === TRUE) {
            $sql_update_visit = "UPDATE visits SET current_stage = 'Accounts' WHERE id = $visit_id";
            if ($conn->query($sql_update_visit) === TRUE) {
                $message = "Medication dispensed successfully. Patient sent to Accounts for billing.";
                $messageType = "success";
                $selected_visit_id = null; 
            } else {
                $message = "Medication dispensed, but failed to route patient: " . $conn->error;
                $messageType = "warning";
            }
        } else {
            $message = "Error dispensing medication: " . $conn->error;
            $messageType = "danger";
        }
    }
    
    // ACTION 2: Walk-In POS Sale (Now Handles Meds AND Services)
    elseif (isset($_POST['action']) && $_POST['action'] == 'pos_sale') {
        if (isset($_POST['pos_item']) && is_array($_POST['pos_item'])) {
            $items = $_POST['pos_item'];
            $quantities = $_POST['pos_qty'];
            $prices = $_POST['pos_price'];
            $item_types = $_POST['pos_type']; // Hidden field tracking if it's 'Med' or 'Service'
            $amount_paid = floatval($_POST['amount_paid']);
            
            $total_amount = 0;
            foreach ($prices as $price) {
                $total_amount += floatval($price);
            }

            // 1. Create or Find Walk-in Patient
            $walk_in_query = "SELECT id FROM patients WHERE first_name = 'Walk-in' AND last_name = 'Client' LIMIT 1";
            $walk_in_result = $conn->query($walk_in_query);
            
            if ($walk_in_result && $walk_in_result->num_rows > 0) {
                $patient_id = $walk_in_result->fetch_assoc()['id'];
            } else {
                $conn->query("INSERT INTO patients (first_name, last_name, gender) VALUES ('Walk-in', 'Client', 'Other')");
                $patient_id = $conn->insert_id;
            }

            // 2. Create Visit
            $conn->query("INSERT INTO visits (patient_id, current_stage) VALUES ($patient_id, 'Cleared')");
            $visit_id = $conn->insert_id;

            // 3. Process Each Item (Routing to appropriate tables based on Type)
            for ($i = 0; $i < count($items); $i++) {
                $name = $conn->real_escape_string($items[$i]);
                $qty = intval($quantities[$i]);
                $type = $conn->real_escape_string($item_types[$i]);
                
                if (!empty($name) && $qty > 0) {
                    
                    if ($type === 'Service') {
                        // Log as a procedure/service applied to this visit so it shows on the receipt
                        for ($q = 0; $q < $qty; $q++) {
                            $conn->query("INSERT INTO procedures (visit_id, procedure_requested, status) VALUES ($visit_id, '$name', 'Completed')");
                        }
                    } else {
                        // Log as a dispensed medication and deduct inventory
                        $conn->query("UPDATE pharmacy_inventory SET stock_quantity = stock_quantity - $qty WHERE item_name = '$name'");
                        $conn->query("INSERT INTO prescriptions (visit_id, medication_name, dose, frequency, route, duration, dispensed) 
                                      VALUES ($visit_id, '$name', '$qty', 'OTC', 'PO', 'N/A', 1)");
                    }
                }
            }

            // 4. Finalize Billing Account
            $conn->query("INSERT INTO accounts (visit_id, total_amount, amount_paid, payment_status) 
                          VALUES ($visit_id, $total_amount, $amount_paid, 'Paid')");

            $message = "Walk-in POS transaction completed successfully.";
            $messageType = "success";
            $completed_sale_visit_id = $visit_id;
        }
    }
}

// Fetch selected patient details
if ($selected_visit_id) {
    $patient_query = "SELECT p.first_name, p.last_name, p.gender, v.id as visit_id 
                      FROM visits v 
                      JOIN patients p ON v.patient_id = p.id 
                      WHERE v.id = $selected_visit_id";
    $patient_result = $conn->query($patient_query);
    if ($patient_result && $patient_result->num_rows > 0) {
        $patient_data = $patient_result->fetch_assoc();
    }
}

// Fetch Master List for POS (Combined Inventory + Services)
$master_pos_list = [];

// 1. Fetch Medications
$inv_query = "SELECT item_name, generic_name, unit_price, stock_quantity FROM pharmacy_inventory WHERE stock_quantity > 0 ORDER BY item_name ASC";
$inv_result = $conn->query($inv_query);
if ($inv_result && $inv_result->num_rows > 0) {
    while($row = $inv_result->fetch_assoc()) {
        $master_pos_list[] = [
            'name' => $row['item_name'],
            'search_tags' => "Gen: " . (!empty($row['generic_name']) ? $row['generic_name'] : 'N/A'),
            'price' => floatval($row['unit_price']),
            'stock' => intval($row['stock_quantity']),
            'type' => 'Med'
        ];
    }
}

// 2. Fetch Hospital Services
$srv_query = "SELECT service_name, cost FROM hospital_services ORDER BY service_name ASC";
$srv_result = $conn->query($srv_query);
if ($srv_result && $srv_result->num_rows > 0) {
    while($row = $srv_result->fetch_assoc()) {
        $master_pos_list[] = [
            'name' => $row['service_name'],
            'search_tags' => "Hospital Service",
            'price' => floatval($row['cost']),
            'stock' => '∞', // Services don't run out of stock
            'type' => 'Service'
        ];
    }
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Pharmacy & POS</h1>
</div>

<?php if ($completed_sale_visit_id): ?>
    <div class="alert alert-success d-flex justify-content-between align-items-center shadow-sm" role="alert">
        <div>
            <i class="fa-solid fa-check-circle me-2"></i> Sale completed successfully. Receipt generated.
        </div>
        <a href="../accounts/receipt.php?visit_id=<?php echo $completed_sale_visit_id; ?>" target="_blank" class="btn btn-dark btn-sm fw-bold">
            <i class="fa-solid fa-file-pdf me-1"></i> Download Receipt
        </a>
    </div>
<?php elseif ($message != ''): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<ul class="nav nav-tabs mb-4" id="pharmacyTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link <?php echo ($selected_visit_id || (!isset($_POST['action']))) ? 'active' : ''; ?> fw-bold text-dark" id="queue-tab" data-bs-toggle="tab" data-bs-target="#queue" type="button" role="tab">
        <i class="fa-solid fa-list-check me-2 text-info"></i> Prescription Queue
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?php echo (isset($_POST['action']) && $_POST['action'] == 'pos_sale') ? 'active' : ''; ?> fw-bold text-dark" id="pos-tab" data-bs-toggle="tab" data-bs-target="#pos" type="button" role="tab">
        <i class="fa-solid fa-cash-register me-2 text-success"></i> Walk-In POS
    </button>
  </li>
</ul>

<div class="tab-content" id="pharmacyTabsContent">
    
    <div class="tab-pane fade <?php echo ($selected_visit_id || (!isset($_POST['action']))) ? 'show active' : ''; ?>" id="queue" role="tabpanel">
        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-info text-dark fw-bold">
                        <i class="fa-solid fa-pills me-2"></i> Waiting
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php
                            $queue_query = "SELECT v.id AS visit_id, p.first_name, p.last_name, v.visit_date 
                                            FROM visits v 
                                            JOIN patients p ON v.patient_id = p.id 
                                            WHERE v.current_stage = 'Pharmacy' 
                                            ORDER BY v.visit_date ASC";
                            $queue_result = $conn->query($queue_query);

                            if ($queue_result && $queue_result->num_rows > 0) {
                                while ($row = $queue_result->fetch_assoc()) {
                                    $active_class = ($selected_visit_id == $row['visit_id']) ? 'active bg-info border-info text-dark' : '';
                                    echo "<a href='index.php?visit_id={$row['visit_id']}' class='list-group-item list-group-item-action {$active_class}'>";
                                    echo "<h6 class='mb-1 fw-bold'><i class='fa-solid fa-prescription-bottle-medical me-2'></i>{$row['first_name']} {$row['last_name']}</h6>";
                                    echo "<small>Wait: " . date('H:i', strtotime($row['visit_date'])) . "</small>";
                                    echo "</a>";
                                }
                            } else {
                                echo "<div class='p-4 text-center text-muted'>No prescriptions waiting.</div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-9 mb-4">
                <?php if (!$selected_visit_id): ?>
                    <div class="card shadow-sm h-100 d-flex justify-content-center align-items-center bg-light">
                        <div class="text-center text-muted py-5">
                            <i class="fa-solid fa-prescription fa-4x mb-3 text-secondary" style="opacity: 0.5;"></i>
                            <h5>Select a patient to view and dispense their prescription.</h5>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card shadow-sm border-info">
                        <div class="card-header bg-white fw-bold">Prescription Details</div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                                <div>
                                    <h5 class="text-primary fw-bold mb-0"><?php echo htmlspecialchars($patient_data['first_name'] . ' ' . $patient_data['last_name']); ?></h5>
                                    <small class="text-muted">Gender: <?php echo htmlspecialchars($patient_data['gender']); ?> | Visit ID: #<?php echo htmlspecialchars($patient_data['visit_id']); ?></small>
                                </div>
                            </div>

                            <h6 class="fw-bold mb-3">Medications Requested:</h6>
                            <div class="table-responsive mb-4">
                                <table class="table table-bordered table-striped align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Medication Name</th>
                                            <th>Dose</th>
                                            <th>Frequency</th>
                                            <th>Route</th>
                                            <th>Duration</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $rx_query = "SELECT * FROM prescriptions WHERE visit_id = $selected_visit_id";
                                        $rx_result = $conn->query($rx_query);
                                        
                                        if ($rx_result && $rx_result->num_rows > 0) {
                                            while ($rx = $rx_result->fetch_assoc()) {
                                                $status_badge = $rx['dispensed'] ? '<span class="badge bg-success">Dispensed</span>' : '<span class="badge bg-warning text-dark">Pending</span>';
                                                echo "<tr>";
                                                echo "<td class='fw-bold text-primary'>{$rx['medication_name']}</td>";
                                                echo "<td>{$rx['dose']}</td>";
                                                echo "<td>{$rx['frequency']}</td>";
                                                echo "<td>{$rx['route']}</td>";
                                                echo "<td>{$rx['duration']}</td>";
                                                echo "<td>{$status_badge}</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='6' class='text-center text-muted'>No medications found for this visit.</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>

                            <form method="POST" action="index.php">
                                <input type="hidden" name="action" value="dispense">
                                <input type="hidden" name="visit_id" value="<?php echo $selected_visit_id; ?>">
                                <div class="d-flex justify-content-end mt-3">
                                    <button type="submit" class="btn btn-info text-dark fw-bold btn-lg shadow-sm">
                                        <i class="fa-solid fa-check-double me-2"></i> Dispense & Send to Accounts
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="tab-pane fade <?php echo (isset($_POST['action']) && $_POST['action'] == 'pos_sale') ? 'show active' : ''; ?>" id="pos" role="tabpanel">
        <div class="card shadow-sm border-success">
            <div class="card-header bg-success text-white fw-bold">
                <i class="fa-solid fa-cash-register me-2"></i> Over-the-Counter Sale (Meds & Services)
            </div>
            <div class="card-body">
                <form method="POST" action="index.php">
                    <input type="hidden" name="action" value="pos_sale">
                    
                    <div class="bg-light p-3 rounded mb-4 border" id="pos_items_container">
                        <div class="row fw-bold text-muted mb-2 px-2">
                            <div class="col-md-5">Search Medicine or Service</div>
                            <div class="col-md-2">Qty</div>
                            <div class="col-md-2 text-center">Type</div>
                            <div class="col-md-2">Price (KES)</div>
                            <div class="col-md-1"></div>
                        </div>
                        
                        <div id="pos_rows">
                            </div>
                        
                        <button type="button" class="btn btn-sm btn-outline-success mt-3" onclick="addPosRow()"><i class="fa-solid fa-plus me-1"></i> Add Row</button>
                        <button type="button" class="btn btn-sm btn-outline-danger mt-3 ms-2" onclick="clearPosRows()"><i class="fa-solid fa-trash-can me-1"></i> Clear Cart</button>
                    </div>

                    <div class="row justify-content-end">
                        <div class="col-md-5">
                            <div class="p-3 bg-light rounded border">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="fw-bold fs-5 text-muted">Total Bill:</span>
                                    <span class="fw-bold fs-5 text-dark">KES <span id="pos_total_display">0.00</span></span>
                                </div>
                                <hr>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Amount Received (KES) *</label>
                                    <input type="number" step="0.01" class="form-control border-success text-success fw-bold form-control-lg" name="amount_paid" required placeholder="0.00">
                                </div>
                                <button type="submit" class="btn btn-success btn-lg w-100"><i class="fa-solid fa-money-bill-wave me-2"></i> Complete Transaction</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<datalist id="master_pos_list">
    <?php foreach ($master_pos_list as $item): ?>
        <option value="<?php echo htmlspecialchars($item['name']); ?>">
            [<?php echo $item['type']; ?>] <?php echo htmlspecialchars($item['search_tags']); ?> | KES <?php echo number_format($item['price'], 2); ?> (Stock: <?php echo $item['stock']; ?>)
        </option>
    <?php endforeach; ?>
</datalist>

<script>
// Load combined PHP array into JS
const masterData = <?php echo json_encode($master_pos_list); ?>;
const itemDictionary = {};

// Map data for fast lookup when user types
masterData.forEach(item => {
    itemDictionary[item.name] = {
        price: parseFloat(item.price),
        type: item.type
    };
});

document.addEventListener("DOMContentLoaded", function() {
    addPosRow();
});

function addPosRow() {
    const container = document.getElementById('pos_rows');
    const row = document.createElement('div');
    row.className = 'row gx-2 mb-2 align-items-center pos-row';
    row.innerHTML = `
        <div class="col-md-5">
            <input type="text" class="form-control med-input" list="master_pos_list" name="pos_item[]" placeholder="Search drug or service..." autocomplete="off" required oninput="updateRowDetails(this)">
        </div>
        <div class="col-md-2">
            <input type="number" class="form-control qty-input" name="pos_qty[]" placeholder="Qty" required oninput="updateRowDetails(this)">
        </div>
        <div class="col-md-2 text-center">
            <span class="badge bg-secondary type-badge w-100 py-2">--</span>
            <input type="hidden" class="type-input" name="pos_type[]" value="">
        </div>
        <div class="col-md-2">
            <input type="number" step="0.01" class="form-control pos-price bg-white fw-bold text-success" name="pos_price[]" placeholder="0.00" readonly required>
        </div>
        <div class="col-md-1 text-end">
            <button type="button" class="btn btn-danger w-100" onclick="removePosRow(this)">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    `;
    container.appendChild(row);
}

function removePosRow(button) {
    button.parentElement.parentElement.remove();
    calculatePosTotal();
}

function clearPosRows() {
    if(confirm('Are you sure you want to clear the cart?')) {
        document.getElementById('pos_rows').innerHTML = '';
        calculatePosTotal();
        addPosRow();
    }
}

function updateRowDetails(element) {
    let row = element.closest('.pos-row');
    let itemInput = row.querySelector('.med-input');
    let qtyInput = row.querySelector('.qty-input');
    let priceInput = row.querySelector('.pos-price');
    let typeBadge = row.querySelector('.type-badge');
    let typeHidden = row.querySelector('.type-input');

    let itemName = itemInput.value;
    let qty = parseFloat(qtyInput.value);
    if (isNaN(qty)) qty = 0;

    // Check if the typed name matches our dictionary exactly
    if (itemDictionary[itemName]) {
        let details = itemDictionary[itemName];
        
        // Update Type Badge UI
        typeHidden.value = details.type;
        if(details.type === 'Service') {
            typeBadge.className = 'badge bg-primary type-badge w-100 py-2';
            typeBadge.innerText = 'Service';
        } else {
            typeBadge.className = 'badge bg-info text-dark type-badge w-100 py-2';
            typeBadge.innerText = 'Medicine';
        }
        
        // Update Price
        priceInput.value = (details.price * qty).toFixed(2);
    } else {
        // Not a recognized item yet
        typeBadge.className = 'badge bg-secondary type-badge w-100 py-2';
        typeBadge.innerText = '--';
        typeHidden.value = '';
        priceInput.value = '';
    }

    calculatePosTotal();
}

function calculatePosTotal() {
    const priceInputs = document.querySelectorAll('.pos-price');
    let total = 0;
    
    priceInputs.forEach(input => {
        const val = parseFloat(input.value);
        if (!isNaN(val)) {
            total += val;
        }
    });
    
    document.getElementById('pos_total_display').innerText = total.toFixed(2);
}
</script>

<?php
include '../../includes/footer.php';
?>