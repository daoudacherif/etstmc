<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Système de gestion d'inventaire</title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/bootstrap-responsive.min.css">
    <link rel="stylesheet" href="css/matrix-style.css">
    <link rel="stylesheet" href="css/matrix-media.css">
    <link rel="stylesheet" href="css/responsive-sidebar.css">
    <link href="font-awesome/css/font-awesome.css" rel="stylesheet">
    <link href='http://fonts.googleapis.com/css?family=Open+Sans:400,700,800' rel='stylesheet' type='text/css'>
    
    <!-- JavaScript Files -->
    <script src="js/jquery.min.js"></script>
    <script src="js/jquery.ui.custom.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/matrix.js"></script>
    <script src="js/responsive-sidebar.js"></script>
    
    <?php include_once('includes/responsive.php'); ?>
</head>
<body>
    <!-- Header -->
    <header id="header">
        <h1><a href="dashboard.php">Système de gestion d'inventaire</a></h1>
    </header>
    
    <!-- Sidebar -->
    <div id="sidebar">
        <ul>
            <li class="submenu">
                <a href="#"><i class="icon icon-home"></i> <span>Tableau de bord</span></a>
                <ul>
                    <li><a href="dashboard.php">Vue d'ensemble</a></li>
                    <li><a href="daily-repport.php">Rapport quotidien</a></li>
                </ul>
            </li>
            <li class="submenu">
                <a href="#"><i class="icon icon-shopping-cart"></i> <span>Ventes</span></a>
                <ul>
                    <li><a href="cart.php">Nouvelle vente</a></li>
                    <li><a href="invoice.php">Factures</a></li>
                    <li><a href="return.php">Retours</a></li>
                </ul>
            </li>
            <li class="submenu">
                <a href="#"><i class="icon icon-truck"></i> <span>Stock</span></a>
                <ul>
                    <li><a href="inventory.php">Inventaire</a></li>
                    <li><a href="arrival.php">Arrivages</a></li>
                    <li><a href="stock-report.php">Rapport de stock</a></li>
                </ul>
            </li>
            <li class="submenu">
                <a href="#"><i class="icon icon-group"></i> <span>Clients</span></a>
                <ul>
                    <li><a href="client-account.php">Comptes clients</a></li>
                    <li><a href="customer-details.php">Détails clients</a></li>
                </ul>
            </li>
            <li class="submenu">
                <a href="#"><i class="icon icon-cog"></i> <span>Paramètres</span></a>
                <ul>
                    <li><a href="profile.php">Profil</a></li>
                    <li><a href="change-password.php">Changer mot de passe</a></li>
                </ul>
            </li>
        </ul>
    </div>
    
    <!-- Content -->
    <div id="content">
        <!-- Content will be loaded here -->
    </div>
</body>
</html>