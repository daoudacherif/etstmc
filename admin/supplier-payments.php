<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

if (strlen($_SESSION['imsaid'] == 0)) {
  header('location:logout.php');
  exit;
}

// ==========================
// 1) Insertion d'un paiement
// ==========================
if (isset($_POST['submit'])) {
  $supplierID  = intval($_POST['supplierid']);
  $payDate     = $_POST['paydate'];
  $amount      = floatval($_POST['amount']);
  $paymentMethod = mysqli_real_escape_string($con, $_POST['payment_method']);
  $comments    = mysqli_real_escape_string($con, $_POST['comments']);
  $reference   = mysqli_real_escape_string($con, $_POST['reference']);

  if ($supplierID <= 0 || $amount <= 0) {
    echo "<script>alert('Données invalides');</script>";
  } else {
    $sql = "
      INSERT INTO tblsupplierpayments(SupplierID, PaymentDate, Amount, PaymentMethod, Reference, Comments)
      VALUES('$supplierID', '$payDate', '$amount', '$paymentMethod', '$reference', '$comments')
    ";
    $res = mysqli_query($con, $sql);
    if ($res) {
      echo "<script>alert('Paiement enregistré !');</script>";
      
      // Si on vient de la page de détails fournisseur, on y retourne
      if (isset($_POST['return_to_supplier']) && intval($_POST['return_to_supplier']) > 0) {
        echo "<script>window.location.href='supplier-payments.php?supplierSearch=" . intval($_POST['return_to_supplier']) . "'</script>";
        exit;
      }
    } else {
      echo "<script>alert('Erreur lors de l\'insertion du paiement');</script>";
    }
  }
  echo "<script>window.location.href='supplier-payments.php'</script>";
  exit;
}

// ==========================
// 2) Filtre pour afficher le total pour un fournisseur
// ==========================
$selectedSupplier = 0;
$totalArrivals = 0;
$totalPaid     = 0;
$totalDue      = 0;
$dateFilter    = isset($_GET['date_filter']) ? $_GET['date_filter'] : 'all';
$dateCondition = "";

// Condition de date pour les filtres
if ($dateFilter == 'month') {
  $dateCondition = " AND MONTH(ArrivalDate) = MONTH(CURRENT_DATE()) AND YEAR(ArrivalDate) = YEAR(CURRENT_DATE()) ";
} elseif ($dateFilter == 'year') {
  $dateCondition = " AND YEAR(ArrivalDate) = YEAR(CURRENT_DATE()) ";
} elseif ($dateFilter == 'custom' && isset($_GET['start_date']) && isset($_GET['end_date'])) {
  $startDate = mysqli_real_escape_string($con, $_GET['start_date']);
  $endDate = mysqli_real_escape_string($con, $_GET['end_date']);
  $dateCondition = " AND ArrivalDate BETWEEN '$startDate' AND '$endDate' ";
}

// Condition de date pour les paiements
$paymentDateCondition = "";
if ($dateFilter == 'month') {
  $paymentDateCondition = " AND MONTH(PaymentDate) = MONTH(CURRENT_DATE()) AND YEAR(PaymentDate) = YEAR(CURRENT_DATE()) ";
} elseif ($dateFilter == 'year') {
  $paymentDateCondition = " AND YEAR(PaymentDate) = YEAR(CURRENT_DATE()) ";
} elseif ($dateFilter == 'custom' && isset($_GET['start_date']) && isset($_GET['end_date'])) {
  $startDate = mysqli_real_escape_string($con, $_GET['start_date']);
  $endDate = mysqli_real_escape_string($con, $_GET['end_date']);
  $paymentDateCondition = " AND PaymentDate BETWEEN '$startDate' AND '$endDate' ";
}

