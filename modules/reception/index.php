<?php
// modules/reception/index.php

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../index.php");
    exit();
}

// Multi-role Security Check
$user_role = isset($_SESSION['role']) ? (string)$_SESSION['role'] : '';
if (strpos($user_role, 'Admin') === false && strpos($user_role, 'Receptionist') === false) {
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

// ==========================================
// HANDLE LIVE AJAX SEARCH (TYPE TO SEARCH)
// ==========================================
if (isset($_GET['ajax_search'])) {
    header('Content-Type: text/html');
    $q = $conn->real_escape_string(trim($_GET['ajax_search']));
    
    if ($q === '') {
        exit(); // Return nothing if empty
    }
    
    $sql_search = "SELECT * FROM patients 
                   WHERE (first_name LIKE '%$q%' 
                      OR last_name LIKE '%$q%' 
                      OR id_number LIKE '%$q%' 
                      OR phone LIKE '%$q%')
                      AND NOT (first_name = 'Walk-in' AND last_name = 'Client')
                   ORDER BY first_name ASC LIMIT 15";
                   
    $res = $conn->query($sql_search);
    
    if ($res && $res->num_rows > 0) {
        while ($p = $res->fetch_assoc()) {
            $age = '--';
            if (!empty($p['dob'])) {
                $dob = new DateTime($p['dob']);
                $now = new DateTime();
                $age = $now->diff($dob)->y . ' yrs';
            }
            $id_disp = !empty($p['id_number']) ? $p['id_number'] : 'N/A';
            $phone_disp = !empty($p['phone']) ? $p['phone'] : 'N/A';

            echo "<tr>";
            echo "<td class='fw-bold text-primary'>{$p['first_name']} {$p['last_name']}</td>";
            echo "<td>
                    <small class='d-block'><i class='fa-regular fa-id-card text-muted me-1'></i> {$id_disp}</small>
                    <small class='d-block'><i class='fa-solid fa-phone text-muted me-1'></i> {$phone_disp}</small>
                  </td>";
            echo "<td>{$p['gender']}<br><small class='text-muted'>{$age}</small></td>";
            echo "<td class='text-end'>
                    <form method='POST' action='index.php'>
                        <input type='hidden' name='action' value='check_in_existing'>
                        <input type='hidden' name='patient_id' value='{$p['id']}'>
                        <button type='submit' class='btn btn-success btn-sm fw-bold shadow-sm'>
                            <i class='fa-solid fa-arrow-right-to-bracket me-1'></i> Check-In
                        </button>
                    </form>
                  </td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='4' class='text-center py-3 text-muted'><i class='fa-solid fa-circle-exclamation me-2'></i>No patients found matching your search.</td></tr>";
    }
    exit(); // Stop loading the rest of the page for AJAX calls
}

$message = '';
$messageType = '';

// ==========================================
// HANDLE FORM SUBMISSIONS (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // ACTION 1: Register a Brand New Patient
    if (isset($_POST['action']) && $_POST['action'] == 'register_new') {
        $full_name = trim($_POST['full_name']);
        $name_parts = explode(' ', $full_name, 2);
        $first_name = $conn->real_escape_string($name_parts[0]);
        $last_name = isset($name_parts[1]) ? $conn->real_escape_string($name_parts[1]) : '';

        $gender = $conn->real_escape_string($_POST['gender']);
        $dob = $conn->real_escape_string($_POST['dob']);
        $id_number = $conn->real_escape_string(trim($_POST['id_number']));
        $phone = $conn->real_escape_string(trim($_POST['phone']));
        $address = $conn->real_escape_string($_POST['address']);

        $nok_name = $conn->real_escape_string($_POST['nok_name']);
        $nok_relationship = $conn->real_escape_string($_POST['nok_relationship']);
        $nok_phone = $conn->real_escape_string($_POST['nok_phone']);
        $nok_address = $conn->real_escape_string($_POST['nok_address']);

        // SMART DUPLICATE CHECK (Allows shared phone numbers for children)
        $duplicate_found = false;

        // Rule 1: If National ID is provided, it MUST be unique
        if (!empty($id_number)) {
            $chk_id = $conn->query("SELECT first_name, last_name FROM patients WHERE id_number = '$id_number' LIMIT 1");
            if ($chk_id && $chk_id->num_rows > 0) {
                $dup = $chk_id->fetch_assoc();
                $duplicate_found = true;
                $message = "<strong>Patient Already Exists!</strong> We found {$dup['first_name']} {$dup['last_name']} with that National ID. Please use the search tab to check them in.";
                $messageType = "danger";
            }
        }

        // Rule 2: Prevent accidental literal double-clicking by checking exact Name + Phone matches
        if (!$duplicate_found) {
            $phone_cond = !empty($phone) ? "AND phone = '$phone'" : "";
            $chk_name = $conn->query("SELECT id FROM patients WHERE first_name = '$first_name' AND last_name = '$last_name' $phone_cond LIMIT 1");
            if ($chk_name && $chk_name->num_rows > 0) {
                $duplicate_found = true;
                $message = "<strong>Patient Already Exists!</strong> A patient named $first_name $last_name with those exact details is already registered.";
                $messageType = "danger";
            }
        }

        // If no duplicate, proceed to save
        if (!$duplicate_found) {
            $sql_patient = "INSERT INTO patients (first_name, last_name, dob, gender, phone, address, id_number, nok_name, nok_relationship, nok_phone, nok_address) 
                            VALUES ('$first_name', '$last_name', '$dob', '$gender', '$phone', '$address', '$id_number', '$nok_name', '$nok_relationship', '$nok_phone', '$nok_address')";

            if ($conn->query($sql_patient) === TRUE) {
                $new_patient_id = $conn->insert_id;
                $sql_visit = "INSERT INTO visits (patient_id, current_stage) VALUES ('$new_patient_id', 'Reception')";
                
                if ($conn->query($sql_visit) === TRUE) {
                    $message = "Patient registered successfully and added to the visit queue!";
                    $messageType = "success";
                } else {
                    $message = "Patient created, but failed to start a visit: " . $conn->error;
                    $messageType = "warning";
                }
            } else {
                $message = "Error registering patient: " . $conn->error;
                $messageType = "danger";
            }
        }
    }
    
    // ACTION 2: Check-in an Existing Patient
    elseif (isset($_POST['action']) && $_POST['action'] == 'check_in_existing') {
        $patient_id = intval($_POST['patient_id']);
        $sql_visit = "INSERT INTO visits (patient_id, current_stage) VALUES ('$patient_id', 'Reception')";
        
        if ($conn->query($sql_visit) === TRUE) {
            $message = "Existing patient has been successfully checked in to the queue!";
            $messageType = "success";
        } else {
            $message = "Failed to start visit: " . $conn->error;
            $messageType = "danger";
        }
    }
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Reception Desk</h1>
</div>

<?php if ($message != ''): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show shadow-sm" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8 mb-4">
        
        <ul class="nav nav-tabs mb-3" id="receptionTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo ($_SERVER['REQUEST_METHOD'] != 'POST' || (isset($_POST['action']) && $_POST['action'] == 'check_in_existing')) ? 'active' : ''; ?> fw-bold text-dark" id="search-tab" data-bs-toggle="tab" data-bs-target="#search" type="button" role="tab">
                    <i class="fa-solid fa-magnifying-glass me-2 text-primary"></i> Find Existing Patient
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo (isset($_POST['action']) && $_POST['action'] == 'register_new') ? 'active' : ''; ?> fw-bold text-dark" id="register-tab" data-bs-toggle="tab" data-bs-target="#register" type="button" role="tab">
                    <i class="fa-solid fa-user-plus me-2 text-success"></i> Register New
                </button>
            </li>
        </ul>

        <div class="tab-content" id="receptionTabsContent">
            
            <div class="tab-pane fade <?php echo ($_SERVER['REQUEST_METHOD'] != 'POST' || (isset($_POST['action']) && $_POST['action'] == 'check_in_existing')) ? 'show active' : ''; ?>" id="search" role="tabpanel">
                <div class="card shadow-sm border-primary border-top border-3">
                    <div class="card-body">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">Live Search Database</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light"><i class="fa-solid fa-search text-primary"></i></span>
                                <input type="text" class="form-control border-primary" id="liveSearchInput" placeholder="Type Name, ID Number, or Phone to instantly search..." autocomplete="off" onkeyup="performLiveSearch(this.value)">
                            </div>
                        </div>

                        <h6 class="fw-bold text-muted mb-3 d-none" id="searchTitle">Search Results</h6>
                        <div class="table-responsive d-none" id="searchResultsContainer">
                            <table class="table table-hover align-middle border">
                                <thead class="table-light">
                                    <tr>
                                        <th>Patient Name</th>
                                        <th>ID / Phone</th>
                                        <th>Gender/Age</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="searchResultsBody">
                                    </tbody>
                            </table>
                        </div>
                        
                    </div>
                </div>
            </div>

            <div class="tab-pane fade <?php echo (isset($_POST['action']) && $_POST['action'] == 'register_new') ? 'show active' : ''; ?>" id="register" role="tabpanel">
                <div class="card shadow-sm border-success border-top border-3">
                    <div class="card-body">
                        <form method="POST" action="index.php">
                            <input type="hidden" name="action" value="register_new">
                            
                            <h6 class="fw-bold text-success mb-3 border-bottom pb-2">Patient Details</h6>
                            
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <label for="full_name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" placeholder="Enter first and last name" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="gender" class="form-label">Gender *</label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="" selected disabled>Select...</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="dob" class="form-label">Date of Birth *</label>
                                    <input type="date" class="form-control" id="dob" name="dob" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="id_number" class="form-label">ID No</label>
                                    <input type="text" class="form-control border-warning" id="id_number" name="id_number" placeholder="National ID (Optional for Kids)">
                                </div>
                                <div class="col-md-4">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="text" class="form-control border-warning" id="phone" name="phone" placeholder="Parent/Guardian if minor">
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <label for="address" class="form-label">Residential Address</label>
                                    <input type="text" class="form-control" id="address" name="address" placeholder="e.g., Valley View Estate, Mlolongo">
                                </div>
                            </div>

                            <h6 class="fw-bold text-success mb-3 border-bottom pb-2 mt-4">Next of Kin Details</h6>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="nok_name" class="form-label">Name</label>
                                    <input type="text" class="form-control" id="nok_name" name="nok_name">
                                </div>
                                <div class="col-md-6">
                                    <label for="nok_relationship" class="form-label">Relationship to Patient</label>
                                    <input type="text" class="form-control" id="nok_relationship" name="nok_relationship" placeholder="e.g., Mother, Father, Spouse">
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <label for="nok_phone" class="form-label">Phone Number</label>
                                    <input type="text" class="form-control" id="nok_phone" name="nok_phone">
                                </div>
                                <div class="col-md-8">
                                    <label for="nok_address" class="form-label">Address</label>
                                    <input type="text" class="form-control" id="nok_address" name="nok_address">
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-4">
                                <button type="reset" class="btn btn-secondary me-2">Clear Form</button>
                                <button type="submit" class="btn btn-success btn-lg shadow-sm"><i class="fa-solid fa-save me-1"></i> Register & Check-In</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-bold">
                Today's Queue
            </div>
            <div class="card-body text-center">
                <p class="text-muted mb-1">Patients waiting at Reception</p>
                <?php
                $queue_query = "SELECT COUNT(id) AS waiting FROM visits WHERE current_stage = 'Reception' AND DATE(visit_date) = CURDATE()";
                $queue_result = $conn->query($queue_query);
                $waiting = $queue_result ? $queue_result->fetch_assoc()['waiting'] : 0;
                ?>
                <h1 class="display-4 text-primary fw-bold"><?php echo $waiting; ?></h1>
            </div>
        </div>
    </div>
</div>

<script>
// JavaScript to handle the Live Search functionality via AJAX
function performLiveSearch(query) {
    const resultsContainer = document.getElementById('searchResultsContainer');
    const resultsTitle = document.getElementById('searchTitle');
    const resultsBody = document.getElementById('searchResultsBody');

    // Only start searching after 2 characters are typed to reduce database load
    if (query.trim().length < 2) {
        resultsContainer.classList.add('d-none');
        resultsTitle.classList.add('d-none');
        return;
    }

    // Fetch the data from this same file using the ajax_search parameter
    fetch('index.php?ajax_search=' + encodeURIComponent(query))
        .then(response => response.text())
        .then(data => {
            resultsBody.innerHTML = data;
            resultsContainer.classList.remove('d-none');
            resultsTitle.classList.remove('d-none');
            resultsTitle.innerText = 'Search Results for "' + query + '"';
        })
        .catch(error => console.error('Error fetching search results:', error));
}
</script>

<?php
include '../../includes/footer.php';
?>