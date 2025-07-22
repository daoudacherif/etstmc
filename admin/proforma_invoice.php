<?php
session_start();
include('includes/dbconnection.php');

// Check if user is logged in
if (!isset($_SESSION['imsaid']) || empty($_SESSION['imsaid'])) {
    header("Location: login.php");
    exit;
}

$currentAdminID = $_SESSION['imsaid'];

// Récupérer le numéro de proforma
$proformaNumber = isset($_GET['number']) ? $_GET['number'] : '';

if (empty($proformaNumber)) {
    echo "<script>alert('Numéro de proforma manquant'); window.location.href='proforma.php';</script>";
    exit;
}

// Récupérer les détails de la proforma
$proformaQuery = mysqli_query($con, "
    SELECT * FROM tblproforma 
    WHERE ProformaNumber = '$proformaNumber' AND AdminID = '$currentAdminID'
");

if (mysqli_num_rows($proformaQuery) == 0) {
    echo "<script>alert('Proforma introuvable'); window.location.href='proforma.php';</script>";
    exit;
}

$proformaData = mysqli_fetch_assoc($proformaQuery);

// Récupérer les détails des articles
$itemsQuery = mysqli_query($con, "
    SELECT 
        p.ProductName,
        p.ModelNumber,
        c.ProductQty,
        c.Price,
        (c.ProductQty * c.Price) as LineTotal
    FROM tblcart c
    LEFT JOIN tblproducts p ON p.ID = c.ProductId
    WHERE c.BillingId = '$proformaNumber' AND c.IsCheckOut = 3
");

// Vérifier qu'il y a des articles
if (!$itemsQuery || mysqli_num_rows($itemsQuery) == 0) {
    echo "<script>
            alert('Aucun article trouvé pour cette proforma. Il peut y avoir eu un problème lors de la génération.');
            window.location.href='proforma.php';
          </script>";
    exit;
}

// Récupérer les informations de l'entreprise (admin)
$adminQuery = mysqli_query($con, "SELECT AdminName FROM tbladmin WHERE ID = '$currentAdminID'");
$adminData = mysqli_fetch_assoc($adminQuery);

// Formater la date de création
$formattedDate = date("d/m/Y", strtotime($proformaData['CreatedAt']));
$formattedValidUntil = date("d/m/Y", strtotime($proformaData['ValidUntil']));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<title>Système de Gestion d'Inventaire || Facture Proforma</title>
<?php include_once('includes/cs.php');?>
<?php include_once('includes/responsive.php'); ?>
<style>
  /* Styles pour l'interface normale */
  .invoice-box {
    background-color: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 20px;
  }
  .invoice-header {
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
    margin-bottom: 15px;
  }
  .invoice-total {
    font-weight: bold;
    color: #4169E1;
  }
  .proforma-watermark {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(-45deg);
    font-size: 60px;
    color: rgba(65, 105, 225, 0.1);
    font-weight: bold;
    z-index: 1;
    pointer-events: none;
  }
  .search-form {
    background-color: #f5f5f5;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
  }
  .customer-info td, .customer-info th {
    padding: 8px;
  }
  .print-header {
    display: none;
  }
  .company-header {
    text-align: center;
    margin-bottom: 20px;
    border-bottom: 2px solid #333;
    padding-bottom: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    position: relative;
  }
  
  /* Styles pour le logo */
  .company-logo {
    margin-bottom: 15px;
  }
  .company-logo img {
    max-width: 120px;
    max-height: 80px;
    object-fit: contain;
  }
  
  .company-title {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 5px;
    text-transform: uppercase;
  }
  .company-subtitle {
    font-size: 14px;
    margin-bottom: 10px;
  }
  .company-contact {
    font-size: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .company-contact .left-info {
    text-align: left;
    flex: 1;
  }
  .company-contact .right-info {
    background-color: #333;
    color: white;
    padding: 5px 10px;
    border-radius: 3px;
  }
  .invoice-footer {
    text-align: center;
    margin-top: 30px;
    padding-top: 15px;
    border-top: 1px solid #ddd;
    font-size: 12px;
    color: #666;
  }

  /* Styles pour les signatures */
  .signature-section {
    margin-top: 40px;
    margin-bottom: 30px;
    padding: 20px 0;
    border-top: 1px solid #ddd;
  }
  
  /* Conteneur pour l'affichage horizontal des signatures */
  .signature-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
  }
  
  .signature-box {
    text-align: center;
    padding: 15px;
    flex: 1;
    margin: 0 10px;
  }
  
  .signature-label {
    font-weight: bold;
    font-size: 14px;
    margin-bottom: 30px;
    color: #333;
  }
  
  .signature-date {
    font-size: 12px;
    color: #666;
    margin-top: 10px;
  }

  /* Style pour la validité de la proforma */
  .validity-notice {
    background-color: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
    padding: 15px;
    margin: 20px 0;
    border-radius: 5px;
    text-align: center;
  }
  
  .proforma-title {
    color: #4169E1;
    font-size: 24px;
    font-weight: bold;
    text-align: center;
    margin: 10px 0;
    text-transform: uppercase;
    border: 2px solid #4169E1;
    padding: 10px;
    background-color: rgba(65, 105, 225, 0.1);
  }
  
  /* Styles spécifiques pour l'impression */
  @media print {
    /* Cacher tous les éléments de navigation et UI */
    header, #header, .header, 
    #sidebar, .sidebar, 
    #user-nav, #search, .navbar, 
    footer, #footer, .footer,
    .no-print, #breadcrumb, 
    #content-header, .widget-title {
      display: none !important;
    }
    
    /* Afficher l'en-tête d'impression qui est normalement caché */
    .print-header {
      display: block;
      text-align: center;
      margin-bottom: 20px;
    }
    
    /* Ajuster la mise en page pour l'impression */
    body {
      background: white !important;
      margin: 0 !important;
      padding: 0 !important;
    }
    
    #content {
      margin: 0 !important;
      padding: 0 !important;
      width: 100% !important;
      left: 0 !important;
      position: relative !important;
    }
    
    .container-fluid {
      padding: 0 !important;
      margin: 0 !important;
      width: 100% !important;
    }
    
    .row-fluid .span12 {
      width: 100% !important;
      margin: 0 !important;
      float: none !important;
    }
    
    /* Retirer les bordures et couleurs de fond pour l'impression */
    .widget-box, .invoice-box {
      border: none !important;
      box-shadow: none !important;
      margin: 0 !important;
      padding: 0 !important;
      background: none !important;
    }
    
    /* S'assurer que le logo s'imprime correctement */
    .company-logo img {
      max-width: 100px !important;
      max-height: 70px !important;
      print-color-adjust: exact;
      -webkit-print-color-adjust: exact;
    }
    
    /* Assurer que les tableaux s'impriment correctement */
    table { page-break-inside: auto; }
    tr { page-break-inside: avoid; page-break-after: auto; }
    thead { display: table-header-group; }
    tfoot { display: table-footer-group; }
    
    /* Supprimer les marges et espacements inutiles */
    hr, br.print-hidden {
      display: none !important;
    }
    
    /* Forcer l'impression en noir et blanc par défaut */
    * {
      color: black !important;
      text-shadow: none !important;
      filter: none !important;
      -ms-filter: none !important;
    }
    
    /* Sauf pour certains éléments spécifiques */
    .invoice-total {
      color: #4169E1 !important;
    }
    
    .proforma-title {
      color: #4169E1 !important;
      border: 2px solid #4169E1 !important;
    }
    
    .company-contact .right-info {
      background-color: #333 !important;
      color: white !important;
    }
    
    .validity-notice {
      background-color: #fff3cd !important;
      border: 1px solid #ffeaa7 !important;
      color: #856404 !important;
    }
    
    /* Assurer que les liens sont visibles et sans URL */
    a, a:visited {
      text-decoration: underline;
    }
    a[href]:after {
      content: "";
    }
    
    /* Masquer le bouton d'impression */
    input[name="printbutton"] {
      display: none !important;
    }
    
    /* Styles d'impression pour les signatures */
    .signature-section {
      margin-top: 30px !important;
      margin-bottom: 20px !important;
      padding: 15px 0 !important;
      border-top: 2px solid #000 !important;
      page-break-inside: avoid;
    }
    
    .signature-container {
      display: flex !important;
      justify-content: space-between !important;
    }
    
    .signature-box {
      padding: 10px !important;
      flex: 1 !important;
    }
    
    .signature-label {
      color: black !important;
      font-weight: bold !important;
      font-size: 12px !important;
      margin-bottom: 20px !important;
    }
    
    .signature-date {
      color: black !important;
      font-size: 10px !important;
    }

    /* Filigrane pour l'impression */
    .proforma-watermark {
      color: rgba(65, 105, 225, 0.2) !important;
      font-size: 80px !important;
    }
  }
