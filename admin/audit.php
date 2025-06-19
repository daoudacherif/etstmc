<?php
session_start();
error_reporting(E_ALL);
include('includes/dbconnection.php');

// Vérifier si l'admin est connecté
if (empty($_SESSION['imsaid'])) {
    header('location:logout.php');
    exit;
}

// Date de réconciliation (aujourd'hui par défaut)
$audit_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Caisse physique déclarée
$caisse_physique = isset($_POST['caisse_physique']) ? floatval($_POST['caisse_physique']) : 14765000;

echo "<!-- AUDIT DE CAISSE POUR LE " . $audit_date . " -->";

// =====================================================
// 1. CALCUL DU SOLDE THÉORIQUE (même logique que rapport)
// =====================================================

// Ventes régulières (avec remises)
$sqlSalesRegular = "
  SELECT COALESCE(SUM(cust.FinalAmount), 0) AS totalSales
  FROM tblcustomer cust
  WHERE cust.ModeofPayment != 'credit'
    AND DATE(cust.BillingDate) <= '$audit_date'
    AND EXISTS (
      SELECT 1 FROM tblcart c 
      WHERE c.BillingId = cust.BillingNumber 
        AND c.IsCheckOut = '1'
    )
";
$stmtSalesRegular = $con->prepare($sqlSalesRegular);
$stmtSalesRegular->execute();
$resultSalesRegular = $stmtSalesRegular->get_result();
$rowSalesRegular = $resultSalesRegular->fetch_assoc();
$totalSalesRegular = $rowSalesRegular['totalSales'];

// Paiements clients 
$sqlPaidAmounts = "
  SELECT COALESCE(SUM(PaymentAmount), 0) AS totalPaid
  FROM tblpayments
  WHERE DATE(PaymentDate) <= '$audit_date'
";
$stmtPaidAmounts = $con->prepare($sqlPaidAmounts);
$stmtPaidAmounts->execute();
$resultPaidAmounts = $stmtPaidAmounts->get_result();
$rowPaidAmounts = $resultPaidAmounts->fetch_assoc();
$totalPaid = $rowPaidAmounts['totalPaid'];

// Dépôts/Retraits manuels
$sqlTransactions = "
  SELECT
    COALESCE(SUM(CASE WHEN TransType='IN' THEN Amount ELSE 0 END), 0) AS totalDeposits,
    COALESCE(SUM(CASE WHEN TransType='OUT' THEN Amount ELSE 0 END), 0) AS totalWithdrawals
  FROM tblcashtransactions
  WHERE DATE(TransDate) <= '$audit_date'
";
$stmtTransactions = $con->prepare($sqlTransactions);
$stmtTransactions->execute();
$resultTransactions = $stmtTransactions->get_result();
$rowTransactions = $resultTransactions->fetch_assoc();
$totalDeposits = $rowTransactions['totalDeposits'];
$totalWithdrawals = $rowTransactions['totalWithdrawals'];

// Retours
$sqlReturns = "
  SELECT COALESCE(SUM(r.Quantity * p.Price), 0) AS totalReturns
  FROM tblreturns r
  JOIN tblproducts p ON p.ID = r.ProductID
  WHERE DATE(r.ReturnDate) <= '$audit_date'
";
$stmtReturns = $con->prepare($sqlReturns);
$stmtReturns->execute();
$resultReturns = $stmtReturns->get_result();
$rowReturns = $resultReturns->fetch_assoc();
$totalReturns = $rowReturns['totalReturns'];

// Solde théorique (selon le système)
$solde_theorique = ($totalSalesRegular + $totalPaid + $totalDeposits) - ($totalWithdrawals + $totalReturns);

// Écart à identifier
$ecart = $caisse_physique - $solde_theorique;

// =====================================================
// 2. ANALYSE DÉTAILLÉE POUR IDENTIFIER L'ÉCART
// =====================================================

