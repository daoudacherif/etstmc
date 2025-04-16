<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

// Vérifier si l'admin est connecté
if (strlen($_SESSION['imsaid'] == 0)) {
  header('location:logout.php');
  exit;
}

// 1) Insertion d'un nouveau fournisseur
if (isset($_POST['submit'])) {
  $name    = mysqli_real_escape_string($con, $_POST['suppliername']);
  $phone   = mysqli_real_escape_string($con, $_POST['phone']);
  $email   = mysqli_real_escape_string($con, $_POST['email']);
  $address = mysqli_real_escape_string($con, $_POST['address']);
  $comments= mysqli_real_escape_string($con, $_POST['comments']);

  $sql = "
    INSERT INTO tblsupplier(SupplierName, Phone, Email, Address, Comments)
    VALUES('$name', '$phone', '$email', '$address', '$comments')
  ";
  $res = mysqli_query($con, $sql);
  if ($res) {
    echo "<script>alert('Fournisseur ajouté !');</script>";
  } else {
    echo "<script>alert('Erreur lors de l\\'insertion');</script>";
  }
  echo "<script>window.location.href='supplier.php'</script>";
  exit;
}

// 2) Liste des fournisseurs
$sqlList = "SELECT * FROM tblsupplier ORDER BY ID DESC";
$resList = mysqli_query($con, $sqlList);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Gestion des Fournisseurs</title>
  <?php include_once('includes/cs.php'); ?>
  <?php include_once('includes/responsive.php'); ?>
<?php include_once('includes/header.php'); ?>
<?php include_once('includes/sidebar.php'); ?>

<div id="content">
  <div id="content-header">
    <h1>Fournisseurs</h1>
  </div>
  <div class="container-fluid">
    <hr>

    <!-- Formulaire d'ajout -->
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-align-justify"></i></span>
            <h5>Ajouter un Fournisseur</h5>
          </div>
          <div class="widget-content nopadding">
            <form method="post" class="form-horizontal">
              <div class="control-group">
                <label class="control-label">Nom du Fournisseur :</label>
                <div class="controls">
                  <input type="text" name="suppliername" required />
                </div>
              </div>
              <div class="control-group">
                <label class="control-label">Téléphone :</label>
                <div class="controls">
                  <input type="text" name="phone" />
                </div>
              </div>
              <div class="control-group">
                <label class="control-label">Email :</label>
                <div class="controls">
                  <input type="email" name="email" />
                </div>
              </div>
              <div class="control-group">
                <label class="control-label">Adresse :</label>
                <div class="controls">
                  <textarea name="address"></textarea>
                </div>
              </div>
              <div class="control-group">
                <label class="control-label">Commentaires :</label>
                <div class="controls">
                  <textarea name="comments"></textarea>
                </div>
              </div>
              <div class="form-actions">
                <button type="submit" name="submit" class="btn btn-success">
                  Enregistrer
                </button>
              </div>
            </form>
          </div><!-- widget-content nopadding -->
        </div><!-- widget-box -->
      </div>
    </div><!-- row-fluid -->

    <hr>

    <!-- Liste des fournisseurs -->
    <div class="row-fluid">
      <div class="span12">
        <div class="widget-box">
          <div class="widget-title">
            <span class="icon"><i class="icon-th"></i></span>
            <h5>Liste des Fournisseurs</h5>
          </div>
          <div class="widget-content nopadding">
            <table class="table table-bordered data-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Nom</th>
                  <th>Téléphone</th>
                  <th>Email</th>
                  <th>Adresse</th>
                  <th>Commentaires</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $cnt=1;
                while ($row = mysqli_fetch_assoc($resList)) {
                  ?>
                  <tr>
                    <td><?php echo $cnt; ?></td>
                    <td><?php echo $row['SupplierName']; ?></td>
                    <td><?php echo $row['Phone']; ?></td>
                    <td><?php echo $row['Email']; ?></td>
                    <td><?php echo $row['Address']; ?></td>
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
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/matrix.tables.js"></script>
</body>
</html>
