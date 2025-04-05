<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

if (strlen($_SESSION['imsaid'] == 0)) {
    header('location:logout.php');
    exit;
}

// 1) Handle new arrival
if (isset($_POST['submit'])) {
    $productID   = intval($_POST['productid']);
    $supplierID  = intval($_POST['supplierid']);
    $quantity    = intval($_POST['quantity']);
    // On ne tient plus compte du $_POST['cost'] de l'utilisateur,
    // car on va recalcule côté serveur pour sécurité :
    // => on va lire le prix du produit en base.
    $comments    = mysqli_real_escape_string($con, $_POST['comments']);
    $arrivalDate = $_POST['arrivaldate'];

    // -- Récupérer le prix unitaire depuis la base (sécurité)
    $priceQ = mysqli_query($con, "SELECT Price FROM tblproducts WHERE ID='$productID' LIMIT 1");
    $priceR = mysqli_fetch_assoc($priceQ);
    $unitPrice = floatval($priceR['Price']);

    // Calcul du coût total côté serveur (pour éviter manip client)
    $cost = $unitPrice * $quantity;

    if ($productID <= 0 || $supplierID <= 0 || $quantity <= 0 || $cost < 0) {
        echo "<script>alert('Invalid data');</script>";
    } else {
        // Insert into tblproductarrivals
        $sqlInsert = "
          INSERT INTO tblproductarrivals(ProductID, SupplierID, ArrivalDate, Quantity, Cost, Comments)
          VALUES('$productID', '$supplierID', '$arrivalDate', '$quantity', '$cost', '$comments')
        ";
        $queryInsert = mysqli_query($con, $sqlInsert);

        if ($queryInsert) {
            // Update tblproducts stock
            $sqlUpdate = "UPDATE tblproducts
                          SET Stock = Stock + $quantity
                          WHERE ID='$productID'";
            mysqli_query($con, $sqlUpdate);

            echo "<script>alert('Product arrival recorded and stock updated!');</script>";
        } else {
            echo "<script>alert('Error inserting arrival record');</script>";
        }
    }
    echo "<script>window.location.href='arrival.php'</script>";
    exit;
}

// 2) Liste des arrivages
$sqlArrivals = "
  SELECT a.ID as arrivalID,
         a.ArrivalDate,
         a.Quantity,
         a.Cost,
         a.Comments,
         p.ProductName,
         p.Price as UnitPrice,
         s.SupplierName
  FROM tblproductarrivals a
  LEFT JOIN tblproducts p ON p.ID = a.ProductID
  LEFT JOIN tblsupplier s ON s.ID = a.SupplierID
  ORDER BY a.ID DESC
  LIMIT 50
";
$resArrivals = mysqli_query($con, $sqlArrivals);
?>
<!DOCTYPE html>
<html lang="en">
<style>
    .control-label {
      font-size: 20px;
      font-weight: bolder;
      color: black;  
    }
  </style>
<head>
    <title>Inventory Management | Product Arrivals</title>
    <?php include_once('includes/cs.php'); ?>
</head>
<body>

<?php include_once('includes/header.php'); ?>
<?php include_once('includes/sidebar.php'); ?>