// Transactions du jour non comptabilisées
$sqlTransactionsJour = "
  SELECT 
    'Vente' as Type,
    cust.BillingNumber as Reference,
    cust.FinalAmount as Montant,
    c.CartDate as Date,
    CONCAT('Facture ', cust.BillingNumber, ' - ', cust.CustomerName) as Description
  FROM tblcustomer cust
  JOIN tblcart c ON c.BillingId = cust.BillingNumber
  WHERE DATE(c.CartDate) = '$audit_date'
    AND c.IsCheckOut = '1'
    AND cust.ModeofPayment != 'credit'
  
  UNION ALL
  
  SELECT 
    'Paiement Client' as Type,
    CONCAT('PAY-', p.ID) as Reference,
    p.PaymentAmount as Montant,
    p.PaymentDate as Date,
    CONCAT('Paiement de ', c.CustomerName, ' (', p.PaymentMethod, ')') as Description
  FROM tblpayments p
  JOIN tblcustomer c ON p.CustomerID = c.ID
  WHERE DATE(p.PaymentDate) = '$audit_date'
  
  UNION ALL
  
  SELECT 
    CASE WHEN TransType='IN' THEN 'Dépôt' ELSE 'Retrait' END as Type,
    CONCAT('TXN-', ID) as Reference,
    CASE WHEN TransType='IN' THEN Amount ELSE -Amount END as Montant,
    TransDate as Date,
    Comments as Description
  FROM tblcashtransactions
  WHERE DATE(TransDate) = '$audit_date'
  
  ORDER BY Date DESC
";
$resultTransactionsJour = mysqli_query($con, $sqlTransactionsJour);

// Vérification des paiements en espèces uniquement
$sqlCashOnly = "
  SELECT COALESCE(SUM(PaymentAmount), 0) AS cashPayments
  FROM tblpayments
  WHERE DATE(PaymentDate) <= '$audit_date'
    AND PaymentMethod = 'Cash'
";
$resultCashOnly = mysqli_query($con, $sqlCashOnly);
$rowCashOnly = mysqli_fetch_assoc($resultCashOnly);
$totalCashPayments = $rowCashOnly['cashPayments'];

