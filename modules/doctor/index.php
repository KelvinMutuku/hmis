<?php
// modules/doctor/index.php

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../index.php");
    exit();
}

// Security Check supporting multi-roles
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

// Fetch Services for the Search List (Labs, Radiology, Procedures)
$services_list = [];
$services_query = $conn->query("SELECT service_name FROM hospital_services ORDER BY service_name ASC");
if ($services_query) {
    while ($row = $services_query->fetch_assoc()) {
        $services_list[] = $row['service_name'];
    }
}

// Fetch Pharmacy Inventory for the Search List
$medications_list = [];
$meds_query = $conn->query("SELECT item_name, generic_name FROM pharmacy_inventory ORDER BY item_name ASC");
if ($meds_query) {
    while ($row = $meds_query->fetch_assoc()) {
        $medications_list[] = $row;
    }
}

$message = '';
$messageType = '';
$selected_visit_id = isset($_GET['visit_id']) ? intval($_GET['visit_id']) : null;
$patient_data = null;
$triage_data = null;

// Initialize variables to track if the patient is returning from ANY department
$is_returning_patient = false;
$lab_results_data = [];
$rad_results_data = [];
$proc_results_data = [];
$original_consult = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $visit_id = intval($_POST['visit_id']);
    $patient_id = intval($_POST['patient_id']);
    $next_stage = $conn->real_escape_string($_POST['next_stage']);
    
    $is_final_routing = isset($_POST['is_final_routing']) && $_POST['is_final_routing'] === '1';

    // Handle Follow-Up Appointment (Applies to Final routing)
    $followup_date = isset($_POST['followup_date']) ? $conn->real_escape_string($_POST['followup_date']) : '';
    $followup_notes = isset($_POST['followup_notes']) ? $conn->real_escape_string($_POST['followup_notes']) : '';
    
    if (!empty($followup_date)) {
        $conn->query("INSERT INTO appointments (patient_id, appointment_date, notes) VALUES ($patient_id, '$followup_date', '$followup_notes')");
    }

    if ($is_final_routing) {
        // FINAL ROUTING (After Lab, Rad, or Proc)
        if ($next_stage === 'Pharmacy' && isset($_POST['medication_name']) && is_array($_POST['medication_name'])) {
            $med_names = $_POST['medication_name'];
            $doses = $_POST['dose'];
            $frequencies = $_POST['frequency'];
            $routes = $_POST['route'];
            $durations = $_POST['duration'];

            for ($i = 0; $i < count($med_names); $i++) {
                $name = $conn->real_escape_string($med_names[$i]);
                $dose = $conn->real_escape_string($doses[$i]);
                $freq = $conn->real_escape_string($frequencies[$i]);
                $route = $conn->real_escape_string($routes[$i]);
                $dur = $conn->real_escape_string($durations[$i]);

                if (!empty($name)) {
                    $sql_rx = "INSERT INTO prescriptions (visit_id, medication_name, dose, frequency, route, duration) 
                               VALUES ($visit_id, '$name', '$dose', '$freq', '$route', '$dur')";
                    $conn->query($sql_rx);
                }
            }
        } elseif ($next_stage === 'Radiology' && isset($_POST['radiology_test']) && is_array($_POST['radiology_test'])) {
            foreach ($_POST['radiology_test'] as $rad) {
                if (!empty($rad)) {
                    $rad_clean = $conn->real_escape_string($rad);
                    $conn->query("INSERT INTO radiology_tests (visit_id, test_requested) VALUES ($visit_id, '$rad_clean')");
                }
            }
        } elseif ($next_stage === 'Procedure' && isset($_POST['procedure_req']) && is_array($_POST['procedure_req'])) {
            foreach ($_POST['procedure_req'] as $proc) {
                if (!empty($proc)) {
                    $proc_clean = $conn->real_escape_string($proc);
                    $conn->query("INSERT INTO procedures (visit_id, procedure_requested) VALUES ($visit_id, '$proc_clean')");
                }
            }
        }

        $sql_update_visit = "UPDATE visits SET current_stage = '$next_stage' WHERE id = $visit_id";
        if ($conn->query($sql_update_visit) === TRUE) {
            $message = "Patient routed to " . $next_stage . " successfully.";
            $messageType = "success";
            $selected_visit_id = null;
        } else {
            $message = "Failed to route patient: " . $conn->error;
            $messageType = "warning";
        }

    } else {
        // INITIAL CONSULTATION
        $symptoms = $conn->real_escape_string($_POST['symptoms']);
        $diagnosis = $conn->real_escape_string($_POST['diagnosis']);
        $notes = $conn->real_escape_string($_POST['notes']);

        $sql_consult = "INSERT INTO consultations (visit_id, symptoms, diagnosis, doctor_notes) 
                        VALUES ($visit_id, '$symptoms', '$diagnosis', '$notes')";

        if ($conn->query($sql_consult) === TRUE) {
            
            // Multiple Routing Checks for Initial Consult
            if ($next_stage === 'Lab' && isset($_POST['lab_test']) && is_array($_POST['lab_test'])) {
                foreach ($_POST['lab_test'] as $test) {
                    if (!empty($test)) {
                        $test_clean = $conn->real_escape_string($test);
                        $conn->query("INSERT INTO lab_tests (visit_id, test_requested) VALUES ($visit_id, '$test_clean')");
                    }
                }
            } elseif ($next_stage === 'Radiology' && isset($_POST['radiology_test']) && is_array($_POST['radiology_test'])) {
                foreach ($_POST['radiology_test'] as $rad) {
                    if (!empty($rad)) {
                        $rad_clean = $conn->real_escape_string($rad);
                        $conn->query("INSERT INTO radiology_tests (visit_id, test_requested) VALUES ($visit_id, '$rad_clean')");
                    }
                }
            } elseif ($next_stage === 'Procedure' && isset($_POST['procedure_req']) && is_array($_POST['procedure_req'])) {
                foreach ($_POST['procedure_req'] as $proc) {
                    if (!empty($proc)) {
                        $proc_clean = $conn->real_escape_string($proc);
                        $conn->query("INSERT INTO procedures (visit_id, procedure_requested) VALUES ($visit_id, '$proc_clean')");
                    }
                }
            } elseif ($next_stage === 'Pharmacy' && isset($_POST['medication_name']) && is_array($_POST['medication_name'])) {
                $med_names = $_POST['medication_name'];
                $doses = $_POST['dose'];
                $frequencies = $_POST['frequency'];
                $routes = $_POST['route'];
                $durations = $_POST['duration'];

                for ($i = 0; $i < count($med_names); $i++) {
                    $name = $conn->real_escape_string($med_names[$i]);
                    $dose = $conn->real_escape_string($doses[$i]);
                    $freq = $conn->real_escape_string($frequencies[$i]);
                    $route = $conn->real_escape_string($routes[$i]);
                    $dur = $conn->real_escape_string($durations[$i]);

                    if (!empty($name)) {
                        $sql_rx = "INSERT INTO prescriptions (visit_id, medication_name, dose, frequency, route, duration) 
                                   VALUES ($visit_id, '$name', '$dose', '$freq', '$route', '$dur')";
                        $conn->query($sql_rx);
                    }
                }
            }

            $sql_update_visit = "UPDATE visits SET current_stage = '$next_stage' WHERE id = $visit_id";
            if ($conn->query($sql_update_visit) === TRUE) {
                $message = "Consultation saved successfully. Patient routed to " . $next_stage . ".";
                $messageType = "success";
                $selected_visit_id = null;
            } else {
                $message = "Consultation saved, but failed to route patient: " . $conn->error;
                $messageType = "warning";
            }
        } else {
            $message = "Error saving consultation: " . $conn->error;
            $messageType = "danger";
        }
    }
}