if (isset($_GET['supplierSearch'])) {
  $selectedSupplier = intval($_GET['supplierSearch']);

  if ($selectedSupplier > 0) {
    // Calculer la somme des arrivages
    $sqlArr = "
      SELECT IFNULL(SUM(Cost),0) as sumArrivals
      FROM tblproductarrivals
      WHERE SupplierID='$selectedSupplier' $dateCondition
    ";
    $resArr = mysqli_query($con, $sqlArr);
    $rowArr = mysqli_fetch_assoc($resArr);
    $totalArrivals = floatval($rowArr['sumArrivals']);

    // Calculer la somme des paiements
    $sqlPay = "
      SELECT IFNULL(SUM(Amount),0) as sumPaid
      FROM tblsupplierpayments
      WHERE SupplierID='$selectedSupplier' $paymentDateCondition
    ";
    $resPay = mysqli_query($con, $sqlPay);
    $rowPay = mysqli_fetch_assoc($resPay);
    $totalPaid = floatval($rowPay['sumPaid']);

    // Solde
    $totalDue = $totalArrivals - $totalPaid;
    if ($totalDue < 0) $totalDue = 0;
    
    // ==========================
    // Récupérer les détails des arrivages pour ce fournisseur
    // ==========================
    $sqlArrivals = "
      SELECT 
        a.ID as arrivalID,
        a.ArrivalDate,
        a.Quantity,
        a.Cost,
        a.Comments,
        p.ProductName,
        p.Price as UnitPrice
      FROM tblproductarrivals a
      LEFT JOIN tblproducts p ON p.ID = a.ProductID
      WHERE a.SupplierID = '$selectedSupplier' $dateCondition
      ORDER BY a.ArrivalDate DESC, a.ID DESC
    ";
    $resArrivals = mysqli_query($con, $sqlArrivals);
    
    // ==========================
    // Récupérer les détails des paiements pour ce fournisseur
    // ==========================
    $sqlPayments = "
      SELECT 
        sp.ID as paymentID,
        sp.PaymentDate,
        sp.Amount,
        sp.PaymentMethod,
        sp.Reference,
        sp.Comments
      FROM tblsupplierpayments sp
      WHERE sp.SupplierID = '$selectedSupplier' $paymentDateCondition
      ORDER BY sp.PaymentDate DESC, sp.ID DESC
    ";
    $resPayments = mysqli_query($con, $sqlPayments);
  }
}

// ==========================
// 3) Liste des paiements (pour tous les fournisseurs si aucun n'est sélectionné)
// ==========================
$sqlList = "
  SELECT sp.ID as paymentID,
         sp.PaymentDate,
         sp.Amount,
         sp.PaymentMethod,
         sp.Reference,
         sp.Comments,
         s.SupplierName
  FROM tblsupplierpayments sp
  LEFT JOIN tblsupplier s ON s.ID = sp.SupplierID
  ORDER BY sp.PaymentDate DESC, sp.ID DESC
  LIMIT 100
";
$resList = mysqli_query($con, $sqlList);

// ==========================
// 4) Liste des fournisseurs avec soldes
// ==========================
$sqlSuppliers = "
  SELECT 
    s.ID as SupplierID,
    s.SupplierName,
    IFNULL(SUM(a.Cost), 0) as TotalArrivals,
    IFNULL((SELECT SUM(Amount) FROM tblsupplierpayments WHERE SupplierID = s.ID), 0) as TotalPaid
  FROM 
    tblsupplier s
  LEFT JOIN 
    tblproductarrivals a ON a.SupplierID = s.ID
  GROUP BY 
    s.ID, s.SupplierName
  ORDER BY 
    s.SupplierName ASC
";
$resSuppliers = mysqli_query($con, $sqlSuppliers);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <title>Paiements Fournisseurs</title>
  <?php include_once('includes/cs.php'); ?>
  <?php include_once('includes/responsive.php'); ?>
  <style>
    .summary-box {
      border: 1px solid #ccc;
      padding: 15px;
      margin-bottom: 20px;
      border-radius: 4px;
      background-color: #f9f9f9;
    }
    .summary-box h4 {
      margin-top: 0;
      border-bottom: 1px solid #eee;
      padding-bottom: 10px;
    }
    .balance-due {
      font-size: 16px;
      font-weight: bold;
      color: #d9534f;
    }
    .arrivals-table {
      margin-top: 15px;
    }
    .supplier-details {
      margin-bottom: 25px;
    }
    .payment-method-cash {
      color: #5cb85c;
    }
    .payment-method-transfer {
      color: #428bca;
    }
    .payment-method-check {
      color: #f0ad4e;
    }
    .progress {
      height: 20px;
      margin-bottom: 10px;
      overflow: hidden;
      background-color: #f5f5f5;
      border-radius: 4px;
      box-shadow: inset 0 1px 2px rgba(0,0,0,.1);
    }
    .progress-bar {
      float: left;
      width: 0;
      height: 100%;
      font-size: 12px;
      line-height: 20px;
      color: #fff;
      text-align: center;
      background-color: #428bca;
      box-shadow: inset 0 -1px 0 rgba(0,0,0,.15);
      transition: width .6s ease;
    }
    .filter-box {
      background-color: #f5f5f5;
      padding: 10px;
      border-radius: 4px;
      margin-bottom: 15px;
    }
    .supplier-card {
      border: 1px solid #ddd;
      border-radius: 4px;
      margin-bottom: 10px;
      padding: 10px;
    }
    .supplier-card:hover {
      background-color: #f5f5f5;
      cursor: pointer;
    }
    .payment-icon {
      margin-right: 5px;
    }
    .quick-payment-form {
      background-color: #e8f4f8;
      padding: 15px;
      border-radius: 4px;
      margin-top: 15px;
      border: 1px solid #bce8f1;
    }
    .supplier-status {
      display: inline-block;
      padding: 3px 7px;
      border-radius: 3px;
      font-size: 12px;
      font-weight: bold;
    }
    .status-paid {
      background-color: #dff0d8;
      color: #3c763d;
    }
    .status-pending {
      background-color: #fcf8e3;
      color: #8a6d3b;
    }
    .status-overdue {
      background-color: #f2dede;
      color: #a94442;
    }
    .quick-filter {
      margin-bottom: 20px;
    }
    @media print {
      .no-print {
        display: none !important;
      }
      .container-fluid {
        padding: 0;
      }
      body {
        padding: 0;
        margin: 0;
      }
    }
  </style>
