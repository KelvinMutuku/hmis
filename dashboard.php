<?php
// dashboard.php

// 1. Start the session and check if the user is logged in
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

// 2. Include the database connection
include 'includes/db_connect.php';

$message = '';
$messageType = '';

// Handle Appointment Completion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'complete_appointment') {
    $apt_id = intval($_POST['appointment_id']);
    $sql_update = "UPDATE appointments SET status = 'Completed' WHERE id = $apt_id";
    
    if ($conn->query($sql_update) === TRUE) {
        $message = "Appointment successfully marked as completed.";
        $messageType = "success";
    } else {
        $message = "Error updating appointment: " . $conn->error;
        $messageType = "danger";
    }
}

// 3. Initialize metric variables
$total_patients = 0;
$total_encounters = 0;
$patients_today = 0;
$encounters_today = 0;
$pending_collections = 0;

// 4. Fetch metrics safely
$query1 = @$conn->query("SELECT COUNT(id) AS count FROM patients");
if ($query1) $total_patients = $query1->fetch_assoc()['count'];

$query2 = @$conn->query("SELECT COUNT(id) AS count FROM visits");
if ($query2) $total_encounters = $query2->fetch_assoc()['count'];

$query3 = @$conn->query("SELECT COUNT(id) AS count FROM patients WHERE DATE(created_at) = CURDATE()");
if ($query3) $patients_today = $query3->fetch_assoc()['count'];

$query4 = @$conn->query("SELECT COUNT(id) AS count FROM visits WHERE DATE(visit_date) = CURDATE()");
if ($query4) $encounters_today = $query4->fetch_assoc()['count'];

// Pending Collections Query
$query5 = @$conn->query("SELECT SUM(total_amount - amount_paid) AS pending_total FROM accounts WHERE payment_status != 'Paid'");
if ($query5) $pending_collections = $query5->fetch_assoc()['pending_total'] ?? 0;

// 5. Include the Header and Sidebar
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Overview Dashboard</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-calendar"></i> <?php echo date('F j, Y'); ?></button>
        </div>
    </div>
</div>

<?php if ($message != ''): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show shadow-sm" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-2 col-md-4 mb-4">
        <div class="card bg-primary text-white shadow-sm h-100">
            <div class="card-body p-3">
                <h6 class="card-title text-uppercase text-white-50 small">Total Patients</h6>
                <h3 class="mb-0 fw-bold"><?php echo $total_patients; ?></h3>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 mb-4">
        <div class="card bg-success text-white shadow-sm h-100">
            <div class="card-body p-3">
                <h6 class="card-title text-uppercase text-white-50 small">Total Encounters</h6>
                <h3 class="mb-0 fw-bold"><?php echo $total_encounters; ?></h3>
            </div>
        </div>
    </div>

    <div class="col-lg-2 col-md-4 mb-4">
        <div class="card bg-warning text-dark shadow-sm h-100">
            <div class="card-body p-3">
                <h6 class="card-title text-uppercase text-dark-50 small" style="opacity: 0.7;">Patients Today</h6>
                <h3 class="mb-0 fw-bold"><?php echo $patients_today; ?></h3>
            </div>
        </div>
    </div>

    <div class="col-lg-2 col-md-4 mb-4">
        <div class="card bg-danger text-white shadow-sm h-100">
            <div class="card-body p-3">
                <h6 class="card-title text-uppercase text-white-50 small">Encounters Today</h6>
                <h3 class="mb-0 fw-bold"><?php echo $encounters_today; ?></h3>
            </div>
        </div>
    </div>

    <div class="col-lg-2 col-md-4 mb-4">
        <div class="card bg-dark text-white shadow-sm h-100 border-0">
            <div class="card-body p-3">
                <h6 class="card-title text-uppercase text-white-50 small">Pending Collections</h6>
                <h4 class="mb-0 fw-bold text-warning">KES <?php echo number_format($pending_collections, 2); ?></h4>
            </div>
        </div>
    </div>
</div>

