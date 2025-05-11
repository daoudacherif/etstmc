<?php
// Function to check if the current user has a specific permission
function user_has_permission($permission_name) {
    // Return true for all permissions during development
    // UNCOMMENT THIS LINE FOR TESTING
    // return true;
    
    global $con;
    
    if (!isset($_SESSION['imsaid'])) return false;
    
    $admin_id = $_SESSION['imsaid'];
    
    // Check if permissions are cached in session
    if (isset($_SESSION['user_permissions']) && isset($_SESSION['user_permissions'][$permission_name])) {
        return true;
    }
    
    $query = mysqli_query($con, "
        SELECT COUNT(*) as count FROM tbladmin a
        JOIN user_roles ur ON a.ID = ur.user_id
        JOIN role_permissions rp ON ur.role_id = rp.role_id
        JOIN permissions p ON rp.permission_id = p.permission_id
        WHERE a.ID = '$admin_id' AND p.permission_name = '$permission_name'
    ");
    
    if (!$query) {
        // If query fails, log the error and return false
        error_log("Permission query failed: " . mysqli_error($con));
        return false;
    }
    
    $result = mysqli_fetch_assoc($query);
    return ($result['count'] > 0);
}
?>

<!-- Menu latéral modernisé et organisé -->
<div id="sidebar">
  <ul>
    <!-- Tableau de bord - Accessible à tous -->
    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
      <a href="dashboard.php"><i class="icon icon-home"></i> <span>Tableau de bord</span></a>
    </li>

    <!-- Gestion des catégories -->
    <?php if(user_has_permission('manage_categories')): ?>
    <li class="submenu <?php echo (strpos(basename($_SERVER['PHP_SELF']), 'category') !== false) ? 'open' : ''; ?>">
      <a href="#"><i class="icon icon-th-list"></i> <span>Catégories</span></a>
      <ul>
        <?php if(user_has_permission('add_categories')): ?>
        <li><a href="add-category.php">Ajouter une catégorie</a></li>
        <?php endif; ?>
        <li><a href="manage-category.php">Gérer les catégories</a></li>
      </ul>
    </li>
    <?php endif; ?>

    <!-- Articles -->
    <?php if(user_has_permission('manage_products')): ?>
    <li class="submenu <?php echo (strpos(basename($_SERVER['PHP_SELF']), 'product') !== false || basename($_SERVER['PHP_SELF']) == 'inventory.php') ? 'open' : ''; ?>">
      <a href="#"><i class="icon icon-info-sign"></i> <span>Articles</span></a>
      <ul>
        <?php if(user_has_permission('add_products')): ?>
        <li><a href="add-product.php">Ajouter un Article</a></li>
        <?php endif; ?>
        <li><a href="manage-product.php">Gérer les Articles</a></li>
        <li><a href="inventory.php">Inventaire</a></li>
      </ul>
    </li>
    <?php endif; ?>

    <!-- Ventes -->
    <?php if(user_has_permission('manage_sales')): ?>
    <li class="submenu <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['cart.php', 'dettecart.php', 'return.php', 'transact.php'])) ? 'open' : ''; ?>">
      <a href="#"><i class="icon-shopping-cart"></i> <span>Ventes</span></a>
      <ul>
        <li><a href="cart.php">Comptant <?php if(isset($cartcountcount)): ?><span class="label label-important"><?php echo htmlentities($cartcountcount);?></span><?php endif; ?></a></li>
        <li><a href="dettecart.php">Terme <?php if(isset($cartcountcount)): ?><span class="label label-important"><?php echo htmlentities($cartcountcount);?></span><?php endif; ?></a></li>
        <li><a href="return.php">Retour</a></li>
        <li><a href="transact.php">Transactions</a></li>
      </ul>
    </li>
    <?php endif; ?>

    <!-- Recherche -->
    <?php if(user_has_permission('use_search')): ?>
    <li class="submenu <?php echo (strpos(basename($_SERVER['PHP_SELF']), 'search') !== false) ? 'open' : ''; ?>">
      <a href="#"><i class="icon-search"></i> <span>Recherche</span></a>
      <ul>
        <li><a href="search.php">Rechercher</a></li>
        <li><a href="invoice-search.php">Factures</a></li>
      </ul>
    </li>
    <?php endif; ?>

    <!-- Fournisseurs -->
    <?php if(user_has_permission('manage_suppliers')): ?>
    <li class="submenu <?php echo (in_array(basename($_SERVER['PHP_SELF']), ['arrival.php', 'supplier.php', 'supplier-payments.php'])) ? 'open' : ''; ?>">
      <a href="#"><i class="icon-group"></i> <span>Fournisseurs</span></a>
      <ul>
        <li><a href="arrival.php">Arrivage</a></li>
        <li><a href="supplier.php">Liste des fournisseurs</a></li>
        <li><a href="supplier-payments.php">Paiements</a></li>
      </ul>
    </li>
    <?php endif; ?>

    <!-- Clients -->
    <?php if(user_has_permission('manage_clients')): ?>
    <li class="submenu <?php echo (strpos(basename($_SERVER['PHP_SELF']), 'client') !== false || basename($_SERVER['PHP_SELF']) == 'customer-details.php') ? 'open' : ''; ?>">
      <a href="#"><i class="icon-group"></i> <span>Clients</span></a>
      <ul>
        <li><a href="client-account.php">Compte client</a></li>
        <li><a href="customer-details.php">Détails client</a></li>
      </ul>
    </li>
    <?php endif; ?>

    <!-- Rapports -->
    <?php if(user_has_permission('view_reports')): ?>
    <li class="submenu <?php echo (strpos(basename($_SERVER['PHP_SELF']), 'report') !== false) ? 'open' : ''; ?>">
      <a href="#"><i class="icon icon-th-list"></i> <span>Rapports</span></a>
      <ul>
        <li><a href="stock-report.php">Stock</a></li>
        <li><a href="sales-report.php">Ventes</a></li>
        <li><a href="daily-repport.php">Journalier</a></li>
      </ul>
    </li>
    <?php endif; ?>
    
    <!-- Administration - nouvelle section pour la gestion des rôles -->
    <?php if(user_has_permission('admin_access')): ?>
    <li class="submenu <?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'open' : ''; ?>">
      <a href="#"><i class="icon icon-cog"></i> <span>Administration</span></a>
      <ul>
        <li><a href="profile.php">Profil</a></li>
        <?php if(user_has_permission('manage_roles')): ?>
        <li><a href="profile.php#role-tab">Gestion des Rôles</a></li>
        <?php endif; ?>
        <?php if(user_has_permission('manage_permissions')): ?>
        <li><a href="profile.php#permission-tab">Gestion des Permissions</a></li>
        <?php endif; ?>
        <?php if(user_has_permission('create_users')): ?>
        <li><a href="profile.php#user-creation-tab">Créer Utilisateur</a></li>
        <?php endif; ?>
        <?php if(user_has_permission('manage_users')): ?>
        <li><a href="profile.php#user-role-tab">Gérer Utilisateurs</a></li>
        <?php endif; ?>
      </ul>
    </li>
    <?php endif; ?>
  </ul>
</div>