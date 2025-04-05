<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');
if (strlen($_SESSION['imsaid']==0)) {
  header('location:logout.php');
  } else{



  ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<title>Système de Gestion d'Inventaire || Tableau de Bord</title>

<?php include_once('includes/cs.php');?>
</head>
<body>

 



<?php include_once('includes/header.php');?>
<?php include_once('includes/sidebar.php');?>
<!--sidebar-menu-->

<!--main-container-part-->
<div id="content">
<!--breadcrumbs-->
  <div id="content-header">
    <div id="breadcrumb"> <a href="dashboard.php" title="Aller à l'accueil" class="tip-bottom"><i class="icon-home"></i> Accueil</a></div>
  </div>
<!--End-breadcrumbs-->

<!--Action boxes-->
<br />
  <div class="container-fluid">
   <div class="widget-box widget-plain">
      <div class="center">

<ul class="quick-actions">

  <?php $query1=mysqli_query($con,"Select * from tblbrand where Status='1'");
$brandcount=mysqli_num_rows($query1);
?>
        <li class="bg_lb"> <a href="manage-brand.php"><i class="fa fa-building-o fa-3x"></i><br /> 
         <span class="label label-important" style="margin-top:5%"><?php echo $brandcount;?></span> Marques </a> </li>

<?php $query2=mysqli_query($con,"Select * from tblcategory where Status='1'");
$catcount=mysqli_num_rows($query2);
?>

      
        <li class="bg_ly"> <a href="manage-category.php"> <i class="icon-list fa-3x"></i>
          <span class="label label-success" style="margin-top:7%"><?php echo $catcount;?></span> Catégories </a> </li>

<?php $query3=mysqli_query($con,"Select * from tblsubcategory where Status='1'");
$subcatcount=mysqli_num_rows($query3);
?>

        <li class="bg_lo"> <a href="manage-subcategory.php">  <i class="icon-th"></i> <span class="label label--success" style="margin-top:7%"><?php echo $subcatcount;?>    </span>&nbsp; Sous-catégories</a> </li>

<?php $query4=mysqli_query($con,"Select * from tblproducts");
$productcount=mysqli_num_rows($query4);
?>

        <li class="bg_ls"> <a href="manage-product.php"> <i class="icon-list-alt"></i>

         <span class="label label-success" style="margin-top:7%"><?php echo $productcount;?></span>  Produits</a> </li>

            <?php $query5=mysqli_query($con,"Select * from tblcustomer");
$totuser=mysqli_num_rows($query5);
?>

        <li class="bg_lo span3"> <a href="form-common.html"> <i class="icon-user"></i>

        <span class="label label--success" style="margin-top:5%"><?php echo $totuser;?>    </span> Utilisateurs</a> </li>

      </ul>




      </div>
    </div>
    <div class="widget-box widget-plain" style="margin-top:12%">
      <div class="center">
        <h3 style="color:blue">Ventes</h3>
        <hr />

<ul class="site-stats">

              <?php
//vente d'aujourd'hui
 $query6=mysqli_query($con,"select tblcart.ProductQty as ProductQty,tblproducts.Price
 from tblcart 
  join tblproducts  on tblproducts.ID=tblcart.ProductId where date(CartDate)=CURDATE() and IsCheckOut='1'");
while($row=mysqli_fetch_array($query6))
{
$todays_sale=$row['ProductQty']*$row['Price'];
$todysale+=$todays_sale;

}
 ?>
    
                <li class="bg_lh"><font style="font-size:22px; font-weight:bold">$</font><strong><?php echo number_format($todysale,2);?></strong> <small>Ventes d'aujourd'hui</small></li>
           <?php
//vente d'hier
 $query7=mysqli_query($con,"select tblcart.ProductQty as ProductQty,tblproducts.Price
 from tblcart 
  join tblproducts  on tblproducts.ID=tblcart.ProductId where date(CartDate)=CURDATE()-1 and IsCheckOut='1'");
while($row=mysqli_fetch_array($query7))
{
$yesterdays_sale=$row['ProductQty']*$row['Price'];
$yesterdaysale+=$yesterdays_sale;

}
 ?>

                <li class="bg_lh"><font style="font-size:22px; font-weight:bold">$</font> <strong><?php echo number_format($yesterdaysale,2);?></strong> <small>Ventes d'hier </small></li>

            <?php
//Vente des sept derniers jours
 $query8=mysqli_query($con,"select tblcart.ProductQty as ProductQty,tblproducts.Price
 from tblcart 
  join tblproducts  on tblproducts.ID=tblcart.ProductId where date(tblcart.CartDate)>=(DATE(NOW()) - INTERVAL 7 DAY) and tblcart.IsCheckOut='1' ");
while($row=mysqli_fetch_array($query8))
{
$sevendays_sale=$row['ProductQty']*$row['Price'];
$tseven+=$sevendays_sale;
}
 ?>
                <li class="bg_lh"><font style="font-size:22px; font-weight:bold">$</font> <strong><?php echo number_format($tseven,2);?></strong> <small>Ventes des sept derniers jours</small></li>
            <?php
//Vente totale
 $query9=mysqli_query($con,"select tblcart.ProductQty as ProductQty,tblproducts.Price
 from tblcart 
  join tblproducts  on tblproducts.ID=tblcart.ProductId where  IsCheckOut='1' ");
while($row=mysqli_fetch_array($query9))
{
$total_sale=$row['ProductQty']*$row['Price'];
$totalsale+=$total_sale;
}
 ?>

                <li class="bg_lh"><font style="font-size:22px; font-weight:bold">$</font> <strong><?php echo number_format($totalsale,2);?></strong> <small>Ventes totales</small></li>
             
              </ul>


      </div>
    </div>
  </div>
</div>
<?php include_once('includes/footer.php');?>

<?php include_once('includes/js.php');?>
</body>
</html>
<?php } ?>