</style>
</head>
<body>
<!-- Éléments qui seront cachés à l'impression -->
<div class="no-print">
  <?php include_once('includes/header.php');?>
  <?php include_once('includes/sidebar.php');?>
</div>

<div id="content">
  <!-- En-tête de contenu - caché à l'impression -->
  <div id="content-header" class="no-print">
    <div id="breadcrumb"> 
      <a href="dashboard.php" title="Aller à l'accueil" class="tip-bottom"><i class="icon-home"></i> Accueil</a> 
      <a href="proforma.php">Proforma</a>
      <a href="proforma_invoice.php" class="current">Facture Proforma</a> 
    </div>
    <h1>Facture Proforma</h1>
  </div>
  
  <div class="container-fluid">
    <hr class="no-print">
    <div class="row-fluid">
      <div class="span12" id="printArea">
        <!-- Filigrane PROFORMA -->
        <div class="proforma-watermark">PROFORMA</div>
        
        <!-- En-tête de l'entreprise avec logo -->
        <div class="company-header">
          <!-- Logo de l'entreprise -->
          <div class="company-logo">
            <img src="includes/img/logo.jpg" alt="Logo de l'entreprise" />
          </div>
          
          <!-- Informations de l'entreprise -->
          <div class="company-info">
            <div class="company-title">VENTE DE MATERIEL DE CONSTRUCTION</div>
            <div class="company-subtitle">Pointes, Contre plaque, Brouette, Fil d'attache, Peinture, et Divers</div>
            <div class="company-contact">
              <div class="left-info">
                Sis à Bailobaya à côté du marché<br>
                Tél 621 59 87 80 / 621 72 36 46
              </div>
              <div class="right-info">C Plaque</div>
            </div>
          </div>
        </div>
        
        <!-- Titre Proforma -->
        <div class="proforma-title">FACTURE PROFORMA</div>
        
        <div class="invoice-box">
          <div class="invoice-header">
            <h3>Proforma #<?php echo $proformaNumber; ?></h3>
          </div>

          <!-- Informations client -->
          <div class="customer-info">
            <table class="table" width="100%" border="1">
              <tr>
                <th width="25%">Nom du client:</th>
                <td width="25%"><?php echo htmlspecialchars($proformaData['CustomerName']); ?></td>
                <th width="25%">Numéro du client:</th>
                <td width="25%"><?php echo htmlspecialchars($proformaData['CustomerMobile']); ?></td>
              </tr>
              <?php if (!empty($proformaData['CustomerEmail'])): ?>
              <tr>
                <th>Email du client:</th>
                <td colspan="3"><?php echo htmlspecialchars($proformaData['CustomerEmail']); ?></td>
              </tr>
              <?php endif; ?>
              <?php if (!empty($proformaData['CustomerAddress'])): ?>
              <tr>
                <th>Adresse:</th>
                <td colspan="3"><?php echo nl2br(htmlspecialchars($proformaData['CustomerAddress'])); ?></td>
              </tr>
              <?php endif; ?>
              <tr>
                <th>Date de création:</th>
                <td><?php echo $formattedDate; ?></td>
                <th>Préparé par:</th>
                <td><?php echo htmlspecialchars($adminData['AdminName']); ?></td>
              </tr>
            </table>
          </div>
          
          <!-- Notice de validité -->
          <div class="validity-notice">
            <strong>⚠️ PROFORMA - DEVIS VALABLE JUSQU'AU: <?php echo $formattedValidUntil; ?></strong><br>
            <small>Cette proforma n'est pas une facture de vente. Elle est fournie à titre informatif uniquement.</small>
          </div>
          
          <div class="widget-box">
            <div class="widget-title no-print"> 
              <span class="icon"><i class="icon-list-alt"></i></span>
              <h5>Détail des Articles</h5>
            </div>
            <div class="widget-content nopadding" width="100%" border="1">
              <table class="table table-bordered data-table" style="font-size: 15px">
                <thead>
                  <tr>
                    <th width="5%">N°</th>
                    <th width="30%">Nom du Article</th>
                    <th width="15%">Numéro de modèle</th>
                    <th width="10%">Quantité</th>
                    <th width="15%">Prix unitaire</th>
                    <th width="15%">Total</th>
                  </tr>
                </thead>
                <tbody>
                <?php
                $cnt = 1;
                $gtotal = 0;

                while ($row = mysqli_fetch_array($itemsQuery)) {
                  $pq = $row['ProductQty'];
                  $ppu = $row['Price'];
                  $total = $pq * $ppu;
                  $gtotal += $total;
                ?>
                  <tr>
                    <td><?php echo $cnt; ?></td>
                    <td><?php echo htmlspecialchars($row['ProductName']); ?></td>
                    <td><?php echo htmlspecialchars($row['ModelNumber']); ?></td>
                    <td><?php echo $pq; ?></td>
                    <td><?php echo number_format($ppu, 2); ?> GNF</td>
                    <td><?php echo number_format($total, 2); ?> GNF</td>
                  </tr>
                <?php 
                  $cnt++;
                }
                
                // Calculer la remise
                $discount = $gtotal - $proformaData['FinalAmount'];
                ?>
                
                <!-- Ligne de sous-total -->
                <tr>
                  <th colspan="5" style="text-align: right; font-weight: bold; font-size: 15px">Sous-total</th>
                  <th style="text-align: center; font-weight: bold; font-size: 15px"><?php echo number_format($gtotal, 2); ?> GNF</th>
                </tr>
                
                <!-- Ligne de remise (si applicable) -->
                <?php if ($discount > 0): ?>
                <tr>
                  <th colspan="5" style="text-align: right; font-weight: bold; font-size: 15px">Remise</th>
                  <th style="text-align: center; font-weight: bold; font-size: 15px">-<?php echo number_format($discount, 2); ?> GNF</th>
                </tr>
                <?php endif; ?>
                
                <!-- Ligne de total -->
                <tr>
                  <th colspan="5" style="text-align: right; color: #4169E1; font-weight: bold; font-size: 15px" class="invoice-total">Total Net</th>
                  <th style="text-align: center; color: #4169E1; font-weight: bold; font-size: 15px" class="invoice-total"><?php echo number_format($proformaData['FinalAmount'], 2); ?> GNF</th>
                </tr>
                </tbody>
              </table>
              
              <!-- Conditions et termes -->
              <div class="row-fluid">
                <div class="span12">
                  <h4 style="color: #4169E1; margin-top: 20px;">Conditions et Termes:</h4>
                  <ul style="margin: 10px 0; padding-left: 20px; line-height: 1.6;">
                    <li>Cette proforma est valable jusqu'au <strong><?php echo $formattedValidUntil; ?></strong></li>
                    <li>Les prix sont exprimés en Franc Guinéen (GNF) et peuvent être modifiés sans préavis après expiration</li>
                    <li><strong>Cette proforma ne constitue pas une facture de vente</strong></li>
                    <li>La disponibilité des articles est sous réserve du stock au moment de la commande</li>
                    <li>Les conditions de paiement et de livraison seront définies lors de la confirmation de commande</li>
                    <li>Pour confirmer votre commande, veuillez nous contacter avec cette référence: <strong><?php echo $proformaNumber; ?></strong></li>
                  </ul>
                  <p style="margin-top: 20px; font-style: italic; text-align: center;">
                    Merci de nous faire confiance pour vos besoins en matériaux de construction !
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Section des signatures -->
        <div class="signature-section">
          <div class="signature-container">
            <div class="signature-box">
              <p class="signature-label">Signature du Vendeur:</p>
              <p class="signature-date">Date: <?php echo $formattedDate; ?></p>
            </div>
            <div class="signature-box">
              <p class="signature-label">Signature du Client:</p>
              <p class="signature-date">Date: _______________</p>
            </div>
          </div>
        </div>
        
        <!-- Pied de page avec RCCM -->
        <div class="invoice-footer">
          <strong>RCCM GN.TCC.2023.A.14202</strong>
          <br><small>Document généré le <?php echo date('d/m/Y à H:i'); ?></small>
        </div>
        
        <!-- Boutons d'action - cachés à l'impression -->
        <div class="row-fluid no-print" style="margin-top: 20px;">
          <div class="span12 text-center">
            <button class="btn btn-primary" onclick="window.print();">
              <i class="icon-print"></i> Imprimer Proforma
            </button>
            <a href="proforma.php" class="btn btn-success">
              <i class="icon-plus"></i> Nouvelle Proforma
            </a>
            <a href="manage_proforma.php" class="btn btn-info">
              <i class="icon-list"></i> Gérer les Proformas
            </a>
            <a href="dashboard.php" class="btn btn-secondary">
              <i class="icon-home"></i> Tableau de bord
            </a>
            <!-- Bouton de diagnostic (à supprimer en production) -->
            <a href="?number=<?php echo $proformaNumber; ?>&debug=1" class="btn btn-warning btn-small">
              <i class="icon-wrench"></i> Debug
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Pied de page - caché à l'impression -->
<div class="no-print">
  <?php include_once('includes/footer.php');?>
