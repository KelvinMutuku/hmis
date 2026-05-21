<?php
// modules/admin/export.php

// Turn on Error Reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../index.php");
    exit();
}

// Security Check: Admins and Doctors can access data exports
$user_role = isset($_SESSION['role']) ? (string)$_SESSION['role'] : '';
if (strpos($user_role, 'Admin') === false && strpos($user_role, 'Doctor') === false) {
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

// ========================================================================
// DELETE PATIENT LOGIC
// ========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_patient') {
    $del_patient_id = intval($_POST['patient_id']);
    
    // Find all visits for this patient to safely delete their clinical & financial history
    $v_query = $conn->query("SELECT id FROM visits WHERE patient_id = $del_patient_id");
    $visit_ids = [];
    if ($v_query && $v_query->num_rows > 0) {
        while ($v = $v_query->fetch_assoc()) {
            $visit_ids[] = $v['id'];
        }
    }
    
    if (!empty($visit_ids)) {
        $v_list = implode(',', $visit_ids);
        
        // Delete all related child records associated with those visits
        $conn->query("DELETE FROM accounts WHERE visit_id IN ($v_list)");
        $conn->query("DELETE FROM prescriptions WHERE visit_id IN ($v_list)");
        $conn->query("DELETE FROM procedures WHERE visit_id IN ($v_list)");
        $conn->query("DELETE FROM radiology_tests WHERE visit_id IN ($v_list)");
        $conn->query("DELETE FROM lab_tests WHERE visit_id IN ($v_list)");
        $conn->query("DELETE FROM consultations WHERE visit_id IN ($v_list)");
        $conn->query("DELETE FROM triage WHERE visit_id IN ($v_list)");
        
        // Delete the visits themselves
        $conn->query("DELETE FROM visits WHERE patient_id = $del_patient_id");
    }
    
    // Delete any pending appointments and the main patient record
    $conn->query("DELETE FROM appointments WHERE patient_id = $del_patient_id");
    $delete_pt = $conn->query("DELETE FROM patients WHERE id = $del_patient_id");
    
    if ($delete_pt) {
        $message = "Patient and all related clinical & financial records have been permanently deleted.";
        $messageType = "success";
    } else {
        $message = "Error deleting patient: " . $conn->error;
        $messageType = "danger";
    }
}