// Recalcul avec seulement les espèces
$solde_theorique_cash_only = ($totalSalesRegular + $totalCashPayments + $totalDeposits) - ($totalWithdrawals + $totalReturns);
$ecart_cash_only = $caisse_physique - $solde_theorique_cash_only;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Audit de Caisse - Réconciliation</title>
  <?php include_once('includes/cs.php'); ?>
  <style>
    .audit-container {
      max-width: 1200px;
      margin: 20px auto;
      padding: 20px;
    }
    .audit-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 20px;
      border-radius: 8px;
      margin-bottom: 30px;
      text-align: center;
    }
    .ecart-box {
      padding: 20px;
      border-radius: 8px;
      margin-bottom: 20px;
      text-align: center;
    }
    .ecart-positif {
      background-color: #d4edda;
      border: 1px solid #c3e6cb;
      color: #155724;
    }
    .ecart-negatif {
      background-color: #f8d7da;
      border: 1px solid #f5c6cb;
      color: #721c24;
    }
    .section-title {
      background-color: #f8f9fa;
      padding: 15px;
      border-left: 4px solid #007bff;
      margin: 20px 0 10px 0;
    }
    .diagnostic-item {
      background: white;
      border: 1px solid #dee2e6;
      border-radius: 5px;
      padding: 15px;
      margin-bottom: 10px;
    }
    .diagnostic-item.urgent {
      border-left: 4px solid #dc3545;
    }
    .diagnostic-item.warning {
      border-left: 4px solid #ffc107;
    }
    .diagnostic-item.info {
      border-left: 4px solid #17a2b8;
    }
    .amount-highlight {
      font-size: 1.3em;
      font-weight: bold;
      color: #007bff;
    }
    .table-audit {
      background: white;
      border-radius: 5px;
      overflow: hidden;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
  </style>
</head>
<body>

<div class="audit-container">
  <!-- En-tête de l'audit -->
  <div class="audit-header">
    <h1>🔍 AUDIT DE CAISSE</h1>
    <p>Réconciliation pour le <?php echo date('d/m/Y', strtotime($audit_date)); ?></p>
    <p>Écart détecté : <strong><?php echo number_format(abs($ecart), 0); ?></strong></p>
  </div>

  <!-- Formulaire de saisie caisse physique -->
  <form method="post" class="form-inline" style="text-align: center; margin-bottom: 30px;">
    <label for="caisse_physique">Montant en caisse physique :</label>
    <input type="number" name="caisse_physique" value="<?php echo $caisse_physique; ?>" style="width: 150px; margin: 0 10px;">
    <input type="hidden" name="date" value="<?php echo $audit_date; ?>">
    <button type="submit" class="btn btn-primary">Recalculer</button>
  </form>

  <!-- Résumé de l'écart -->
  <div class="<?php echo ($ecart >= 0) ? 'ecart-positif' : 'ecart-negatif'; ?> ecart-box">
    <h3>
      <?php if($ecart > 0): ?>
        💰 SURPLUS EN CAISSE : +<?php echo number_format($ecart, 0); ?>
      <?php elseif($ecart < 0): ?>
        ⚠️ MANQUE EN CAISSE : <?php echo number_format($ecart, 0); ?>
      <?php else: ?>
        ✅ CAISSE ÉQUILIBRÉE
      <?php endif; ?>
    </h3>
    <p>Caisse physique : <span class="amount-highlight"><?php echo number_format($caisse_physique, 0); ?></span></p>
    <p>Solde théorique : <span class="amount-highlight"><?php echo number_format($solde_theorique, 0); ?></span></p>
  </div>

  <!-- Diagnostic détaillé -->
  <div class="section-title">
    <h4>🔎 DIAGNOSTIC - Sources possibles de l'écart</h4>
  </div>

  <!-- 1. Vérification paiements en espèces uniquement -->
  <div class="diagnostic-item <?php echo (abs($ecart_cash_only) < abs($ecart)) ? 'info' : 'warning'; ?>">
    <h5>1. Méthodes de paiement</h5>
    <p><strong>Hypothèse :</strong> Seuls les paiements en espèces sont en caisse</p>
    <p>Solde avec espèces uniquement : <strong><?php echo number_format($solde_theorique_cash_only, 0); ?></strong></p>
    <p>Écart si espèces seulement : <strong><?php echo number_format($ecart_cash_only, 0); ?></strong></p>
    <p>Paiements non-espèces : <strong><?php echo number_format($totalPaid - $totalCashPayments, 0); ?></strong></p>
    <?php if(abs($ecart_cash_only) < 1000): ?>
      <p class="text-success">✅ <strong>Probable cause identifiée !</strong> L'écart devient négligeable si on ne compte que les espèces.</p>
    <?php endif; ?>
  </div>

  <!-- 2. Analyse des composants -->
  <div class="diagnostic-item info">
    <h5>2. Décomposition du solde théorique</h5>
    <table class="table table-striped">
      <tr><td>Ventes régulières (avec remises)</td><td style="text-align: right;">+<?php echo number_format($totalSalesRegular, 0); ?></td></tr>
      <tr><td>Paiements clients (tous moyens)</td><td style="text-align: right;">+<?php echo number_format($totalPaid, 0); ?></td></tr>
      <tr><td style="padding-left: 20px;">→ dont espèces uniquement</td><td style="text-align: right;">+<?php echo number_format($totalCashPayments, 0); ?></td></tr>
      <tr><td>Dépôts manuels</td><td style="text-align: right;">+<?php echo number_format($totalDeposits, 0); ?></td></tr>
      <tr><td>Retraits</td><td style="text-align: right;">-<?php echo number_format($totalWithdrawals, 0); ?></td></tr>
      <tr><td>Retours</td><td style="text-align: right;">-<?php echo number_format($totalReturns, 0); ?></td></tr>
      <tr style="border-top: 2px solid #ddd; font-weight: bold;">
        <td>TOTAL THÉORIQUE</td>
        <td style="text-align: right;"><?php echo number_format($solde_theorique, 0); ?></td>
      </tr>
    </table>
  </div>

  <!-- 3. Transactions du jour -->
  <div class="diagnostic-item">
    <h5>3. Transactions du jour (<?php echo $audit_date; ?>)</h5>
    <p>Vérifiez si toutes ces transactions sont reflétées dans votre caisse :</p>
    <table class="table table-bordered table-audit">
      <thead>
        <tr>
          <th>Type</th>
          <th>Référence</th>
          <th>Montant</th>
          <th>Heure</th>
          <th>Description</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        $total_jour = 0;
        while($txn = mysqli_fetch_assoc($resultTransactionsJour)): 
          $total_jour += $txn['Montant'];
        ?>
        <tr>
          <td><span class="label <?php echo ($txn['Montant'] > 0) ? 'label-success' : 'label-important'; ?>">
            <?php echo $txn['Type']; ?>
          </span></td>
          <td><?php echo $txn['Reference']; ?></td>
          <td style="text-align: right;"><?php echo number_format($txn['Montant'], 0); ?></td>
          <td><?php echo date('H:i', strtotime($txn['Date'])); ?></td>
          <td><?php echo htmlspecialchars($txn['Description']); ?></td>
        </tr>
        <?php endwhile; ?>
        <tr style="background-color: #f8f9fa; font-weight: bold;">
          <td colspan="2">TOTAL DU JOUR</td>
          <td style="text-align: right;"><?php echo number_format($total_jour, 0); ?></td>
          <td colspan="2"></td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- 4. Actions recommandées -->
  <div class="section-title">
    <h4>🎯 ACTIONS RECOMMANDÉES</h4>
  </div>

  <?php if(abs($ecart) > 100000): ?>
  <div class="diagnostic-item urgent">
    <h5>🚨 Écart important détecté</h5>
    <ul>
      <li>Vérifiez les gros retraits non enregistrés</li>
      <li>Contrôlez les ventes en espèces non saisies</li>
      <li>Vérifiez si des dépôts ont été oubliés</li>
      <li>Recherchez des erreurs de frappe dans les montants</li>
    </ul>
  </div>
  <?php endif; ?>

  <div class="diagnostic-item info">
    <h5>✅ Liste de vérification</h5>
    <div style="columns: 2;">
      <label><input type="checkbox"> Compter physiquement la caisse</label><br>
      <label><input type="checkbox"> Vérifier les dernières ventes non saisies</label><br>
      <label><input type="checkbox"> Contrôler les retraits du jour</label><br>
      <label><input type="checkbox"> Vérifier les dépôts oubliés</label><br>
      <label><input type="checkbox"> Contrôler les paiements par carte/virement</label><br>
      <label><input type="checkbox"> Vérifier les remboursements clients</label><br>
      <label><input type="checkbox"> Contrôler la monnaie rendue</label><br>
      <label><input type="checkbox"> Vérifier les factures annulées</label><br>
    </div>
  </div>

  <!-- 5. Boutons d'action -->
  <div style="text-align: center; margin-top: 30px;">
    <a href="transact.php" class="btn btn-success">
      <i class="icon-plus"></i> Corriger par une transaction
    </a>
    <a href="report.php" class="btn btn-info">
      <i class="icon-chart-bar"></i> Voir le rapport financier
    </a>
    <button onclick="window.print()" class="btn btn-primary">
      <i class="icon-print"></i> Imprimer l'audit
    </button>
  </div>

</div>

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
</body>
</html>