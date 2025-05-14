<?php
session_start();
error_reporting(E_ALL);
include('includes/dbconnection.php');

// Verify admin is logged in
if (empty($_SESSION['imsaid'])) {
    header('Location: logout.php');
    exit;
}

// Get current admin details
$currentAdminID = $_SESSION['imsaid'];
$adminQuery = mysqli_query($con, "SELECT AdminName FROM tbladmin WHERE ID = '$currentAdminID'");
$adminData = mysqli_fetch_assoc($adminQuery);
$currentAdminName = $adminData['AdminName'];

// Get invoice ID from URL
$invoiceid = isset($_GET['invoiceid']) ? $_GET['invoiceid'] : '';

// Auto-print functionality
$autoPrint = isset($_GET['print']) && $_GET['print'] == 'auto';

// Validate invoice ID
if (empty($invoiceid)) {
    echo "<script>alert('Numéro de facture invalide.'); window.location='admin_invoices.php';</script>";
    exit;
}

// Get invoice details
$invoiceQuery = mysqli_query($con, "
    SELECT 
        c.BillingNumber,
        c.CustomerName,
        c.MobileNumber,
        c.ModeofPayment,
        c.BillingDate,
        c.FinalAmount,
        c.Paid,
        c.Dues,
        a.AdminName as ProcessedBy
    FROM tblcustomer c
    JOIN tblcreditcart cart ON c.BillingNumber = cart.BillingId
    JOIN tbladmin a ON cart.AdminID = a.ID
    WHERE c.BillingNumber = '$invoiceid'
    GROUP BY c.BillingNumber
");

if (!$invoiceQuery || mysqli_num_rows($invoiceQuery) == 0) {
    echo "<script>alert('Facture introuvable.'); window.location='admin_invoices.php';</script>";
    exit;
}

$invoice = mysqli_fetch_assoc($invoiceQuery);

// Get invoice items
$itemsQuery = mysqli_query($con, "
    SELECT 
        c.ProductQty,
        c.Price,
        p.ProductName,
        p.ModelNumber,
        (c.ProductQty * c.Price) as TotalPrice
    FROM tblcreditcart c
    JOIN tblproducts p ON c.ProductId = p.ID
    WHERE c.BillingId = '$invoiceid' AND c.IsCheckOut = 1
");

$items = array();
$itemCount = 0;

if ($itemsQuery) {
    while ($item = mysqli_fetch_assoc($itemsQuery)) {
        $items[] = $item;
        $itemCount++;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Facture à Terme #<?php echo $invoiceid; ?></title>
    <?php include_once('includes/cs.php'); ?>
    <?php include_once('includes/responsive.php'); ?>
    <style>
        .invoice-box {
            background-color: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .invoice-header {
            border-bottom: 2px solid #ddd;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }
        
        .invoice-details {
            margin-bottom: 20px;
        }
        
        .invoice-details table {
            width: 100%;
        }
        
        .invoice-details td {
            padding: 5px;
        }
        
        .payment-status {
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 3px;
        }
        
        .status-paid {
            background-color: #dff0d8;
            color: #3c763d;
        }
        
        .status-partial {
            background-color: #fcf8e3;
            color: #8a6d3b;
        }
        
        .status-unpaid {
            background-color: #f2dede;
            color: #a94442;
        }
        
        .invoice-items th {
            background-color: #f9f9f9;
        }
        
        .invoice-total {
            border-top: 2px solid #ddd;
            margin-top: 20px;
            padding-top: 10px;
            text-align: right;
        }
        
        .print-btn {
            margin-bottom: 20px;
        }
        
        @media print {
            body * {
                visibility: hidden;
            }
            
            #invoice-printable, #invoice-printable * {
                visibility: visible;
            }
            
            #invoice-printable {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Header + Sidebar -->
    <?php include_once('includes/header.php'); ?>
    <?php include_once('includes/sidebar.php'); ?>

    <div id="content">
        <div id="content-header">
            <div id="breadcrumb">
                <a href="dashboard.php" title="Aller à l'accueil" class="tip-bottom">
                    <i class="icon-home"></i> Accueil
                </a>
                <a href="admin_invoices.php">Factures</a>
                <a href="invoice_dettecard.php?invoiceid=<?php echo $invoiceid; ?>" class="current">Facture à Terme #<?php echo $invoiceid; ?></a>
            </div>
            <h1>Facture à Terme #<?php echo $invoiceid; ?></h1>
        </div>

        <div class="container-fluid">
            <div class="row-fluid no-print print-btn">
                <div class="span12">
                    <button onclick="window.print();" class="btn btn-primary">
                        <i class="icon-print"></i> Imprimer la facture
                    </button>
                    <a href="admin_invoices.php" class="btn">
                        <i class="icon-arrow-left"></i> Retour aux factures
                    </a>
                </div>
            </div>
            
            <div id="invoice-printable">
                <div class="row-fluid">
                    <div class="span12">
                        <div class="invoice-box">
                            <div class="invoice-header">
                                <div class="row-fluid">
                                    <div class="span6">
                                        <h2>Facture à Terme #<?php echo $invoiceid; ?></h2>
                                    </div>
                                    <div class="span6" style="text-align: right;">
                                        <h3>Votre Entreprise</h3>
                                        <p>Adresse de l'entreprise<br>
                                        Téléphone: +224 000000000<br>
                                        Email: contact@entreprise.com</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="invoice-details">
                                <div class="row-fluid">
                                    <div class="span6">
                                        <h4>Client</h4>
                                        <p>
                                            <strong><?php echo htmlspecialchars($invoice['CustomerName']); ?></strong><br>
                                            Téléphone: <?php echo $invoice['MobileNumber']; ?>
                                        </p>
                                    </div>
                                    <div class="span6" style="text-align: right;">
                                        <h4>Détails de la facture</h4>
                                        <table style="float: right;">
                                            <tr>
                                                <td><strong>Date:</strong></td>
                                                <td><?php echo date('d/m/Y', strtotime($invoice['BillingDate'])); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Mode de paiement:</strong></td>
                                                <td><?php echo $invoice['ModeofPayment']; ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Traité par:</strong></td>
                                                <td><?php echo htmlspecialchars($invoice['ProcessedBy']); ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Statut:</strong></td>
                                                <td>
                                                    <?php 
                                                    if ($invoice['Dues'] <= 0) {
                                                        echo '<span class="payment-status status-paid">Payé</span>';
                                                    } elseif ($invoice['Paid'] > 0) {
                                                        echo '<span class="payment-status status-partial">Partiellement payé</span>';
                                                    } else {
                                                        echo '<span class="payment-status status-unpaid">Non payé</span>';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="widget-content">
                                <table class="table table-bordered table-striped invoice-items">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Article</th>
                                            <th>Modèle</th>
                                            <th>Quantité</th>
                                            <th>Prix unitaire</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $i = 1;
                                        $subtotal = 0;
                                        foreach ($items as $item) {
                                            $subtotal += $item['TotalPrice'];
                                        ?>
                                        <tr>
                                            <td><?php echo $i++; ?></td>
                                            <td><?php echo htmlspecialchars($item['ProductName']); ?></td>
                                            <td><?php echo $item['ModelNumber']; ?></td>
                                            <td><?php echo $item['ProductQty']; ?></td>
                                            <td><?php echo number_format($item['Price'], 2); ?> GNF</td>
                                            <td><?php echo number_format($item['TotalPrice'], 2); ?> GNF</td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="invoice-total">
                                <div class="row-fluid">
                                    <div class="span6">
                                        <h4>Notes</h4>
                                        <p>Merci pour votre achat!</p>
                                    </div>
                                    <div class="span6">
                                        <table class="table table-condensed" style="width: 300px; float: right;">
                                            <tr>
                                                <td><strong>Sous-total:</strong></td>
                                                <td><?php echo number_format($subtotal, 2); ?> GNF</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Remise:</strong></td>
                                                <td><?php echo number_format($subtotal - $invoice['FinalAmount'], 2); ?> GNF</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Total:</strong></td>
                                                <td><?php echo number_format($invoice['FinalAmount'], 2); ?> GNF</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Payé:</strong></td>
                                                <td><?php echo number_format($invoice['Paid'], 2); ?> GNF</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Reste à payer:</strong></td>
                                                <td><?php echo number_format($invoice['Dues'], 2); ?> GNF</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include_once('includes/footer.php'); ?>

    <!-- Scripts -->
    <script src="js/jquery.min.js"></script>
    <script src="js/jquery.ui.custom.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/jquery.uniform.js"></script>

    <!-- FIX for DataTables issues -->
    <script>
    $(document).ready(function() {
        // Use a simplified DataTable initialization without column definitions
        $('.table').dataTable({
            "bJQueryUI": true,
            "sPaginationType": "full_numbers",
            "sDom": '<""l>t<"F"fp>',
            "bSort": true,
            "bAutoWidth": false,
            "bPaginate": false,  // Disable pagination for invoice items
            "bInfo": false       // Disable table info display
        });
        
        <?php if ($autoPrint): ?>
        // Auto-print if requested
        setTimeout(function() {
            window.print();
        }, 1000);
        <?php endif; ?>
    });
    </script>
</body>
</html>