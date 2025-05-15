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
  <meta charset="UTF-8">
  <title>Système de Gestion d'Inventaire || Tableau de Bord</title>
  <!-- Viewport meta for responsive scaling -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <!-- Include external CSS (your responsive CSS should be linked here) -->
  <?php include_once('includes/cs.php'); ?>
  <?php include_once('includes/responsive.php'); ?>
</head>
<body>
  <!-- Main application container -->
  <div id="app-container">
    <?php include_once('includes/header.php'); ?>
    <?php include_once('includes/sidebar.php'); ?>
    <!--sidebar-menu-->

    <!-- main-container-part -->
    <div id="content">
      <!--breadcrumbs-->
      <div id="content-header">
        <div id="breadcrumb">
          <a href="dashboard.php" title="Aller à l'accueil" class="tip-bottom">
            <i class="icon-home"></i> Accueil
          </a>
        </div>
      </div>
      <!--End-breadcrumbs-->

      <!--Action boxes-->
      <br />
      <div class="container-fluid">
        <div class="widget-box widget-plain">
          <div class="center">
            <ul class="quick-actions">
             
              <?php 
              $query2 = mysqli_query($con,"Select * from tblcategory where Status='1'");
              $catcount = mysqli_num_rows($query2);
              ?>
              <li class="bg_ly">
                <a href="manage-category.php">
                  <i class="icon-list fa-3x"></i>
                  <span class="label label-success" style="margin-top:7%"><?php echo $catcount; ?></span> Catégories 
                </a>
              </li>
             
              <?php 
              $query4 = mysqli_query($con,"Select * from tblproducts");
              $productcount = mysqli_num_rows($query4);
              ?>
              <li class="bg_ls">
                <a href="manage-product.php">
                  <i class="icon-list-alt"></i>
                  <span class="label label-success" style="margin-top:7%"><?php echo $productcount; ?></span> Articles
                </a>
              </li>
              <?php 
              $query5 = mysqli_query($con,"Select * from tblcustomer");
              $totuser = mysqli_num_rows($query5);
              ?>
              <li class="bg_lo span3">
                <a href="profile.php">
                  <i class="icon-user"></i>
                  <span class="label label--success" style="margin-top:5%"><?php echo $totuser; ?></span> Utilisateurs
                </a>
              </li>
            </ul>
          </div>
        </div>
        
        <!-- SECTION VENTES -->
        <div class="widget-box widget-plain" style="margin-top:12%">
          <div class="center">
            <h3 style="color:blue">Ventes</h3>
            <hr />
            <ul class="site-stats">
              <?php
              // Initialisation des variables
              $todysale = 0;
              $yesterdaysale = 0;
              $tseven = 0;
              $totalsale = 0;
              
              // Vente d'aujourd'hui - Ventes standard
              $query6 = mysqli_query($con,"select tblcart.ProductQty as ProductQty,tblproducts.Price
                from tblcart join tblproducts on tblproducts.ID=tblcart.ProductId 
                where date(CartDate)=CURDATE() and IsCheckOut='1'");
              while($row = mysqli_fetch_array($query6))
              {
                $todays_sale = $row['ProductQty'] * $row['Price'];
                $todysale += $todays_sale;
              }
              
              // Vente d'aujourd'hui - Ventes à crédit
              $query6credit = mysqli_query($con,"select tblcreditcart.ProductQty as ProductQty,tblproducts.Price
                from tblcreditcart join tblproducts on tblproducts.ID=tblcreditcart.ProductId 
                where date(CartDate)=CURDATE() and IsCheckOut='1'");
              while($row = mysqli_fetch_array($query6credit))
              {
                $todays_sale_credit = $row['ProductQty'] * $row['Price'];
                $todysale += $todays_sale_credit;
              }
              ?>
              <li class="bg_lh">
                <font style="font-size:22px; font-weight:bold">$</font><strong><?php echo number_format($todysale,2); ?></strong>
                <small>Ventes d'aujourd'hui</small>
              </li>
              
              <?php
              // Vente d'hier - Ventes standard
              $query7 = mysqli_query($con,"select tblcart.ProductQty as ProductQty,tblproducts.Price
                from tblcart join tblproducts on tblproducts.ID=tblcart.ProductId 
                where date(CartDate)=CURDATE()-1 and IsCheckOut='1'");
              while($row = mysqli_fetch_array($query7))
              {
                $yesterdays_sale = $row['ProductQty'] * $row['Price'];
                $yesterdaysale += $yesterdays_sale;
              }
              
              // Vente d'hier - Ventes à crédit
              $query7credit = mysqli_query($con,"select tblcreditcart.ProductQty as ProductQty,tblproducts.Price
                from tblcreditcart join tblproducts on tblproducts.ID=tblcreditcart.ProductId 
                where date(CartDate)=CURDATE()-1 and IsCheckOut='1'");
              while($row = mysqli_fetch_array($query7credit))
              {
                $yesterdays_sale_credit = $row['ProductQty'] * $row['Price'];
                $yesterdaysale += $yesterdays_sale_credit;
              }
              ?>
              <li class="bg_lh">
                <font style="font-size:22px; font-weight:bold">$</font><strong><?php echo number_format($yesterdaysale,2); ?></strong>
                <small>Ventes d'hier</small>
              </li>
              
              <?php
              // Vente des sept derniers jours - Ventes standard
              $query8 = mysqli_query($con,"select tblcart.ProductQty as ProductQty,tblproducts.Price
                from tblcart join tblproducts on tblproducts.ID=tblcart.ProductId 
                where date(tblcart.CartDate)>=(DATE(NOW()) - INTERVAL 7 DAY) and tblcart.IsCheckOut='1'");
              while($row = mysqli_fetch_array($query8))
              {
                $sevendays_sale = $row['ProductQty'] * $row['Price'];
                $tseven += $sevendays_sale;
              }
              
              // Vente des sept derniers jours - Ventes à crédit
              $query8credit = mysqli_query($con,"select tblcreditcart.ProductQty as ProductQty,tblproducts.Price
                from tblcreditcart join tblproducts on tblproducts.ID=tblcreditcart.ProductId 
                where date(tblcreditcart.CartDate)>=(DATE(NOW()) - INTERVAL 7 DAY) and tblcreditcart.IsCheckOut='1'");
              while($row = mysqli_fetch_array($query8credit))
              {
                $sevendays_sale_credit = $row['ProductQty'] * $row['Price'];
                $tseven += $sevendays_sale_credit;
              }
              ?>
              <li class="bg_lh">
                <font style="font-size:22px; font-weight:bold">$</font><strong><?php echo number_format($tseven,2); ?></strong>
                <small>Ventes des sept derniers jours</small>
              </li>
              
              <?php
              // Vente totale - Ventes standard
              $query9 = mysqli_query($con,"select tblcart.ProductQty as ProductQty,tblproducts.Price
                from tblcart join tblproducts on tblproducts.ID=tblcart.ProductId where IsCheckOut='1'");
              while($row = mysqli_fetch_array($query9))
              {
                $total_sale = $row['ProductQty'] * $row['Price'];
                $totalsale += $total_sale;
              }
              
              // Vente totale - Ventes à crédit
              $query9credit = mysqli_query($con,"select tblcreditcart.ProductQty as ProductQty,tblproducts.Price
                from tblcreditcart join tblproducts on tblproducts.ID=tblcreditcart.ProductId where IsCheckOut='1'");
              while($row = mysqli_fetch_array($query9credit))
              {
                $total_sale_credit = $row['ProductQty'] * $row['Price'];
                $totalsale += $total_sale_credit;
              }
              ?>
              <li class="bg_lh">
                <font style="font-size:22px; font-weight:bold">$</font><strong><?php echo number_format($totalsale,2); ?></strong>
                <small>Ventes totales</small>
              </li>
            </ul>
          </div>
        </div>
        
        <!-- NOUVELLES STATISTIQUES FINANCIÈRES -->
        <div class="widget-box widget-plain" style="margin-top:12%">
          <div class="center">
            <h3 style="color:blue">Finances et Créances</h3>
            <hr />
            <ul class="site-stats">
              <?php
              // Initialisation des variables
              $caisse_jour = 0;
              $total_creances = 0;
              $paiements_jour = 0;
              
              // MONTANT TOTAL DES CRÉANCES (DUES)
              $query_creances = mysqli_query($con, "SELECT SUM(Dues) as total_creances FROM tblcustomer");
              if($row = mysqli_fetch_array($query_creances)) {
                $total_creances = $row['total_creances'];
              }
              ?>
              <li class="bg_lr">
                <font style="font-size:22px; font-weight:bold">$</font><strong><?php echo number_format($total_creances,2); ?></strong>
                <small>Créances Totales</small>
              </li>
              
              <?php
              // CAISSE DU JOUR (Ventes du jour + Paiements du jour)
              // 1. Ventes standard du jour
              $query_caisse1 = mysqli_query($con, "SELECT SUM(tblcart.ProductQty * tblproducts.Price) as ventes_jour
                FROM tblcart 
                JOIN tblproducts ON tblproducts.ID = tblcart.ProductId
                WHERE date(CartDate) = CURDATE() AND IsCheckOut = '1'");
              
              if($row = mysqli_fetch_array($query_caisse1)) {
                $caisse_jour += $row['ventes_jour'];
              }
              
              // 2. Paiements reçus aujourd'hui
              $query_caisse2 = mysqli_query($con, "SELECT SUM(Paid) as paiements_jour
                FROM tblcustomer
                WHERE date(BillingDate) = CURDATE()");
              
              if($row = mysqli_fetch_array($query_caisse2)) {
                $paiements_jour = $row['paiements_jour'];
                $caisse_jour += $paiements_jour;
              }
              ?>
              <li class="bg_lg">
                <font style="font-size:22px; font-weight:bold">$</font><strong><?php echo number_format($caisse_jour,2); ?></strong>
                <small>Caisse du Jour</small>
              </li>
              
              <li class="bg_ly">
                <font style="font-size:22px; font-weight:bold">$</font><strong><?php echo number_format($paiements_jour,2); ?></strong>
                <small>Paiements Reçus Aujourd'hui</small>
              </li>
              
              <?php
              // Taux de recouvrement (pourcentage des factures payées)
              $query_taux = mysqli_query($con, "SELECT 
                  SUM(FinalAmount) as montant_total,
                  SUM(Paid) as montant_paye
                FROM tblcustomer");
              
              $taux_txt = "N/A";
              if($row = mysqli_fetch_array($query_taux)) {
                $montant_total = $row['montant_total'];
                $montant_paye = $row['montant_paye'];
                
                if($montant_total > 0) {
                  $taux = ($montant_paye / $montant_total) * 100;
                  $taux_txt = number_format($taux, 1) . "%";
                }
              }
              ?>
              <li class="bg_lb">
                <strong><?php echo $taux_txt; ?></strong>
                <small>Taux de Recouvrement</small>
              </li>
              
              <?php
              // Montant moyen des factures
              $query_avg = mysqli_query($con, "SELECT AVG(FinalAmount) as montant_moyen FROM tblcustomer");
              $montant_moyen = 0;
              if($row = mysqli_fetch_array($query_avg)) {
                $montant_moyen = $row['montant_moyen'];
              }
              ?>
              <li class="bg_lo">
                <font style="font-size:22px; font-weight:bold">$</font><strong><?php echo number_format($montant_moyen,2); ?></strong>
                <small>Montant Moyen des Factures</small>
              </li>
            </ul>
          </div>
        </div>

        <!-- TOP CLIENTS DÉBITEURS -->
        <div class="widget-box widget-plain" style="margin-top:12%">
          <div class="center">
            <h3 style="color:orange">Top Clients Débiteurs</h3>
            <hr />
            <?php
            // CLIENTS AVEC LES PLUS GROSSES CRÉANCES
            $query_debiteurs = mysqli_query($con, "SELECT 
                ID, CustomerName, MobileNumber, FinalAmount, Paid, Dues
              FROM tblcustomer
              WHERE Dues > 0
              ORDER BY Dues DESC
              LIMIT 5");
            
            $count_debiteurs = mysqli_num_rows($query_debiteurs);
            ?>
            
            <table class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>Client</th>
                  <th>Contact</th>
                  <th>Montant Total</th>
                  <th>Payé</th>
                  <th>Reste à Payer</th>
                </tr>
              </thead>
              <tbody>
                <?php 
                if($count_debiteurs == 0) {
                  echo "<tr><td colspan='5' class='text-center'>Aucun client débiteur</td></tr>";
                } else {
                  while($row = mysqli_fetch_array($query_debiteurs)) {
                    ?>
                    <tr>
                      <td><?php echo $row['CustomerName']; ?></td>
                      <td><?php echo $row['MobileNumber']; ?></td>
                      <td>$<?php echo number_format($row['FinalAmount'], 2); ?></td>
                      <td>$<?php echo number_format($row['Paid'], 2); ?></td>
                      <td><strong>$<?php echo number_format($row['Dues'], 2); ?></strong></td>
                    </tr>
                    <?php
                  }
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- FACTURES RÉCENTES -->
        <div class="widget-box widget-plain" style="margin-top:12%">
          <div class="center">
            <h3 style="color:purple">Factures Récentes</h3>
            <hr />
            <?php
            // DERNIÈRES FACTURES
            $query_factures = mysqli_query($con, "SELECT 
                ID, BillingNumber, CustomerName, BillingDate, ModeofPayment, FinalAmount, Paid, Dues
              FROM tblcustomer
              ORDER BY BillingDate DESC
              LIMIT 5");
            ?>
            
            <table class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>N° Facture</th>
                  <th>Client</th>
                  <th>Date</th>
                  <th>Mode</th>
                  <th>Montant</th>
                  <th>Statut</th>
                </tr>
              </thead>
              <tbody>
                <?php 
                while($row = mysqli_fetch_array($query_factures)) {
                  ?>
                  <tr>
                    <td><?php echo $row['BillingNumber']; ?></td>
                    <td><?php echo $row['CustomerName']; ?></td>
                    <td><?php echo date('d/m/Y', strtotime($row['BillingDate'])); ?></td>
                    <td><?php echo $row['ModeofPayment']; ?></td>
                    <td>$<?php echo number_format($row['FinalAmount'], 2); ?></td>
                    <td>
                      <?php if($row['Dues'] <= 0) { ?>
                        <span class="label label-success">PAYÉ</span>
                      <?php } else { ?>
                        <span class="label label-warning">PARTIEL</span>
                      <?php } ?>
                    </td>
                  </tr>
                  <?php
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- STATISTIQUES DES MODES DE PAIEMENT -->
        <div class="widget-box widget-plain" style="margin-top:12%">
          <div class="center">
            <h3 style="color:teal">Modes de Paiement</h3>
            <hr />
            <?php
            // STATISTIQUES PAR MODE DE PAIEMENT
            $query_modes = mysqli_query($con, "SELECT 
                ModeofPayment,
                COUNT(*) as nombre_transactions,
                SUM(FinalAmount) as montant_total,
                SUM(Paid) as montant_paye,
                SUM(Dues) as montant_du
              FROM tblcustomer
              GROUP BY ModeofPayment
              ORDER BY montant_total DESC");
            ?>
            
            <table class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>Mode de Paiement</th>
                  <th>Transactions</th>
                  <th>Montant Total</th>
                  <th>Montant Payé</th>
                  <th>Montant Dû</th>
                </tr>
              </thead>
              <tbody>
                <?php 
                while($row = mysqli_fetch_array($query_modes)) {
                  ?>
                  <tr>
                    <td><?php echo $row['ModeofPayment']; ?></td>
                    <td><?php echo $row['nombre_transactions']; ?></td>
                    <td>$<?php echo number_format($row['montant_total'], 2); ?></td>
                    <td>$<?php echo number_format($row['montant_paye'], 2); ?></td>
                    <td>$<?php echo number_format($row['montant_du'], 2); ?></td>
                  </tr>
                  <?php
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ALERTES ET STOCKS -->
        <div class="widget-box widget-plain" style="margin-top:12%">
          <div class="center">
            <h3 style="color:red">Alertes Stock</h3>
            <hr />
            <?php
            // PRODUITS EN RUPTURE OU À RÉAPPROVISIONNER
            $query_stock = mysqli_query($con, "SELECT ID, ProductName, Stock 
              FROM tblproducts 
              WHERE Stock <= ReorderLevel
              ORDER BY Stock ASC
              LIMIT 5");
            
            $count_stock_alerts = mysqli_num_rows($query_stock);
            ?>
            
            <table class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>Produit</th>
                  <th>Stock Actuel</th>
                  <th>Statut</th>
                </tr>
              </thead>
              <tbody>
                <?php 
                if($count_stock_alerts == 0) {
                  echo "<tr><td colspan='3' class='text-center'>Aucun produit en alerte stock</td></tr>";
                } else {
                  while($row = mysqli_fetch_array($query_stock)) {
                    ?>
                    <tr>
                      <td><?php echo $row['ProductName']; ?></td>
                      <td><?php echo $row['Stock']; ?></td>
                      <td>
                        <?php if($row['Stock'] <= 0) { ?>
                          <span class="label label-important">RUPTURE</span>
                        <?php } else { ?>
                          <span class="label label-warning">RÉAPPROVISIONNER</span>
                        <?php } ?>
                      </td>
                    </tr>
                    <?php
                  }
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- TOP PRODUITS VENDUS -->
        <div class="widget-box widget-plain" style="margin-top:12%">
          <div class="center">
            <h3 style="color:green">Top Produits Vendus</h3>
            <hr />
            <?php
            // TOP 5 DES PRODUITS LES PLUS VENDUS
            $query_top = mysqli_query($con, "SELECT 
                p.ProductName,
                SUM(CASE WHEN cart.ID IS NOT NULL THEN cart.ProductQty ELSE 0 END) + 
                SUM(CASE WHEN ccart.ID IS NOT NULL THEN ccart.ProductQty ELSE 0 END) as quantite_totale,
                SUM(CASE WHEN cart.ID IS NOT NULL THEN cart.ProductQty * p.Price ELSE 0 END) + 
                SUM(CASE WHEN ccart.ID IS NOT NULL THEN ccart.ProductQty * p.Price ELSE 0 END) as montant_total
              FROM tblproducts p
              LEFT JOIN tblcart cart ON cart.ProductId = p.ID AND cart.IsCheckOut = '1'
              LEFT JOIN tblcreditcart ccart ON ccart.ProductId = p.ID AND ccart.IsCheckOut = '1'
              GROUP BY p.ID
              ORDER BY quantite_totale DESC
              LIMIT 5");
            ?>
            
            <table class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>Produit</th>
                  <th>Quantité Vendue</th>
                  <th>Montant Total</th>
                </tr>
              </thead>
              <tbody>
                <?php 
                while($row = mysqli_fetch_array($query_top)) {
                  ?>
                  <tr>
                    <td><?php echo $row['ProductName']; ?></td>
                    <td><?php echo $row['quantite_totale']; ?></td>
                    <td>$<?php echo number_format($row['montant_total'], 2); ?></td>
                  </tr>
                  <?php
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <?php include_once('includes/footer.php'); ?>
  </div><!-- End of #app-container -->

  <!-- Include external JS -->
  <?php include_once('includes/js.php'); ?>
  
  <!-- Optionally, inline JavaScript for additional functionality -->
  <script>
    // Example: Toggle hamburger menu if you want to show/hide the sidebar in mobile view
    document.getElementById('my_menu_input') && document.getElementById('my_menu_input').addEventListener('click', function(){
      var sidebar = document.getElementById('sidebar');
      if(sidebar.style.display === "block") {
        sidebar.style.display = "none";
      } else {
        sidebar.style.display = "block";
      }
    });
  </script>
  
</body>
</html>
<?php } ?>