// Fetch selected patient details
if ($selected_visit_id) {
    $patient_query = "SELECT p.id AS patient_id, p.first_name, p.last_name, p.dob, p.gender FROM visits v 
                      JOIN patients p ON v.patient_id = p.id 
                      WHERE v.id = $selected_visit_id";
    $patient_result = $conn->query($patient_query);
    if ($patient_result && $patient_result->num_rows > 0) {
        $patient_data = $patient_result->fetch_assoc();
        $dob = new DateTime($patient_data['dob']);
        $now = new DateTime();
        $patient_data['age'] = $now->diff($dob)->y;
    }

    $triage_query = "SELECT * FROM triage WHERE visit_id = $selected_visit_id ORDER BY recorded_at DESC LIMIT 1";
    $triage_result = $conn->query($triage_query);
    if ($triage_result && $triage_result->num_rows > 0) {
        $triage_data = $triage_result->fetch_assoc();
    }

    // Check for Completed Labs
    $lab_check_query = "SELECT * FROM lab_tests WHERE visit_id = $selected_visit_id AND status = 'Completed'";
    $lab_check_result = $conn->query($lab_check_query);
    if ($lab_check_result && $lab_check_result->num_rows > 0) {
        $is_returning_patient = true;
        while($row = $lab_check_result->fetch_assoc()) $lab_results_data[] = $row;
    }

    // Check for Completed Radiology Scans
    $rad_check_query = "SELECT * FROM radiology_tests WHERE visit_id = $selected_visit_id AND status = 'Completed'";
    $rad_check_result = $conn->query($rad_check_query);
    if ($rad_check_result && $rad_check_result->num_rows > 0) {
        $is_returning_patient = true;
        while($row = $rad_check_result->fetch_assoc()) $rad_results_data[] = $row;
    }

    // Check for Completed Procedures
    $proc_check_query = "SELECT * FROM procedures WHERE visit_id = $selected_visit_id AND status = 'Completed'";
    $proc_check_result = $conn->query($proc_check_query);
    if ($proc_check_result && $proc_check_result->num_rows > 0) {
        $is_returning_patient = true;
        while($row = $proc_check_result->fetch_assoc()) $proc_results_data[] = $row;
    }

    // If returning from ANY department, fetch original diagnosis
    if ($is_returning_patient) {
        $consult_query = "SELECT * FROM consultations WHERE visit_id = $selected_visit_id ORDER BY recorded_at DESC LIMIT 1";
        $consult_result = $conn->query($consult_query);
        if ($consult_result && $consult_result->num_rows > 0) {
            $original_consult = $consult_result->fetch_assoc();
        }
    }
}

