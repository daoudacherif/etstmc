<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

// Check admin login
if (strlen($_SESSION['imsaid'] == 0)) {
    header('location:logout.php');
    exit;
}

// 1) Handle partial payment submission
if (isset($_POST['addPayment'])) {
    $cid       = intval($_POST['cid']);        // ID from tblcustomer
    $payAmount = intval($_POST['payAmount']); // The additional payment

    if ($payAmount <= 0) {
        echo "<script>alert('Montant invalide. Doit être > 0.');</script>";
    } else {
        // Fetch current Paid & Dues for this row
        $sql = "SELECT Paid, Dues FROM tblcustomer WHERE ID='$cid' LIMIT 1";
        $res = mysqli_query($con, $sql);
        if (mysqli_num_rows($res) > 0) {
            $row     = mysqli_fetch_assoc($res);
            $oldPaid = intval($row['Paid']);
            $oldDues = intval($row['Dues']);

            // Calculate new amounts
            $newPaid = $oldPaid + $payAmount;
            $newDues = $oldDues - $payAmount;
            if ($newDues < 0) {
                $newDues = 0; // cannot go below zero
            }

            // Update the record
            $update = "UPDATE tblcustomer 
                       SET Paid='$newPaid', Dues='$newDues'
                       WHERE ID='$cid'";
            mysqli_query($con, $update);

            echo "<script>alert('Paiement mis à jour !');</script>";
        } else {
            echo "<script>alert('Client non trouvé.');</script>";
        }
    }
    // Refresh the page
    echo "<script>window.location.href='customer-details.php'</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Gestion des Stocks | Détails Client</title>
    <?php include_once('includes/cs.php'); ?>
    <?php include_once('includes/responsive.php'); ?>

<?php include_once('includes/header.php'); ?>
<?php include_once('includes/sidebar.php'); ?>

<div id="content">
  <div id="content-header">
    <div id="breadcrumb">
      <a href="dashboard.php" title="Aller à l'accueil" class="tip-bottom">
        <i class="icon-home"></i> Accueil
      </a>
      <a href="customer-details.php" class="current">Détails Client</a>
    </div>
    <h1>Clients / Factures</h1>
  </div>

  <div class="container-fluid">
    <hr>
    <div class="row-fluid">
      <div class="span12">

        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-th"></i></span>
            <h5>Factures</h5>
          </div>
          <div class="widget-content nopadding">

            <table class="table table-bordered data-table">
              <thead>
                <tr>
                  <th>N°</th>
                  <th>Facture #</th>
                  <th>Client</th>
                  <th>Mobile</th>
                  <th>Mode</th>
                  <th>Date</th>
                  <th>Montant</th>
                  <th>Payé</th>
                  <th>Dû</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php
                // Initialize totals
                $totalPaid = 0;
                $totalDues = 0;

                // Fetch all rows from tblcustomer
                $ret = mysqli_query($con, "SELECT * FROM tblcustomer ORDER BY ID DESC");
                $cnt = 1;
                while ($row = mysqli_fetch_array($ret)) {
                    // Accumulate for totals
                    $totalPaid += intval($row['Paid']);
                    $totalDues += intval($row['Dues']);
                ?>
                    <tr class="gradeX">
                      <td><?php echo $cnt; ?></td>
                      <td><?php echo $row['BillingNumber']; ?></td>
                      <td><?php echo $row['CustomerName']; ?></td>
                      <td><?php echo $row['MobileNumber']; ?></td>
                      <td><?php echo $row['ModeofPayment']; ?></td>
                      <td><?php echo $row['BillingDate']; ?></td>
                      <td><?php echo number_format(intval($row['FinalAmount']), 0); ?></td>
                      <td><?php echo number_format(intval($row['Paid']), 0); ?></td>
                      <td><?php echo number_format(intval($row['Dues']), 0); ?></td>
                      <td>
                        <?php if ($row['Dues'] > 0) { ?>
                          <!-- Inline form to add partial payment -->
                          <form method="post" style="margin:0; display:inline;">
                            <input type="hidden" name="cid" value="<?php echo $row['ID']; ?>" />
                            <input type="number" name="payAmount" step="1" min="1" placeholder="Montant" style="width:60px;" />
                            <button type="submit" name="addPayment" class="btn btn-info btn-mini">
                              Payer
                            </button>
                          </form>
                        <?php } else { ?>
                          <span style="color: green; font-weight: bold;">Soldé</span>
                        <?php } ?>
                      </td>
                    </tr>
                <?php
                    $cnt++;
                } // end while
                ?>
              </tbody>
              <!-- Add a final row for totals -->
              <tfoot>
                <tr>
                  <!-- We'll merge the first 7 columns -->
                  <th colspan="7" style="text-align: right; font-weight: bold;">
                    Totaux:
                  </th>
                  <!-- Display the total of the Paid column -->
                  <th style="font-weight: bold;">
                    <?php echo number_format($totalPaid, 0); ?>
                  </th>
                  <!-- Display the total of the Dues column -->
                  <th style="font-weight: bold;">
                    <?php echo number_format($totalDues, 0); ?>
                  </th>
                  <th></th> <!-- Action column blank -->
                </tr>
              </tfoot>
            </table>

          </div><!-- widget-content nopadding -->
        </div><!-- widget-box -->

      </div><!-- span12 -->
    </div><!-- row-fluid -->
  </div><!-- container-fluid -->
</div><!-- content -->

<!-- Footer -->
<?php include_once('includes/footer.php'); ?>

<!-- Scripts -->
<script src="js/jquery.min.js"></script>
<script src="js/jquery.ui.custom.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/jquery.uniform.js"></script>
<script src="js/select2.min.js"></script>
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/matrix.js"></script>
<script src="js/matrix.tables.js"></script>
</body>
</html>