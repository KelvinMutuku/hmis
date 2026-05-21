<?php
// modules/admin/services.php

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../index.php");
    exit();
}

// Security Check: Only Admins can manage the price list
$user_role = isset($_SESSION['role']) ? (string)$_SESSION['role'] : '';
if (strpos($user_role, 'Admin') === false) {
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
$active_tab = 'services'; // Default tab

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Action 1: Add a new Hospital Service
    if (isset($_POST['action']) && $_POST['action'] == 'add_service') {
        $service_name = $conn->real_escape_string($_POST['service_name']);
        $cost = floatval($_POST['cost']);

        $sql = "INSERT INTO hospital_services (service_name, cost) VALUES ('$service_name', $cost)";
        if ($conn->query($sql) === TRUE) {
            $message = "New service added to the price list.";
            $messageType = "success";
        } else {
            $message = "Error adding service: " . $conn->error;
            $messageType = "danger";
        }
        $active_tab = 'services';
    }
    
    // Action 2: Update an existing Hospital Service price
    elseif (isset($_POST['action']) && $_POST['action'] == 'update_price') {
        $service_id = intval($_POST['service_id']);
        $new_cost = floatval($_POST['new_cost']);

        $sql = "UPDATE hospital_services SET cost = $new_cost WHERE id = $service_id";
        if ($conn->query($sql) === TRUE) {
            $message = "Service price updated successfully.";
            $messageType = "success";
        } else {
            $message = "Error updating price: " . $conn->error;
            $messageType = "danger";
        }
        $active_tab = 'services';
    }

    // Action 3: Delete a Hospital Service
    elseif (isset($_POST['action']) && $_POST['action'] == 'delete_service') {
        $service_id = intval($_POST['service_id']);
        
        $sql = "DELETE FROM hospital_services WHERE id = $service_id";
        if ($conn->query($sql) === TRUE) {
            $message = "Service removed from the system.";
            $messageType = "success";
        } else {
            $message = "Error deleting service: " . $conn->error;
            $messageType = "danger";
        }
        $active_tab = 'services';
    }

    // Action 4: Add New Inventory Item
    elseif (isset($_POST['action']) && $_POST['action'] == 'add_inventory') {
        $item_name = $conn->real_escape_string($_POST['item_name']);
        $generic_name = $conn->real_escape_string($_POST['generic_name']);
        $method_of_intake = $conn->real_escape_string($_POST['method_of_intake']);
        $unit_of_measure = $conn->real_escape_string($_POST['unit_of_measure']);
        
        $buying_price = floatval($_POST['buying_price']);
        $unit_price = floatval($_POST['unit_price']); // Selling price
        $stock_quantity = intval($_POST['stock_quantity']);
        $stock_out_level = intval($_POST['stock_out_level']);
        $expiry_date = !empty($_POST['expiry_date']) ? "'" . $conn->real_escape_string($_POST['expiry_date']) . "'" : "NULL";

        $sql_inv = "INSERT INTO pharmacy_inventory (item_name, generic_name, method_of_intake, unit_of_measure, buying_price, unit_price, stock_quantity, stock_out_level, expiry_date) 
                    VALUES ('$item_name', '$generic_name', '$method_of_intake', '$unit_of_measure', $buying_price, $unit_price, $stock_quantity, $stock_out_level, $expiry_date)";
        
        if ($conn->query($sql_inv) === TRUE) {
            $message = "New medication added to inventory successfully.";
            $messageType = "success";
        } else {
            $message = "Error adding to inventory: " . $conn->error;
            $messageType = "danger";
        }
        $active_tab = 'inventory';
    }

    // Action 5: Edit Existing Inventory Item
    elseif (isset($_POST['action']) && $_POST['action'] == 'edit_inventory') {
        $inv_id = intval($_POST['inv_id']);
        
        $item_name = $conn->real_escape_string($_POST['item_name']);
        $generic_name = $conn->real_escape_string($_POST['generic_name']);
        $method_of_intake = $conn->real_escape_string($_POST['method_of_intake']);
        $unit_of_measure = $conn->real_escape_string($_POST['unit_of_measure']);
        
        $buying_price = floatval($_POST['buying_price']);
        $unit_price = floatval($_POST['unit_price']); // Selling price
        $stock_quantity = intval($_POST['stock_quantity']);
        $stock_out_level = intval($_POST['stock_out_level']);
        $expiry_date = !empty($_POST['expiry_date']) ? "'" . $conn->real_escape_string($_POST['expiry_date']) . "'" : "NULL";

        $sql_update = "UPDATE pharmacy_inventory SET 
                        item_name='$item_name', 
                        generic_name='$generic_name', 
                        method_of_intake='$method_of_intake', 
                        unit_of_measure='$unit_of_measure', 
                        buying_price=$buying_price, 
                        unit_price=$unit_price, 
                        stock_quantity=$stock_quantity, 
                        stock_out_level=$stock_out_level, 
                        expiry_date=$expiry_date 
                       WHERE id = $inv_id";

        if ($conn->query($sql_update) === TRUE) {
            $message = "Inventory item updated successfully.";
            $messageType = "success";
        } else {
            $message = "Error updating inventory: " . $conn->error;
            $messageType = "danger";
        }
        $active_tab = 'inventory';
    }
    
    // Action 6: Delete Inventory Item
    elseif (isset($_POST['action']) && $_POST['action'] == 'delete_inventory') {
        $inv_id = intval($_POST['inv_id']);
        $sql = "DELETE FROM pharmacy_inventory WHERE id = $inv_id";
        if ($conn->query($sql) === TRUE) {
            $message = "Inventory item removed successfully.";
            $messageType = "success";
        } else {
            $message = "Error deleting inventory item: " . $conn->error;
            $messageType = "danger";
        }
        $active_tab = 'inventory';
    }
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Services & Inventory Pricing</h1>
</div>

<?php if ($message != ''): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show shadow-sm" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<ul class="nav nav-tabs mb-4" id="pricingTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo ($active_tab == 'services') ? 'active' : ''; ?> fw-bold text-dark" id="services-tab" data-bs-toggle="tab" data-bs-target="#services" type="button" role="tab">
            <i class="fa-solid fa-stethoscope me-2 text-primary"></i> Hospital Services
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo ($active_tab == 'inventory') ? 'active' : ''; ?> fw-bold text-dark" id="inventory-tab" data-bs-toggle="tab" data-bs-target="#inventory" type="button" role="tab">
            <i class="fa-solid fa-boxes-stacked me-2 text-warning"></i> Pharmacy Inventory
        </button>
    </li>
</ul>

<div class="tab-content" id="pricingTabsContent">
    
    <div class="tab-pane fade <?php echo ($active_tab == 'services') ? 'show active' : ''; ?>" id="services" role="tabpanel">
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm border-primary">
                    <div class="card-header bg-primary text-white fw-bold">
                        <i class="fa-solid fa-plus-circle me-2"></i> Add New Service
                    </div>
                    <div class="card-body">
                        <form method="POST" action="services.php">
                            <input type="hidden" name="action" value="add_service">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Service Name</label>
                                <input type="text" class="form-control" name="service_name" required placeholder="e.g. Ultrasound Scan">
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label fw-bold">Cost (KES)</label>
                                <input type="number" step="0.01" class="form-control" name="cost" required placeholder="0.00">
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="fa-solid fa-save me-1"></i> Save Service</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white fw-bold">
                        Current Price List
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Service Name</th>
                                        <th>Current Cost</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $services_query = $conn->query("SELECT * FROM hospital_services ORDER BY service_name ASC");
                                    if ($services_query && $services_query->num_rows > 0) {
                                        while ($service = $services_query->fetch_assoc()) {
                                            echo "<tr>";
                                            echo "<td class='fw-bold text-dark'>{$service['service_name']}</td>";
                                            echo "<td class='text-success fw-bold'>KES " . number_format($service['cost'], 2) . "</td>";
                                            echo "<td class='text-end'>";
                                            
                                            echo "<form method='POST' action='services.php' class='d-inline-block me-2' onsubmit='return confirm(\"Update this price?\");'>";
                                            echo "<input type='hidden' name='action' value='update_price'>";
                                            echo "<input type='hidden' name='service_id' value='{$service['id']}'>";
                                            echo "<div class='input-group input-group-sm' style='width: 180px; float: left;'>";
                                            echo "<input type='number' step='0.01' class='form-control' name='new_cost' placeholder='New Cost' required>";
                                            echo "<button class='btn btn-warning text-dark fw-bold' type='submit'>Update</button>";
                                            echo "</div>";
                                            echo "</form>";

                                            echo "<form method='POST' action='services.php' class='d-inline-block' onsubmit='return confirm(\"Are you sure you want to completely remove this service?\");'>";
                                            echo "<input type='hidden' name='action' value='delete_service'>";
                                            echo "<input type='hidden' name='service_id' value='{$service['id']}'>";
                                            echo "<button class='btn btn-danger btn-sm' type='submit' title='Delete Service'><i class='fa-solid fa-trash'></i></button>";
                                            echo "</form>";
                                            
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='3' class='text-center py-4'>No services found.</td></tr>";
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

    <div class="tab-pane fade <?php echo ($active_tab == 'inventory') ? 'show active' : ''; ?>" id="inventory" role="tabpanel">
        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="card shadow-sm border-warning">
                    <div class="card-header bg-warning text-dark fw-bold">
                        <i class="fa-solid fa-plus-circle me-2"></i> Add New Medicine
                    </div>
                    <div class="card-body">
                        <form method="POST" action="services.php">
                            <input type="hidden" name="action" value="add_inventory">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Brand Name *</label>
                                <input type="text" class="form-control form-control-sm border-warning" name="item_name" required placeholder="e.g. Panadol Extra">
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Generic Name *</label>
                                <input type="text" class="form-control form-control-sm border-warning" name="generic_name" required placeholder="e.g. Paracetamol">
                            </div>

                            <div class="row mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-bold small">Unit of Measure *</label>
                                    <select class="form-select form-select-sm" name="unit_of_measure" required>
                                        <option value="" selected disabled>Select...</option>
                                        <option value="Tabs">Tabs</option>
                                        <option value="Caps">Capsules (Caps)</option>
                                        <option value="Bottle">Bottle</option>
                                        <option value="Sachet">Sachet</option>
                                        <option value="Vial">Vial / Ampoule</option>
                                        <option value="Pair">Pair (e.g. Gloves)</option>
                                        <option value="Tube">Tube</option>
                                        <option value="Piece">Piece / Item</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold small">Intake Method</label>
                                    <select class="form-select form-select-sm" name="method_of_intake">
                                        <option value="" selected disabled>Select...</option>
                                        <option value="Oral">Oral (PO)</option>
                                        <option value="IV">IV</option>
                                        <option value="IM">IM</option>
                                        <option value="Topical">Topical</option>
                                        <option value="Subcut">Subcutaneous</option>
                                        <option value="Other">Other / N/A</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-bold small">Buying Price (In)</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm text-danger" name="buying_price" required placeholder="0.00">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold small">Selling Price (Out)</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm text-success" name="unit_price" required placeholder="0.00">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-bold small">Initial Stock Qty</label>
                                    <input type="number" class="form-control form-control-sm border-primary" name="stock_quantity" required placeholder="0">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold small text-danger">Alert Level (Min)</label>
                                    <input type="number" class="form-control form-control-sm" name="stock_out_level" required value="10" title="System alerts when stock drops below this number">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold small">Expiry Date</label>
                                <input type="date" class="form-control form-control-sm" name="expiry_date">
                            </div>
                            
                            <button type="submit" class="btn btn-warning w-100 fw-bold"><i class="fa-solid fa-save me-1"></i> Save to Inventory</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-9 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white fw-bold d-flex flex-wrap justify-content-between align-items-center">
                        <span class="mb-2 mb-md-0">Current Stock & Pricing</span>
                        <div class="d-flex gap-2 align-items-center flex-wrap">
                            <div class="input-group input-group-sm" style="width: 250px;">
                                <span class="input-group-text bg-light"><i class="fa-solid fa-search"></i></span>
                                <input type="text" id="invSearch" class="form-control border-secondary" placeholder="Type to search medicine..." onkeyup="filterInventory()">
                            </div>
                            <select id="invFilter" class="form-select form-select-sm w-auto border-secondary" onchange="filterInventory()">
                                <option value="all">Show All Items</option>
                                <option value="stockout">⚠ Out of Stock ONLY</option>
                                <option value="lowstock">⚠ Low Stock Alerts</option>
                                <option value="expiring">⚠ Expiring Soon (6 Months)</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Medicine Details</th>
                                        <th>Prices (Buy / Sell)</th>
                                        <th>Stock Level</th>
                                        <th>Expiry Date</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $all_inv = $conn->query("SELECT * FROM pharmacy_inventory ORDER BY item_name ASC");
                                    if ($all_inv && $all_inv->num_rows > 0) {
                                        while ($item = $all_inv->fetch_assoc()) {
                                            $qty = intval($item['stock_quantity']);
                                            $alert_level = isset($item['stock_out_level']) ? intval($item['stock_out_level']) : 10;
                                            
                                            $stock_html = '';
                                            $filter_tags = 'all ';
                                            $search_term = strtolower(htmlspecialchars($item['item_name'] . ' ' . $item['generic_name'], ENT_QUOTES));
                                            
                                            if ($qty <= 0) {
                                                $stock_html = '<div class="text-danger fw-bold fs-6">0</div><small class="badge bg-danger">Out of Stock</small>';
                                                $filter_tags .= 'stockout lowstock ';
                                            } elseif ($qty <= $alert_level) {
                                                $stock_html = '<div class="text-danger fw-bold fs-6">'.$qty.'</div><small class="text-danger"><i class="fa-solid fa-triangle-exclamation"></i> Low Stock</small>';
                                                $filter_tags .= 'lowstock ';
                                            } else {
                                                $stock_html = '<div class="text-success fw-bold fs-6">'.$qty.'</div>';
                                            }

                                            $expiry_date = isset($item['expiry_date']) ? $item['expiry_date'] : '';
                                            $expiry_html = '<span class="text-muted">N/A</span>';
                                            
                                            if (!empty($expiry_date) && $expiry_date !== '0000-00-00') {
                                                $exp_dt = new DateTime($expiry_date);
                                                $today = new DateTime();
                                                $six_months_from_now = (new DateTime())->modify('+6 months');
                                                
                                                if ($exp_dt < $today) {
                                                    $expiry_html = '<span class="badge bg-danger">EXPIRED ('.$expiry_date.')</span>';
                                                    $filter_tags .= 'expiring ';
                                                } elseif ($exp_dt <= $six_months_from_now) {
                                                    $expiry_html = '<span class="badge bg-warning text-dark">'.$expiry_date.' (Soon)</span>';
                                                    $filter_tags .= 'expiring ';
                                                } else {
                                                    $expiry_html = '<span class="text-muted">'.$expiry_date.'</span>';
                                                }
                                            }

                                            echo "<tr class='inventory-row' data-tags='{$filter_tags}' data-search='{$search_term}'>";
                                            echo "<td><strong class='text-primary d-block'>{$item['item_name']}</strong><small class='text-muted'>Gen: {$item['generic_name']}</small></td>";
                                            echo "<td><small class='text-danger d-block'>Buy: KES " . number_format($item['buying_price'], 2) . "</small><strong class='text-success d-block'>Sell: KES " . number_format($item['unit_price'], 2) . "</strong></td>";
                                            echo "<td>{$stock_html}</td>";
                                            echo "<td>{$expiry_html}</td>";
                                            echo "<td class='text-end'>
                                                    <button class='btn btn-sm btn-outline-primary shadow-sm me-2' data-bs-toggle='modal' data-bs-target='#editInvModal'
                                                    data-id='{$item['id']}' data-item='".htmlspecialchars($item['item_name'], ENT_QUOTES)."' data-generic='".htmlspecialchars($item['generic_name'], ENT_QUOTES)."'
                                                    data-buy='{$item['buying_price']}' data-sell='{$item['unit_price']}' data-qty='{$qty}' data-alert='{$alert_level}' data-expiry='{$expiry_date}'
                                                    onclick='populateEditModal(this)'><i class='fa-solid fa-edit'></i></button>

                                                    <form method='POST' action='services.php' class='d-inline-block' onsubmit='return confirm(\"Are you sure you want to delete this inventory item?\");'>
                                                        <input type='hidden' name='action' value='delete_inventory'>
                                                        <input type='hidden' name='inv_id' value='{$item['id']}'>
                                                        <button class='btn btn-sm btn-outline-danger shadow-sm' type='submit'><i class='fa-solid fa-trash'></i></button>
                                                    </form>
                                                  </td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='5' class='text-center py-4 text-muted'>No items in inventory.</td></tr>";
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
</div>

<div class="modal fade" id="editInvModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-primary border-top border-5">
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold text-primary">Edit Inventory Item</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="services.php">
          <div class="modal-body">
              <input type="hidden" name="action" value="edit_inventory">
              <input type="hidden" name="inv_id" id="edit_inv_id">
              <div class="mb-2"><label class="form-label fw-bold small">Brand Name</label><input type="text" class="form-control form-control-sm" id="edit_item_name" name="item_name" required></div>
              <div class="mb-2"><label class="form-label fw-bold small">Generic Name</label><input type="text" class="form-control form-control-sm" id="edit_generic_name" name="generic_name" required></div>
              <div class="row mb-2">
                  <div class="col-6"><label class="form-label fw-bold small">Buying Price</label><input type="number" step="0.01" class="form-control form-control-sm" id="edit_buying_price" name="buying_price" required></div>
                  <div class="col-6"><label class="form-label fw-bold small">Selling Price</label><input type="number" step="0.01" class="form-control form-control-sm" id="edit_unit_price" name="unit_price" required></div>
              </div>
              <div class="row mb-2">
                  <div class="col-6"><label class="form-label fw-bold small">Stock Qty</label><input type="number" class="form-control form-control-sm" id="edit_stock" name="stock_quantity" required></div>
                  <div class="col-6"><label class="form-label fw-bold small">Alert Level</label><input type="number" class="form-control form-control-sm" id="edit_alert" name="stock_out_level" required></div>
              </div>
              <div class="mb-2"><label class="form-label fw-bold small">Expiry Date</label><input type="date" class="form-control form-control-sm" id="edit_expiry" name="expiry_date"></div>
          </div>
          <div class="modal-footer bg-light"><button type="submit" class="btn btn-primary fw-bold">Update Item</button></div>
      </form>
    </div>
  </div>
</div>

<script>
function filterInventory() {
    var filter = document.getElementById('invFilter').value;
    var searchQuery = document.getElementById('invSearch').value.toLowerCase();
    var rows = document.querySelectorAll('.inventory-row');
    rows.forEach(function(row) {
        var matchesFilter = (filter === 'all' || row.dataset.tags.includes(filter));
        var matchesSearch = (searchQuery === '' || row.dataset.search.includes(searchQuery));
        row.style.display = (matchesFilter && matchesSearch) ? '' : 'none';
    });
}
function populateEditModal(btn) {
    document.getElementById('edit_inv_id').value = btn.getAttribute('data-id');
    document.getElementById('edit_item_name').value = btn.getAttribute('data-item');
    document.getElementById('edit_generic_name').value = btn.getAttribute('data-generic');
    document.getElementById('edit_buying_price').value = btn.getAttribute('data-buy');
    document.getElementById('edit_unit_price').value = btn.getAttribute('data-sell');
    document.getElementById('edit_stock').value = btn.getAttribute('data-qty');
    document.getElementById('edit_alert').value = btn.getAttribute('data-alert');
    document.getElementById('edit_expiry').value = btn.getAttribute('data-expiry');
}
</script>

<?php include '../../includes/footer.php'; ?>