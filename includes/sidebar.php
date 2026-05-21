<?php
// Grab the user's role from the session.
$user_role = isset($_SESSION['role']) ? (string)$_SESSION['role'] : '';

// Get the current URL path to determine which tab should be active
$current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Helper function to set active class
function getActive($path, $current_path) {
    return (strpos($current_path, $path) !== false) ? 'bg-white bg-opacity-25 fw-bold rounded' : '';
}
?>
<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse shadow-sm position-fixed" style="top: 24px; bottom: 0; overflow-y: auto; z-index: 99; background-color: #158a43;">
    <div class="pt-4 px-2">
        
        <div class="px-3 mb-4 text-center text-white">
            <i class="fa-solid fa-circle-user fa-3x mb-2 text-white-50"></i>
            <h6 class="mb-0 fw-bold"><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User'; ?></h6>
            <small class="badge bg-dark mt-1">
                <?php echo htmlspecialchars(str_replace(',', ', ', $user_role)); ?>
            </small>
        </div>

        <ul class="nav flex-column mb-3">
            <li class="nav-item mb-1">
                <a class="nav-link text-white <?php echo getActive('dashboard.php', $current_path); ?>" href="/dashboard.php">
                    <i class="fa-solid fa-chart-pie me-2"></i> Dashboard
                </a>
            </li>
            
            <?php if (strpos($user_role, 'Admin') !== false || strpos($user_role, 'Receptionist') !== false): ?>
            <li class="nav-item mb-1">
                <a class="nav-link text-white <?php echo getActive('/reception/', $current_path); ?>" href="/modules/reception/index.php">
                    <i class="fa-solid fa-users me-2"></i> Reception
                </a>
            </li>
            <li class="nav-item mb-1">
                <a class="nav-link text-white <?php echo getActive('/triage/', $current_path); ?>" href="/modules/triage/index.php">
                    <i class="fa-solid fa-heart-pulse me-2"></i> Triage
                </a>
            </li>
            <?php endif; ?>

            <?php if (strpos($user_role, 'Admin') !== false || strpos($user_role, 'Doctor') !== false): ?>
            <li class="nav-item mb-1">
                <a class="nav-link text-white <?php echo getActive('/doctor/', $current_path); ?>" href="/modules/doctor/index.php">
                    <i class="fa-solid fa-user-doctor me-2"></i> Doctor
                </a>
            </li>
            <?php endif; ?>

            <?php if (strpos($user_role, 'Admin') !== false || strpos($user_role, 'Lab') !== false): ?>
            <li class="nav-item mb-1">
                <a class="nav-link text-white <?php echo getActive('/lab/', $current_path); ?>" href="/modules/lab/index.php">
                    <i class="fa-solid fa-microscope me-2"></i> Lab
                </a>
            </li>
            <?php endif; ?>

            <?php if (strpos($user_role, 'Admin') !== false || strpos($user_role, 'Radiology') !== false): ?>
            <li class="nav-item mb-1">
                <a class="nav-link text-white <?php echo getActive('/radiology/', $current_path); ?>" href="/modules/radiology/index.php">
                    <i class="fa-solid fa-x-ray me-2"></i> Radiology
                </a>
            </li>
            <?php endif; ?>

            <?php if (strpos($user_role, 'Admin') !== false || strpos($user_role, 'Procedure') !== false): ?>
            <li class="nav-item mb-1">
                <a class="nav-link text-white <?php echo getActive('/procedure/', $current_path); ?>" href="/modules/procedure/index.php">
                    <i class="fa-solid fa-scissors me-2"></i> Procedure Room
                </a>
            </li>
             <?php endif; ?>
            
            <?php if (strpos($user_role, 'Admin') !== false || strpos($user_role, 'Pharmacy') !== false): ?>
            <li class="nav-item mb-1">
                <a class="nav-link text-white <?php echo getActive('/pharmacy/', $current_path); ?>" href="/modules/pharmacy/index.php">
                    <i class="fa-solid fa-pills me-2"></i> Pharmacy
                </a>
            </li>
            <?php endif; ?>

            <?php if (strpos($user_role, 'Admin') !== false || strpos($user_role, 'Accounts') !== false): ?>
            <li class="nav-item mb-1">
                <a class="nav-link text-white <?php echo getActive('/accounts/', $current_path); ?>" href="/modules/accounts/index.php">
                    <i class="fa-solid fa-file-invoice-dollar me-2"></i> Accounts
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <?php if (strpos($user_role, 'Admin') !== false || strpos($user_role, 'Doctor') !== false): ?>
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-2 text-white-50 text-uppercase fw-bold" style="font-size: 0.75rem;">
            <span><?php echo (strpos($user_role, 'Admin') !== false) ? 'Administration' : 'User Settings'; ?></span>
        </h6>
        <ul class="nav flex-column mb-2">
            <?php if (strpos($user_role, 'Admin') !== false): ?>
            <li class="nav-item mb-1">
                <a class="nav-link text-white <?php echo getActive('/admin/staff.php', $current_path); ?>" href="/modules/admin/staff.php">
                    <i class="fa-solid fa-user-shield me-2"></i> Manage Staff
                </a>
            </li>
            <?php endif; ?>
            
            <li class="nav-item mb-1">
                <a class="nav-link text-white <?php echo getActive('/admin/change_password.php', $current_path); ?>" href="/modules/admin/change_password.php">
                    <i class="fa-solid fa-key me-2"></i> Change Password
                </a>
            </li>

            <?php if (strpos($user_role, 'Admin') !== false): ?>
            <li class="nav-item mb-1">
                <a class="nav-link text-white <?php echo getActive('/admin/services.php', $current_path); ?>" href="/modules/admin/services.php">
                    <i class="fa-solid fa-tags me-2"></i> Services & Pricing
                </a>
            </li>
            <li class="nav-item mb-1">
                <a class="nav-link text-white <?php echo getActive('/admin/reports.php', $current_path); ?>" href="/modules/admin/reports.php">
                    <i class="fa-solid fa-chart-line me-2"></i> Financial Reports
                </a>
            </li>
            <li class="nav-item mb-1">
                <a class="nav-link text-white <?php echo getActive('/admin/export.php', $current_path); ?>" href="/modules/admin/export.php">
                    <i class="fa-solid fa-file-excel me-2"></i> Export Data
                </a>
            </li>
            <?php endif; ?>
        </ul>
        <?php endif; ?>
    </div>
</nav>

<main class="col-md-9 offset-md-3 col-lg-10 offset-lg-2 px-md-4 py-4">