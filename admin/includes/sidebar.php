<nav id="sidebar" role="navigation">
  <ul class="menu">
    <!-- Tableau de bord -->
    <li class="menu-item active">
      <a href="dashboard.php">
        <i class="icon icon-home"></i>
        <span>Tableau de bord</span>
      </a>
    </li>

    <!-- Catalogues -->
    <li class="menu-item has-submenu">
      <a href="#" aria-haspopup="true" aria-expanded="false">
        <i class="icon icon-th-list"></i>
        <span>Catalogues</span>
      </a>
      <ul class="submenu" role="menu">
        <!-- Catégorie -->
        <li class="submenu-item has-submenu">
          <a href="#" aria-haspopup="true" aria-expanded="false">
            <i class="icon icon-th"></i>
            <span>Catégorie</span>
          </a>
          <ul class="submenu" role="menu">
            <li role="none"><a role="menuitem" href="add-category.php">Ajouter</a></li>
            <li role="none"><a role="menuitem" href="manage-category.php">Gérer</a></li>
          </ul>
        </li>

        <!-- Sous-catégorie -->
        <li class="submenu-item has-submenu">
          <a href="#" aria-haspopup="true" aria-expanded="false">
            <i class="icon icon-th-large"></i>
            <span>Sous-catégorie</span>
          </a>
          <ul class="submenu" role="menu">
            <li role="none"><a role="menuitem" href="add-subcategory.php">Ajouter</a></li>
            <li role="none"><a role="menuitem" href="manage-subcategory.php">Gérer</a></li>
          </ul>
        </li>

        <!-- Marque -->
        <li class="submenu-item has-submenu">
          <a href="#" aria-haspopup="true" aria-expanded="false">
            <i class="icon icon-tag"></i>
            <span>Marque</span>
          </a>
          <ul class="submenu" role="menu">
            <li role="none"><a role="menuitem" href="add-brand.php">Ajouter</a></li>
            <li role="none"><a role="menuitem" href="manage-brand.php">Gérer</a></li>
          </ul>
        </li>

        <!-- Produit -->
        <li class="submenu-item has-submenu">
          <a href="#" aria-haspopup="true" aria-expanded="false">
            <i class="icon icon-barcode"></i>
            <span>Produit</span>
          </a>
          <ul class="submenu" role="menu">
            <li role="none"><a role="menuitem" href="add-product.php">Ajouter</a></li>
            <li role="none"><a role="menuitem" href="manage-product.php">Gérer</a></li>
          </ul>
        </li>
      </ul>
    </li>

    <!-- Inventaire -->
    <li class="menu-item">
      <a href="inventory.php">
        <i class="icon icon-hdd"></i>
        <span>Inventaire</span>
      </a>
    </li>

    <!-- Ventes -->
    <li class="menu-item has-submenu">
      <a href="#" aria-haspopup="true" aria-expanded="false">
        <i class="icon-shopping-cart"></i>
        <span>Ventes</span>
      </a>
      <ul class="submenu" role="menu">
        <li role="none">
          <a role="menuitem" href="cart.php">
            Comptant
            <span class="label label-important"><?php echo htmlentities($cartcountcount);?></span>
          </a>
        </li>
        <li role="none">
          <a role="menuitem" href="dettecart.php">
            Terme
            <span class="label label-important"><?php echo htmlentities($cartcountcount);?></span>
          </a>
        </li>
        <li role="none"><a role="menuitem" href="return.php">Retour</a></li>
      </ul>
    </li>

    <!-- Fournisseurs -->
    <li class="menu-item has-submenu">
      <a href="#" aria-haspopup="true" aria-expanded="false">
        <i class="icon icon-truck"></i>
        <span>Fournisseurs</span>
      </a>
      <ul class="submenu" role="menu">
        <li role="none"><a role="menuitem" href="supplier.php">Liste fournisseurs</a></li>
        <li role="none"><a role="menuitem" href="arrival.php">Arrivages</a></li>
        <li role="none"><a role="menuitem" href="supplier-payments.php">Paiements</a></li>
      </ul>
    </li>

    <!-- Clients -->
    <li class="menu-item has-submenu">
      <a href="#" aria-haspopup="true" aria-expanded="false">
        <i class="icon icon-user"></i>
        <span>Clients</span>
      </a>
      <ul class="submenu" role="menu">
        <li role="none"><a role="menuitem" href="client-account.php">Comptes clients</a></li>
        <li role="none"><a role="menuitem" href="customer-details.php">Détails clients</a></li>
      </ul>
    </li>

    <!-- Transactions -->
    <li class="menu-item has-submenu">
      <a href="#" aria-haspopup="true" aria-expanded="false">
        <i class="icon icon-exchange"></i>
        <span>Transactions</span>
      </a>
      <ul class="submenu" role="menu">
        <li role="none"><a role="menuitem" href="transact.php">Historique</a></li>
        <li role="none"><a role="menuitem" href="invoice-search.php">Recherche facture</a></li>
      </ul>
    </li>

    <!-- Rapports -->
    <li class="menu-item has-submenu">
      <a href="#" aria-haspopup="true" aria-expanded="false">
        <i class="icon icon-file"></i>
        <span>Rapports</span>
      </a>
      <ul class="submenu" role="menu">
        <li role="none"><a role="menuitem" href="stock-report.php">Stock</a></li>
        <li role="none"><a role="menuitem" href="sales-report.php">Ventes</a></li>
        <li role="none"><a role="menuitem" href="daily-report.php">Journalier</a></li>
      </ul>
    </li>

    <!-- Recherche -->
    <li class="menu-item">
      <a href="search.php">
        <i class="icon icon-search"></i>
        <span>Recherche</span>
      </a>
    </li>
  </ul>
</nav>
