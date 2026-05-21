<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gem Cherith Healthcare Centre</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/hospital-system/assets/css/style.css">
</head>
<body>

<header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow-sm" style="z-index: 100;">
    
    <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3 fs-6 d-flex align-items-center" href="/hospital-system/dashboard.php">
        <img src="/assets/images/logo.jpg" alt="Logo" style="height: 50px; margin-right: 10px; border-radius: 4px; background-color: white; padding: 2px;">
        <span class="fw-bold tracking-wide">Gem Cherith Healthcare Centre</span>
    </a>
    
    <button class="navbar-toggler position-absolute d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    
    <div class="navbar-nav w-100 d-flex flex-row justify-content-end align-items-center px-4">
        
        <div class="nav-item text-nowrap text-white me-4 d-flex align-items-center">
            <i class="fa-solid fa-user-md fa-lg me-2 text-info"></i> 
            <span class="me-1">Welcome,</span>
            <span class="fw-bold">
                <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Staff'; ?>
            </span>
            
            <?php if(isset($_SESSION['role'])): ?>
                <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($_SESSION['role']); ?></span>
            <?php endif; ?>
        </div>

        <div class="nav-item text-nowrap border-start border-secondary ps-3">
            <a class="btn btn-sm btn-outline-danger fw-bold d-flex align-items-center" href="/logout.php">
                <i class="fa-solid fa-right-from-bracket me-2"></i> Sign Out
            </a>
        </div>
        
    </div>

</header>

<div class="container-fluid">
    <div class="row">