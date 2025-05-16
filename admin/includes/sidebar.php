<?php
// Récupérer le type d'utilisateur connecté
$adminid = $_SESSION['imsaid'];
$ret = mysqli_query($con, "SELECT UserName FROM tbladmin WHERE ID='$adminid'");
$row = mysqli_fetch_array($ret);
$username = $row['UserName'];

// Compteur du panier avec gestion d'erreur
try {
    // Essayez de déterminer le bon nom de colonne pour l'utilisateur dans la table tblcart
    $cartcountcount = 0; // Valeur par défaut
    
    // Tentative avec différents noms de colonnes possibles
    $possible_columns = ['AdminID', 'admin_id', 'UserID', 'user_id'];
    
    foreach($possible_columns as $column) {
        $check_column = mysqli_query($con, "SHOW COLUMNS FROM tblcart LIKE '$column'");
        if(mysqli_num_rows($check_column) > 0) {
            $ret1 = mysqli_query($con, "SELECT count(ID) as cartcount FROM tblcart WHERE $column = '$adminid'");
            $row1 = mysqli_fetch_array($ret1);
            $cartcountcount = $row1['cartcount'];
            break; // Sortir de la boucle si nous trouvons une colonne qui fonctionne
        }
    }
} catch (Exception $e) {
    // En cas d'erreur, simplement définir le compteur à 0
    $cartcountcount = 0;
}

