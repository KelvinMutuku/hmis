<?php
// modules/procedure/index.php

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../index.php");
    exit();
}

// Security Check
$user_role = isset($_SESSION['role']) ? (string)$_SESSION['role'] : '';
if (strpos($user_role, 'Admin') === false && strpos($user_role, 'Procedure') === false) {
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

// Handle form submission (Saving Procedure Notes)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $visit_id = intval($_POST['visit_id']);
    
    // Save notes for each procedure requested
    if (isset($_POST['proc_ids']) && is_array($_POST['proc_ids'])) {
        $proc_ids = $_POST['proc_ids'];
        $notes = $_POST['notes'];

        for ($i = 0; $i < count($proc_ids); $i++) {
            $p_id = intval($proc_ids[$i]);
            $note = $conn->real_escape_string($notes[$i]);
            
            if (!empty($note)) {
                $conn->query("UPDATE procedures SET notes = '$note', status = 'Completed' WHERE id = $p_id");
            }
        }

        // Send patient back to Doctor for review
        $sql_update_visit = "UPDATE visits SET current_stage = 'Doctor' WHERE id = $visit_id";
        if ($conn->query($sql_update_visit) === TRUE) {
            $message = "Procedure completed and notes saved successfully. Patient routed back to the Doctor.";
            $messageType = "success";
            $selected_visit_id = null;
        } else {
            $message = "Failed to route patient: " . $conn->error;
            $messageType = "danger";
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

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Procedure Room</h1>
</div>

<?php if ($message != ''): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show shadow-sm" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm h-100 border-danger">
            <div class="card-header bg-danger text-white fw-bold">
                <i class="fa-solid fa-scissors me-2"></i> Procedure Queue
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php
                    $queue_query = "SELECT v.id AS visit_id, p.first_name, p.last_name, v.visit_date 
                                    FROM visits v 
                                    JOIN patients p ON v.patient_id = p.id 
                                    WHERE v.current_stage = 'Procedure' 
                                    ORDER BY v.visit_date ASC";
                    $queue_result = $conn->query($queue_query);

                    if ($queue_result && $queue_result->num_rows > 0) {
                        while ($row = $queue_result->fetch_assoc()) {
                            $active_class = ($selected_visit_id == $row['visit_id']) ? 'active bg-danger border-danger text-white' : '';
                            $text_class = ($selected_visit_id == $row['visit_id']) ? 'text-white fw-bold' : 'text-danger fw-bold';
                            
                            echo "<a href='index.php?visit_id={$row['visit_id']}' class='list-group-item list-group-item-action {$active_class}'>";
                            echo "<div class='d-flex justify-content-between align-items-center mb-1'>";
                            echo "<h6 class='mb-0 {$text_class}'><i class='fa-regular fa-user me-2'></i>{$row['first_name']} {$row['last_name']}</h6>";
                            echo "<small>" . date('H:i', strtotime($row['visit_date'])) . "</small>";
                            echo "</div>";
                            echo "</a>";
                        }
                    } else {
                        echo "<div class='p-4 text-center text-muted'>No patients waiting.</div>";
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
                    <i class="fa-solid fa-scissors fa-4x mb-3 text-secondary" style="opacity: 0.5;"></i>
                    <h5>Select a patient from the queue to begin procedure.</h5>
                </div>
            </div>
        <?php else: ?>
            <div class="card shadow-sm border-dark">
                <div class="card-header bg-dark text-white fw-bold">
                    Procedure Notes
                </div>
                <div class="card-body">
                    <h5 class="text-primary fw-bold mb-4 border-bottom pb-2">Patient: <?php echo $patient_data['first_name'] . ' ' . $patient_data['last_name']; ?></h5>
                    
                    <form method="POST" action="index.php">
                        <input type="hidden" name="visit_id" value="<?php echo $selected_visit_id; ?>">
                        
                        <?php
                        $proc_query = "SELECT * FROM procedures WHERE visit_id = $selected_visit_id AND status = 'Pending'";
                        $proc_result = $conn->query($proc_query);

                        if ($proc_result && $proc_result->num_rows > 0) {
                            while ($proc = $proc_result->fetch_assoc()) {
                                echo "<div class='mb-4 bg-light p-3 border rounded'>";
                                echo "<h6 class='fw-bold text-dark mb-2'><i class='fa-solid fa-clipboard-check me-2 text-danger'></i> Requested Procedure: {$proc['procedure_requested']}</h6>";
                                echo "<input type='hidden' name='proc_ids[]' value='{$proc['id']}'>";
                                echo "<label class='form-label fw-bold mt-2'>Procedure Notes / Details</label>";
                                echo "<textarea class='form-control border-danger' name='notes[]' rows='4' required placeholder='Enter execution notes, materials used, patient reaction, etc...'></textarea>";
                                echo "</div>";
                            }
                        } else {
                            echo "<div class='alert alert-info'>No pending procedure requests found for this visit.</div>";
                        }
                        ?>

                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn btn-danger text-white fw-bold btn-lg shadow-sm">
                                <i class="fa-solid fa-check-circle me-2"></i> Complete Procedure & Return
                            </button>
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