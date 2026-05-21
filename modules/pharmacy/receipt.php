<?php
// modules/pharmacy/receipt.php

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../../index.php");
    exit();
}

include '../../includes/db_connect.php';

$visit_id = isset($_GET['visit_id']) ? intval($_GET['visit_id']) : 0;

if ($visit_id === 0) {
    die("Invalid receipt ID.");
}

// Fetch Account Details
$acct_query = $conn->query("SELECT * FROM accounts WHERE visit_id = $visit_id");
$account = $acct_query->fetch_assoc();

// Fetch Prescription/POS items
$rx_query = $conn->query("SELECT * FROM prescriptions WHERE visit_id = $visit_id");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?php echo $visit_id; ?></title>
    <style>
        body { 
            background-color: #f4f7f6; 
            font-family: 'Courier New', Courier, monospace; 
            margin: 0; 
            padding: 20px; 
        }
        .receipt-container { 
            background-color: #fff; 
            max-width: 350px; 
            margin: auto; 
            padding: 20px; 
            border: 1px solid #ccc; 
            box-shadow: 0 0 10px rgba(0,0,0,0.1); 
        }
        .receipt-header { 
            text-align: center; 
            margin-bottom: 20px; 
        }
        .receipt-header h3 { margin: 0 0 5px 0; }
        .receipt-header p { margin: 0; font-size: 14px; color: #555; }
        .item-row { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 8px; 
            font-size: 14px;
        }
        .divider { 
            border-top: 1px dashed #000; 
            margin: 10px 0; 
        }
        .total-row { 
            display: flex; 
            justify-content: space-between; 
            font-weight: bold; 
            font-size: 16px;
        }
        .btn-print {
            display: block;
            width: 100%;
            padding: 10px;
            background-color: #0d6efd;
            color: white;
            text-align: center;
            border: none;
            cursor: pointer;
            font-weight: bold;
            margin-top: 20px;
        }
        /* Hide buttons when actually printing/saving to PDF */
        @media print {
            body { background-color: #fff; padding: 0; }
            .receipt-container { box-shadow: none; border: none; max-width: 100%; width: 100%; padding: 0;}
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="receipt-container">
        <div class="receipt-header">
            <img src="../../assets/images/logo.jpg" style="max-height: 50px; margin-bottom: 10px;">
            <h3>Gem Cherith Healthcare Centre</h3>
            <p>Walk-in POS Receipt</p>
            <p>Date: <?php echo date('Y-m-d H:i'); ?></p>
            <p>Receipt #: <?php echo $visit_id; ?></p>
        </div>

        <div class="divider"></div>
        <div class="item-row" style="font-weight: bold;">
            <span>Item (Qty)</span>
        </div>
        <div class="divider"></div>

        <?php while ($rx = $rx_query->fetch_assoc()): ?>
            <div class="item-row">
                <span><?php echo $rx['medication_name']; ?></span>
                <span>x<?php echo $rx['dose']; ?></span>
            </div>
        <?php endwhile; ?>

        <div class="divider"></div>

        <div class="total-row mb-2">
            <span>Total Bill:</span>
            <span>KES <?php echo number_format($account['total_amount'], 2); ?></span>
        </div>
        <div class="item-row">
            <span>Amount Paid:</span>
            <span>KES <?php echo number_format($account['amount_paid'], 2); ?></span>
        </div>
        <div class="item-row text-muted">
            <span>Change:</span>
            <span>KES <?php echo number_format($account['amount_paid'] - $account['total_amount'], 2); ?></span>
        </div>

        <div class="divider"></div>
        <div class="receipt-header" style="margin-top: 20px;">
            <p>Thank you for your business!</p>
            <p>Please come again.</p>
        </div>

        <button class="btn-print no-print" onclick="window.print()">🖨️ Print / Save as PDF</button>
        <button class="btn-print no-print" style="background-color: #6c757d; margin-top: 10px;" onclick="window.close()">Close Window</button>
    </div>

</body>
</html>