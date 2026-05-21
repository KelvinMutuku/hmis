<?php
// modules/accounts/receipt.php

// Turn on Error Reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    exit('Access Denied: Please log in.');
}

// Safe Database Connection Check
$db_path = '../../includes/db_connect.php';
if (!file_exists($db_path)) {
    exit('<div style="padding:20px; color:red; font-family:sans-serif;"><b>Critical Error:</b> Cannot find the database connection. Please make sure this file is saved inside the <b>modules/accounts/</b> folder!</div>');
}
include $db_path;

$visit_id = isset($_GET['visit_id']) ? intval($_GET['visit_id']) : 0;

if ($visit_id <= 0) {
    exit('Error: Invalid Visit ID provided.');
}

// 1. Fetch Patient and exact Payment Date from the database
$query = "SELECT p.first_name, p.last_name, p.phone, v.visit_date, a.total_amount, a.amount_paid, a.payment_status, a.updated_at AS payment_date 
          FROM visits v 
          JOIN patients p ON v.patient_id = p.id 
          JOIN accounts a ON a.visit_id = v.id 
          WHERE v.id = $visit_id LIMIT 1";
$result = $conn->query($query);

if (!$result || $result->num_rows === 0) {
    exit('Error: Invoice not found or payment has not been processed yet.');
}

$data = $result->fetch_assoc();

// 2. Safely Reconstruct the Billable Items
$bill_items = [];

// Consultations
$consults = $conn->query("SELECT id FROM consultations WHERE visit_id = $visit_id LIMIT 1");
if ($consults && $consults->num_rows > 0) {
    $srv = $conn->query("SELECT cost FROM hospital_services WHERE service_name LIKE '%Consultation%' LIMIT 1");
    $cost = 0;
    if ($srv && $srv->num_rows > 0) {
        $row = $srv->fetch_assoc();
        $cost = floatval($row['cost']);
    }
    $bill_items[] = ['name' => 'Doctor Consultation', 'price' => $cost, 'qty' => 1];
}

// Labs
$labs = $conn->query("SELECT test_requested FROM lab_tests WHERE visit_id = $visit_id");
if ($labs && $labs->num_rows > 0) {
    while ($lab = $labs->fetch_assoc()) {
        $test_name = $conn->real_escape_string(trim($lab['test_requested']));
        $srv = $conn->query("SELECT cost FROM hospital_services WHERE service_name = '$test_name' OR service_name LIKE '%$test_name%' LIMIT 1");
        $cost = 0;
        if ($srv && $srv->num_rows > 0) {
            $row = $srv->fetch_assoc();
            $cost = floatval($row['cost']);
        }
        $bill_items[] = ['name' => 'Lab: ' . $lab['test_requested'], 'price' => $cost, 'qty' => 1];
    }
}

// Radiology
$rads = $conn->query("SELECT test_requested FROM radiology_tests WHERE visit_id = $visit_id");
if ($rads && $rads->num_rows > 0) {
    while ($rad = $rads->fetch_assoc()) {
        $test_name = $conn->real_escape_string(trim($rad['test_requested']));
        $srv = $conn->query("SELECT cost FROM hospital_services WHERE service_name = '$test_name' OR service_name LIKE '%$test_name%' LIMIT 1");
        $cost = 0;
        if ($srv && $srv->num_rows > 0) {
            $row = $srv->fetch_assoc();
            $cost = floatval($row['cost']);
        }
        $bill_items[] = ['name' => 'Radiology: ' . $rad['test_requested'], 'price' => $cost, 'qty' => 1];
    }
}

// Procedures
$procs = $conn->query("SELECT procedure_requested FROM procedures WHERE visit_id = $visit_id");
if ($procs && $procs->num_rows > 0) {
    while ($proc = $procs->fetch_assoc()) {
        $proc_name = $conn->real_escape_string(trim($proc['procedure_requested']));
        $srv = $conn->query("SELECT cost FROM hospital_services WHERE service_name = '$proc_name' OR service_name LIKE '%$proc_name%' LIMIT 1");
        $cost = 0;
        if ($srv && $srv->num_rows > 0) {
            $row = $srv->fetch_assoc();
            $cost = floatval($row['cost']);
        }
        $bill_items[] = ['name' => 'Procedure: ' . $proc['procedure_requested'], 'price' => $cost, 'qty' => 1];
    }
}

