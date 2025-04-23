<!-- Menu latéral modernisé et organisé -->
<div id="sidebar">
  <ul>
    <!-- Tableau de bord -->
    <li class="active">
      <a href="dashboard.php"><i class="icon icon-home"></i> <span>Tableau de bord</span></a>
    </li>

    <!-- Gestion des catégories -->
    <li class="submenu">
      <a href="#"><i class="icon icon-th-list"></i> <span>Catégories</span></a>
      <ul>
        <li><a href="add-category.php">Ajouter une catégorie</a></li>
        <li><a href="manage-category.php">Gérer les catégories</a></li>
      </ul>
    </li>

    <!-- Produits -->
    <li class="submenu">
      <a href="#"><i class="icon icon-info-sign"></i> <span>Produits</span></a>
      <ul>
        <li><a href="add-product.php">Ajouter un produit</a></li>
        <li><a href="manage-product.php">Gérer les produits</a></li>
        <li><a href="inventory.php">Inventaire</a></li>
      </ul>
    </li>

    <!-- Ventes -->
    <li class="submenu">
      <a href="#"><i class="icon-shopping-cart"></i> <span>Ventes</span></a>
      <ul>
        <li><a href="cart.php">Comptant <span class="label label-important"><?php echo htmlentities($cartcountcount);?></span></a></li>
        <li><a href="dettecart.php">Terme <span class="label label-important"><?php echo htmlentities($cartcountcount);?></span></a></li>
        <li><a href="return.php">Retour</a></li>
        <li><a href="transact.php">Transactions</a></li>
      </ul>
    </li>

    <!-- Recherche -->
    <li class="submenu">
      <a href="#"><i class="icon-search"></i> <span>Recherche</span></a>
      <ul>
        <li><a href="search.php">Rechercher</a></li>
        <li><a href="invoice-search.php">Factures</a></li>
      </ul>
    </li>

    <!-- Fournisseurs -->
    <li class="submenu">
      <a href="#"><i class="icon-group"></i> <span>Fournisseurs</span></a>
      <ul>
        <li><a href="arrival.php">Arrivage</a></li>
        <li><a href="supplier.php">Liste des fournisseurs</a></li>
        <li><a href="supplier-payments.php">Paiements</a></li>
      </ul>
    </li>

    <!-- Clients -->
    <li class="submenu">
      <a href="#"><i class="icon-group"></i> <span>Clients</span></a>
      <ul>
        <li><a href="client-account.php">Compte client</a></li>
        <li><a href="customer-details.php">Détails client</a></li>
      </ul>
    </li>

    <!-- Rapports -->
    <li class="submenu">
      <a href="#"><i class="icon icon-th-list"></i> <span>Rapports</span></a>
      <ul>
        <li><a href="stock-report.php">Stock</a></li>
        <li><a href="sales-report.php">Ventes</a></li>
        <li><a href="daily-repport.php">Journalier</a></li>
      </ul>
    </li>
  </ul>
</div>