// ========================================================================
// PRINT MODES (SINGLE & BATCH) - RENDERS CLEAN HTML FOR PRINTING ONLY
// ========================================================================
if (isset($_GET['print_patient']) || (isset($_POST['action']) && $_POST['action'] == 'batch_print')) {
    
    $patient_ids_to_print = [];
    
    if (isset($_GET['print_patient'])) {
        $patient_ids_to_print[] = intval($_GET['print_patient']);
    } elseif (isset($_POST['patient_ids']) && is_array($_POST['patient_ids'])) {
        foreach ($_POST['patient_ids'] as $id) {
            $patient_ids_to_print[] = intval($id);
        }
    }

    if (empty($patient_ids_to_print)) {
        exit('Error: No patients selected for printing.');
    }

    $print_visit_id = isset($_GET['print_visit']) ? intval($_GET['print_visit']) : 0;
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Medical History Report</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background: #fff; color: #000; font-family: Arial, sans-serif; }
            .print-container { max-width: 900px; margin: 0 auto; padding: 20px; }
            .hospital-header { text-align: center; border-bottom: 3px solid #158a43; padding-bottom: 10px; margin-bottom: 20px; }
            .hospital-header h2 { color: #158a43; font-weight: bold; margin: 0; }
            .visit-block { border: 1px solid #ddd; border-radius: 5px; padding: 15px; margin-bottom: 20px; page-break-inside: avoid; }
            .visit-header { background: #f8f9fa; padding: 10px; margin: -15px -15px 15px -15px; border-bottom: 1px solid #ddd; font-weight: bold; }
            .section-title { font-size: 0.9em; font-weight: bold; color: #555; text-transform: uppercase; border-bottom: 1px solid #eee; margin-bottom: 8px; margin-top: 15px; }
            @media print {
                .no-print { display: none !important; }
                .page-break { page-break-after: always; }
                body { padding: 0; margin: 0; }
            }
        </style>
    </head>
    <body>
        <div class="print-container">
            <div class="text-end mb-3 no-print">
                <button onclick="window.print()" class="btn btn-success fw-bold"><i class="fa-solid fa-print"></i> Print Document</button>
                <button onclick="window.close()" class="btn btn-secondary">Close</button>
            </div>

            <?php 
            $count = 0;
            $total_docs = count($patient_ids_to_print);
            
            foreach ($patient_ids_to_print as $p_id): 
                $count++;
                
                // Fetch Patient Info
                $pt_query = $conn->query("SELECT * FROM patients WHERE id = $p_id LIMIT 1");
                if (!$pt_query || $pt_query->num_rows == 0) continue;
                $pt = $pt_query->fetch_assoc();
                
                $age = '--';
                if (!empty($pt['dob'])) {
                    $dob = new DateTime($pt['dob']);
                    $now = new DateTime();
                    $age = $now->diff($dob)->y . ' yrs';
                }
            ?>
            
            <div class="hospital-header">
                <h2>Gem Cherith Healthcare Centre</h2>
                <p class="mb-0"><?php echo ($print_visit_id > 0) ? 'Medical Visit Report' : 'Comprehensive Medical History Report'; ?></p>
            </div>

            <div class="row mb-4">
                <div class="col-6">
                    <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($pt['first_name'] . ' ' . $pt['last_name']); ?></h5>
                    <div><strong>ID:</strong> <?php echo !empty($pt['id_number']) ? htmlspecialchars($pt['id_number']) : 'N/A'; ?></div>
                    <div><strong>Phone:</strong> <?php echo !empty($pt['phone']) ? htmlspecialchars($pt['phone']) : 'N/A'; ?></div>
                </div>
                <div class="col-6 text-end">
                    <div><strong>Gender:</strong> <?php echo htmlspecialchars($pt['gender']); ?></div>
                    <div><strong>Age:</strong> <?php echo $age; ?></div>
                    <div><strong>Registered:</strong> <?php echo date('d M Y', strtotime($pt['created_at'])); ?></div>
                </div>
            </div>

            <h5 class="fw-bold mb-3 border-bottom pb-2"><?php echo ($print_visit_id > 0) ? 'Visit Details' : 'Visit Timeline'; ?></h5>

            <?php
            $visit_where = "patient_id = $p_id";
            if ($print_visit_id > 0) $visit_where .= " AND id = $print_visit_id";

            $visits_query = $conn->query("SELECT * FROM visits WHERE $visit_where ORDER BY visit_date DESC");
            
            if ($visits_query && $visits_query->num_rows > 0):
                while ($visit = $visits_query->fetch_assoc()):
                    $v_id = $visit['id'];
            ?>
                <div class="visit-block">
                    <div class="visit-header d-flex justify-content-between">
                        <span>Visit Date: <?php echo date('d M Y, h:i A', strtotime($visit['visit_date'])); ?></span>
                        <span>Visit ID: #<?php echo $v_id; ?></span>
                    </div>

                    <?php 
                    $triage = $conn->query("SELECT * FROM triage WHERE visit_id = $v_id LIMIT 1");
                    if ($triage && $triage->num_rows > 0): $tr = $triage->fetch_assoc(); ?>
                        <div class="section-title">Triage & Vitals</div>
                        <div class="row text-muted small">
                            <div class="col-3"><strong>BP:</strong> <?php echo htmlspecialchars($tr['blood_pressure']); ?></div>
                            <div class="col-3"><strong>Pulse:</strong> <?php echo htmlspecialchars($tr['pulse_rate']); ?> bpm</div>
                            <div class="col-3"><strong>Temp:</strong> <?php echo htmlspecialchars($tr['temperature']); ?> °C</div>
                            <div class="col-3"><strong>Weight:</strong> <?php echo htmlspecialchars($tr['weight']); ?> kg</div>
                        </div>
                    <?php endif; ?>

                    <?php 
                    $consults = $conn->query("SELECT * FROM consultations WHERE visit_id = $v_id LIMIT 1");
                    if ($consults && $consults->num_rows > 0): $cn = $consults->fetch_assoc(); ?>
                        <div class="section-title">Doctor's Consultation</div>
                        <div><strong>Symptoms:</strong> <?php echo nl2br(htmlspecialchars($cn['symptoms'])); ?></div>
                        <div class="mt-1"><strong>Diagnosis:</strong> <?php echo nl2br(htmlspecialchars($cn['diagnosis'])); ?></div>
                    <?php endif; ?>

                    <?php 
                    $labs = $conn->query("SELECT * FROM lab_tests WHERE visit_id = $v_id");
                    if ($labs && $labs->num_rows > 0): ?>
                        <div class="section-title">Laboratory Results</div>
                        <ul class="mb-0 ps-3 small">
                            <?php while ($lab = $labs->fetch_assoc()): ?>
                                <li><strong><?php echo htmlspecialchars($lab['test_requested']); ?>:</strong> <?php echo !empty($lab['test_results']) ? htmlspecialchars($lab['test_results']) : 'Pending/No result recorded'; ?></li>
                            <?php endwhile; ?>
                        </ul>
                    <?php endif; ?>

                    <?php 
                    $meds = $conn->query("SELECT * FROM prescriptions WHERE visit_id = $v_id AND dispensed = 1");
                    if ($meds && $meds->num_rows > 0): ?>
                        <div class="section-title">Medications Dispensed</div>
                        <ul class="mb-0 ps-3 small">
                            <?php while ($med = $meds->fetch_assoc()): ?>
                                <li><?php echo htmlspecialchars($med['medication_name']); ?> (<?php echo htmlspecialchars($med['dose']); ?>, <?php echo htmlspecialchars($med['frequency']); ?>)</li>
                            <?php endwhile; ?>
                        </ul>
                    <?php endif; ?>
                    
                </div>
            <?php 
                endwhile; 
            else: 
            ?>
                <p class="text-muted text-center py-3">No medical visits found.</p>
            <?php endif; ?>

            <?php if ($count < $total_docs): ?>
                <div class="page-break"></div>
            <?php endif; ?>

            <?php endforeach; ?>
        </div>
        <script>
            window.onload = function() { setTimeout(function() { window.print(); }, 800); };
        </script>
    </body>
    </html>
    <?php
    exit();
}

// ========================================================================
// DETAILED PATIENT HISTORY VIEW
// ========================================================================
if (isset($_GET['patient_id'])) {
    $p_id = intval($_GET['patient_id']);
    
    $pt_query = $conn->query("SELECT * FROM patients WHERE id = $p_id LIMIT 1");
    if (!$pt_query || $pt_query->num_rows == 0) {
        header("Location: export.php");
        exit();
    }
    $pt = $pt_query->fetch_assoc();
    
    include '../../includes/header.php';
    include '../../includes/sidebar.php';
    ?>
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Patient History File</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="export.php" class="btn btn-sm btn-outline-secondary me-2"><i class="fa-solid fa-arrow-left me-1"></i> Back to Directory</a>
            <a href="export.php?print_patient=<?php echo $p_id; ?>" target="_blank" class="btn btn-sm btn-success fw-bold"><i class="fa-solid fa-print me-1"></i> Print Complete History</a>
        </div>
    </div>

    <div class="card shadow-sm border-dark mb-4">
        <div class="card-body bg-light">
            <div class="row align-items-center">
                <div class="col-md-6 border-end">
                    <h4 class="text-primary fw-bold mb-1"><?php echo htmlspecialchars($pt['first_name'] . ' ' . $pt['last_name']); ?></h4>
                    <p class="mb-0 text-muted">
                        <i class="fa-regular fa-id-card me-1"></i> <?php echo !empty($pt['id_number']) ? htmlspecialchars($pt['id_number']) : 'N/A'; ?> | 
                        <i class="fa-solid fa-phone me-1"></i> <?php echo !empty($pt['phone']) ? htmlspecialchars($pt['phone']) : 'N/A'; ?>
                    </p>
                </div>
                <div class="col-md-6">
                    <div class="row text-center text-muted small">
                        <div class="col-4"><strong>Gender</strong><br><span class="text-dark fs-6"><?php echo htmlspecialchars($pt['gender']); ?></span></div>
                        <div class="col-4"><strong>Date of Birth</strong><br><span class="text-dark fs-6"><?php echo !empty($pt['dob']) ? date('d M Y', strtotime($pt['dob'])) : 'N/A'; ?></span></div>
                        <div class="col-4"><strong>Registered</strong><br><span class="text-dark fs-6"><?php echo date('d M Y', strtotime($pt['created_at'])); ?></span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <h5 class="fw-bold text-secondary mb-3"><i class="fa-solid fa-timeline me-2"></i> Chronological Visit Timeline</h5>

    <?php
    $visits_query = $conn->query("SELECT * FROM visits WHERE patient_id = $p_id ORDER BY visit_date DESC");
    
    if ($visits_query && $visits_query->num_rows > 0):
        while ($visit = $visits_query->fetch_assoc()):
            $v_id = $visit['id'];
    ?>
        <div class="card shadow-sm mb-4 border-secondary">
            <div class="card-header bg-white d-flex justify-content-between align-items-center fw-bold text-secondary">
                <div><i class="fa-regular fa-calendar me-2"></i> <?php echo date('l, F j, Y - h:i A', strtotime($visit['visit_date'])); ?></div>
                <div>
                    <span class="badge bg-secondary me-2">Visit #<?php echo $v_id; ?></span>
                    <a href="export.php?print_patient=<?php echo $p_id; ?>&print_visit=<?php echo $v_id; ?>" target="_blank" class="btn btn-sm btn-outline-success py-0" title="Print this specific visit"><i class="fa-solid fa-print me-1"></i> Print Visit</a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 border-end">
                        <h6 class="fw-bold text-info border-bottom pb-1">Clinical Assessment</h6>
                        <?php 
                        $triage = $conn->query("SELECT * FROM triage WHERE visit_id = $v_id LIMIT 1");
                        if ($triage && $triage->num_rows > 0): $tr = $triage->fetch_assoc(); ?>
                            <div class="bg-light p-2 rounded mb-3 small">
                                <strong>Vitals:</strong> BP: <?php echo htmlspecialchars($tr['blood_pressure']); ?> | Pulse: <?php echo htmlspecialchars($tr['pulse_rate']); ?> | Temp: <?php echo htmlspecialchars($tr['temperature']); ?> | Wt: <?php echo htmlspecialchars($tr['weight']); ?>
                            </div>
                        <?php endif; ?>
                        <?php 
                        $consults = $conn->query("SELECT * FROM consultations WHERE visit_id = $v_id LIMIT 1");
                        if ($consults && $consults->num_rows > 0): $cn = $consults->fetch_assoc(); ?>
                            <div class="mb-2"><strong>Symptoms:</strong> <br><span class="text-muted"><?php echo nl2br(htmlspecialchars($cn['symptoms'])); ?></span></div>
                            <div><strong>Diagnosis:</strong> <br><span class="text-success fw-bold"><?php echo nl2br(htmlspecialchars($cn['diagnosis'])); ?></span></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold text-warning border-bottom pb-1">Interventions & Results</h6>
                        <?php 
                        $labs = $conn->query("SELECT * FROM lab_tests WHERE visit_id = $v_id");
                        if ($labs && $labs->num_rows > 0): ?>
                            <div class="small mb-2">
                                <strong class="text-danger"><i class="fa-solid fa-microscope me-1"></i> Lab Tests:</strong>
                                <ul class="mb-0 ps-3 text-muted">
                                    <?php while ($lab = $labs->fetch_assoc()): ?>
                                        <li><?php echo htmlspecialchars($lab['test_requested']); ?> - <em><?php echo !empty($lab['test_results']) ? htmlspecialchars($lab['test_results']) : 'Pending'; ?></em></li>
                                    <?php endwhile; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <?php 
                        $meds = $conn->query("SELECT * FROM prescriptions WHERE visit_id = $v_id AND dispensed = 1");
                        if ($meds && $meds->num_rows > 0): ?>
                            <div class="small">
                                <strong class="text-primary"><i class="fa-solid fa-pills me-1"></i> Medications:</strong>
                                <ul class="mb-0 ps-3 text-muted">
                                    <?php while ($med = $meds->fetch_assoc()): ?>
                                        <li><?php echo htmlspecialchars($med['medication_name']); ?> (<?php echo htmlspecialchars($med['dose']); ?>)</li>
                                    <?php endwhile; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php 
        endwhile; 
    else: 
    ?>
        <div class="alert alert-info text-center py-4 shadow-sm">
            <i class="fa-solid fa-folder-open fa-2x mb-2 d-block"></i>
            This patient has no recorded visits yet.
        </div>
    <?php endif; ?>

    <?php
    include '../../includes/footer.php';
    exit();
}

// ========================================================================
// DEFAULT DIRECTORY VIEW (Search & Batch Action)
// ========================================================================
$search_query = isset($_GET['search']) ? $conn->real_escape_string(trim($_GET['search'])) : '';
$where_clause = "NOT (first_name = 'Walk-in' AND last_name = 'Client')";

if ($search_query !== '') {
    $where_clause .= " AND (first_name LIKE '%$search_query%' OR last_name LIKE '%$search_query%' OR id_number LIKE '%$search_query%' OR phone LIKE '%$search_query%')";
}

// Join accounts to get sum of balances
$query = "SELECT p.*, SUM(a.total_amount - a.amount_paid) as outstanding_balance 
          FROM patients p 
          LEFT JOIN visits v ON p.id = v.patient_id 
          LEFT JOIN accounts a ON v.id = a.visit_id 
          WHERE $where_clause 
          GROUP BY p.id 
          ORDER BY p.first_name ASC LIMIT 100";
$patients_q = $conn->query($query);

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Data Export & Patient Records</h1>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body bg-light">
        <form method="GET" action="export.php" class="row gx-2 align-items-center">
            <div class="col-md-9">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fa-solid fa-search text-primary"></i></span>
                    <input type="text" class="form-control border-start-0" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Filter clients by Name, ID Number, or Phone...">
                </div>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100 fw-bold">Filter Directory</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center py-3">
        <span><i class="fa-solid fa-folder-open me-2 text-warning"></i> Client Master Directory</span>
    </div>
    <div class="card-body p-0">
        <form method="POST" action="export.php" target="_blank">
            <input type="hidden" name="action" value="batch_print">
            
            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th style="width: 40px;" class="text-center"><input class="form-check-input" type="checkbox" id="selectAll" onclick="toggleAll(this)"></th>
                            <th>Patient Name</th>
                            <th>ID / Phone</th>
                            <th>Registered On</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($patients_q && $patients_q->num_rows > 0) {
                            while ($p = $patients_q->fetch_assoc()) {
                                $bal = floatval($p['outstanding_balance']);
                                $bal_html = ($bal > 0) ? "<small class='badge bg-danger d-block mt-1' style='font-size:0.75em;'>Bal: KES " . number_format($bal, 2) . "</small>" : "";
                                
                                echo "<tr>";
                                echo "<td class='text-center'><input class='form-check-input row-checkbox' type='checkbox' name='patient_ids[]' value='{$p['id']}'></td>";
                                echo "<td class='fw-bold text-dark'>{$p['first_name']} {$p['last_name']} {$bal_html}</td>";
                                echo "<td>
                                        <small class='d-block'><i class='fa-regular fa-id-card text-muted me-1'></i> " . (!empty($p['id_number']) ? htmlspecialchars($p['id_number']) : 'N/A') . "</small>
                                        <small class='d-block'><i class='fa-solid fa-phone text-muted me-1'></i> " . (!empty($p['phone']) ? htmlspecialchars($p['phone']) : 'N/A') . "</small>
                                      </td>";
                                echo "<td class='text-muted small'>" . date('M j, Y', strtotime($p['created_at'])) . "</td>";
                                echo "<td class='text-end'>
                                        <a href='export.php?patient_id={$p['id']}' class='btn btn-sm btn-outline-primary shadow-sm me-2'>
                                            <i class='fa-solid fa-eye'></i> View History
                                        </a>
                                        <a href='export.php?print_patient={$p['id']}' target='_blank' class='btn btn-sm btn-outline-success shadow-sm me-2'>
                                            <i class='fa-solid fa-print'></i>
                                        </a>
                                        <form method='POST' action='export.php' class='d-inline-block' onsubmit='return confirm(\"Are you sure?\");'>
                                            <input type='hidden' name='action' value='delete_patient'>
                                            <input type='hidden' name='patient_id' value='{$p['id']}'>
                                            <button type='submit' class='btn btn-sm btn-outline-danger shadow-sm'><i class='fa-solid fa-trash'></i></button>
                                        </form>
                                      </td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' class='text-center py-4 text-muted'>No clients found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="bg-light p-3 border-top d-flex justify-content-between align-items-center">
                <small class="text-muted"><i class="fa-solid fa-circle-info me-1"></i> Select multiple clients to print their histories at once.</small>
                <button type="submit" class="btn btn-success fw-bold shadow-sm" id="batchPrintBtn" disabled>
                    <i class="fa-solid fa-print me-1"></i> Print Selected Group
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleAll(source) {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    checkboxes.forEach(cb => cb.checked = source.checked);
    checkPrintButton();
}
document.querySelectorAll('.row-checkbox').forEach(cb => {
    cb.addEventListener('change', checkPrintButton);
});
function checkPrintButton() {
    const anyChecked = document.querySelectorAll('.row-checkbox:checked').length > 0;
    document.getElementById('batchPrintBtn').disabled = !anyChecked;
}
</script>

<?php include '../../includes/footer.php'; ?>