<?php
// modules/admin/change_password.php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../index.php");
    exit();
}

include '../../includes/db_connect.php';
include '../../includes/header.php';
include '../../includes/sidebar.php';

$message = '';
$messageType = '';

// Get the current user's ID (Make sure you set this during login)
$current_user_id = $_SESSION['user_id']; 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_pass = $_POST['current_pass'];
    $new_pass = $_POST['new_pass'];
    $confirm_pass = $_POST['confirm_pass'];

    // 1. Verify current password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // Note: If you aren't hashing passwords yet, use ($current_pass == $user['password'])
    // If you are using password_hash(), use password_verify($current_pass, $user['password'])
    if ($current_pass !== $user['password']) {
        $message = "Error: Current password incorrect.";
        $messageType = "danger";
    } elseif ($new_pass !== $confirm_pass) {
        $message = "Error: New passwords do not match.";
        $messageType = "danger";
    } else {
        // Update to new password (Consider using password_hash($new_pass, PASSWORD_DEFAULT) here)
        $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update_stmt->bind_param("si", $new_pass, $current_user_id);
        
        if ($update_stmt->execute()) {
            $message = "Password updated successfully!";
            $messageType = "success";
        } else {
            $message = "Error updating database: " . $conn->error;
            $messageType = "danger";
        }
    }
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Account Security</h1>
</div>

<?php if ($message != ''): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold">Change Password</div>
            <div class="card-body">
                <form method="POST" action="change_password.php">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Current Password</label>
                        <input type="password" class="form-control" name="current_pass" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">New Password</label>
                        <input type="password" class="form-control" name="new_pass" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Confirm New Password</label>
                        <input type="password" class="form-control" name="confirm_pass" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>