// Prescriptions / Pharmacy
$meds = $conn->query("SELECT medication_name, dose FROM prescriptions WHERE visit_id = $visit_id AND dispensed = 1");
if ($meds && $meds->num_rows > 0) {
    while ($med = $meds->fetch_assoc()) {
        $med_name = $conn->real_escape_string(trim($med['medication_name']));
        $inv = $conn->query("SELECT unit_price FROM pharmacy_inventory WHERE item_name = '$med_name' OR generic_name = '$med_name' LIMIT 1");
        $cost = 0;
        if ($inv && $inv->num_rows > 0) {
            $row = $inv->fetch_assoc();
            $cost = floatval($row['unit_price']);
        }
        
        $qty = 1;
        if (isset($data['first_name']) && $data['first_name'] == 'Walk-in' && $data['last_name'] == 'Client') {
            $qty = intval($med['dose']);
        }
        
        $bill_items[] = ['name' => 'Rx: ' . $med['medication_name'], 'price' => $cost, 'qty' => $qty];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?php echo $visit_id; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; color: #333; }
        .receipt-container { max-width: 800px; margin: 40px auto; background: #fff; padding: 40px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 8px; }
        .hospital-header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #158a43; padding-bottom: 20px; }
        .hospital-header h2 { color: #158a43; font-weight: bold; margin-bottom: 5px; }
        @media print {
            body { background: white; }
            .receipt-container { box-shadow: none; margin: 0; padding: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="receipt-container">
    <div class="text-end mb-3 no-print">
        <button onclick="window.print()" class="btn btn-success fw-bold shadow-sm"><i class="fa-solid fa-print me-2"></i> Print Document</button>
        <button onclick="window.close()" class="btn btn-outline-secondary">Close</button>
    </div>

    <div class="hospital-header">
        <img src="../../assets/images/logo.jpg" style="max-height: 150px; margin-bottom: 10px;" alt="Logo">
        <h2>Gem Cherith Healthcare Centre</h2>
        <p class="mb-0 fw-bold small">Diani House, Ongata Rongai, off Gataka Road, Nairobi</p>
        <p class="mb-0 small text-muted">Tel: 0736818848 / 0725044797 / 0112838806 / 0110807036</p>
        <p class="mb-2 small text-muted">Email: gemcherith@gmail.com</p>
        <hr style="width: 50%; margin: 10px auto; border-top: 1px dashed #158a43;">
        <p class="mb-0 text-uppercase fw-bold" style="color: #158a43;">Official Payment Receipt</p>
    </div>

    <div class="row mb-4">
        <div class="col-sm-6">
            <h6 class="text-muted mb-1">Billed To:</h6>
            <h5 class="fw-bold"><?php echo htmlspecialchars($data['first_name'] . ' ' . $data['last_name']); ?></h5>
            <?php if (!empty($data['phone'])): ?>
                <div><i class="fa-solid fa-phone me-1 text-muted"></i> <?php echo htmlspecialchars($data['phone']); ?></div>
            <?php endif; ?>
        </div>
        <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
            <h6 class="text-muted mb-1">Invoice Details:</h6>
            <div><strong>Invoice No:</strong> #INV-<?php echo str_pad($visit_id, 5, '0', STR_PAD_LEFT); ?></div>
            <div><strong>Visit Date:</strong> <?php echo date('d M Y', strtotime($data['visit_date'])); ?></div>
            <div><strong>Paid On:</strong> <?php echo date('d M Y, h:i A', strtotime($data['payment_date'])); ?></div>
            <div><strong>Status:</strong> <span class="text-uppercase fw-bold text-<?php echo ($data['payment_status'] == 'Paid') ? 'success' : 'danger'; ?>"><?php echo $data['payment_status']; ?></span></div>
        </div>
    </div>

    <table class="table table-bordered table-striped mb-4">
        <thead class="table-light">
            <tr>
                <th>Description / Service</th>
                <th class="text-center">Qty</th>
                <th class="text-end">Unit Price (KES)</th>
                <th class="text-end">Amount (KES)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $calculated_total = 0;
            if (!empty($bill_items)): 
                foreach ($bill_items as $item): 
                    $row_total = $item['price'] * $item['qty'];
                    $calculated_total += $row_total;
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td class="text-center"><?php echo $item['qty']; ?></td>
                    <td class="text-end"><?php echo number_format($item['price'], 2); ?></td>
                    <td class="text-end fw-bold"><?php echo number_format($row_total, 2); ?></td>
                </tr>
            <?php 
                endforeach; 
            else: 
            ?>
                <tr>
                    <td colspan="4" class="text-center text-muted">No clinical items found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="row justify-content-end">
        <div class="col-sm-6 col-md-5">
            <table class="table table-sm table-borderless">
                <tr>
                    <td class="fw-bold">Total Bill:</td>
                    <td class="text-end fw-bold fs-5">KES <?php echo number_format($data['total_amount'], 2); ?></td>
                </tr>
                <tr>
                    <td class="fw-bold text-success border-top pt-2">Amount Paid:</td>
                    <td class="text-end fw-bold text-success border-top pt-2">KES <?php echo number_format($data['amount_paid'], 2); ?></td>
                </tr>
                <?php if ($data['total_amount'] > $data['amount_paid']): ?>
                <tr>
                    <td class="fw-bold text-danger border-top pt-2">Balance Due:</td>
                    <td class="text-end fw-bold text-danger border-top pt-2">KES <?php echo number_format($data['total_amount'] - $data['amount_paid'], 2); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <div class="text-center mt-5 pt-3 border-top text-muted small">
        <p class="mb-1">Thank you for trusting us with your healthcare.</p>
        <p class="fw-bold">Wishing you a quick recovery!</p>
    </div>
</div>

<script>
    window.onload = function() {
        setTimeout(function() {
            window.print();
        }, 800);
    };
</script>
</body>
</html>