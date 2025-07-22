<?php
session_start();
include('includes/dbconnection.php');

// Check if user is logged in
if (!isset($_SESSION['imsaid']) || empty($_SESSION['imsaid'])) {
    header("Location: login.php");
    exit;
}

$currentAdminID = $_SESSION['imsaid'];

// Get the current admin name
$adminQuery = mysqli_query($con, "SELECT AdminName FROM tbladmin WHERE ID = '$currentAdminID'");
$adminData = mysqli_fetch_assoc($adminQuery);
$currentAdminName = $adminData['AdminName'];

// Handle proforma deletion
if (isset($_GET['delid'])) {
    $delid = intval($_GET['delid']);
    
    // Get proforma number before deletion for logging
    $proformaQuery = mysqli_query($con, "SELECT ProformaNumber FROM tblproforma WHERE ID = '$delid' AND AdminID = '$currentAdminID'");
    
    if (mysqli_num_rows($proformaQuery) > 0) {
        $proformaData = mysqli_fetch_assoc($proformaQuery);
        $proformaNumber = $proformaData['ProformaNumber'];
        
        // Start transaction
        mysqli_autocommit($con, false);
        
        try {
            // Delete related cart items
            $deleteCartQuery = "DELETE FROM tblcart WHERE BillingId = '$proformaNumber' AND IsCheckOut = 3";
            if (!mysqli_query($con, $deleteCartQuery)) {
                throw new Exception("Erreur lors de la suppression des articles: " . mysqli_error($con));
            }
            
            // Delete proforma
            $deleteProformaQuery = "DELETE FROM tblproforma WHERE ID = '$delid' AND AdminID = '$currentAdminID'";
            if (!mysqli_query($con, $deleteProformaQuery)) {
                throw new Exception("Erreur lors de la suppression de la proforma: " . mysqli_error($con));
            }
            
            // Commit transaction
            mysqli_commit($con);
            
            echo "<script>
                    alert('Proforma $proformaNumber supprimée avec succès');
                    window.location.href='manage_proforma.php';
                  </script>";
            exit;
            
        } catch (Exception $e) {
            mysqli_rollback($con);
            echo "<script>alert('Erreur: " . addslashes($e->getMessage()) . "');</script>";
        }
        
        mysqli_autocommit($con, true);
    } else {
        echo "<script>alert('Proforma introuvable'); window.location.href='manage_proforma.php';</script>";
        exit;
    }
}

// Handle status update
if (isset($_POST['updateStatus'])) {
    $proformaId = intval($_POST['proformaId']);
    $newStatus = mysqli_real_escape_string($con, $_POST['status']);
    
    $updateQuery = "UPDATE tblproforma SET Status = '$newStatus' WHERE ID = '$proformaId' AND AdminID = '$currentAdminID'";
    if (mysqli_query($con, $updateQuery)) {
        echo "<script>alert('Statut mis à jour avec succès'); window.location.href='manage_proforma.php';</script>";
        exit;
    } else {
        echo "<script>alert('Erreur lors de la mise à jour du statut');</script>";
    }
}

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Search functionality
$searchWhere = "";
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = mysqli_real_escape_string($con, $_GET['search']);
    $searchWhere = " AND (ProformaNumber LIKE '%$search%' OR CustomerName LIKE '%$search%' OR CustomerMobile LIKE '%$search%')";
}

// Status filter
$statusWhere = "";
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $status = mysqli_real_escape_string($con, $_GET['status']);
    $statusWhere = " AND Status = '$status'";
}

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM tblproforma WHERE AdminID = '$currentAdminID' $searchWhere $statusWhere";
$countResult = mysqli_query($con, $countQuery);
$totalRecords = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalRecords / $limit);

// Get proformas
$proformaQuery = "
    SELECT 
        p.*,
        (SELECT COUNT(*) FROM tblcart WHERE BillingId = p.ProformaNumber AND IsCheckOut = 3) as ItemCount
    FROM tblproforma p
    WHERE p.AdminID = '$currentAdminID' $searchWhere $statusWhere
    ORDER BY p.CreatedAt DESC
    LIMIT $limit OFFSET $offset
";

