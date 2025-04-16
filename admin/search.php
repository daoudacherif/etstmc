<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');
if (strlen($_SESSION['imsaid']==0)) {
  header('location:logout.php');
  } else{
// Code for add to cart

if(isset($_POST['cart']))
{

$pid=$_POST['pid'];
$pqty=$_POST['pqty'];
$ischecout=0;
$remainqty=$_SESSION['rqty'];
if($pqty<=$remainqty)
{
$query=mysqli_query($con,"insert into tblcart(ProductId,ProductQty,IsCheckOut) value('$pid','$pqty','$ischecout')");
 echo "<script>alert('Le produit a été ajouté au panier');</script>"; 
  echo "<script>window.location.href = 'search.php'</script>";     
} else{
$msg="Vous ne pouvez pas ajouter une quantité supérieure à la quantité restante";

}

}
  ?>
<!DOCTYPE html>
<html lang="en">
<head>
<title>Système de gestion des stocks || Ajouter des produits</title>
<?php include_once('includes/cs.php');?>
<?php include_once('includes/responsive.php'); ?>

<?php include_once('includes/header.php');?>
<?php include_once('includes/sidebar.php');?>


<div id="content">
  <div id="content-header">
    <div id="breadcrumb"> <a href="dashboard.php" title="Aller à l'accueil" class="tip-bottom"><i class="icon-home"></i> Accueil</a> <a href="search.php" class="current">Rechercher des produits</a> </div>
    <h1>Rechercher des produits</h1>
  </div>
  <div class="container-fluid">
    <hr>
    <div class="row-fluid">
      <div class="span12">
        
       <div class="widget-content nopadding">
          <form method="post" class="form-horizontal">
           
            <div class="control-group">
              <label class="control-label">Rechercher un produit :</label>
              <div class="controls">
                <input type="text" class="span11" name="pname" id="pname" value="" required='true' />
              </div>
            </div>
          
           <div class="text-center">
                  <button class="btn btn-primary my-4" type="submit" name="search">Rechercher</button>
                </div>
          </form>
            <br>
        </div>
   <?php
if(isset($_POST['search']))
{ 

$sdata=$_POST['pname'];
  ?>
  <h4 align="center">Résultat pour le mot-clé "<?php echo $sdata;?>" </h4> 
        
        <div class="widget-box">
          <div class="widget-title"> <span class="icon"><i class="icon-th"></i></span>
            <h5>Rechercher des produits</h5>
          </div>
          <div class="widget-content nopadding">
             
            <table class="table table-bordered data-table">
              <thead>
                <tr>
                  <th>N°</th>
                  <th>Nom du produit</th>
                  <th>Nom de la catégorie</th>
                   <th>Nom de la sous-catégorie</th>
                  <th>Nom de la marque</th>
                  <th>Numéro de modèle</th>
                  <th>Stock</th>
                  <th>Stock restant</th>
                  <th>Quantité d'achat</th>
                  <th>Statut</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
              
                <?php
$ret=mysqli_query($con,"select tblcategory.CategoryName,tblsubcategory.SubCategoryname as subcat,tblproducts.ProductName,tblproducts.BrandName,tblproducts.ID as pid,tblproducts.Status,tblproducts.CreationDate,tblproducts.ModelNumber,tblproducts.Stock,sum(tblcart.ProductQty) as selledqty from tblproducts join tblcategory on tblcategory.ID=tblproducts.CatID join tblsubcategory on tblsubcategory.ID=tblproducts.SubcatID left join tblcart  on tblproducts.ID=tblcart.ProductId where tblproducts.ProductName like '%$sdata%' group by tblproducts.ProductName");
$qty=$result['selledqty'];
$num=mysqli_num_rows($ret);
if($num>0){
$cnt=1;
while ($row=mysqli_fetch_array($ret)) {
$qty=$row['selledqty'];
?>
 <form name="cart" method="post">
                <tr class="gradeX">
                    <input type="hidden" name="pid" value="<?php echo $row['pid'];?>">
                  <td><?php echo $cnt;?></td>
                  <td><?php  echo $row['ProductName'];?></td>
                  <td><?php  echo $row['CategoryName'];?></td>
                  <td><?php  echo $row['subcat'];?></td>
                  <td><?php  echo $row['BrandName'];?></td>
                  <td><?php  echo $row['ModelNumber'];?></td>
                  <td><?php  echo $row['Stock'];?></td>
                   <td><?php  echo ($_SESSION['rqty']=$row['Stock']-$qty);?></td>
 <td><input type="number" name="pqty" value="1" required="true" style="width:40px;"></td>
                  <?php if($row['Status']=="1"){ ?>

                     <td><?php echo "Actif"; ?></td>
<?php } else { ?>                  <td><?php echo "Inactif"; ?>
                  </td>
                  <?php } ?>
                 <td><button type="submit" name="cart" class="btn btn-primary my-4">Ajouter au panier</button></td>               
                </tr>
              </form>
                <?php 
$cnt=$cnt+1;
} }  else { ?>
  <tr>
    <td colspan="8"> Aucun enregistrement trouvé.</td>

  </tr>
   
<?php }} ?>
              </tbody>
            </table>
   
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<!--Footer-part-->
<?php include_once('includes/footer.php');?>
<!--end-Footer-part-->
<script src="js/jquery.min.js"></script> 
<script src="js/jquery.ui.custom.js"></script> 
<script src="js/bootstrap.min.js"></script> 
<script src="js/jquery.uniform.js"></script> 
<script src="js/select2.min.js"></script> 

<script src="js/matrix.js"></script> 
<script src="js/matrix.tables.js"></script>
</body>
</html>
<?php } ?>