// Medical Logic: Calculate Highlight Classes for Vitals
$bp_class = "text-dark";
$pulse_class = "text-dark";
$spo2_class = "text-info";
$temp_class = "text-warning";

if ($triage_data) {
    if (!empty($triage_data['blood_pressure'])) {
        $parts = explode('/', $triage_data['blood_pressure']);
        if (count($parts) == 2 && ($parts[0] > 140 || $parts[0] < 90 || $parts[1] > 90 || $parts[1] < 60)) {
            $bp_class = 'text-danger bg-danger bg-opacity-10 px-2 rounded';
        }
    }
    if (!empty($triage_data['pulse_rate']) && ($triage_data['pulse_rate'] > 100 || $triage_data['pulse_rate'] < 60)) {
        $pulse_class = 'text-danger bg-danger bg-opacity-10 px-2 rounded';
    }
    if (!empty($triage_data['spo2']) && $triage_data['spo2'] < 95) {
        $spo2_class = 'text-danger bg-danger bg-opacity-10 px-2 rounded';
    }
    if (!empty($triage_data['temperature']) && ($triage_data['temperature'] > 37.5 || $triage_data['temperature'] < 36.0)) {
        $temp_class = 'text-danger bg-danger bg-opacity-10 px-2 rounded';
    }
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Doctor's Consultation</h1>
</div>

<?php if ($message != ''): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-success text-white fw-bold">
                <i class="fa-solid fa-user-doctor me-2"></i> Waiting for Doctor
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php
                    $queue_query = "SELECT v.id AS visit_id, p.first_name, p.last_name, v.visit_date,
                                       (SELECT COUNT(id) FROM lab_tests WHERE visit_id = v.id AND status = 'Completed') AS lab_count,
                                       (SELECT COUNT(id) FROM radiology_tests WHERE visit_id = v.id AND status = 'Completed') AS rad_count,
                                       (SELECT COUNT(id) FROM procedures WHERE visit_id = v.id AND status = 'Completed') AS proc_count
                                    FROM visits v 
                                    JOIN patients p ON v.patient_id = p.id 
                                    WHERE v.current_stage = 'Doctor' 
                                    ORDER BY (lab_count + rad_count + proc_count) DESC, v.visit_date ASC";
                    $queue_result = $conn->query($queue_query);

                    if ($queue_result && $queue_result->num_rows > 0) {
                        while ($row = $queue_result->fetch_assoc()) {
                            $active_class = ($selected_visit_id == $row['visit_id']) ? 'active bg-success border-success' : '';
                            $text_class = ($selected_visit_id == $row['visit_id']) ? 'text-white' : '';
                            
                            $total_returns = $row['lab_count'] + $row['rad_count'] + $row['proc_count'];

                            if ($total_returns > 0) {
                                $badge = "<span class='badge bg-warning text-dark ms-2' style='font-size: 0.75em;'><i class='fa-solid fa-folder-open me-1'></i> Review Results</span>";
                            } else {
                                $badge = "<span class='badge bg-light text-secondary border ms-2' style='font-size: 0.75em;'>New Consult</span>";
                            }
                            
                            echo "<a href='index.php?visit_id={$row['visit_id']}' class='list-group-item list-group-item-action {$active_class}'>";
                            echo "<div class='d-flex w-100 justify-content-between align-items-center mb-1'>";
                            echo "<h6 class='mb-0 {$text_class}'><i class='fa-regular fa-user me-2'></i>{$row['first_name']} {$row['last_name']}</h6>";
                            echo "</div>";
                            echo "<div>{$badge} <small class='{$text_class} float-end mt-1'>" . date('H:i', strtotime($row['visit_date'])) . "</small></div>";
                            echo "</a>";
                        }
                    } else {
                        echo "<div class='p-4 text-center text-muted'>No patients currently waiting.</div>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-8 mb-4">
        
        <?php if (!$selected_visit_id): ?>
            <div class="card shadow-sm h-100 d-flex justify-content-center align-items-center bg-light">
                <div class="text-center text-muted py-5">
                    <i class="fa-solid fa-stethoscope fa-4x mb-3 text-secondary" style="opacity: 0.5;"></i>
                    <h5>Select a patient from the queue to begin consultation.</h5>
                </div>
            </div>
        <?php else: ?>
            
            <div class="card shadow-sm mb-3 border-info">
                <div class="card-body bg-light">
                    <div class="row align-items-center">
                        <div class="col-md-4 border-end">
                            <h5 class="text-primary fw-bold mb-1"><?php echo htmlspecialchars($patient_data['first_name'] . ' ' . $patient_data['last_name']); ?></h5>
                            <p class="mb-0 text-muted">
                                <strong>Age:</strong> <?php echo $patient_data['age']; ?> yrs <br>
                                <strong>Gender:</strong> <?php echo $patient_data['gender']; ?>
                            </p>
                        </div>
                        <div class="col-md-8">
                            <?php if ($triage_data): ?>
                                <div class="row text-center g-2">
                                    <div class="col-4">
                                        <small class="text-muted d-block">BP</small>
                                        <span class="fw-bold <?php echo $bp_class; ?>"><?php echo !empty($triage_data['blood_pressure']) ? $triage_data['blood_pressure'] : '--'; ?></span>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted d-block">Pulse</small>
                                        <span class="fw-bold <?php echo $pulse_class; ?>"><?php echo !empty($triage_data['pulse_rate']) ? $triage_data['pulse_rate'] . ' bpm' : '--'; ?></span>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted d-block">SpO2</small>
                                        <span class="fw-bold <?php echo $spo2_class; ?>"><?php echo !empty($triage_data['spo2']) ? $triage_data['spo2'] . '%' : '--'; ?></span>
                                    </div>
                                    <div class="col-4 mt-2">
                                        <small class="text-muted d-block">Temp</small>
                                        <span class="fw-bold <?php echo $temp_class; ?>"><?php echo !empty($triage_data['temperature']) ? $triage_data['temperature'] . ' °C' : '--'; ?></span>
                                    </div>
                                    <div class="col-4 mt-2">
                                        <small class="text-muted d-block">Weight</small>
                                        <span class="fw-bold text-success"><?php echo !empty($triage_data['weight']) ? $triage_data['weight'] . ' kg' : '--'; ?></span>
                                    </div>
                                    <div class="col-4 mt-2">
                                        <small class="text-muted d-block">BMI</small>
                                        <span class="fw-bold text-primary"><?php echo !empty($triage_data['bmi']) ? $triage_data['bmi'] : '--'; ?></span>
                                    </div>
                                </div>
                                <?php if (!empty($triage_data['notes'])): ?>
                                    <hr class="my-2">
                                    <small class="text-muted"><strong>Nurse Notes:</strong> <?php echo htmlspecialchars($triage_data['notes']); ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="text-muted mb-0 text-center">No triage vitals recorded for this visit.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($is_returning_patient): ?>
                
                <div class="alert alert-warning border-warning">
                    <i class="fa-solid fa-folder-open me-2"></i> Patient has returned with completed reports.
                </div>

                <div class="card shadow-sm mb-3 border-danger">
                    <div class="card-header bg-white fw-bold text-danger">
                        Completed Results & Reports
                    </div>
                    <div class="card-body">
                        <?php foreach($lab_results_data as $lab): ?>
                            <div class="mb-3 border-bottom pb-2">
                                <h6 class="fw-bold mb-1"><i class="fa-solid fa-microscope text-primary me-2"></i>Lab: <?php echo htmlspecialchars($lab['test_requested']); ?></h6>
                                <p class="mb-0 text-dark"><strong>Findings:</strong> <?php echo nl2br(htmlspecialchars($lab['test_results'])); ?></p>
                            </div>
                        <?php endforeach; ?>

                        <?php foreach($rad_results_data as $rad): ?>
                            <div class="mb-3 border-bottom pb-2">
                                <h6 class="fw-bold mb-1"><i class="fa-solid fa-x-ray text-warning me-2"></i>Radiology: <?php echo htmlspecialchars($rad['test_requested']); ?></h6>
                                <p class="mb-0 text-dark"><strong>Findings:</strong> <?php echo nl2br(htmlspecialchars($rad['results'])); ?></p>
                            </div>
                        <?php endforeach; ?>

                        <?php foreach($proc_results_data as $proc): ?>
                            <div class="mb-3 border-bottom pb-2">
                                <h6 class="fw-bold mb-1"><i class="fa-solid fa-scissors text-danger me-2"></i>Procedure: <?php echo htmlspecialchars($proc['procedure_requested']); ?></h6>
                                <p class="mb-0 text-dark"><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($proc['notes'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if($original_consult): ?>
                            <div class="mt-3 bg-light p-3 rounded border">
                                <small class="text-muted d-block fw-bold mb-1">Your Original Diagnosis:</small>
                                <small><?php echo nl2br(htmlspecialchars($original_consult['diagnosis'])); ?></small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-white fw-bold">
                        Final Prescriptions & Routing
                    </div>
                    <div class="card-body">
                        <form method="POST" action="index.php">
                            <input type="hidden" name="visit_id" value="<?php echo $selected_visit_id; ?>">
                            <input type="hidden" name="patient_id" value="<?php echo $patient_data['patient_id']; ?>">
                            <input type="hidden" name="is_final_routing" value="1">

                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <label for="next_stage_final" class="form-label fw-bold text-primary">Route Patient To:</label>
                                    <select class="form-select border-primary" id="next_stage_final" name="next_stage" required onchange="toggleFinalRoutingFields()">
                                        <option value="" selected disabled>Select Next Step...</option>
                                        <option value="Pharmacy">Pharmacy (Prescribe Meds)</option>
                                        <option value="Radiology">Radiology</option>
                                        <option value="Procedure">Procedure Room</option>
                                        <option value="Accounts">Accounts (Discharge/Billing)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-8 d-none mb-4" id="radiology_fields_final">
                                <label class="form-label fw-bold">Radiology Tests Requested</label>
                                <div id="rad_container_final"></div>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addServiceRow('rad_container_final', 'radiology_test[]', 'Search Radiology...')"><i class="fa-solid fa-plus me-1"></i> Add Scan</button>
                            </div>

                            <div class="col-md-8 d-none mb-4" id="procedure_fields_final">
                                <label class="form-label fw-bold">Procedures Requested</label>
                                <div id="proc_container_final"></div>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addServiceRow('proc_container_final', 'procedure_req[]', 'Search Procedure...')"><i class="fa-solid fa-plus me-1"></i> Add Procedure</button>
                            </div>

                            <div class="d-none bg-light p-3 rounded mb-4 border" id="pharmacy_fields_final">
                                <h6 class="fw-bold mb-3">Add Prescriptions</h6>
                                <div id="rx_container_final"></div>
                                <button type="button" class="btn btn-sm btn-outline-info mt-2" onclick="addRxRow('rx_container_final')"><i class="fa-solid fa-plus me-1"></i> Add Medication</button>
                            </div>

                            <h6 class="fw-bold mt-4 border-bottom pb-2 text-secondary">Follow-up Appointment (Optional)</h6>
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Date</label>
                                    <input type="date" class="form-control border-info" name="followup_date">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label fw-bold">Reason / Notes</label>
                                    <input type="text" class="form-control border-info" name="followup_notes" placeholder="e.g. Check healing progress">
                                </div>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary btn-lg"><i class="fa-solid fa-check-circle me-1"></i> Save & Route</button>
                            </div>
                        </form>
                    </div>
                </div>

            <?php else: ?>

                <div class="card shadow-sm">
                    <div class="card-header bg-white fw-bold">
                        Clinical Notes & Routing
                    </div>
                    <div class="card-body">
                        <form method="POST" action="index.php">
                            <input type="hidden" name="visit_id" value="<?php echo $selected_visit_id; ?>">
                            <input type="hidden" name="patient_id" value="<?php echo $patient_data['patient_id']; ?>">

                            <div class="mb-3">
                                <label for="symptoms" class="form-label fw-bold">Symptoms (Presenting Complaints) *</label>
                                <textarea class="form-control" id="symptoms" name="symptoms" rows="2" required></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="diagnosis" class="form-label fw-bold">Differential Diagnosis *</label>
                                <textarea class="form-control" id="diagnosis" name="diagnosis" rows="2" required></textarea>
                            </div>

                            <div class="mb-4">
                                <label for="notes" class="form-label fw-bold">Doctor's Private Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                            </div>

                            <hr>

                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <label for="next_stage" class="form-label fw-bold text-primary">Route Patient To:</label>
                                    <select class="form-select border-primary" id="next_stage" name="next_stage" required onchange="toggleRoutingFields()">
                                        <option value="" selected disabled>Select Next Step...</option>
                                        <option value="Lab">Lab (Request Tests)</option>
                                        <option value="Radiology">Radiology</option>
                                        <option value="Procedure">Procedure Room</option>
                                        <option value="Pharmacy">Pharmacy (Prescribe Meds)</option>
                                        <option value="Accounts">Accounts (Discharge/Billing)</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-8 d-none" id="lab_fields">
                                    <label class="form-label fw-bold">Tests Requested</label>
                                    <div id="lab_container_initial"></div>
                                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addServiceRow('lab_container_initial', 'lab_test[]', 'Search Lab Test...')"><i class="fa-solid fa-plus me-1"></i> Add Test</button>
                                </div>

                                <div class="col-md-8 d-none" id="radiology_fields">
                                    <label class="form-label fw-bold">Radiology Tests Requested</label>
                                    <div id="rad_container_initial"></div>
                                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addServiceRow('rad_container_initial', 'radiology_test[]', 'Search Radiology...')"><i class="fa-solid fa-plus me-1"></i> Add Scan</button>
                                </div>

                                <div class="col-md-8 d-none" id="procedure_fields">
                                    <label class="form-label fw-bold">Procedures Requested</label>
                                    <div id="proc_container_initial"></div>
                                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addServiceRow('proc_container_initial', 'procedure_req[]', 'Search Procedure...')"><i class="fa-solid fa-plus me-1"></i> Add Procedure</button>
                                </div>
                            </div>

                            <div class="d-none bg-light p-3 rounded mb-4 border" id="pharmacy_fields">
                                <h6 class="fw-bold mb-3">Add Prescriptions</h6>
                                <div id="rx_container_initial"></div>
                                <button type="button" class="btn btn-sm btn-outline-info mt-2" onclick="addRxRow('rx_container_initial')"><i class="fa-solid fa-plus me-1"></i> Add Medication</button>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary btn-lg"><i class="fa-solid fa-check-circle me-1"></i> Save & Route Patient</button>
                            </div>
                        </form>
                    </div>
                </div>

            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<datalist id="services_list">
    <?php foreach ($services_list as $service): ?>
        <option value="<?php echo htmlspecialchars($service); ?>">
    <?php endforeach; ?>
</datalist>

<datalist id="medications_list">
    <?php foreach ($medications_list as $med): 
        $generic = !empty($med['generic_name']) ? htmlspecialchars($med['generic_name']) : 'N/A';
    ?>
        <option value="<?php echo htmlspecialchars($med['item_name']); ?>">Gen: <?php echo $generic; ?></option>
    <?php endforeach; ?>
</datalist>

<script>
// Logic to dynamically generate multiple test/service request rows
function addServiceRow(containerId, fieldName, placeholder) {
    const container = document.getElementById(containerId);
    const row = document.createElement('div');
    row.className = 'input-group input-group-sm mb-2';
    row.innerHTML = `
        <input type="text" class="form-control" list="services_list" name="${fieldName}" placeholder="${placeholder}" required>
        <button type="button" class="btn btn-danger" onclick="this.parentElement.remove()"><i class="fa-solid fa-trash"></i></button>
    `;
    container.appendChild(row);
}

// Logic to dynamically generate multiple prescription rows
function addRxRow(containerId) {
    const container = document.getElementById(containerId);
    const row = document.createElement('div');
    row.className = 'row gx-2 mb-2 align-items-center';
    row.innerHTML = `
        <div class="col-md-3">
            <input type="text" class="form-control form-control-sm" list="medications_list" name="medication_name[]" placeholder="Search Drug..." required>
        </div>
        <div class="col-md-2">
            <input type="text" class="form-control form-control-sm" name="dose[]" placeholder="Dose (e.g. 500mg)" required>
        </div>
        <div class="col-md-2">
            <input type="text" class="form-control form-control-sm" name="frequency[]" placeholder="Freq (e.g. BD)" required>
        </div>
        <div class="col-md-2">
            <select class="form-select form-select-sm" name="route[]" required>
                <option value="PO (Oral)">PO</option>
                <option value="IV">IV</option>
                <option value="IM">IM</option>
                <option value="Topical">Topical</option>
                <option value="Subcut">Subcut</option>
            </select>
        </div>
        <div class="col-md-2">
            <input type="text" class="form-control form-control-sm" name="duration[]" placeholder="Days" required>
        </div>
        <div class="col-md-1 text-end">
            <button type="button" class="btn btn-danger btn-sm w-100" onclick="this.parentElement.parentElement.remove()">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    `;
    container.appendChild(row);
}

// Logic to toggle fields required status based on routing
function toggleRequiredFields(containerId, isRequired) {
    const inputs = document.getElementById(containerId).querySelectorAll('input, select');
    inputs.forEach(input => input.required = isRequired);
}

// Logic for the initial consult dropdown
function toggleRoutingFields() {
    var route = document.getElementById('next_stage').value;
    var labFields = document.getElementById('lab_fields');
    var radFields = document.getElementById('radiology_fields');
    var procFields = document.getElementById('procedure_fields');
    var rxFields = document.getElementById('pharmacy_fields');

    labFields.classList.add('d-none');
    radFields.classList.add('d-none');
    procFields.classList.add('d-none');
    rxFields.classList.add('d-none');
    
    toggleRequiredFields('lab_container_initial', false);
    toggleRequiredFields('rad_container_initial', false);
    toggleRequiredFields('proc_container_initial', false);
    toggleRequiredFields('rx_container_initial', false);

    if (route === 'Lab') {
        labFields.classList.remove('d-none');
        if(document.getElementById('lab_container_initial').children.length === 0) {
            addServiceRow('lab_container_initial', 'lab_test[]', 'Search Lab Test...');
        }
        toggleRequiredFields('lab_container_initial', true);
    } else if (route === 'Radiology') {
        radFields.classList.remove('d-none');
        if(document.getElementById('rad_container_initial').children.length === 0) {
            addServiceRow('rad_container_initial', 'radiology_test[]', 'Search Radiology...');
        }
        toggleRequiredFields('rad_container_initial', true);
    } else if (route === 'Procedure') {
        procFields.classList.remove('d-none');
        if(document.getElementById('proc_container_initial').children.length === 0) {
            addServiceRow('proc_container_initial', 'procedure_req[]', 'Search Procedure...');
        }
        toggleRequiredFields('proc_container_initial', true);
    } else if (route === 'Pharmacy') {
        rxFields.classList.remove('d-none');
        if(document.getElementById('rx_container_initial').children.length === 0) {
            addRxRow('rx_container_initial');
        }
        toggleRequiredFields('rx_container_initial', true);
    }
}

// Logic for the final routing dropdown (after lab)
function toggleFinalRoutingFields() {
    var route = document.getElementById('next_stage_final').value;
    var radFields = document.getElementById('radiology_fields_final');
    var procFields = document.getElementById('procedure_fields_final');
    var rxFields = document.getElementById('pharmacy_fields_final');

    radFields.classList.add('d-none');
    procFields.classList.add('d-none');
    rxFields.classList.add('d-none');
    
    toggleRequiredFields('rad_container_final', false);
    toggleRequiredFields('proc_container_final', false);
    toggleRequiredFields('rx_container_final', false);

    if (route === 'Radiology') {
        radFields.classList.remove('d-none');
        if(document.getElementById('rad_container_final').children.length === 0) {
            addServiceRow('rad_container_final', 'radiology_test[]', 'Search Radiology...');
        }
        toggleRequiredFields('rad_container_final', true);
    } else if (route === 'Procedure') {
        procFields.classList.remove('d-none');
        if(document.getElementById('proc_container_final').children.length === 0) {
            addServiceRow('proc_container_final', 'procedure_req[]', 'Search Procedure...');
        }
        toggleRequiredFields('proc_container_final', true);
    } else if (route === 'Pharmacy') {
        rxFields.classList.remove('d-none');
        if(document.getElementById('rx_container_final').children.length === 0) {
            addRxRow('rx_container_final');
        }
        toggleRequiredFields('rx_container_final', true);
    }
}
</script>

<?php
include '../../includes/footer.php';
?>