</div>

<!-- Scripts JS -->
<script src="js/jquery.min.js"></script> 
<script src="js/jquery.ui.custom.js"></script> 
<script src="js/bootstrap.min.js"></script> 
<script src="js/jquery.uniform.js"></script> 
<script src="js/select2.min.js"></script> 
<script src="js/jquery.dataTables.min.js"></script> 
<script src="js/matrix.js"></script> 
<script src="js/matrix.tables.js"></script>

<script>
// Auto-impression si demandée
<?php if (isset($_GET['print']) && $_GET['print'] === 'auto'): ?>
window.onload = function() {
    setTimeout(function() {
        window.print();
    }, 1000);
};
<?php endif; ?>

// Forcer le rafraîchissement si nécessaire
<?php if (isset($_GET['refresh'])): ?>
// Supprimer le paramètre refresh de l'URL pour éviter les rafraîchissements répétés
if (window.location.search.indexOf('refresh=1') !== -1) {
    var newUrl = window.location.href.replace(/[?&]refresh=1/, '');
    window.history.replaceState({}, document.title, newUrl);
}
<?php endif; ?>

// Diagnostic pour vérifier les articles (à supprimer en production)
<?php if (isset($_GET['debug'])): ?>
console.log('Proforma Number: <?php echo $proformaNumber; ?>');
console.log('Items found: <?php echo mysqli_num_rows($itemsQuery); ?>');
<?php endif; ?>
</script>
</body>
</html>