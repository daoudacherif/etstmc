<?php 
session_start();
// Affiche toutes les erreurs (à désactiver en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include('includes/dbconnection.php');

if (empty($_SESSION['imsaid'])) {
    header('location:logout.php');
    exit;
}

$productId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Récupérer les infos du produit
$productQuery = "SELECT * FROM tblproducts WHERE ID = ?";
$stmt = mysqli_prepare($con, $productQuery);
mysqli_stmt_bind_param($stmt, "i", $productId);
mysqli_stmt_execute($stmt);
$productResult = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($productResult);

if (!$product) {
    header('location:inventory-history.php');
    exit;
}

// Enregistrer un mouvement de stock
if (isset($_POST['add_movement'])) {
    $movementType = $_POST['movement_type'];
    $quantity = $_POST['quantity'];
    $reason = $_POST['reason'];
    $date = date('Y-m-d H:i:s');
    
    // Créer la table si elle n'existe pas
    $createTableQuery = "
        CREATE TABLE IF NOT EXISTS tblstock_movements (
            ID INT AUTO_INCREMENT PRIMARY KEY,
            ProductID INT,
            MovementType VARCHAR(50),
            Quantity INT,
            Reason TEXT,
            MovementDate DATETIME,
            CreatedBy INT,
            FOREIGN KEY (ProductID) REFERENCES tblproducts(ID)
        )
    ";
    mysqli_query($con, $createTableQuery);
    
    // Insérer le mouvement
    $insertQuery = "INSERT INTO tblstock_movements (ProductID, MovementType, Quantity, Reason, MovementDate, CreatedBy) 
                    VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($con, $insertQuery);
    mysqli_stmt_bind_param($stmt, "isissi", $productId, $movementType, $quantity, $reason, $date, $_SESSION['imsaid']);
    
    if (mysqli_stmt_execute($stmt)) {
    // Mettre à jour le stock selon le type de mouvement
    if ($movementType == 'entree') {
        $updateQuery = "UPDATE tblproducts SET Stock = Stock + ? WHERE ID = ?";
    } elseif ($movementType == 'sortie') {
        $updateQuery = "UPDATE tblproducts SET Stock = Stock - ? WHERE ID = ?";
    } elseif ($movementType == 'inventaire') {
        // Pour un inventaire physique, on définit directement le nouveau stock
        $updateQuery = "UPDATE tblproducts SET Stock = ? WHERE ID = ?";
    }
        $stmt2 = mysqli_prepare($con, $updateQuery);
        mysqli_stmt_bind_param($stmt2, "ii", $quantity, $productId);
        mysqli_stmt_execute($stmt2);
        
        $successMsg = "Mouvement de stock enregistré avec succès!";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Historique - <?= htmlspecialchars($product['ProductName']) ?></title>
  <?php include_once('includes/cs.php'); ?>
  <style>
    .product-header { background: #f5f5f5; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
    .movement-in { color: #4CAF50; font-weight: bold; }
    .movement-out { color: #F44336; font-weight: bold; }
    .movement-return { color: #2196F3; font-weight: bold; }
    .timeline { position: relative; padding: 20px 0; }
    .timeline-item { margin-bottom: 20px; padding-left: 40px; position: relative; }
    .timeline-item:before { content: ''; position: absolute; left: 10px; top: 5px; width: 10px; height: 10px; border-radius: 50%; background: #ccc; }
    .timeline-item.in:before { background: #4CAF50; }
    .timeline-item.out:before { background: #F44336; }
    .timeline-item.return:before { background: #2196F3; }
  </style>
</head>
<body>
<?php include_once('includes/header.php'); ?>
<?php include_once('includes/sidebar.php'); ?>

<div id="content">
  <div id="content-header">
    <div id="breadcrumb">
      <a href="dashboard.php" class="tip-bottom"><i class="icon-home"></i> Accueil</a>
      <a href="inventory.php">Inventaire</a>
      <strong>Historique du Produit</strong>
    </div>
    <h1>Historique des Mouvements de Stock</h1>
  </div>
  
  <div class="container-fluid">
    <hr>
    
    <?php if (isset($successMsg)): ?>
    <div class="alert alert-success"><?= $successMsg ?></div>
    <?php endif; ?>
    
    <!-- En-tête du produit -->
    <div class="product-header">
      <h2><?= htmlspecialchars($product['ProductName']) ?></h2>
      <div class="row-fluid">
        <div class="span3">
          <strong>Marque:</strong> <?= htmlspecialchars($product['BrandName']) ?>
        </div>
        <div class="span3">
          <strong>Modèle:</strong> <?= htmlspecialchars($product['ModelNumber']) ?>
        </div>
        <div class="span3">
          <strong>Stock Actuel:</strong> 
          <span class="badge badge-info" style="font-size: 16px;"><?= $product['Stock'] ?></span>
        </div>
        <div class="span3">
          <strong>Prix:</strong> <?= number_format($product['Price'], 2) ?> €
        </div>
      </div>
    </div>
    
    <div class="row-fluid">
      <!-- Formulaire d'ajout de mouvement -->
      <div class="span4">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-plus"></i></span>
            <h5>Enregistrer un Mouvement</h5>
          </div>
          <div class="widget-content">
            <form method="POST" action="">
              <div class="control-group">
                <label>Type de Mouvement:</label>
                <select name="movement_type" class="form-control" required onchange="updateQuantityLabel(this.value)">
                  <option value="">Sélectionner...</option>
                  <option value="entree">Entrée (Réapprovisionnement)</option>
                  <option value="sortie">Sortie (Ajustement négatif)</option>
                  <option value="inventaire">Inventaire Physique (Définir le stock)</option>
                </select>
              </div>
              
              <div class="control-group">
                <label id="quantity_label">Quantité:</label>
                <input type="number" name="quantity" min="0" required class="form-control">
                <small id="quantity_help" class="form-text text-muted"></small>
              </div>
              
              <div class="control-group">
                <label>Raison/Commentaire:</label>
                <textarea name="reason" rows="3" class="form-control" required></textarea>
              </div>
              
              <div class="control-group">
                <button type="submit" name="add_movement" class="btn btn-primary">
                  <i class="icon-save"></i> Enregistrer
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
      
      <!-- Historique des mouvements -->
      <div class="span8">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-time"></i></span>
            <h5>Historique Complet</h5>
          </div>
          <div class="widget-content">
            <div class="timeline">
              <?php
              // Récupérer tous les mouvements
              $query = "
                SELECT 
                  'vente' as type,
                  c.ProductQty as quantity,
                  CONCAT('Vente - Facture #', c.InvoiceId) as reason,
                  c.CartDate as date,
                  'Système' as created_by
                FROM tblcart c
                WHERE c.ProductId = ? AND c.IsCheckOut = 1
                
                UNION ALL
                
                SELECT 
                  'retour' as type,
                  r.Quantity as quantity,
                  CONCAT('Retour - ', r.Reason) as reason,
                  r.ReturnDate as date,
                  'Système' as created_by
                FROM tblreturns r
                WHERE r.ProductID = ?
                
                UNION ALL
                
                SELECT 
                  m.MovementType as type,
                  m.Quantity as quantity,
                  m.Reason as reason,
                  m.MovementDate as date,
                  CONCAT('Admin #', m.CreatedBy) as created_by
                FROM tblstock_movements m
                WHERE m.ProductID = ?
                
                ORDER BY date DESC
                LIMIT 50
              ";
              
              $stmt = mysqli_prepare($con, $query);
              mysqli_stmt_bind_param($stmt, "iii", $productId, $productId, $productId);
              mysqli_stmt_execute($stmt);
              $result = mysqli_stmt_get_result($stmt);
              
              while ($movement = mysqli_fetch_assoc($result)) {
                $class = '';
                $icon = '';
                $prefix = '';
                
                switch($movement['type']) {
                  case 'entree':
                    $class = 'in movement-in';
                    $icon = 'icon-arrow-down';
                    $prefix = '+';
                    break;
                  case 'sortie':
                  case 'vente':
                    $class = 'out movement-out';
                    $icon = 'icon-arrow-up';
                    $prefix = '-';
                    break;
                  case 'retour':
                    $class = 'return movement-return';
                    $icon = 'icon-refresh';
                    $prefix = '+';
                    break;
                  default:
                    $class = '';
                    $icon = 'icon-edit';
                    $prefix = '';
                }
                ?>
                <div class="timeline-item <?= $class ?>">
                  <div class="row-fluid">
                    <div class="span2">
                      <small><?= date('d/m/Y H:i', strtotime($movement['date'])) ?></small>
                    </div>
                    <div class="span2">
                      <i class="<?= $icon ?>"></i> 
                      <span class="<?= str_replace(' ', '-', $movement['type']) ?>"><?= $prefix ?><?= $movement['quantity'] ?></span>
                    </div>
                    <div class="span5">
                      <?= htmlspecialchars($movement['reason']) ?>
                    </div>
                    <div class="span3">
                      <small>Par: <?= $movement['created_by'] ?></small>
                    </div>
                  </div>
                </div>
                <?php
              }
              ?>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Graphique d'évolution du stock -->
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-signal"></i></span>
            <h5>Évolution du Stock (30 derniers jours)</h5>
          </div>
          <div class="widget-content">
            <canvas id="stockChart" height="100"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include_once('includes/footer.php'); ?>

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Fonction pour mettre à jour le label de quantité
function updateQuantityLabel(movementType) {
    var label = document.getElementById('quantity_label');
    var help = document.getElementById('quantity_help');
    var currentStock = <?= $product['Stock'] ?>;
    
    switch(movementType) {
        case 'entree':
            label.textContent = 'Quantité à ajouter:';
            help.textContent = 'Nombre d\'unités à ajouter au stock actuel';
            break;
        case 'sortie':
            label.textContent = 'Quantité à retirer:';
            help.textContent = 'Nombre d\'unités à retirer du stock actuel (max: ' + currentStock + ')';
            break;
        case 'inventaire':
            label.textContent = 'Nouveau stock total:';
            help.textContent = 'Stock actuel: ' + currentStock + ' - Entrez le nouveau total après comptage physique';
            break;
        default:
            label.textContent = 'Quantité:';
            help.textContent = '';
    }
}

// Graphique d'évolution du stock
<?php
// Préparer les données pour le graphique
$chartQuery = "
  SELECT 
    DATE(date) as day,
    SUM(CASE 
      WHEN type IN ('entree', 'retour') THEN quantity 
      WHEN type IN ('sortie', 'vente') THEN -quantity 
      ELSE 0 
    END) as movement
  FROM (
    SELECT 'vente' as type, ProductQty as quantity, CartDate as date
    FROM tblcart WHERE ProductId = ? AND IsCheckOut = 1
    UNION ALL
    SELECT 'retour' as type, Quantity as quantity, ReturnDate as date
    FROM tblreturns WHERE ProductID = ?
    UNION ALL
    SELECT MovementType as type, Quantity as quantity, MovementDate as date
    FROM tblstock_movements WHERE ProductID = ?
  ) as movements
  WHERE date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
  GROUP BY DATE(date)
  ORDER BY day
";

$stmt = mysqli_prepare($con, $chartQuery);
mysqli_stmt_bind_param($stmt, "iii", $productId, $productId, $productId);
mysqli_stmt_execute($stmt);
$chartResult = mysqli_stmt_get_result($stmt);

$dates = [];
$movements = [];
while ($row = mysqli_fetch_assoc($chartResult)) {
    $dates[] = $row['day'];
    $movements[] = $row['movement'];
}
?>

var ctx = document.getElementById('stockChart').getContext('2d');
var stockChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($dates) ?>,
        datasets: [{
            label: 'Mouvements de Stock',
            data: <?= json_encode($movements) ?>,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>

</body>
</html>