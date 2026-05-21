<?php
// modules/admin/staff.php

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../index.php");
    exit();
}

// Check if the user has Admin privileges using strpos to support multi-roles
if (strpos($_SESSION['role'], 'Admin') === false) {
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
$active_tab = 'directory'; // Default tab

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Action 1: Add New Staff Member
    if (isset($_POST['action']) && $_POST['action'] == 'add_staff') {
        $full_name = $conn->real_escape_string($_POST['full_name']);
        $username = $conn->real_escape_string($_POST['username']);
        $password = $conn->real_escape_string($_POST['password']);
        
        // Handle multiple roles array
        $roles = isset($_POST['roles']) ? $_POST['roles'] : [];
        $role_string = $conn->real_escape_string(implode(',', $roles));

        if (empty($role_string)) {
            $message = "Error: You must select at least one role.";
            $messageType = "danger";
        } else {
            // Check if username already exists
            $check = $conn->query("SELECT id FROM users WHERE username = '$username'");
            if ($check->num_rows > 0) {
                $message = "Error: That username is already taken.";
                $messageType = "danger";
            } else {
                $sql = "INSERT INTO users (full_name, username, password, role) VALUES ('$full_name', '$username', '$password', '$role_string')";
                if ($conn->query($sql) === TRUE) {
                    $message = "New staff member added successfully.";
                    $messageType = "success";
                } else {
                    $message = "Database Error: " . $conn->error;
                    $messageType = "danger";
                }
            }
        }
        $active_tab = 'directory';
    }
    
    // Action 2: Update Password
    elseif (isset($_POST['action']) && $_POST['action'] == 'update_password') {
        $user_id = intval($_POST['user_id']);
        $new_password = $conn->real_escape_string($_POST['new_password']);

        $sql = "UPDATE users SET password = '$new_password' WHERE id = $user_id";
        if ($conn->query($sql) === TRUE) {
            $message = "Password updated successfully.";
            $messageType = "success";
        } else {
            $message = "Error updating password: " . $conn->error;
            $messageType = "danger";
        }
        $active_tab = 'directory';
    }

    // Action 3: Delete Single Staff Member
    elseif (isset($_POST['action']) && $_POST['action'] == 'delete_staff') {
        $user_id = intval($_POST['user_id']);
        
        if ($user_id === 1) {
            $message = "Error: You cannot delete the master Admin account.";
            $messageType = "danger";
        } else {
            $sql = "DELETE FROM users WHERE id = $user_id";
            if ($conn->query($sql) === TRUE) {
                $message = "Staff member removed from the system.";
                $messageType = "success";
            }
        }
        $active_tab = 'directory';
    }

    // Action 4: CLEAR SELECTED DATA
    elseif (isset($_POST['action']) && $_POST['action'] == 'clear_selected_data') {
        $cleared_items = [];
        $error_items = [];

        // 1. Clear Inventory
        if (isset($_POST['clear_inventory'])) {
            if ($conn->query("DELETE FROM pharmacy_inventory") === TRUE) {
                $cleared_items[] = "Inventory Data";
            } else {
                $error_items[] = "Inventory";
            }
        }

        // 2. Clear Hospital Services
        if (isset($_POST['clear_services'])) {
            if ($conn->query("DELETE FROM hospital_services") === TRUE) {
                $cleared_items[] = "Hospital Services";
            } else {
                $error_items[] = "Services";
            }
        }

        // 3. Clear Accounts ONLY (If patients are not cleared)
        if (isset($_POST['clear_accounts'])) {
            if ($conn->query("DELETE FROM accounts") === TRUE) {
                if (!in_array("Accounts Data", $cleared_items)) {
                    $cleared_items[] = "Accounts Data";
                }
            } else {
                $error_items[] = "Accounts";
            }
        }

        // 4. Clear Expense Ledger
        if (isset($_POST['clear_expenses'])) {
            // Note: Adjust the table name "expenses" if your actual database uses a different name
            if ($conn->query("DELETE FROM expenses") === TRUE) {
                $cleared_items[] = "Expense Ledger";
            } else {
                $error_items[] = "Expenses";
            }
        }

        // 5. Clear Patients & Clinical Records
        if (isset($_POST['clear_patients'])) {
            // Delete from child tables first to respect database structure, then delete patients
            $tables_to_clear = [
                'accounts', 'appointments', 'prescriptions', 'procedures', 
                'radiology_tests', 'lab_tests', 'consultations', 'triage', 'visits', 'patients'
            ];
            
            $pt_success = true;
            foreach ($tables_to_clear as $tbl) {
                if (!$conn->query("DELETE FROM $tbl")) {
                    $pt_success = false;
                }
            }
            
            if ($pt_success) {
                $cleared_items[] = "Patients & Clinical Records";
            } else {
                $error_items[] = "Patients Data";
            }
        }

        // 6. Clear Staff
        if (isset($_POST['clear_staff'])) {
            // Protect the Master Admin (id = 1) from being deleted
            $sql = "DELETE FROM users WHERE id != 1";
            if ($conn->query($sql) === TRUE) {
                $cleared_items[] = "Staff Directory";
            } else {
                $error_items[] = "Staff Data";
            }
        }

        // Format success or error message
        if (empty($cleared_items) && empty($error_items)) {
            $message = "No data categories were selected for deletion.";
            $messageType = "info";
        } elseif (!empty($error_items)) {
            $message = "Error clearing some data: " . implode(', ', $error_items) . ". Please check database constraints.";
            $messageType = "danger";
        } else {
            $message = "Successfully wiped: " . implode(', ', $cleared_items) . ".";
            $messageType = "warning";
        }
        $active_tab = 'data';
    }
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Manage Hospital Staff</h1>
</div>

<?php if ($message != ''): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show shadow-sm" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<ul class="nav nav-tabs mb-4" id="staffTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo ($active_tab == 'directory') ? 'active' : ''; ?> fw-bold text-dark" id="directory-tab" data-bs-toggle="tab" data-bs-target="#directory" type="button" role="tab">
            <i class="fa-solid fa-users me-2 text-primary"></i> Staff Directory
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo ($active_tab == 'data') ? 'active' : ''; ?> fw-bold text-dark" id="data-tab" data-bs-toggle="tab" data-bs-target="#data" type="button" role="tab">
            <i class="fa-solid fa-database me-2 text-danger"></i> Data Management
        </button>
    </li>
</ul>

<div class="tab-content" id="staffTabsContent">

    <div class="tab-pane fade <?php echo ($active_tab == 'directory') ? 'show active' : ''; ?>" id="directory" role="tabpanel">
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm border-primary">
                    <div class="card-header bg-primary text-white fw-bold">
                        <i class="fa-solid fa-user-plus me-2"></i> Add New User
                    </div>
                    <div class="card-body">
                        <form method="POST" action="staff.php">
                            <input type="hidden" name="action" value="add_staff">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Full Name</label>
                                <input type="text" class="form-control" name="full_name" required placeholder="e.g. Dr. Jane Doe">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Username</label>
                                <input type="text" class="form-control" name="username" required placeholder="For logging in">
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Roles / Departments</label>
                                <div class="bg-light p-3 border rounded">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="roles[]" value="Admin" id="roleAdmin">
                                        <label class="form-check-label fw-bold text-danger" for="roleAdmin">System Admin</label>
                                    </div>
                                    <hr class="mt-1 mb-2">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="roles[]" value="Receptionist" id="roleReception">
                                        <label class="form-check-label" for="roleReception">Receptionist</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="roles[]" value="Doctor" id="roleDoctor">
                                        <label class="form-check-label" for="roleDoctor">Doctor</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="roles[]" value="Nurse" id="roleNurse">
                                        <label class="form-check-label" for="roleNurse">Nurse</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="roles[]" value="Lab" id="roleLab">
                                        <label class="form-check-label" for="roleLab">Lab Tech</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="roles[]" value="Radiology" id="roleRadiology">
                                        <label class="form-check-label" for="roleRadiology">Radiologist</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="roles[]" value="Procedure" id="roleProcedure">
                                        <label class="form-check-label" for="roleProcedure">Procedure</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="roles[]" value="Pharmacy" id="rolePharmacy">
                                        <label class="form-check-label" for="rolePharmacy">Pharmacist</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="roles[]" value="Accounts" id="roleAccounts">
                                        <label class="form-check-label" for="roleAccounts">Cashier</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label fw-bold">Initial Password</label>
                                <input type="text" class="form-control" name="password" required placeholder="Enter a temporary password">
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="fa-solid fa-save me-1"></i> Create Account</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white fw-bold">
                        Active Staff Directory
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Assigned Roles</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $users_query = $conn->query("SELECT * FROM users ORDER BY full_name ASC");
                                    if ($users_query && $users_query->num_rows > 0) {
                                        while ($user = $users_query->fetch_assoc()) {
                                            echo "<tr>";
                                            echo "<td class='fw-bold'>{$user['full_name']}</td>";
                                            echo "<td>{$user['username']}</td>";
                                            
                                            // Split the comma-separated roles and display them as badges
                                            echo "<td>";
                                            $user_roles = explode(',', $user['role']);
                                            foreach ($user_roles as $r) {
                                                $r = trim($r);
                                                if (empty($r)) continue;
                                                $badge_class = ($r == 'Admin') ? 'bg-danger' : 'bg-secondary';
                                                echo "<span class='badge {$badge_class} me-1 mb-1'>{$r}</span>";
                                            }
                                            echo "</td>";

                                            echo "<td class='text-end'>";
                                            
                                            echo "<form method='POST' action='staff.php' class='d-inline-block me-2' onsubmit='return confirm(\"Are you sure you want to change this password?\");'>";
                                            echo "<input type='hidden' name='action' value='update_password'>";
                                            echo "<input type='hidden' name='user_id' value='{$user['id']}'>";
                                            echo "<div class='input-group input-group-sm' style='width: 200px; float: left;'>";
                                            echo "<input type='text' class='form-control' name='new_password' placeholder='New Password' required>";
                                            echo "<button class='btn btn-warning text-dark fw-bold' type='submit'>Update</button>";
                                            echo "</div>";
                                            echo "</form>";

                                            if ($user['id'] !== '1') {
                                                echo "<form method='POST' action='staff.php' class='d-inline-block' onsubmit='return confirm(\"Are you sure you want to completely remove this user?\");'>";
                                                echo "<input type='hidden' name='action' value='delete_staff'>";
                                                echo "<input type='hidden' name='user_id' value='{$user['id']}'>";
                                                echo "<button class='btn btn-danger btn-sm' type='submit' title='Delete User'><i class='fa-solid fa-trash'></i></button>";
                                                echo "</form>";
                                            }
                                            
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='4' class='text-center py-4'>No users found.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade <?php echo ($active_tab == 'data') ? 'show active' : ''; ?>" id="data" role="tabpanel">
        <div class="row">
            <div class="col-md-7 mb-4">
                <div class="card shadow-sm border-danger border-top border-5">
                    <div class="card-header bg-white fw-bold text-danger d-flex align-items-center">
                        <i class="fa-solid fa-triangle-exclamation fa-lg me-2"></i> Danger Zone: Wipe System Data
                    </div>
                    <div class="card-body">
                        <p class="card-text text-muted mb-4">Select the modules you wish to permanently clear. <strong class="text-dark">This action is irreversible and cannot be undone.</strong> Please ensure you have exported any necessary data before proceeding.</p>
                        
                        <form method="POST" action="staff.php" onsubmit="return confirm('CRITICAL WARNING: You are about to permanently delete the selected database records. This CANNOT be undone. Are you absolutely sure?');">
                            <input type="hidden" name="action" value="clear_selected_data">
                            
                            <div class="bg-light p-3 border rounded mb-4">
                                
                                <div class="form-check mb-3 pb-3 border-bottom">
                                    <input class="form-check-input border-danger wipe-checkbox" type="checkbox" name="clear_inventory" id="clear_inventory">
                                    <label class="form-check-label fw-bold" for="clear_inventory">Pharmacy Inventory Data</label>
                                    <div class="text-muted small">Deletes all medications, stock levels, and pharmacy prices.</div>
                                </div>

                                <div class="form-check mb-3 pb-3 border-bottom">
                                    <input class="form-check-input border-danger wipe-checkbox" type="checkbox" name="clear_services" id="clear_services">
                                    <label class="form-check-label fw-bold" for="clear_services">Hospital Services Pricing</label>
                                    <div class="text-muted small">Deletes all defined hospital services (e.g. Consultations, Procedures) and their prices.</div>
                                </div>
                                
                                <div class="form-check mb-3 pb-3 border-bottom">
                                    <input class="form-check-input border-danger wipe-checkbox" type="checkbox" name="clear_accounts" id="clear_accounts">
                                    <label class="form-check-label fw-bold" for="clear_accounts">Accounts Data</label>
                                    <div class="text-muted small">Deletes all financial records, invoices, and completed patient payments.</div>
                                </div>

                                <div class="form-check mb-3 pb-3 border-bottom">
                                    <input class="form-check-input border-danger wipe-checkbox" type="checkbox" name="clear_expenses" id="clear_expenses">
                                    <label class="form-check-label fw-bold" for="clear_expenses">Expense Ledger Data</label>
                                    <div class="text-muted small">Deletes all tracked hospital expenses and external outgoings.</div>
                                </div>
                                
                                <div class="form-check mb-3 pb-3 border-bottom">
                                    <input class="form-check-input border-danger wipe-checkbox" type="checkbox" name="clear_patients" id="clear_patients">
                                    <label class="form-check-label fw-bold" for="clear_patients">Patients & Clinical Records</label>
                                    <div class="text-muted small">Deletes all registered patients, triage vitals, doctor notes, labs, radiology files, procedures, and appointments. <br><em>(Note: Selecting this will also clear Accounts data to prevent orphaned records).</em></div>
                                </div>
                                
                                <div class="form-check">
                                    <input class="form-check-input border-danger wipe-checkbox" type="checkbox" name="clear_staff" id="clear_staff">
                                    <label class="form-check-label fw-bold" for="clear_staff">Staff Directory</label>
                                    <div class="text-muted small">Deletes all user accounts and passwords. <span class="fw-bold text-dark">The Master Admin account will be preserved to prevent lockout.</span></div>
                                </div>

                            </div>

                            <button type="submit" class="btn btn-danger fw-bold w-100 shadow-sm py-2" id="clearDataBtn" disabled>
                                <i class="fa-solid fa-trash-can me-2"></i>Permanently Clear Selected Data
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-5 mb-4">
                <div class="card bg-light border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="fw-bold"><i class="fa-solid fa-circle-info text-primary me-2"></i> Before you wipe data:</h6>
                        <ul class="text-muted small mt-3 ps-3">
                            <li class="mb-2">We strongly recommend navigating to the <strong>Export Data</strong> module to save your patient histories before clearing the system.</li>
                            <li class="mb-2">Clearing patient data will automatically delete all invoices associated with those patients to keep your database structurally safe.</li>
                            <li class="mb-2">If you are starting a new year or month, clearing just "Accounts" and "Patients" will give you a fresh queue while keeping your staff list and pharmacy inventory intact.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
// Logic to ensure the Danger button is only clickable if at least one box is checked
document.addEventListener("DOMContentLoaded", function() {
    const checkboxes = document.querySelectorAll('.wipe-checkbox');
    const wipeBtn = document.getElementById('clearDataBtn');

    function checkWipeSelection() {
        let anyChecked = false;
        checkboxes.forEach(cb => {
            if (cb.checked) anyChecked = true;
        });
        wipeBtn.disabled = !anyChecked;
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', checkWipeSelection);
    });
});
</script>

<?php
include '../../includes/footer.php';
?>