</head>
<body>
<?php include_once('includes/header.php'); ?>
<?php include_once('includes/sidebar.php'); ?>

<div id="content">
  <div id="content-header">
    <h1>Paiements aux Fournisseurs</h1>
  </div>
  <div class="container-fluid">
    <hr>

    <!-- ========== FILTRE DE RECHERCHE AMÉLIORÉ ========== -->
    <div class="row-fluid no-print">
      <div class="span12">
        <div class="widget-box filter-box">
          <form method="get" action="supplier-payments.php" class="form-inline">
            <div class="row-fluid">
              <div class="span6">
                <label><strong>Fournisseur :</strong></label>
                <select name="supplierSearch" class="span10">
                  <option value="">-- Tous --</option>
                  <?php
                  // Charger la liste des fournisseurs
                  $suppQ = mysqli_query($con, "SELECT ID, SupplierName FROM tblsupplier ORDER BY SupplierName ASC");
                  while ($sRow = mysqli_fetch_assoc($suppQ)) {
                    $sid   = $sRow['ID'];
                    $sname = $sRow['SupplierName'];
                    $sel   = ($sid == $selectedSupplier) ? 'selected' : '';
                    echo "<option value='$sid' $sel>$sname</option>";
                  }
                  ?>
                </select>
              </div>
              <div class="span6">
                <label><strong>Période :</strong></label>
                <select name="date_filter" id="date_filter" class="span6">
                  <option value="all" <?php echo ($dateFilter == 'all') ? 'selected' : ''; ?>>Toutes les dates</option>
                  <option value="month" <?php echo ($dateFilter == 'month') ? 'selected' : ''; ?>>Mois en cours</option>
                  <option value="year" <?php echo ($dateFilter == 'year') ? 'selected' : ''; ?>>Année en cours</option>
                  <option value="custom" <?php echo ($dateFilter == 'custom') ? 'selected' : ''; ?>>Personnalisée</option>
                </select>
              </div>
            </div>
            
            <div class="row-fluid" id="custom_date_range" style="<?php echo ($dateFilter == 'custom') ? '' : 'display:none;'; ?> margin-top:10px;">
              <div class="span6">
                <label><strong>Date de début :</strong></label>
                <input type="date" name="start_date" class="span10" value="<?php echo isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); ?>">
              </div>
              <div class="span6">
                <label><strong>Date de fin :</strong></label>
                <input type="date" name="end_date" class="span10" value="<?php echo isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); ?>">
              </div>
            </div>
            
            <div class="row-fluid" style="margin-top:10px;">
              <div class="span12">
                <button type="submit" class="btn btn-info">
                  <i class="icon-search"></i> Filtrer
                </button>
                <?php if (isset($_GET['supplierSearch']) || isset($_GET['date_filter'])): ?>
                <a href="supplier-payments.php" class="btn">
                  <i class="icon-refresh"></i> Réinitialiser
                </a>
                <?php endif; ?>
                <?php if ($selectedSupplier > 0): ?>
                <button type="button" class="btn btn-success" onclick="window.print();">
                  <i class="icon-print"></i> Imprimer
                </button>
                <?php endif; ?>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <?php
    // Si aucun fournisseur n'est sélectionné, afficher un aperçu de tous les fournisseurs
    if ($selectedSupplier == 0): 
    ?>
    <!-- ========== APERÇU DE TOUS LES FOURNISSEURS ========== -->
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-briefcase"></i></span>
            <h5>Aperçu des Fournisseurs</h5>
          </div>
          <div class="widget-content nopadding">
            <table class="table table-bordered data-table">
              <thead>
                <tr>
                  <th>Fournisseur</th>
                  <th>Total Arrivages</th>
                  <th>Total Payé</th>
                  <th>Solde Dû</th>
                  <th>Statut</th>
                  <th class="no-print">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php
                while ($supplier = mysqli_fetch_assoc($resSuppliers)) {
                  $totalArrivals = floatval($supplier['TotalArrivals']);
                  $totalPaid = floatval($supplier['TotalPaid']);
                  $balance = $totalArrivals - $totalPaid;
                  $balance = ($balance < 0) ? 0 : $balance;
                  
                  // Déterminer le statut de paiement
                  $status = "";
                  $statusClass = "";
                  
                  if ($balance <= 0) {
                    $status = "Payé";
                    $statusClass = "status-paid";
                  } elseif ($balance < ($totalArrivals * 0.3)) {
                    $status = "En cours";
                    $statusClass = "status-pending";
                  } else {
                    $status = "À payer";
                    $statusClass = "status-overdue";
                  }
                  
                  // Calculer le pourcentage payé
                  $percentPaid = ($totalArrivals > 0) ? ($totalPaid / $totalArrivals) * 100 : 100;
                  $percentPaid = min(100, $percentPaid); // Ne pas dépasser 100%
                ?>
                <tr>
                  <td>
                    <strong><?php echo $supplier['SupplierName']; ?></strong>
                  </td>
                  <td><?php echo number_format($totalArrivals, 2); ?></td>
                  <td><?php echo number_format($totalPaid, 2); ?></td>
                  <td><?php echo number_format($balance, 2); ?></td>
                  <td>
                    <span class="supplier-status <?php echo $statusClass; ?>"><?php echo $status; ?></span>
                    <div class="progress">
                      <div class="progress-bar" style="width: <?php echo $percentPaid; ?>%"></div>
                    </div>
                  </td>
                  <td class="no-print">
                    <a href="supplier-payments.php?supplierSearch=<?php echo $supplier['SupplierID']; ?>" class="btn btn-mini btn-info">
                      <i class="icon-eye-open"></i> Détails
                    </a>
                    <button class="btn btn-mini btn-success quick-pay-btn" data-supplier-id="<?php echo $supplier['SupplierID']; ?>" data-supplier-name="<?php echo $supplier['SupplierName']; ?>" data-balance="<?php echo $balance; ?>">
                      <i class="icon-money"></i> Payer
                    </button>
                  </td>
                </tr>
                <?php
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <?php else: ?>
    <!-- ========== DÉTAILS DU FOURNISSEUR SÉLECTIONNÉ ========== -->
    <?php
    // Récupérer son nom
    $qsupp = mysqli_query($con, "SELECT SupplierName, Phone, Email, Address FROM tblsupplier WHERE ID='$selectedSupplier' LIMIT 1");
    $rSupp = mysqli_fetch_assoc($qsupp);
    $supplierName = $rSupp ? $rSupp['SupplierName'] : '???';
    $supplierPhone = $rSupp['Phone'];
    $supplierEmail = $rSupp['Email'];
    $supplierAddress = $rSupp['Address'];
    ?>
    
    <div class="row-fluid supplier-details">
      <div class="span12">
        <div class="summary-box">
          <h4>Fournisseur : <strong><?php echo $supplierName; ?></strong></h4>
          <?php if(!empty($supplierPhone) || !empty($supplierEmail) || !empty($supplierAddress)): ?>
          <div class="row-fluid">
            <div class="span4">
              <?php if(!empty($supplierPhone)): ?>
              <p><i class="icon-phone"></i> <?php echo $supplierPhone; ?></p>
              <?php endif; ?>
            </div>
            <div class="span4">
              <?php if(!empty($supplierEmail)): ?>
              <p><i class="icon-envelope"></i> <?php echo $supplierEmail; ?></p>
              <?php endif; ?>
            </div>
            <div class="span4">
              <?php if(!empty($supplierAddress)): ?>
              <p><i class="icon-home"></i> <?php echo $supplierAddress; ?></p>
              <?php endif; ?>
            </div>
          </div>
          <hr>
          <?php endif; ?>
          
          <div class="row-fluid">
            <div class="span3">
              <p>Total des arrivages : <strong><?php echo number_format($totalArrivals, 2); ?></strong></p>
            </div>
            <div class="span3">
              <p>Total payé : <strong><?php echo number_format($totalPaid, 2); ?></strong></p>
            </div>
            <div class="span3">
              <p class="balance-due">Solde dû : <strong><?php echo number_format($totalDue, 2); ?></strong></p>
            </div>
            <div class="span3">
              <?php 
              // Calculer le pourcentage payé
              $percentPaid = ($totalArrivals > 0) ? ($totalPaid / $totalArrivals) * 100 : 100;
              $percentPaid = min(100, $percentPaid); // Ne pas dépasser 100%
              ?>
              <p>Progression de paiement :</p>
              <div class="progress">
                <div class="progress-bar" style="width: <?php echo $percentPaid; ?>%"><?php echo round($percentPaid); ?>%</div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Formulaire de paiement rapide -->
        <div class="quick-payment-form no-print">
          <h5><i class="icon-money"></i> Effectuer un Paiement Rapide</h5>
          <form method="post" class="form-horizontal">
            <input type="hidden" name="supplierid" value="<?php echo $selectedSupplier; ?>">
            <input type="hidden" name="return_to_supplier" value="<?php echo $selectedSupplier; ?>">
            
            <div class="row-fluid">
              <div class="span3">
                <div class="control-group">
                  <label class="control-label">Date :</label>
                  <div class="controls">
                    <input type="date" name="paydate" value="<?php echo date('Y-m-d'); ?>" required class="span12">
                  </div>
                </div>
              </div>
              <div class="span3">
                <div class="control-group">
                  <label class="control-label">Montant :</label>
                  <div class="controls">
                    <input type="number" name="amount" step="any" min="0" value="<?php echo $totalDue; ?>" required class="span12">
                  </div>
                </div>
              </div>
              <div class="span3">
                <div class="control-group">
                  <label class="control-label">Méthode :</label>
                  <div class="controls">
                    <select name="payment_method" class="span12">
                      <option value="Espèces">Espèces</option>
                      <option value="Chèque">Chèque</option>
                      <option value="Virement">Virement</option>
                      <option value="Carte">Carte</option>
                    </select>
                  </div>
                </div>
              </div>
              <div class="span3">
                <div class="control-group">
                  <label class="control-label">Référence :</label>
                  <div class="controls">
                    <input type="text" name="reference" placeholder="N° chèque, virement..." class="span12">
                  </div>
                </div>
              </div>
            </div>
            
            <div class="row-fluid">
              <div class="span9">
                <div class="control-group">
                  <label class="control-label">Commentaires :</label>
                  <div class="controls">
                    <input type="text" name="comments" placeholder="Notes de paiement..." class="span12">
                  </div>
                </div>
              </div>
              <div class="span3">
                <button type="submit" name="submit" class="btn btn-success btn-block">
                  <i class="icon-check"></i> Enregistrer Paiement
                </button>
              </div>
            </div>
          </form>
        </div>
        
        <!-- ONGLETS POUR ARRIVAGES ET PAIEMENTS -->
        <div class="widget-box">
          <div class="widget-title">
            <ul class="nav nav-tabs">
              <li class="active"><a data-toggle="tab" href="#tab-arrivals"><i class="icon-truck"></i> Arrivages</a></li>
              <li><a data-toggle="tab" href="#tab-payments"><i class="icon-money"></i> Paiements</a></li>
            </ul>
          </div>
          <div class="widget-content tab-content">
            <!-- ONGLET DES ARRIVAGES -->
            <div id="tab-arrivals" class="tab-pane active">
              <?php if (isset($resArrivals) && mysqli_num_rows($resArrivals) > 0): ?>
              <table class="table table-bordered table-striped arrivals-table">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Date d'Arrivage</th>
                    <th>Produit</th>
                    <th>Quantité</th>
                    <th>Prix Unitaire</th>
                    <th>Coût Total</th>
                    <th>Commentaires</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  while ($arrival = mysqli_fetch_assoc($resArrivals)) {
                    ?>
                    <tr>
                      <td><?php echo $arrival['arrivalID']; ?></td>
                      <td><?php echo date('d/m/Y', strtotime($arrival['ArrivalDate'])); ?></td>
                      <td><?php echo $arrival['ProductName']; ?></td>
                      <td><?php echo $arrival['Quantity']; ?></td>
                      <td><?php echo number_format($arrival['UnitPrice'], 2); ?></td>
                      <td><?php echo number_format($arrival['Cost'], 2); ?></td>
                      <td><?php echo $arrival['Comments']; ?></td>
                    </tr>
                  <?php
                  }
                  ?>
                </tbody>
              </table>
              <?php else: ?>
                <div class="alert alert-info">
                  <button class="close" data-dismiss="alert">×</button>
                  <strong>Info!</strong> Aucun arrivage trouvé pour ce fournisseur dans la période sélectionnée.
                </div>
              <?php endif; ?>
            </div>
            
            <!-- ONGLET DES PAIEMENTS -->
            <div id="tab-payments" class="tab-pane">
              <?php if (isset($resPayments) && mysqli_num_rows($resPayments) > 0): ?>
              <table class="table table-bordered table-striped payments-table">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Montant</th>
                    <th>Méthode</th>
                    <th>Référence</th>
                    <th>Commentaires</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  while ($payment = mysqli_fetch_assoc($resPayments)) {
                    // Définir la classe CSS selon la méthode de paiement
                    $methodClass = "";
                    if ($payment['PaymentMethod'] == 'Espèces') {
                      $methodClass = "payment-method-cash";
                      $methodIcon = "icon-money";
                    } elseif ($payment['PaymentMethod'] == 'Virement') {
                      $methodClass = "payment-method-transfer";
                      $methodIcon = "icon-exchange";
                    } elseif ($payment['PaymentMethod'] == 'Chèque') {
                      $methodClass = "payment-method-check";
                      $methodIcon = "icon-file-alt";
                    } else {
                      $methodIcon = "icon-credit-card";
                    }
                    ?>
                    <tr>
                      <td><?php echo $payment['paymentID']; ?></td>
                      <td><?php echo date('d/m/Y', strtotime($payment['PaymentDate'])); ?></td>
                      <td><strong><?php echo number_format($payment['Amount'], 2); ?></strong></td>
                      <td class="<?php echo $methodClass; ?>">
                        <i class="<?php echo $methodIcon; ?> payment-icon"></i>
                        <?php echo $payment['PaymentMethod']; ?>
                      </td>
                      <td><?php echo $payment['Reference']; ?></td>
                      <td><?php echo $payment['Comments']; ?></td>
                    </tr>
                  <?php
                  }
                  ?>
                </tbody>
              </table>
              <?php else: ?>
                <div class="alert alert-info">
                  <button class="close" data-dismiss="alert">×</button>
                  <strong>Info!</strong> Aucun paiement trouvé pour ce fournisseur dans la période sélectionnée.
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
    
    <?php if ($selectedSupplier == 0): // N'afficher cette section que si aucun fournisseur n'est sélectionné ?>
    <hr class="no-print">

    <!-- ========== FORMULAIRE d'ajout de paiement ========== -->
    <div class="row-fluid no-print">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-money"></i></span>
            <h5>Enregistrer un paiement</h5>
          </div>
          <div class="widget-content nopadding">
            <form method="post" class="form-horizontal">
              <div class="control-group">
                <label class="control-label">Fournisseur :</label>
                <div class="controls">
                  <select name="supplierid" required>
                    <option value="">-- Choisir --</option>
                    <?php
                    // Recharger la liste
                    $suppQ2 = mysqli_query($con, "SELECT ID, SupplierName FROM tblsupplier ORDER BY SupplierName ASC");
                    while ($rowS2 = mysqli_fetch_assoc($suppQ2)) {
                      echo '<option value="'.$rowS2['ID'].'">'.$rowS2['SupplierName'].'</option>';
                    }
                    ?>
                  </select>
                </div>
              </div>
              <div class="control-group">
                <label class="control-label">Date de paiement :</label>
                <div class="controls">
                  <input type="date" name="paydate" value="<?php echo date('Y-m-d'); ?>" required />
                </div>
              </div>
              <div class="control-group">
                <label class="control-label">Montant :</label>
                <div class="controls">
                  <input type="number" name="amount" step="any" min="0" value="0" required />
                </div>
              </div>
              <div class="control-group">
                <label class="control-label">Méthode de paiement :</label>
                <div class="controls">
                  <select name="payment_method">
                    <option value="Espèces">Espèces</option>
                    <option value="Chèque">Chèque</option>
                    <option value="Virement">Virement</option>
                    <option value="Carte">Carte</option>
                  </select>
                </div>
              </div>
              <div class="control-group">
                <label class="control-label">Référence :</label>
                <div class="controls">
                  <input type="text" name="reference" placeholder="N° chèque, virement..." />
                </div>
              </div>
              <div class="control-group">
                <label class="control-label">Commentaires :</label>
                <div class="controls">
                  <input type="text" name="comments" placeholder="Notes supplémentaires..." />
                </div>
              </div>
              <div class="form-actions">
                <button type="submit" name="submit" class="btn btn-success">
                  <i class="icon-check"></i> Enregistrer
                </button>
              </div>
            </form>
          </div><!-- widget-content nopadding -->
        </div><!-- widget-box -->
      </div>
    </div><!-- row-fluid -->

    <hr class="no-print">

    <!-- ========== LISTE DES PAIEMENTS ========== -->
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-th"></i></span>
            <h5>Liste des Paiements Récents</h5>
          </div>
          <div class="widget-content nopadding">
            <table class="table table-bordered data-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Date</th>
                  <th>Fournisseur</th>
                  <th>Montant</th>
                  <th>Méthode</th>
                  <th>Référence</th>
                  <th>Commentaires</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $cnt=1;
                while ($row = mysqli_fetch_assoc($resList)) {
                  // Définir la classe CSS selon la méthode de paiement
                  $methodClass = "";
                  if ($row['PaymentMethod'] == 'Espèces') {
                    $methodClass = "payment-method-cash";
                    $methodIcon = "icon-money";
                  } elseif ($row['PaymentMethod'] == 'Virement') {
                    $methodClass = "payment-method-transfer";
                    $methodIcon = "icon-exchange";
                  } elseif ($row['PaymentMethod'] == 'Chèque') {
                    $methodClass = "payment-method-check";
                    $methodIcon = "icon-file-alt";
                  } else {
                    $methodIcon = "icon-credit-card";
                  }
                  ?>
                  <tr>
                    <td><?php echo $cnt; ?></td>
                    <td><?php echo date('d/m/Y', strtotime($row['PaymentDate'])); ?></td>
                    <td>
                      <a href="supplier-payments.php?supplierSearch=<?php echo $row['SupplierID']; ?>">
                        <?php echo $row['SupplierName']; ?>
                      </a>
                    </td>
                    <td><?php echo number_format($row['Amount'],2); ?></td>
                    <td class="<?php echo $methodClass; ?>">
                      <i class="<?php echo $methodIcon; ?> payment-icon"></i>
                      <?php echo $row['PaymentMethod']; ?>
                    </td>
                    <td><?php echo $row['Reference']; ?></td>
                    <td><?php echo $row['Comments']; ?></td>
                  </tr>
                  <?php
                  $cnt++;
                }
                ?>
              </tbody>
            </table>
          </div><!-- widget-content nopadding -->
        </div><!-- widget-box -->
      </div>
    </div><!-- row-fluid -->
    <?php endif; ?>

  </div><!-- container-fluid -->
</div><!-- content -->

<?php include_once('includes/footer.php'); ?>
<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/matrix.tables.js"></script>
<script>
$(document).ready(function() {
  // Affichage du sélecteur de dates personnalisées
  $('#date_filter').change(function() {
    if ($(this).val() === 'custom') {
      $('#custom_date_range').show();
    } else {
      $('#custom_date_range').hide();
    }
  });
  
  // Initialisation des tableaux de données
  $('.data-table').dataTable({
    "bJQueryUI": true,
    "sPaginationType": "full_numbers",
    "sDom": '<""l>t<"F"fp>'
  });
  
  // Gestion du bouton de paiement rapide depuis la liste des fournisseurs
  $('.quick-pay-btn').click(function() {
    var supplierId = $(this).data('supplier-id');
    var supplierName = $(this).data('supplier-name');
    var balance = $(this).data('balance');
    
    // Redirection vers la page du fournisseur
    window.location.href = 'supplier-payments.php?supplierSearch=' + supplierId;
  });
});
</script>
</body>
</html>