<?php
// index.php

// Start the session
session_start();

// If already logged in, send them straight to the dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: dashboard.php");
    exit();
}

include 'includes/db_connect.php';

$error = '';

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $conn->real_escape_string(trim($_POST['username']));
    $password = $conn->real_escape_string(trim($_POST['password']));

    // Query the users table for a match
    $query = "SELECT id, full_name, role FROM users WHERE username = '$username' AND password = '$password' LIMIT 1";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Set session variables
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['full_name'];
        $_SESSION['role'] = $user['role']; // Save their specific role
        
        // Redirect to the dashboard
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Hospital Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; }
        .login-card { border-radius: 15px; border: none; }
        .login-header { background: linear-gradient(135deg, #0d6efd, #0dcaf0); border-radius: 15px 15px 0 0; }
    </style>
</head>
<body class="d-flex align-items-center py-4 bg-light" style="min-height: 100vh;">

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                
                <div class="card shadow-lg login-card">
<div class="card-header login-header text-white text-center py-4">
    <img src="assets/images/logo.jpg" alt="Company Logo" style="max-height: 140px; object-fit: contain;" class="mb-2 bg-white p-1 rounded">
    
    <h4 class="fw-bold mb-0">Gem Cherith Healthcare Centre</h4>
    <small>Staff Login Portal</small>
</div>
                    <div class="card-body p-4">
                        
                        <?php if ($error != ''): ?>
                            <div class="alert alert-danger text-center shadow-sm">
                                <i class="fa-solid fa-circle-exclamation me-1"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="index.php">
                            <div class="mb-3">
                                <label for="username" class="form-label fw-bold">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fa-solid fa-user text-muted"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" required placeholder="Enter username">
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label fw-bold">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fa-solid fa-lock text-muted"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required placeholder="Enter password">
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg fw-bold shadow-sm">Sign In</button>
                            </div>
                        </form>

                    </div>
                </div>

            </div>
        </div>
    </div>

</body>
</html>