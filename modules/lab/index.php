<?php
// modules/lab/index.php

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../index.php");
    exit();
}
if ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Lab Technician') {
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
$selected_lab_id = isset($_GET['lab_id']) ? intval($_GET['lab_id']) : null;
$lab_data = null;

// Handle form submission (Saving Lab Results)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $lab_id = intval($_POST['lab_id']);
    $visit_id = intval($_POST['visit_id']);
    $test_results = $conn->real_escape_string($_POST['test_results']);
    
    // 1. Update the lab_tests table
    $sql_update_lab = "UPDATE lab_tests 
                       SET test_results = '$test_results', status = 'Completed' 
                       WHERE id = $lab_id";

    if ($conn->query($sql_update_lab) === TRUE) {
        
        // 2. Route the patient back to the Doctor to review results
        $sql_update_visit = "UPDATE visits SET current_stage = 'Doctor' WHERE id = $visit_id";
        
        if ($conn->query($sql_update_visit) === TRUE) {
            $message = "Results saved successfully. Patient routed back to the Doctor.";
            $messageType = "success";
            $selected_lab_id = null; // Clear selection after saving
        } else {
            $message = "Results saved, but failed to route patient: " . $conn->error;
            $messageType = "warning";
        }
    } else {
        $message = "Error saving results: " . $conn->error;
        $messageType = "danger";
    }
}

// Fetch selected lab test details if one was clicked
if ($selected_lab_id) {
    $lab_query = "SELECT l.*, p.first_name, p.last_name, p.gender, v.id as visit_id 
                  FROM lab_tests l 
                  JOIN visits v ON l.visit_id = v.id 
                  JOIN patients p ON v.patient_id = p.id 
                  WHERE l.id = $selected_lab_id";
    $lab_result = $conn->query($lab_query);
    if ($lab_result && $lab_result->num_rows > 0) {
        $lab_data = $lab_result->fetch_assoc();
    }
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Laboratory</h1>
</div>

<?php if ($message != ''): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Left Column: Pending Lab Requests -->
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-dark text-white fw-bold">
                <i class="fa-solid fa-microscope me-2"></i> Pending Tests
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php
                    // Fetch pending tests
                    $queue_query = "SELECT l.id AS lab_id, l.test_requested, p.first_name, p.last_name, l.updated_at 
                                    FROM lab_tests l 
                                    JOIN visits v ON l.visit_id = v.id 
                                    JOIN patients p ON v.patient_id = p.id 
                                    WHERE l.status = 'Pending' 
                                    ORDER BY l.updated_at ASC";
                    $queue_result = $conn->query($queue_query);

                    if ($queue_result && $queue_result->num_rows > 0) {
                        while ($row = $queue_result->fetch_assoc()) {
                            $active_class = ($selected_lab_id == $row['lab_id']) ? 'active bg-dark border-dark text-white' : '';
                            $text_class = ($selected_lab_id == $row['lab_id']) ? 'text-white' : 'text-primary';
                            
                            echo "<a href='index.php?lab_id={$row['lab_id']}' class='list-group-item list-group-item-action {$active_class}'>";
                            echo "<div class='d-flex w-100 justify-content-between'>";
                            echo "<h6 class='mb-1'><i class='fa-solid fa-vial me-2'></i>{$row['first_name']} {$row['last_name']}</h6>";
                            echo "</div>";
                            echo "<small class='{$text_class} fw-bold'>Test: {$row['test_requested']}</small>";
                            echo "</a>";
                        }
                    } else {
                        echo "<div class='p-4 text-center text-muted'>No pending lab requests.</div>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Results Entry Form -->
    <div class="col-md-8 mb-4">
        
        <?php if (!$selected_lab_id): ?>
            <div class="card shadow-sm h-100 d-flex justify-content-center align-items-center bg-light">
                <div class="text-center text-muted py-5">
                    <i class="fa-solid fa-flask fa-4x mb-3 text-secondary" style="opacity: 0.5;"></i>
                    <h5>Select a pending test from the queue to enter results.</h5>
                </div>
            </div>
        <?php else: ?>
            
            <div class="card shadow-sm border-dark">
                <div class="card-header bg-white fw-bold">
                    Enter Test Results
                </div>
                <div class="card-body">
                    
                    <!-- Patient & Request Details -->
                    <div class="alert alert-secondary">
                        <div class="row">
                            <div class="col-md-6 border-end">
                                <strong>Patient:</strong> <?php echo $lab_data['first_name'] . ' ' . $lab_data['last_name']; ?><br>
                                <strong>Gender:</strong> <?php echo $lab_data['gender']; ?>
                            </div>
                            <div class="col-md-6 text-danger">
                                <strong>Test Requested:</strong><br>
                                <i class="fa-solid fa-arrow-right me-1"></i> <?php echo $lab_data['test_requested']; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Results Form -->
                    <form method="POST" action="index.php">
                        <input type="hidden" name="lab_id" value="<?php echo $lab_data['id']; ?>">
                        <input type="hidden" name="visit_id" value="<?php echo $lab_data['visit_id']; ?>">

                        <div class="mb-4">
                            <label for="test_results" class="form-label fw-bold">Test Results / Findings *</label>
                            <textarea class="form-control border-dark" id="test_results" name="test_results" rows="5" required placeholder="Type the lab findings here..."></textarea>
                        </div>

                        <div class="d-flex justify-content-end align-items-center mt-3">
                            <span class="text-muted me-3 small"><i class="fa-solid fa-info-circle"></i> Saving will send the patient back to the Doctor.</span>
                            <button type="submit" class="btn btn-dark btn-lg"><i class="fa-solid fa-file-medical me-1"></i> Submit Results</button>
                        </div>
                    </form>
                </div>
            </div>

        <?php endif; ?>
    </div>
</div>

<?php
include '../../includes/footer.php';
?>