<div class="row mt-2">
    <div class="col-md-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-bold">
                <i class="fa-solid fa-clock-rotate-left me-2 text-secondary"></i> Recent Patient Encounters
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date & Time</th>
                                <th>Patient Name</th>
                                <th>Visit ID</th>
                                <th>Current Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $recent_query = "SELECT v.id AS visit_id, v.visit_date, v.current_stage, p.first_name, p.last_name 
                                             FROM visits v 
                                             JOIN patients p ON v.patient_id = p.id 
                                             ORDER BY v.visit_date DESC 
                                             LIMIT 6";
                            $recent_result = @$conn->query($recent_query);

                            if ($recent_result && $recent_result->num_rows > 0) {
                                while ($row = $recent_result->fetch_assoc()) {
                                    $badge_class = 'bg-secondary';
                                    switch($row['current_stage']) {
                                        case 'Reception': $badge_class = 'bg-primary'; break;
                                        case 'Triage': $badge_class = 'bg-warning text-dark'; break;
                                        case 'Doctor': $badge_class = 'bg-success'; break;
                                        case 'Lab': $badge_class = 'bg-danger'; break;
                                        case 'Radiology': $badge_class = 'bg-info text-dark'; break;
                                        case 'Procedure': $badge_class = 'bg-dark text-white'; break;
                                        case 'Pharmacy': $badge_class = 'bg-info text-dark'; break;
                                        case 'Accounts': $badge_class = 'bg-secondary'; break;
                                        case 'Cleared': $badge_class = 'bg-dark'; break;
                                    }
                                    echo "<tr>";
                                    echo "<td class='text-muted small'>" . date('M j, Y h:i A', strtotime($row['visit_date'])) . "</td>";
                                    echo "<td class='fw-bold text-dark'>{$row['first_name']} {$row['last_name']}</td>";
                                    echo "<td><span class='text-muted'>#{$row['visit_id']}</span></td>";
                                    echo "<td><span class='badge {$badge_class}'>{$row['current_stage']}</span></td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='4' class='text-center py-4 text-muted'>No recent patient encounters found.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-bold">
                Quick Actions
            </div>
            <div class="card-body d-grid gap-2">
                <a href="/modules/reception/index.php" class="btn btn-outline-primary text-start"><i class="fa-solid fa-user-plus me-2"></i> Register New Patient</a>
                <a href="/modules/triage/index.php" class="btn btn-outline-warning text-start"><i class="fa-solid fa-heart-pulse me-2"></i> Go to Triage</a>
                <a href="/modules/doctor/index.php" class="btn btn-outline-success text-start"><i class="fa-solid fa-user-doctor me-2"></i> Doctor Queue</a>
            </div>
        </div>

        <div class="card shadow-sm border-info border-top border-3">
            <div class="card-header bg-white fw-bold">
                <i class="fa-solid fa-calendar-check me-2 text-info"></i> Upcoming Appointments
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php
                    $apt_query = "SELECT a.id AS apt_id, a.appointment_date, a.notes, p.first_name, p.last_name 
                                  FROM appointments a 
                                  JOIN patients p ON a.patient_id = p.id 
                                  WHERE a.appointment_date >= CURDATE() AND a.status = 'Pending'
                                  ORDER BY a.appointment_date ASC 
                                  LIMIT 5";
                    $apt_result = @$conn->query($apt_query);

                    if ($apt_result && $apt_result->num_rows > 0) {
                        while ($apt = $apt_result->fetch_assoc()) {
                            $apt_date = date('M j, Y', strtotime($apt['appointment_date']));
                            $notes = !empty($apt['notes']) ? "<div class='text-muted small mt-1'><i class='fa-regular fa-comment-dots me-1'></i>{$apt['notes']}</div>" : "";
                            
                            echo "<div class='list-group-item py-3'>";
                            echo "<div class='d-flex justify-content-between align-items-start'>";
                            echo "<div>";
                            echo "<h6 class='mb-0 fw-bold'>{$apt['first_name']} {$apt['last_name']}</h6>";
                            echo $notes;
                            echo "</div>";
                            echo "<div class='text-end d-flex flex-column align-items-end'>";
                            echo "<span class='badge bg-info text-dark mb-2'>{$apt_date}</span>";
                            echo "<form method='POST' action='dashboard.php' class='m-0 p-0'>";
                            echo "<input type='hidden' name='action' value='complete_appointment'>";
                            echo "<input type='hidden' name='appointment_id' value='{$apt['apt_id']}'>";
                            echo "<button type='submit' class='btn btn-sm btn-outline-success fw-bold' onclick='return confirm(\"Mark this appointment as done?\");'><i class='fa-solid fa-check me-1'></i> Done</button>";
                            echo "</form>";
                            echo "</div>";
                            echo "</div>";
                            echo "</div>";
                        }
                    } else {
                        echo "<div class='p-4 text-center text-muted'><i class='fa-solid fa-calendar-xmark mb-2 d-block fa-2x text-light'></i>No upcoming appointments scheduled.</div>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include 'includes/footer.php';
?>