<div id="content">
  <div id="content-header">
    <div id="breadcrumb">
      <a href="dashboard.php" class="tip-bottom"><i class="icon-home"></i> Home</a>
      <a href="arrival.php" class="current">Product Arrivals</a>
    </div>
    <h1>Manage Product Arrivals (Stock In)</h1>
  </div>

  <div class="container-fluid">
    <hr>

    <!-- NEW ARRIVAL FORM -->
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-align-justify"></i></span>
            <h5>Add New Product Arrival</h5>
          </div>
          <div class="widget-content nopadding">
            <form method="post" class="form-horizontal" id="arrivalForm">
              
              <!-- Arrival Date -->
              <div class="control-group">
                <label class="control-label">Arrival Date :</label>
                <div class="controls">
                  <input type="date" name="arrivaldate" value="<?php echo date('Y-m-d'); ?>" required />
                </div>
              </div>

              <!-- Product -->
              <div class="control-group">
                <label class="control-label">Select Product :</label>
                <div class="controls">
                  <select name="productid" id="productSelect" required>
                    <option value="">-- Choose Product --</option>
                    <?php
                    // Charger produits avec data-price
                    $prodQ = mysqli_query($con, "SELECT ID, ProductName, Price FROM tblproducts ORDER BY ProductName ASC");
                    while ($pRow = mysqli_fetch_assoc($prodQ)) {
                      // On stocke le prix dans data-price
                      echo '<option value="'.$pRow['ID'].'" data-price="'.$pRow['Price'].'">'.$pRow['ProductName'].'</option>';
                    }
                    ?>
                  </select>
                </div>
              </div>

              <!-- Supplier -->
              <div class="control-group">
                <label class="control-label">Select Supplier :</label>
                <div class="controls">
                  <select name="supplierid" required>
                    <option value="">-- Choose Supplier --</option>
                    <?php
                    $suppQ = mysqli_query($con, "SELECT ID, SupplierName FROM tblsupplier ORDER BY SupplierName ASC");
                    while ($sRow = mysqli_fetch_assoc($suppQ)) {
                      echo '<option value="'.$sRow['ID'].'">'.$sRow['SupplierName'].'</option>';
                    }
                    ?>
                  </select>
                </div>
              </div>

              <!-- Quantity -->
              <div class="control-group">
                <label class="control-label">Quantity :</label>
                <div class="controls">
                  <input type="number" name="quantity" id="quantity" min="1" value="1" required />
                </div>
              </div>

              <!-- Cost (auto-calculé) -->
              <div class="control-group">
                <label class="control-label">Total Cost (auto) :</label>
                <div class="controls">
                  <input type="number" name="cost" id="cost" step="any" min="0"
                         value="0" readonly />
                </div>
              </div>

              <!-- Comments (Optional) -->
              <div class="control-group">
                <label class="control-label">Comments (optional) :</label>
                <div class="controls">
                  <input type="text" name="comments" placeholder="Invoice #, notes..." />
                </div>
              </div>

              <div class="form-actions">
                <button type="submit" name="submit" class="btn btn-success">
                  Record Arrival
                </button>
              </div>
            </form>
          </div><!-- widget-content nopadding -->
        </div><!-- widget-box -->
      </div>
    </div><!-- row-fluid -->

    <hr>

    <!-- RECENT ARRIVALS LIST -->
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-th"></i></span>
            <h5>Recent Product Arrivals</h5>
          </div>
          <div class="widget-content nopadding">
            <table class="table table-bordered data-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Arrival Date</th>
                  <th>Product</th>
                  <th>Supplier</th>
                  <th>Qty</th>
                  <th>Unit Price</th>
                  <th>Total Price</th>
                  <th>Cost (Saisi)</th>
                  <th>Comments</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $cnt = 1;
                while ($row = mysqli_fetch_assoc($resArrivals)) {
                  $unitPrice = floatval($row['UnitPrice']);
                  $qty       = floatval($row['Quantity']);
                  $lineTotal = $unitPrice * $qty;
                  ?>
                  <tr>
                    <td><?php echo $cnt; ?></td>
                    <td><?php echo $row['ArrivalDate']; ?></td>
                    <td><?php echo $row['ProductName']; ?></td>
                    <td><?php echo $row['SupplierName']; ?></td>
                    <td><?php echo $qty; ?></td>
                    <td><?php echo number_format($unitPrice,2); ?></td>
                    <td><?php echo number_format($lineTotal,2); ?></td>
                    <td><?php echo number_format($row['Cost'],2); ?></td>
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

  </div><!-- container-fluid -->
</div><!-- content -->

<?php include_once('includes/footer.php'); ?>
<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script>
// =====================
// Auto-calc cost client-side
// =====================
function updateCost() {
  const productSelect = document.getElementById('productSelect');
  const quantityInput = document.getElementById('quantity');
  const costInput     = document.getElementById('cost');

  if (!productSelect || !quantityInput || !costInput) return;

  // Prix unitaire depuis data-price
  const selectedOption = productSelect.options[productSelect.selectedIndex];
  const unitPrice = parseFloat(selectedOption.getAttribute('data-price')) || 0;

  // Quantité
  const qty = parseFloat(quantityInput.value) || 0;

  // Calcul
  const total = unitPrice * qty;
  costInput.value = total.toFixed(2);
}

// Ecouter les changements
document.addEventListener('DOMContentLoaded', function() {
  // Sur le select du produit
  const productSelect = document.getElementById('productSelect');
  if (productSelect) {
    productSelect.addEventListener('change', updateCost);
  }

  // Sur la quantité
  const quantityInput = document.getElementById('quantity');
  if (quantityInput) {
    quantityInput.addEventListener('input', updateCost);
  }
});
</script>
</body>
</html>