// Déterminer la page actuelle
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Menu latéral modernisé et organisé -->
<div id="sidebar">
  <ul>
    <?php if($username != 'saler'): ?>
    <!-- MENU POUR LES UTILISATEURS NORMAUX -->
    
    <!-- Tableau de bord -->
    <li class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
      <a href="dashboard.php"><i class="icon icon-home"></i> <span>Tableau de bord</span></a>
    </li>

    <!-- Gestion des catégories -->
    <li class="submenu <?php echo in_array($current_page, ['add-category.php', 'manage-category.php']) ? 'open active' : ''; ?>">
      <a href="#"><i class="icon icon-th-list"></i> <span>Catégories</span></a>
      <ul style="<?php echo in_array($current_page, ['add-category.php', 'manage-category.php']) ? 'display:block' : ''; ?>">
        <li class="<?php echo $current_page == 'add-category.php' ? 'active' : ''; ?>"><a href="add-category.php">Ajouter une catégorie</a></li>
        <li class="<?php echo $current_page == 'manage-category.php' ? 'active' : ''; ?>"><a href="manage-category.php">Gérer les catégories</a></li>
      </ul>
    </li>

    <!-- Articles -->
    <li class="submenu <?php echo in_array($current_page, ['add-product.php', 'manage-product.php', 'inventory.php']) ? 'open active' : ''; ?>">
      <a href="#"><i class="icon icon-info-sign"></i> <span>Articles</span></a>
      <ul style="<?php echo in_array($current_page, ['add-product.php', 'manage-product.php', 'inventory.php']) ? 'display:block' : ''; ?>">
        <li class="<?php echo $current_page == 'add-product.php' ? 'active' : ''; ?>"><a href="add-product.php">Ajouter un Article</a></li>
        <li class="<?php echo $current_page == 'manage-product.php' ? 'active' : ''; ?>"><a href="manage-product.php">Gérer les Articles</a></li>
        <li class="<?php echo $current_page == 'inventory.php' ? 'active' : ''; ?>"><a href="inventory.php">Inventaire</a></li>
      </ul>
    </li>
    <?php endif; ?>

    <!-- PARTIE COMMUNE (VENTES) - visible pour tous les utilisateurs -->
    <!-- Ventes -->
    <li class="submenu <?php echo in_array($current_page, ['cart.php', 'dettecart.php', 'return.php', 'transact.php', 'facture.php', 'admin_invoices.php']) ? 'open active' : ''; ?>">
      <a href="#"><i class="icon-shopping-cart"></i> <span>Ventes</span></a>
      <ul style="<?php echo in_array($current_page, ['cart.php', 'dettecart.php', 'return.php', 'transact.php', 'facture.php', 'admin_invoices.php']) ? 'display:block' : ''; ?>">
        <li class="<?php echo $current_page == 'cart.php' ? 'active' : ''; ?>"><a href="cart.php">Comptant <span class="label label-important"><?php echo htmlentities($cartcountcount);?></span></a></li>
        <li class="<?php echo $current_page == 'dettecart.php' ? 'active' : ''; ?>"><a href="dettecart.php">Terme <span class="label label-important"><?php echo htmlentities($cartcountcount);?></span></a></li>
        <li class="<?php echo $current_page == 'return.php' ? 'active' : ''; ?>"><a href="return.php">Retour</a></li>
        <li class="<?php echo $current_page == 'transact.php' ? 'active' : ''; ?>"><a href="transact.php">Transactions</a></li>
        <li class="<?php echo $current_page == 'facture.php' ? 'active' : ''; ?>"><a href="facture.php">Factures</a></li>
        <li class="<?php echo $current_page == 'admin_invoices.php' ? 'active' : ''; ?>"><a href="admin_invoices.php">Factures par Admin</a></li>
      </ul>
    </li>

    <!-- SUITE DU MENU POUR LES UTILISATEURS NORMAUX -->
    
    <!-- Recherche -->
    <li class="submenu <?php echo in_array($current_page, ['search.php', 'invoice-search.php']) ? 'open active' : ''; ?>">
      <a href="#"><i class="icon-search"></i> <span>Recherche</span></a>
      <ul style="<?php echo in_array($current_page, ['search.php', 'invoice-search.php']) ? 'display:block' : ''; ?>">
        <li class="<?php echo $current_page == 'search.php' ? 'active' : ''; ?>"><a href="search.php">Rechercher</a></li>
        <li class="<?php echo $current_page == 'invoice-search.php' ? 'active' : ''; ?>"><a href="invoice-search.php">Factures</a></li>
      </ul>
    </li>

    <!-- Fournisseurs -->
    <li class="submenu <?php echo in_array($current_page, ['arrival.php', 'supplier.php', 'supplier-payments.php']) ? 'open active' : ''; ?>">
      <a href="#"><i class="icon-group"></i> <span>Fournisseurs</span></a>
      <ul style="<?php echo in_array($current_page, ['arrival.php', 'supplier.php', 'supplier-payments.php']) ? 'display:block' : ''; ?>">
        <li class="<?php echo $current_page == 'arrival.php' ? 'active' : ''; ?>"><a href="arrival.php">Arrivage</a></li>
        <li class="<?php echo $current_page == 'supplier.php' ? 'active' : ''; ?>"><a href="supplier.php">Liste des fournisseurs</a></li>
        <li class="<?php echo $current_page == 'supplier-payments.php' ? 'active' : ''; ?>"><a href="supplier-payments.php">Paiements</a></li>
      </ul>
    </li>

    <!-- Clients -->
    <li class="submenu <?php echo in_array($current_page, ['client-account.php', 'customer-details.php']) ? 'open active' : ''; ?>">
      <a href="#"><i class="icon-group"></i> <span>Clients</span></a>
      <ul style="<?php echo in_array($current_page, ['client-account.php', 'customer-details.php']) ? 'display:block' : ''; ?>">
        <li class="<?php echo $current_page == 'client-account.php' ? 'active' : ''; ?>"><a href="client-account.php">Compte client</a></li>
        <li class="<?php echo $current_page == 'customer-details.php' ? 'active' : ''; ?>"><a href="customer-details.php">Détails client</a></li>
      </ul>
    </li>

    <!-- Rapports -->
    <li class="submenu <?php echo in_array($current_page, ['stock-report.php', 'sales-report.php', 'daily-repport.php']) ? 'open active' : ''; ?>">
      <a href="#"><i class="icon icon-th-list"></i> <span>Rapports</span></a>
      <ul style="<?php echo in_array($current_page, ['stock-report.php', 'sales-report.php', 'daily-repport.php']) ? 'display:block' : ''; ?>">
        <li class="<?php echo $current_page == 'stock-report.php' ? 'active' : ''; ?>"><a href="stock-report.php">Stock</a></li>
        <li class="<?php echo $current_page == 'sales-report.php' ? 'active' : ''; ?>"><a href="sales-report.php">Ventes</a></li>
        <li class="<?php echo $current_page == 'daily-repport.php' ? 'active' : ''; ?>"><a href="daily-repport.php">Journalier</a></li>
      </ul>
    </li>
   
  </ul>
</div>