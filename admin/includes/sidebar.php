<nav id="sidebar">
  <ul>
    <!-- Tableau de bord -->
    <li class="active">
      <a href="dashboard.php">
        <i class="icon icon-home"></i>
        <span>Tableau de bord</span>
      </a>
    </li>

    <!-- Catalogues -->
    <li class="submenu">
      <a href="#">
        <i class="icon icon-th-list"></i>
        <span>Catalogues</span>
      </a>
      <ul>
        <!-- Catégorie -->
        <li class="submenu">
          <a href="#">
            <i class="icon icon-th"></i>
            <span>Catégorie</span>
          </a>
          <ul>
            <li><a href="add-category.php">Ajouter</a></li>
            <li><a href="manage-category.php">Gérer</a></li>
          </ul>
        </li>

        <!-- Sous-catégorie -->
        <li class="submenu">
          <a href="#">
            <i class="icon icon-th-large"></i>
            <span>Sous-catégorie</span>
          </a>
          <ul>
            <li><a href="add-subcategory.php">Ajouter</a></li>
            <li><a href="manage-subcategory.php">Gérer</a></li>
          </ul>
        </li>

        <!-- Marque -->
        <li class="submenu">
          <a href="#">
            <i class="icon icon-tag"></i>
            <span>Marque</span>
          </a>
          <ul>
            <li><a href="add-brand.php">Ajouter</a></li>
            <li><a href="manage-brand.php">Gérer</a></li>
          </ul>
        </li>

        <!-- Produit -->
        <li class="submenu">
          <a href="#">
            <i class="icon icon-barcode"></i>
            <span>Produit</span>
          </a>
          <ul>
            <li><a href="add-product.php">Ajouter</a></li>
            <li><a href="manage-product.php">Gérer</a></li>
          </ul>
        </li>
      </ul>
    </li>

    <!-- Ventes -->
    <li class="submenu">
      <a href="#">
        <i class="icon-shopping-cart"></i>
        <span>Ventes</span>
      </a>
      <ul>
        <li>
          <a href="cart.php">
            Comptant
            <span class="label label-important"><?php echo htmlentities($cartcountcount);?></span>
          </a>
        </li>
        <li>
          <a href="dettecart.php">
            Terme
            <span class="label label-important"><?php echo htmlentities($cartcountcount);?></span>
          </a>
        </li>
        <li><a href="return.php">Retour</a></li>
      </ul>
    </li>

    <!-- Fournisseurs -->
    <li class="submenu">
      <a href="#">
        <i class="icon icon-truck"></i>
        <span>Fournisseurs</span>
      </a>
      <ul>
        <li><a href="supplier.php">Liste fournisseurs</a></li>
        <li><a href="arrival.php">Arrivages</a></li>
        <li><a href="supplier-payments.php">Paiements</a></li>
      </ul>
    </li>

    <!-- Clients -->
    <li class="submenu">
      <a href="#">
        <i class="icon icon-user"></i>
        <span>Clients</span>
      </a>
      <ul>
        <li><a href="client-account.php">Comptes clients</a></li>
        <li><a href="customer-details.php">Détails clients</a></li>
      </ul>
    </li>

    <!-- Transactions -->
    <li class="submenu">
      <a href="#">
        <i class="icon icon-exchange"></i>
        <span>Transactions</span>
      </a>
      <ul>
        <li><a href="transact.php">Historique</a></li>
        <li><a href="invoice-search.php">Recherche facture</a></li>
      </ul>
    </li>

    <!-- Rapports -->
    <li class="submenu">
      <a href="#">
        <i class="icon icon-file"></i>
        <span>Rapports</span>
      </a>
      <ul>
        <li><a href="stock-report.php">Stock</a></li>
        <li><a href="sales-report.php">Ventes</a></li>
        <li><a href="daily-report.php">Journalier</a></li>
      </ul>
    </li>

    <!-- Autres éléments -->
    <li><a href="inventory.php"><i class="icon icon-hdd"></i> <span>Inventaire</span></a></li>
    <li><a href="search.php"><i class="icon icon-search"></i> <span>Recherche</span></a></li>
  </ul>
</nav>