$proformaResult = mysqli_query($con, $proformaQuery);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Système de gestion des stocks | Gérer les Proformas</title>
    <?php include_once('includes/cs.php'); ?>
    <?php include_once('includes/responsive.php'); ?>
    <style>
        .status-active { color: #28a745; font-weight: bold; }
        .status-expired { color: #dc3545; font-weight: bold; }
        .status-converted { color: #6c757d; font-weight: bold; }
        .proforma-actions { white-space: nowrap; }
        .search-panel {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .stats-panel {
            background-color: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include_once('includes/header.php'); ?>
    <?php include_once('includes/sidebar.php'); ?>

    <div id="content">
        <div id="content-header">
            <div id="breadcrumb">
                <a href="dashboard.php" title="Aller à l'accueil" class="tip-bottom">
                    <i class="icon-home"></i> Accueil
                </a>
                <a href="proforma.php">Proforma</a>
                <a href="manage_proforma.php" class="current">Gérer les Proformas</a>
            </div>
            <h1>Gérer les Factures Proforma</h1>
        </div>

        <div class="container-fluid">
            <hr>
            
            <!-- Statistics Panel -->
            <div class="stats-panel">
                <div class="row-fluid">
                    <?php
                    // Get statistics
                    $statsQuery = "
                        SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN Status = 'active' THEN 1 ELSE 0 END) as active,
                            SUM(CASE WHEN Status = 'expired' THEN 1 ELSE 0 END) as expired,
                            SUM(CASE WHEN Status = 'converted' THEN 1 ELSE 0 END) as converted,
                            SUM(FinalAmount) as total_amount
                        FROM tblproforma 
                        WHERE AdminID = '$currentAdminID'
                    ";
                    $statsResult = mysqli_query($con, $statsQuery);
                    $stats = mysqli_fetch_assoc($statsResult);
                    ?>
                    <div class="span2">
                        <div class="text-center">
                            <h4><?php echo $stats['total']; ?></h4>
                            <small>Total Proformas</small>
                        </div>
                    </div>
                    <div class="span2">
                        <div class="text-center">
                            <h4 class="status-active"><?php echo $stats['active']; ?></h4>
                            <small>Actives</small>
                        </div>
                    </div>
                    <div class="span2">
                        <div class="text-center">
                            <h4 class="status-expired"><?php echo $stats['expired']; ?></h4>
                            <small>Expirées</small>
                        </div>
                    </div>
                    <div class="span2">
                        <div class="text-center">
                            <h4 class="status-converted"><?php echo $stats['converted']; ?></h4>
                            <small>Converties</small>
                        </div>
                    </div>
                    <div class="span4">
                        <div class="text-center">
                            <h4><?php echo number_format($stats['total_amount'], 2); ?> GNF</h4>
                            <small>Montant Total</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Panel -->
            <div class="search-panel">
                <form method="get" class="form-inline">
                    <label>Rechercher:</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                           placeholder="Numéro, nom client, téléphone..." class="span3" />
                    
                    <label style="margin-left: 15px;">Statut:</label>
                    <select name="status" class="span2">
                        <option value="">Tous les statuts</option>
                        <option value="active" <?php echo ($_GET['status'] ?? '') == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="expired" <?php echo ($_GET['status'] ?? '') == 'expired' ? 'selected' : ''; ?>>Expirée</option>
                        <option value="converted" <?php echo ($_GET['status'] ?? '') == 'converted' ? 'selected' : ''; ?>>Convertie</option>
                    </select>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="icon-search"></i> Rechercher
                    </button>
                    
                    <a href="manage_proforma.php" class="btn">
                        <i class="icon-refresh"></i> Réinitialiser
                    </a>
                    
                    <a href="proforma.php" class="btn btn-success" style="margin-left: 15px;">
                        <i class="icon-plus"></i> Nouvelle Proforma
                    </a>
                </form>
            </div>

            <!-- Proformas Table -->
            <div class="row-fluid">
                <div class="span12">
                    <div class="widget-box">
                        <div class="widget-title">
                            <span class="icon"><i class="icon-th"></i></span>
                            <h5>Liste des Proformas (Page <?php echo $page; ?> sur <?php echo $totalPages; ?>)</h5>
                        </div>
                        <div class="widget-content nopadding">
                            <table class="table table-bordered data-table">
                                <thead>
                                    <tr>
                                        <th>N° Proforma</th>
                                        <th>Client</th>
                                        <th>Téléphone</th>
                                        <th>Articles</th>
                                        <th>Montant</th>
                                        <th>Validité</th>
                                        <th>Statut</th>
                                        <th>Créée le</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (mysqli_num_rows($proformaResult) > 0) {
                                        while ($row = mysqli_fetch_assoc($proformaResult)) {
                                            $isExpired = strtotime($row['ValidUntil']) < time();
                                            $statusClass = 'status-' . $row['Status'];
                                            
                                            // Auto-update expired proformas
                                            if ($isExpired && $row['Status'] == 'active') {
                                                mysqli_query($con, "UPDATE tblproforma SET Status = 'expired' WHERE ID = '{$row['ID']}'");
                                                $row['Status'] = 'expired';
                                            }
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo $row['ProformaNumber']; ?></strong>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($row['CustomerName']); ?>
                                                    <?php if (!empty($row['CustomerEmail'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($row['CustomerEmail']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $row['CustomerMobile']; ?></td>
                                                <td class="text-center"><?php echo $row['ItemCount']; ?></td>
                                                <td class="text-right">
                                                    <strong><?php echo number_format($row['FinalAmount'], 2); ?> GNF</strong>
                                                </td>
                                                <td>
                                                    <?php echo date('d/m/Y', strtotime($row['ValidUntil'])); ?>
                                                    <?php if ($isExpired): ?>
                                                        <br><small class="text-danger">Expirée</small>
                                                    <?php else: ?>
                                                        <br><small class="text-success"><?php echo ceil((strtotime($row['ValidUntil']) - time()) / 86400); ?> jours</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="<?php echo $statusClass; ?>">
                                                        <?php 
                                                        switch($row['Status']) {
                                                            case 'active': echo 'Active'; break;
                                                            case 'expired': echo 'Expirée'; break;
                                                            case 'converted': echo 'Convertie'; break;
                                                        }
                                                        ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($row['CreatedAt'])); ?></td>
                                                <td class="proforma-actions">
                                                    <!-- View/Print -->
                                                    <a href="proforma_invoice.php?number=<?php echo $row['ProformaNumber']; ?>" 
                                                       class="btn btn-info btn-mini" title="Voir/Imprimer">
                                                        <i class="icon-eye-open"></i>
                                                    </a>
                                                    
                                                    <!-- Update Status -->
                                                    <div class="btn-group">
                                                        <button class="btn btn-mini dropdown-toggle" data-toggle="dropdown">
                                                            <i class="icon-edit"></i> <span class="caret"></span>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <?php if ($row['Status'] != 'active'): ?>
                                                            <li>
                                                                <a href="#" onclick="updateStatus(<?php echo $row['ID']; ?>, 'active')">
                                                                    Marquer comme Active
                                                                </a>
                                                            </li>
                                                            <?php endif; ?>
                                                            <?php if ($row['Status'] != 'expired'): ?>
                                                            <li>
                                                                <a href="#" onclick="updateStatus(<?php echo $row['ID']; ?>, 'expired')">
                                                                    Marquer comme Expirée
                                                                </a>
                                                            </li>
                                                            <?php endif; ?>
                                                            <?php if ($row['Status'] != 'converted'): ?>
                                                            <li>
                                                                <a href="#" onclick="updateStatus(<?php echo $row['ID']; ?>, 'converted')">
                                                                    Marquer comme Convertie
                                                                </a>
                                                            </li>
                                                            <?php endif; ?>
                                                        </ul>
                                                    </div>
                                                    
                                                    <!-- Delete -->
                                                    <a href="manage_proforma.php?delid=<?php echo $row['ID']; ?>" 
                                                       class="btn btn-danger btn-mini" 
                                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette proforma? Cette action supprimera aussi tous les articles associés et ne peut pas être annulée.');" 
                                                       title="Supprimer">
                                                        <i class="icon-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php
                                        }
                                    } else {
                                        ?>
                                        <tr>
                                            <td colspan="9" class="text-center">
                                                <p>Aucune proforma trouvée.</p>
                                                <a href="proforma.php" class="btn btn-primary">
                                                    <i class="icon-plus"></i> Créer votre première proforma
                                                </a>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="pagination pagination-centered">
                        <ul>
                            <?php if ($page > 1): ?>
                                <li><a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&status=<?php echo urlencode($_GET['status'] ?? ''); ?>">&laquo; Précédent</a></li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                                <li class="<?php echo $i == $page ? 'active' : ''; ?>">
                                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&status=<?php echo urlencode($_GET['status'] ?? ''); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li><a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&status=<?php echo urlencode($_GET['status'] ?? ''); ?>">Suivant &raquo;</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden form for status updates -->
    <form method="post" id="statusUpdateForm" style="display: none;">
        <input type="hidden" name="proformaId" id="statusProformaId" />
        <input type="hidden" name="status" id="statusValue" />
        <input type="hidden" name="updateStatus" value="1" />
    </form>

    <?php include_once('includes/footer.php'); ?>

    <!-- Scripts -->
    <script src="js/jquery.min.js"></script>
    <script src="js/jquery.ui.custom.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/jquery.uniform.js"></script>
    <script src="js/select2.min.js"></script>
    <script src="js/jquery.dataTables.min.js"></script>
    <script src="js/matrix.js"></script>
    <script src="js/matrix.tables.js"></script>

    <script>
        function updateStatus(proformaId, status) {
            if (confirm('Êtes-vous sûr de vouloir changer le statut de cette proforma?')) {
                document.getElementById('statusProformaId').value = proformaId;
                document.getElementById('statusValue').value = status;
                document.getElementById('statusUpdateForm').submit();
            }
        }
    </script>
</body>
</html>