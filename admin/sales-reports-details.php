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
<title>Système de gestion d'inventaire || Détails du rapport de ventes</title>
<?php include_once('includes/cs.php');?>
<?php include_once('includes/responsive.php'); ?>

<?php include_once('includes/header.php');?>
<?php include_once('includes/sidebar.php');?>


<div id="content">
  <div id="content-header">
    <div id="breadcrumb"> <a href="dashboard.php" title="Aller à l'accueil" class="tip-bottom"><i class="icon-home"></i> Accueil</a> <a href="sales-report.php" class="current">Détails du rapport de ventes</a> </div>
    <h1>Détails du rapport de ventes</h1>
  </div>
  <div class="container-fluid">
    <hr>
    <div class="row-fluid">
      <div class="span12">
        
       
     
        
        <div class="widget-box">
          <div class="widget-title"> <span class="icon"><i class="icon-th"></i></span>
            <?php
$fdate=$_POST['fromdate'];
$tdate=$_POST['todate'];
$rtype=$_POST['requesttype'];
?>
<?php if($rtype=='mtwise'){
$month1=strtotime($fdate);
$month2=strtotime($tdate);
$m1=date("F",$month1);
$m2=date("F",$month2);
$y1=date("Y",$month1);
$y2=date("Y",$month2);
    ?>
 <h5 style="color: blue;font-size: 15px">Rapport de ventes de <?php echo $m1."-".$y1;?> à <?php echo $m2."-".$y2;?></h5>

          </div>
          <div class="widget-content nopadding">
            <table class="table table-bordered data-table">
              <thead>
                <tr>
                  <th>N°</th>
                  <th>Mois / Année</th>
                  <th>Nom du produit</th>
                  <th>Numéro de modèle</th>
                  <th>Quantité vendue</th>
                  <th>Prix unitaire</th>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                <?php
$ret=mysqli_query($con,"select month(tblcart.CartDate) as lmonth,year(tblcart.CartDate) as lyear,tblproducts.ProductName,tblproducts.Price,tblproducts.BrandName,tblproducts.ID as pid,tblproducts.Status,tblproducts.CreationDate,tblproducts.ModelNumber,tblproducts.Stock,sum(tblcart.ProductQty) as selledqty from tblproducts left join tblcart  on tblproducts.ID=tblcart.ProductId where date(tblcart.CartDate) between '$fdate' and '$tdate' group by lmonth,lyear, tblproducts.ProductName");
$qty=$result['selledqty'];
$num=mysqli_num_rows($ret);
if($num>0){
$cnt=1;
while ($row=mysqli_fetch_array($ret)) {
$qty=$row['selledqty'];
?>
                <tr class="gradeX">
                  <td><?php echo $cnt;?></td>
                   <td><?php  echo $row['lmonth']."/".$row['lyear'];?></td>
                  <td><?php  echo $row['ProductName'];?></td>
                  <td><?php  echo $row['ModelNumber'];?></td>
                 <td><?php  echo $qty=$row['selledqty'];?></td>
                  <td><?php  echo $ppunit=$row['Price'];?></td>
                  <td><?php  echo ($total=$qty*$ppunit)?></td>
                                  
                </tr>
               <?php 
$gtotal+=$total;                
$cnt=$cnt+1;
}?>
 <tr>
<th colspan="6" style="text-align: center;color: red;font-size: 15px">Total général</th>  
<th style="text-align: center;color: red;font-size: 15px"><?php echo $gtotal;?></th>  
</tr>
</tbody></table>
<?php } } else {
$year1=strtotime($fdate);
$year2=strtotime($tdate);
$y1=date("Y",$year1);
$y2=date("Y",$year2);
 ?>
 <h5 style="color: blue;font-size: 15px">Rapport de ventes de l'année <?php echo $y1;?> à l'année <?php echo $y2;?></h5>

          </div>
          <div class="widget-content nopadding">
            <table class="table table-bordered data-table">
              <thead>
                <tr>
                  <th>N°</th>
                  <th>Année</th>
                  <th>Nom du produit</th>
                  <th>Numéro de modèle</th>
                  <th>Quantité vendue</th>
                  <th>Prix unitaire</th>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                <?php
$ret=mysqli_query($con,"select year(tblcart.CartDate) as lyear,tblproducts.ProductName,tblproducts.Price,tblproducts.BrandName,tblproducts.ID as pid,tblproducts.Status,tblproducts.CreationDate,tblproducts.ModelNumber,tblproducts.Stock,sum(tblcart.ProductQty) as selledqty from tblproducts left join tblcart  on tblproducts.ID=tblcart.ProductId where date(tblcart.CartDate) between '$fdate' and '$tdate' group by lyear, tblproducts.ProductName");
$qty=$result['selledqty'];
$num=mysqli_num_rows($ret);
if($num>0){
$cnt=1;
while ($row=mysqli_fetch_array($ret)) {
$qty=$row['selledqty'];
?>
                <tr class="gradeX">
                  <td><?php echo $cnt;?></td>
                   <td><?php  echo $row['lyear'];?></td>
                  <td><?php  echo $row['ProductName'];?></td>
                  <td><?php  echo $row['ModelNumber'];?></td>
                 <td><?php  echo $qty=$row['selledqty'];?></td>
                  <td><?php  echo $ppunit=$row['Price'];?></td>
                  <td><?php  echo ($total=$qty*$ppunit)?></td>
                                  
                </tr>
               <?php 
$gtotal+=$total;                
$cnt=$cnt+1;
}?>
 <tr>
<th colspan="6" style="text-align: center;color: red;font-size: 15px">Grand Total</th>  
<th style="text-align: center;color: red;font-size: 15px"><?php echo $gtotal;?></th>  
</tr>
</tbody></table>
<?php } }?>

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
<script src="js/jquery.dataTables.min.js"></script> 
<script src="js/matrix.js"></script> 
<script src="js/matrix.tables.js"></script>
</body>
</html>
<?php } ?>