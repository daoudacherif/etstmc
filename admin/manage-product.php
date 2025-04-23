<?php
session_start();
error_reporting(E_ALL);
include('includes/dbconnection.php');

// Vérifier si l'admin est connecté
if (strlen($_SESSION['imsaid']) == 0) {
    header('location:logout.php');
    exit;
}

// Traitement du formulaire d'ajout de produit
if (isset($_POST['submit'])) {
    $pname    = mysqli_real_escape_string($con, $_POST['pname']);
    $category = intval($_POST['category']);
    $modelno  = mysqli_real_escape_string($con, $_POST['modelno']);
    $stock    = intval($_POST['stock']);
    $price    = floatval($_POST['price']);
    $status   = isset($_POST['status']) ? 1 : 0;

    // Vérifier si un produit portant le même nom existe
    $checkQuery = mysqli_query($con, "SELECT ID FROM tblproducts WHERE ProductName='$pname'");
    if (mysqli_num_rows($checkQuery) > 0) {
        echo '<script>alert("Ce produit existe déjà. Veuillez choisir un autre nom.");</script>';
    } else {
        // Insertion en base
        $query = mysqli_query($con, "INSERT INTO tblproducts
            (ProductName, CatID, ModelNumber, Stock, Price, Status)
            VALUES
            ('$pname', '$category', '$modelno', '$stock', '$price', '$status')");
        if ($query) {
            echo '<script>alert("Le produit a été créé avec succès.");</script>';
        } else {
            echo '<script>alert("Erreur lors de la création du produit : ' . mysqli_error($con) . '");</script>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter un Produit</title>
    <?php include_once('includes/cs.php'); ?>
    <?php include_once('includes/responsive.php'); ?>
</head>
<body>
<?php include_once('includes/header.php'); ?>
<?php include_once('includes/sidebar.php'); ?>

<div id="content">
    <div id="content-header">
        <div id="breadcrumb">
            <a href="dashboard.php" class="tip-bottom"><i class="icon-home"></i> Accueil</a>
            <a class="current">Ajouter un Produit</a>
        </div>
        <h1>Ajouter un Produit</h1>
    </div>
    <div class="container-fluid">
        <hr>
        <div class="row-fluid">
            <div class="span12">
                <div class="widget-box">
                    <div class="widget-title">
                        <span class="icon"><i class="icon-align-justify"></i></span>
                        <h5>Formulaire d'ajout</h5>
                    </div>
                    <div class="widget-content nopadding">
                        <form method="post" class="form-horizontal">

                            <!-- Nom du Produit -->
                            <div class="control-group">
                                <label class="control-label">Nom du Produit :</label>
                                <div class="controls">
                                    <input type="text" name="pname" class="span11" required placeholder="Entrez le nom du produit" />
                                </div>
                            </div>

                            <!-- Catégorie -->
                            <div class="control-group">
                                <label class="control-label">Catégorie :</label>
                                <div class="controls">
                                    <select name="category" class="span11" required>
                                        <option value="">Sélectionnez une Catégorie</option>
                                        <?php
                                        $catQuery = mysqli_query($con, "SELECT ID, CategoryName FROM tblcategory WHERE Status=1");
                                        while ($row = mysqli_fetch_assoc($catQuery)) {
                                            echo '<option value="'.$row['ID'].'">'.htmlspecialchars($row['CategoryName']).'</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Numéro de Modèle -->
                            <div class="control-group">
                                <label class="control-label">Numéro de Modèle :</label>
                                <div class="controls">
                                    <input type="text" name="modelno" class="span11" maxlength="20" placeholder="Ex: ABC123" />
                                </div>
                            </div>

                            <!-- Stock -->
                            <div class="control-group">
                                <label class="control-label">Stock (unités) :</label>
                                <div class="controls">
                                    <input type="number" name="stock" class="span11" required placeholder="Entrez le stock" min="0" />
                                </div>
                            </div>

                            <!-- Prix -->
                            <div class="control-group">
                                <label class="control-label">Prix (par unité) :</label>
                                <div class="controls">
                                    <input type="number" step="any" name="price" class="span11" required placeholder="Entrez le prix" min="0" />
                                </div>
                            </div>

                            <!-- Statut -->
                            <div class="control-group">
                                <label class="control-label">Statut :</label>
                                <div class="controls">
                                    <label><input type="checkbox" name="status" value="1" checked /> Actif</label>
                                </div>
                            </div>

                            <div class="form-actions text-center">
                                <button type="submit" name="submit" class="btn btn-success">Ajouter</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once('includes/footer.php'); ?>
<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
</body>
</html>
