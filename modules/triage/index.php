<?php
// modules/triage/index.php

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../index.php");
    exit();
}
if ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Nurse') {
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
    
    // Stop the rest of the page from loading
    exit();
}
include '../../includes/db_connect.php';

$message = '';
$messageType = '';
$selected_visit_id = isset($_GET['visit_id']) ? intval($_GET['visit_id']) : null;
$selected_patient_name = "No patient selected";

// Handle form submission (Saving Vitals)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $visit_id = intval($_POST['visit_id']);
    
    // Sanitize and format the inputs
    $bp = $conn->real_escape_string($_POST['blood_pressure']);
    $pulse = isset($_POST['pulse_rate']) && $_POST['pulse_rate'] !== '' ? intval($_POST['pulse_rate']) : 0;
    $spo2 = isset($_POST['spo2']) && $_POST['spo2'] !== '' ? intval($_POST['spo2']) : 0;
    $temp = floatval($_POST['temperature']);
    $weight = floatval($_POST['weight']);
    $height = floatval($_POST['height']);
    $notes = $conn->real_escape_string($_POST['notes']);

    // Calculate BMI safely in PHP before saving to the database
    $bmi = 0;
    if ($weight > 0 && $height > 0) {
        $bmi = round($weight / ($height * $height), 2);
    }

    // 1. Insert vitals into the triage table
    $sql_triage = "INSERT INTO triage (visit_id, blood_pressure, pulse_rate, spo2, temperature, weight, height, bmi, notes) 
                   VALUES ($visit_id, '$bp', $pulse, $spo2, $temp, $weight, $height, $bmi, '$notes')";

    if ($conn->query($sql_triage) === TRUE) {
        // 2. Update the visit stage to 'Doctor'
        $sql_update_visit = "UPDATE visits SET current_stage = 'Doctor' WHERE id = $visit_id";
        
        if ($conn->query($sql_update_visit) === TRUE) {
            $message = "Vitals recorded successfully. Patient sent to the Doctor's queue.";
            $messageType = "success";
            $selected_visit_id = null; // Clear the selection
        } else {
            $message = "Vitals saved, but failed to update visit stage: " . $conn->error;
            $messageType = "warning";
        }
    } else {
        $message = "Error saving vitals: " . $conn->error;
        $messageType = "danger";
    }
}

// Fetch selected patient details if one was clicked from the queue
if ($selected_visit_id) {
    $patient_query = "SELECT p.first_name, p.last_name FROM visits v 
                      JOIN patients p ON v.patient_id = p.id 
                      WHERE v.id = $selected_visit_id";
    $patient_result = $conn->query($patient_query);
    if ($patient_result && $patient_result->num_rows > 0) {
        $row = $patient_result->fetch_assoc();
        $selected_patient_name = $row['first_name'] . ' ' . $row['last_name'];
    }
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Triage Station</h1>
</div>

<?php if ($message != ''): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-5 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-warning text-dark fw-bold">
                <i class="fa-solid fa-list-ol me-2"></i> Waiting for Triage
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php
                    $queue_query = "SELECT v.id AS visit_id, p.first_name, p.last_name, p.gender, v.visit_date 
                                    FROM visits v 
                                    JOIN patients p ON v.patient_id = p.id 
                                    WHERE v.current_stage = 'Reception' 
                                    ORDER BY v.visit_date ASC";
                    $queue_result = $conn->query($queue_query);

                    if ($queue_result && $queue_result->num_rows > 0) {
                        while ($row = $queue_result->fetch_assoc()) {
                            $active_class = ($selected_visit_id == $row['visit_id']) ? 'active bg-warning border-warning' : '';
                            $text_class = ($selected_visit_id == $row['visit_id']) ? 'text-dark' : '';
                            
                            echo "<a href='index.php?visit_id={$row['visit_id']}' class='list-group-item list-group-item-action {$active_class}'>";
                            echo "<div class='d-flex w-100 justify-content-between'>";
                            echo "<h6 class='mb-1 {$text_class}'><i class='fa-regular fa-user me-2'></i>{$row['first_name']} {$row['last_name']}</h6>";
                            echo "<small class='{$text_class}'>" . date('H:i', strtotime($row['visit_date'])) . "</small>";
                            echo "</div>";
                            echo "<small class='{$text_class}'>Gender: {$row['gender']} | Visit ID: {$row['visit_id']}</small>";
                            echo "</a>";
                        }
                    } else {
                        echo "<div class='p-4 text-center text-muted'>No patients currently waiting for triage.</div>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-7 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-primary text-white fw-bold">
                <i class="fa-solid fa-heart-pulse me-2"></i> Record Vitals
            </div>
            <div class="card-body">
                
                <?php if (!$selected_visit_id): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fa-solid fa-hand-pointer fa-3x mb-3 text-light"></i>
                        <h5>Please select a patient from the queue to record vitals.</h5>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <strong>Selected Patient:</strong> <?php echo $selected_patient_name; ?>
                    </div>

                    <form method="POST" action="index.php">
                        <input type="hidden" name="visit_id" value="<?php echo $selected_visit_id; ?>">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="blood_pressure" class="form-label">Blood Pressure (mmHg) *</label>
                                <input type="text" class="form-control" id="blood_pressure" name="blood_pressure" placeholder="e.g., 120/80" required>
                            </div>
                            <div class="col-md-6">
                                <label for="temperature" class="form-label">Temperature (°C) *</label>
                                <input type="number" step="0.1" class="form-control" id="temperature" name="temperature" placeholder="e.g., 37.2" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="pulse_rate" class="form-label">Pulse Rate (bpm)</label>
                                <input type="number" class="form-control" id="pulse_rate" name="pulse_rate" placeholder="e.g., 75">
                            </div>
                            <div class="col-md-6">
                                <label for="spo2" class="form-label">SpO2 (%)</label>
                                <input type="number" class="form-control" id="spo2" name="spo2" placeholder="e.g., 98">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="weight" class="form-label">Weight (kg) *</label>
                                <input type="number" step="0.1" class="form-control" id="weight" name="weight" placeholder="e.g., 70.5" required oninput="calculateBMI()">
                            </div>
                            <div class="col-md-4">
                                <label for="height" class="form-label">Height (m) *</label>
                                <input type="number" step="0.01" class="form-control" id="height" name="height" placeholder="e.g., 1.75" required oninput="calculateBMI()">
                            </div>
                            <div class="col-md-4">
                                <label for="bmi" class="form-label">BMI</label>
                                <input type="text" class="form-control bg-light fw-bold text-primary" id="bmi" name="bmi" readonly placeholder="Auto-calculated">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="notes" class="form-label">Nursing Notes / Allergies</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Any initial observations..."></textarea>
                        </div>

                        <div class="d-flex justify-content-end">
                            <a href="index.php" class="btn btn-secondary me-2">Cancel</a>
                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane me-1"></i> Save & Send to Doctor</button>
                        </div>
                    </form>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script>
function calculateBMI() {
    var weightInput = document.getElementById('weight').value;
    var heightInput = document.getElementById('height').value;
    var bmiField = document.getElementById('bmi');

    var weight = parseFloat(weightInput);
    var height = parseFloat(heightInput);

    // Only calculate if both values are valid numbers and height is greater than 0
    if (!isNaN(weight) && !isNaN(height) && height > 0) {
        var bmi = weight / (height * height);
        bmiField.value = bmi.toFixed(2); // Rounds to 2 decimal places
    } else {
        bmiField.value = ''; // Clear the field if inputs are deleted or invalid
    }
}
</script>

<?php
include '../../includes/footer.php';
?>