<?php
// modules/admin/reports.php

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../index.php");
    exit();
}

// Security Check
if ($_SESSION['role'] !== 'Admin') {
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

// Handle Add Expense Form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_expense') {
    $description = $conn->real_escape_string($_POST['description']);
    $amount = floatval($_POST['amount']);
    $expense_date = $conn->real_escape_string($_POST['expense_date']);

    $sql_expense = "INSERT INTO expenses (description, amount, expense_date) VALUES ('$description', $amount, '$expense_date')";
    if ($conn->query($sql_expense) === TRUE) {
        $message = "Expense recorded successfully.";
        $messageType = "success";
    } else {
        $message = "Error recording expense: " . $conn->error;
        $messageType = "danger";
    }
}

// --- Dynamic Filter Logic ---
$start_date = isset($_GET['start_date']) ? $conn->real_escape_string($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? $conn->real_escape_string($_GET['end_date']) : '';
$search_ledger = isset($_GET['search_ledger']) ? $conn->real_escape_string(trim($_GET['search_ledger'])) : '';

$revenue_where = "1=1";
$expense_where = "1=1";

if ($start_date !== '') {
    $revenue_where .= " AND DATE(a.created_at) >= '$start_date'";
    $expense_where .= " AND expense_date >= '$start_date'";
}
if ($end_date !== '') {
    $revenue_where .= " AND DATE(a.created_at) <= '$end_date'";
    $expense_where .= " AND expense_date <= '$end_date'";
}
if ($search_ledger !== '') {
    $revenue_where .= " AND (p.first_name LIKE '%$search_ledger%' OR p.last_name LIKE '%$search_ledger%' OR p.id_number LIKE '%$search_ledger%' OR p.phone LIKE '%$search_ledger%')";
}

// 1. Calculate Current Inventory Value (Static/Current Asset)
$inv_query = $conn->query("SELECT SUM(stock_quantity * unit_price) AS total_value FROM pharmacy_inventory");
$inventory_value = $inv_query->fetch_assoc()['total_value'] ?? 0;

// 2. Calculate OTC (Walk-in) Revenue (Dynamically Filtered)
$otc_query = $conn->query("
    SELECT SUM(a.amount_paid) AS otc_revenue 
    FROM accounts a 
    JOIN visits v ON a.visit_id = v.id 
    JOIN patients p ON v.patient_id = p.id 
    WHERE p.first_name = 'Walk-in' AND p.last_name = 'Client' AND $revenue_where
");
$otc_revenue = $otc_query->fetch_assoc()['otc_revenue'] ?? 0;

// 3. Calculate Hospital Services Revenue (Dynamically Filtered)
$srv_query = $conn->query("
    SELECT SUM(a.amount_paid) AS srv_revenue 
    FROM accounts a 
    JOIN visits v ON a.visit_id = v.id 
    JOIN patients p ON v.patient_id = p.id 
    WHERE NOT (p.first_name = 'Walk-in' AND p.last_name = 'Client') AND $revenue_where
");
$srv_revenue = $srv_query->fetch_assoc()['srv_revenue'] ?? 0;

// 4. Calculate Total Revenue
$total_revenue = $otc_revenue + $srv_revenue;

// 5. Calculate Total Expenses (Dynamically Filtered)
$exp_query = $conn->query("SELECT SUM(amount) AS total_exp FROM expenses WHERE $expense_where");
$total_expenses = $exp_query->fetch_assoc()['total_exp'] ?? 0;

// 6. Calculate Net Profit or Loss
$net_profit = $total_revenue - $total_expenses;

// Determine colors for the Profit/Loss indicator
$profit_label = "Net Profit";
$profit_color = "bg-success";
$profit_icon = "fa-arrow-trend-up";

if ($net_profit < 0) {
    $profit_label = "Net Loss";
    $profit_color = "bg-danger";
    $profit_icon = "fa-arrow-trend-down";
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
    <h1 class="h2">Financial Reports</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
            <i class="fa-solid fa-print me-1"></i> Print Report
        </button>
    </div>
</div>

<?php if ($message != ''): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show shadow-sm" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-primary text-white shadow-sm h-100 border-0">
            <div class="card-body">
                <h6 class="text-uppercase text-white-50 fw-bold mb-1">Total Revenue</h6>
                <h3 class="mb-0 fw-bold">KES <?php echo number_format($total_revenue, 2); ?></h3>
            </div>
            <div class="card-footer bg-primary border-top-0 pt-0">
                <small class="text-white-50"><i class="fa-solid fa-coins me-1"></i> Based on filtered dates</small>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card bg-danger text-white shadow-sm h-100 border-0">
            <div class="card-body">
                <h6 class="text-uppercase text-white-50 fw-bold mb-1">Total Expenses</h6>
                <h3 class="mb-0 fw-bold">KES <?php echo number_format($total_expenses, 2); ?></h3>
            </div>
            <div class="card-footer bg-danger border-top-0 pt-0">
                <small class="text-white-50"><i class="fa-solid fa-money-bill-transfer me-1"></i> Based on filtered dates</small>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card <?php echo $profit_color; ?> text-white shadow-sm h-100 border-0">
            <div class="card-body">
                <h6 class="text-uppercase text-white-50 fw-bold mb-1"><?php echo $profit_label; ?></h6>
                <h3 class="mb-0 fw-bold">KES <?php echo number_format(abs($net_profit), 2); ?></h3>
            </div>
            <div class="card-footer <?php echo $profit_color; ?> border-top-0 pt-0">
                <small class="text-white-50"><i class="fa-solid <?php echo $profit_icon; ?> me-1"></i> Revenue minus expenses</small>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-light shadow-sm border-0">
            <div class="card-body py-2 d-flex justify-content-between align-items-center">
                <span class="text-muted fw-bold">Clinical Services</span>
                <span class="fw-bold text-success">KES <?php echo number_format($srv_revenue, 2); ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-light shadow-sm border-0">
            <div class="card-body py-2 d-flex justify-content-between align-items-center">
                <span class="text-muted fw-bold">Pharmacy OTC</span>
                <span class="fw-bold text-info">KES <?php echo number_format($otc_revenue, 2); ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-light shadow-sm border-0">
            <div class="card-body py-2 d-flex justify-content-between align-items-center">
                <span class="text-muted fw-bold">Current Inventory Value</span>
                <span class="fw-bold text-warning">KES <?php echo number_format($inventory_value, 2); ?></span>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4 mb-4 mb-md-0">
        <div class="card shadow-sm border-danger border-top border-3">
            <div class="card-header bg-white fw-bold text-danger">
                <i class="fa-solid fa-plus-circle me-2"></i> Log New Expense
            </div>
            <div class="card-body">
                <form method="POST" action="reports.php">
                    <input type="hidden" name="action" value="add_expense">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Description / Category</label>
                        <input type="text" class="form-control" name="description" required placeholder="e.g. Electricity Bill, Restocking">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Amount (KES)</label>
                        <input type="number" step="0.01" class="form-control" name="amount" required placeholder="0.00">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Date</label>
                        <input type="date" class="form-control" name="expense_date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-danger w-100 fw-bold"><i class="fa-solid fa-save me-1"></i> Record Expense</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-danger text-white fw-bold d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-file-invoice me-2"></i> Expense Ledger</span>
                <span class="badge bg-light text-danger">Recent Records</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $expense_ledger = $conn->query("SELECT * FROM expenses ORDER BY expense_date DESC, created_at DESC LIMIT 30");

                            if ($expense_ledger && $expense_ledger->num_rows > 0) {
                                while ($row = $expense_ledger->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td class='text-muted'>" . date('M j, Y', strtotime($row['expense_date'])) . "</td>";
                                    echo "<td class='fw-bold'>{$row['description']}</td>";
                                    echo "<td class='text-end fw-bold text-danger'>- KES " . number_format($row['amount'], 2) . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='3' class='text-center py-4 text-muted'>No expenses recorded yet.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mt-2">
    <div class="card-header bg-white fw-bold d-flex flex-wrap justify-content-between align-items-center py-3 gap-2">
        <span class="mb-2 mb-md-0"><i class="fa-solid fa-file-invoice-dollar me-2 text-success"></i> Revenue Ledger</span>
        
        <form method="GET" action="reports.php" class="d-flex align-items-center gap-2 flex-wrap">
            <input type="text" name="search_ledger" class="form-control form-control-sm border-secondary" placeholder="Search Name, ID, Phone..." value="<?php echo htmlspecialchars($search_ledger); ?>" style="width: 200px;">
            <input type="date" name="start_date" class="form-control form-control-sm border-secondary" value="<?php echo htmlspecialchars($start_date); ?>" title="Start Date">
            <span class="text-muted small">to</span>
            <input type="date" name="end_date" class="form-control form-control-sm border-secondary" value="<?php echo htmlspecialchars($end_date); ?>" title="End Date">
            <button type="submit" class="btn btn-sm btn-primary fw-bold px-3 shadow-sm">Filter</button>
            <?php if($start_date !== '' || $end_date !== '' || $search_ledger !== ''): ?>
                <a href="reports.php" class="btn btn-sm btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date & Time</th>
                        <th>Visit ID</th>
                        <th>Patient Name</th>
                        <th>Type</th>
                        <th>Total Bill</th>
                        <th>Amount Paid</th>
                        <th>Status / Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $ledger_query = $conn->query("
                        SELECT a.id, a.total_amount, a.amount_paid, a.payment_status, a.created_at, 
                               p.first_name, p.last_name, v.id as visit_id 
                        FROM accounts a 
                        JOIN visits v ON a.visit_id = v.id 
                        JOIN patients p ON v.patient_id = p.id 
                        WHERE $revenue_where
                        ORDER BY a.created_at DESC 
                        LIMIT 100
                    ");

                    if ($ledger_query && $ledger_query->num_rows > 0) {
                        while ($row = $ledger_query->fetch_assoc()) {
                            $is_otc = ($row['first_name'] == 'Walk-in' && $row['last_name'] == 'Client');
                            $type_badge = $is_otc ? "<span class='badge bg-info text-dark'>Pharmacy OTC</span>" : "<span class='badge bg-success'>Hospital Service</span>";
                            
                            $status_color = 'success';
                            if ($row['payment_status'] == 'Partial') $status_color = 'warning text-dark';
                            if ($row['payment_status'] == 'Unpaid') $status_color = 'danger';

                            echo "<tr>";
                            echo "<td class='text-muted small'>" . date('M j, Y h:i A', strtotime($row['created_at'])) . "</td>";
                            echo "<td class='fw-bold'>#{$row['visit_id']}</td>";
                            echo "<td>{$row['first_name']} {$row['last_name']}</td>";
                            echo "<td>{$type_badge}</td>";
                            echo "<td>KES " . number_format($row['total_amount'], 2) . "</td>";
                            echo "<td class='fw-bold text-success'>+ KES " . number_format($row['amount_paid'], 2) . "</td>";
                            echo "<td>
                                    <span class='badge bg-{$status_color} me-2'>{$row['payment_status']}</span>
                                    <a href='../accounts/receipt.php?visit_id={$row['visit_id']}' target='_blank' class='btn btn-sm btn-outline-secondary py-0 px-2 shadow-sm' title='Print Receipt'><i class='fa-solid fa-print'></i></a>
                                  </td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7' class='text-center py-4 text-muted'>No financial transactions found for the selected filters.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include '../../includes/footer.php';
?>