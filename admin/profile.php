<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');
if (strlen($_SESSION['imsaid']==0)) {
  header('location:logout.php');
} else {
  // Profile Update
  if(isset($_POST['submit'])) {
    $adminid=$_SESSION['imsaid'];
    $aname=$_POST['adminname'];
    $mobno=$_POST['contactnumber'];
    
    $query=mysqli_query($con, "update tbladmin set AdminName ='$aname', MobileNumber='$mobno' where ID='$adminid'");
    if ($query) {
      echo '<script>alert("Le profil de l\'administrateur a été mis à jour.")</script>';
    } else {
      echo '<script>alert("Quelque chose a mal tourné. Veuillez réessayer.")</script>';
    }
  }
  
  // Create Role
  if(isset($_POST['create_role'])) {
    $role_name = $_POST['role_name'];
    $role_description = $_POST['role_description'];
    $query = mysqli_query($con, "INSERT INTO roles (role_name, role_description) VALUES ('$role_name', '$role_description')");
    if ($query) {
      echo '<script>alert("Rôle créé avec succès.")</script>';
    } else {
      echo '<script>alert("Erreur lors de la création du rôle.")</script>';
    }
  }
  
  // Delete Role
  if(isset($_GET['delete_role'])) {
    $role_id = $_GET['delete_role'];
    // Check if role is assigned to any users first
    $check_query = mysqli_query($con, "SELECT * FROM user_roles WHERE role_id = '$role_id'");
    if(mysqli_num_rows($check_query) > 0) {
      echo '<script>alert("Ce rôle est attribué à des utilisateurs. Supprimez d\'abord ces attributions.")</script>';
    } else {
      // Delete role-permission relationships
      mysqli_query($con, "DELETE FROM role_permissions WHERE role_id = '$role_id'");
      // Delete role
      $query = mysqli_query($con, "DELETE FROM roles WHERE role_id = '$role_id'");
      if ($query) {
        echo '<script>alert("Rôle supprimé avec succès.")</script>';
      } else {
        echo '<script>alert("Erreur lors de la suppression du rôle.")</script>';
      }
    }
  }
  
  // Create Permission
  if(isset($_POST['create_permission'])) {
    $permission_name = $_POST['permission_name'];
    $permission_category = $_POST['permission_category'];
    $query = mysqli_query($con, "INSERT INTO permissions (permission_name, category) VALUES ('$permission_name', '$permission_category')");
    if ($query) {
      echo '<script>alert("Permission créée avec succès.")</script>';
    } else {
      echo '<script>alert("Erreur lors de la création de la permission.")</script>';
    }
  }
  
  // Delete Permission
  if(isset($_GET['delete_permission'])) {
    $permission_id = $_GET['delete_permission'];
    // Check if permission is assigned to any roles first
    $check_query = mysqli_query($con, "SELECT * FROM role_permissions WHERE permission_id = '$permission_id'");
    if(mysqli_num_rows($check_query) > 0) {
      echo '<script>alert("Cette permission est attribuée à des rôles. Supprimez d\'abord ces attributions.")</script>';
    } else {
      $query = mysqli_query($con, "DELETE FROM permissions WHERE permission_id = '$permission_id'");
      if ($query) {
        echo '<script>alert("Permission supprimée avec succès.")</script>';
      } else {
        echo '<script>alert("Erreur lors de la suppression de la permission.")</script>';
      }
    }
  }
  
  // Add Role Permission
  if(isset($_POST['add_permission'])) {
    $role_id = $_POST['role_id'];
    $permission_id = $_POST['permission_id'];
    
    // Check if permission already exists
    $check = mysqli_query($con, "SELECT * FROM role_permissions WHERE role_id = '$role_id' AND permission_id = '$permission_id'");
    if(mysqli_num_rows($check) > 0) {
      echo '<script>alert("Cette permission est déjà attribuée à ce rôle.")</script>';
    } else {
      $query = mysqli_query($con, "INSERT INTO role_permissions (role_id, permission_id) VALUES ('$role_id', '$permission_id')");
      if ($query) {
        echo '<script>alert("Permission ajoutée avec succès.")</script>';
      } else {
        echo '<script>alert("Erreur lors de l\'ajout de la permission.")</script>';
      }
    }
  }
  
  // Bulk Add Permissions
  if(isset($_POST['bulk_add_permissions'])) {
    $role_id = $_POST['bulk_role_id'];
    $permission_ids = isset($_POST['bulk_permissions']) ? $_POST['bulk_permissions'] : array();
    
    if(empty($permission_ids)) {
      echo '<script>alert("Aucune permission sélectionnée.")</script>';
    } else {
      $success_count = 0;
      foreach($permission_ids as $permission_id) {
        // Check if permission already exists
        $check = mysqli_query($con, "SELECT * FROM role_permissions WHERE role_id = '$role_id' AND permission_id = '$permission_id'");
        if(mysqli_num_rows($check) == 0) {
          $query = mysqli_query($con, "INSERT INTO role_permissions (role_id, permission_id) VALUES ('$role_id', '$permission_id')");
          if($query) {
            $success_count++;
          }
        }
      }
      
      if($success_count > 0) {
        echo '<script>alert("' . $success_count . ' permission(s) ajoutée(s) avec succès.")</script>';
      } else {
        echo '<script>alert("Aucune nouvelle permission ajoutée.")</script>';
      }
    }
  }
  
  // Remove Role Permission
  if(isset($_GET['remove_permission'])) {
    $ids = explode('_', $_GET['remove_permission']);
    $role_id = $ids[0];
    $permission_id = $ids[1];
    
    $query = mysqli_query($con, "DELETE FROM role_permissions WHERE role_id = '$role_id' AND permission_id = '$permission_id'");
    if ($query) {
      echo '<script>alert("Permission supprimée avec succès.")</script>';
    } else {
      echo '<script>alert("Erreur lors de la suppression de la permission.")</script>';
    }
  }
  
  // Assign Role to User
  if(isset($_POST['assign_role'])) {
    $user_id = $_POST['user_id'];
    $role_id = $_POST['role_id'];
    
    // Check if role is already assigned
    $check = mysqli_query($con, "SELECT * FROM user_roles WHERE user_id = '$user_id' AND role_id = '$role_id'");
    if(mysqli_num_rows($check) > 0) {
      echo '<script>alert("Ce rôle est déjà attribué à cet utilisateur.")</script>';
    } else {
      $query = mysqli_query($con, "INSERT INTO user_roles (user_id, role_id) VALUES ('$user_id', '$role_id')");
      if ($query) {
        echo '<script>alert("Rôle attribué avec succès.")</script>';
      } else {
        echo '<script>alert("Erreur lors de l\'attribution du rôle.")</script>';
      }
    }
  }
  
  // Remove User Role
  if(isset($_GET['remove_user_role'])) {
    $ids = explode('_', $_GET['remove_user_role']);
    $user_id = $ids[0];
    $role_id = $ids[1];
    
    $query = mysqli_query($con, "DELETE FROM user_roles WHERE user_id = '$user_id' AND role_id = '$role_id'");
    if ($query) {
      echo '<script>alert("Rôle retiré avec succès.")</script>';
    } else {
      echo '<script>alert("Erreur lors du retrait du rôle.")</script>';
    }
  }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<title>Système de gestion des stocks || Profil</title>
<?php include_once('includes/cs.php');?>
<?php include_once('includes/responsive.php'); ?>
<style>
  .nav-tabs { margin-bottom: 20px; }
  .permission-list { max-height: 300px; overflow-y: auto; }
  .role-table { width: 100%; }
  .role-table th, .role-table td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
  .role-table tr:hover { background-color: #f5f5f5; }
  .btn-sm { padding: 2px 5px; font-size: 12px; }
  .permission-badge { display: inline-block; background: #eee; padding: 2px 5px; margin: 2px; border-radius: 3px; }
  .permission-badge a { color: red; margin-left: 5px; }
  .search-box { padding: 8px; margin-bottom: 15px; width: 100%; border: 1px solid #ddd; border-radius: 4px; }
  .category-pill { display: inline-block; background: #007bff; color: white; padding: 1px 6px; border-radius: 10px; font-size: 11px; margin-left: 5px; }
  .permission-group { margin-bottom: 15px; border: 1px solid #eee; border-radius: 5px; padding: 10px; }
  .permission-group h4 { margin-top: 0; padding-bottom: 5px; border-bottom: 1px solid #eee; }
  .checkbox-container { display: flex; flex-wrap: wrap; }
  .checkbox-item { width: 25%; padding: 5px; }
  @media (max-width: 767px) {
    .checkbox-item { width: 50%; }
  }
  @media (max-width: 480px) {
    .checkbox-item { width: 100%; }
  }
</style>

<!--Header-part-->
<?php include_once('includes/header.php');?>
<?php include_once('includes/sidebar.php');?>


<div id="content">
<div id="content-header">
  <div id="breadcrumb"> <a href="dashboard.php" title="Aller à l'accueil" class="tip-bottom"><i class="icon-home"></i> Accueil</a> <a href="profile.php" class="tip-bottom">Profil</a></div>
  <h1>Administration</h1>
</div>
<div class="container-fluid">
  <hr>
  <div class="row-fluid">
    <div class="span12">
      <!-- Tabs -->
      <ul class="nav nav-tabs">
        <li class="active"><a href="#profile-tab" data-toggle="tab">Profil</a></li>
        <li><a href="#role-tab" data-toggle="tab">Gestion des Rôles</a></li>
        <li><a href="#permission-tab" data-toggle="tab">Gestion des Permissions</a></li>
        <li><a href="#user-role-tab" data-toggle="tab">Attribution des Rôles</a></li>
      </ul>
      
      <div class="tab-content">
        <!-- Profile Tab -->
        <div class="tab-pane active" id="profile-tab">
          <div class="widget-box">
            <div class="widget-title"> <span class="icon"> <i class="icon-user"></i> </span>
              <h5>Profil Administrateur</h5>
            </div>
            <div class="widget-content nopadding">
              <form method="post" class="form-horizontal">
                <?php
                $adminid=$_SESSION['imsaid'];
                $ret=mysqli_query($con,"select * from tbladmin where ID='$adminid'");
                while ($row=mysqli_fetch_array($ret)) {
                ?>
                <div class="control-group">
                  <label class="control-label">Nom de l'administrateur :</label>
                  <div class="controls">
                    <input type="text" class="span11" name="adminname" id="adminname" value="<?php echo $row['AdminName'];?>" required='true' />
                  </div>
                </div>
                <div class="control-group">
                  <label class="control-label">Nom d'utilisateur :</label>
                  <div class="controls">
                    <input type="text" class="span11" name="username" id="username" value="<?php echo $row['UserName'];?>" readonly="true" />
                  </div>
                </div>
                <div class="control-group">
                  <label class="control-label">Numéro de contact :</label>
                  <div class="controls">
                    <input type="text" class="span11" id="contactnumber" name="contactnumber" value="<?php echo $row['MobileNumber'];?>" required='true' maxlength='10' pattern='[0-9]+' />
                  </div>
                </div>
                <div class="control-group">
                  <label class="control-label">Adresse e-mail :</label>
                  <div class="controls">
                    <input type="email" class="span11" id="email" name="email" value="<?php echo $row['Email'];?>" readonly='true' />
                  </div>
                </div>
                <div class="control-group">
                  <label class="control-label">Date d'inscription :</label>
                  <div class="controls">
                    <input type="text" class="span11" value="<?php echo $row['AdminRegdate'];?>" readonly="true" />
                  </div>
                </div>
                <?php } ?>
                <div class="form-actions">
                  <button type="submit" class="btn btn-success" name="submit">Mettre à jour</button>
                </div>
              </form>
            </div>
          </div>
        </div>
        
        <!-- Role Management Tab -->
        <div class="tab-pane" id="role-tab">
          <div class="widget-box">
            <div class="widget-title"> <span class="icon"> <i class="icon-th"></i> </span>
              <h5>Gestion des Rôles</h5>
            </div>
            <div class="widget-content">
              <!-- Create Role Form -->
              <form method="post" class="form-horizontal" style="margin-bottom: 20px;">
                <div class="control-group">
                  <label class="control-label">Nouveau Rôle:</label>
                  <div class="controls">
                    <input type="text" name="role_name" required placeholder="Nom du rôle" class="span6">
                  </div>
                </div>
                <div class="control-group">
                  <label class="control-label">Description:</label>
                  <div class="controls">
                    <textarea name="role_description" placeholder="Description du rôle" class="span6" rows="2"></textarea>
                  </div>
                </div>
                <div class="form-actions">
                  <button type="submit" name="create_role" class="btn btn-primary">Créer Rôle</button>
                </div>
              </form>
              
              <!-- Search roles -->
              <input type="text" id="role-search" class="search-box" placeholder="Rechercher un rôle...">
              
              <!-- Role Table -->
              <table class="role-table" id="role-table">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Nom du Rôle</th>
                    <th>Description</th>
                    <th>Permissions</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $roles_query = mysqli_query($con, "SELECT * FROM roles ORDER BY role_id");
                  while($role = mysqli_fetch_array($roles_query)) {
                  ?>
                  <tr>
                    <td><?php echo $role['role_id']; ?></td>
                    <td><?php echo $role['role_name']; ?></td>
                    <td><?php echo $role['role_description']; ?></td>
                    <td>
                      <?php
                      // Get permissions for this role
                      $permissions_query = mysqli_query($con, 
                        "SELECT p.permission_name, p.permission_id, p.category 
                         FROM permissions p 
                         JOIN role_permissions rp ON p.permission_id = rp.permission_id 
                         WHERE rp.role_id = '".$role['role_id']."'
                         ORDER BY p.category, p.permission_name");
                      
                      while($perm = mysqli_fetch_array($permissions_query)) {
                        echo '<span class="permission-badge">' . $perm['permission_name'];
                        if(!empty($perm['category'])) {
                          echo '<span class="category-pill">' . $perm['category'] . '</span>';
                        }
                        echo '<a href="profile.php?remove_permission='.$role['role_id'].'_'.$perm['permission_id'].'" 
                               onclick="return confirm(\'Êtes-vous sûr de vouloir supprimer cette permission?\');">×</a></span> ';
                      }
                      ?>
                    </td>
                    <td>
                      <!-- Actions -->
                      <a href="#" data-toggle="modal" data-target="#addPermissionModal<?php echo $role['role_id']; ?>" class="btn btn-info btn-sm">
                        <i class="icon-plus"></i> Ajouter
                      </a>
                      <a href="#" data-toggle="modal" data-target="#bulkAddPermissionModal<?php echo $role['role_id']; ?>" class="btn btn-primary btn-sm">
                        <i class="icon-list"></i> Multiple
                      </a>
                      <a href="profile.php?delete_role=<?php echo $role['role_id']; ?>" 
                         onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce rôle?');" 
                         class="btn btn-danger btn-sm">
                        <i class="icon-trash"></i> Supprimer
                      </a>
                    </td>
                  </tr>
                  
                  <!-- Add Permission Modal for each role -->
                  <div id="addPermissionModal<?php echo $role['role_id']; ?>" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
                    <div class="modal-header">
                      <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                      <h3 id="myModalLabel">Ajouter Permissions: <?php echo $role['role_name']; ?></h3>
                    </div>
                    <div class="modal-body">
                      <form method="post">
                        <input type="hidden" name="role_id" value="<?php echo $role['role_id']; ?>">
                        <div class="control-group">
                          <label class="control-label">Sélectionner Permission:</label>
                          <div class="controls">
                            <select name="permission_id" class="span12">
                              <?php
                              // Get permissions not already assigned to this role
                              $available_perms = mysqli_query($con, 
                                "SELECT * FROM permissions 
                                 WHERE permission_id NOT IN 
                                 (SELECT permission_id FROM role_permissions WHERE role_id = '".$role['role_id']."')
                                 ORDER BY category, permission_name");
                              
                              while($perm = mysqli_fetch_array($available_perms)) {
                                echo '<option value="'.$perm['permission_id'].'">';
                                echo $perm['permission_name'];
                                if(!empty($perm['category'])) {
                                  echo ' [' . $perm['category'] . ']';
                                }
                                echo '</option>';
                              }
                              ?>
                            </select>
                          </div>
                        </div>
                        <div class="form-actions">
                          <button type="submit" name="add_permission" class="btn btn-primary">Ajouter Permission</button>
                        </div>
                      </form>
                    </div>
                  </div>
                  
                  <!-- Bulk Add Permission Modal for each role -->
                  <div id="bulkAddPermissionModal<?php echo $role['role_id']; ?>" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="bulkModalLabel" aria-hidden="true" style="width: 700px; margin-left: -350px;">
                    <div class="modal-header">
                      <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                      <h3 id="bulkModalLabel">Ajouter Plusieurs Permissions: <?php echo $role['role_name']; ?></h3>
                    </div>
                    <div class="modal-body">
                      <form method="post">
                        <input type="hidden" name="bulk_role_id" value="<?php echo $role['role_id']; ?>">
                        
                        <input type="text" id="permission-search-<?php echo $role['role_id']; ?>" class="search-box" placeholder="Filtrer les permissions...">
                        
                        <?php
                        // Get all permission categories
                        $categories_query = mysqli_query($con, "SELECT DISTINCT category FROM permissions WHERE category != '' ORDER BY category");
                        $categories = array();
                        while($cat = mysqli_fetch_array($categories_query)) {
                          $categories[] = $cat['category'];
                        }
                        
                        // For each category, show permissions
                        foreach($categories as $category) {
                          echo '<div class="permission-group">';
                          echo '<h4>' . $category . '</h4>';
                          echo '<div class="checkbox-container">';
                          
                          // Get permissions for this category not assigned to the role
                          $perms_query = mysqli_query($con, 
                            "SELECT * FROM permissions 
                             WHERE category = '$category' AND permission_id NOT IN 
                             (SELECT permission_id FROM role_permissions WHERE role_id = '".$role['role_id']."')
                             ORDER BY permission_name");
                          
                          while($perm = mysqli_fetch_array($perms_query)) {
                            echo '<div class="checkbox-item permission-item-'.$role['role_id'].'">';
                            echo '<label>';
                            echo '<input type="checkbox" name="bulk_permissions[]" value="'.$perm['permission_id'].'">';
                            echo ' ' . $perm['permission_name'];
                            echo '</label>';
                            echo '</div>';
                          }
                          
                          echo '</div>'; // End checkbox-container
                          echo '</div>'; // End permission-group
                        }
                        
                        // Uncategorized permissions
                        $uncategorized_query = mysqli_query($con, 
                          "SELECT * FROM permissions 
                           WHERE (category = '' OR category IS NULL) AND permission_id NOT IN 
                           (SELECT permission_id FROM role_permissions WHERE role_id = '".$role['role_id']."')
                           ORDER BY permission_name");
                        
                        if(mysqli_num_rows($uncategorized_query) > 0) {
                          echo '<div class="permission-group">';
                          echo '<h4>Non catégorisé</h4>';
                          echo '<div class="checkbox-container">';
                          
                          while($perm = mysqli_fetch_array($uncategorized_query)) {
                            echo '<div class="checkbox-item permission-item-'.$role['role_id'].'">';
                            echo '<label>';
                            echo '<input type="checkbox" name="bulk_permissions[]" value="'.$perm['permission_id'].'">';
                            echo ' ' . $perm['permission_name'];
                            echo '</label>';
                            echo '</div>';
                          }
                          
                          echo '</div>'; // End checkbox-container
                          echo '</div>'; // End permission-group
                        }
                        ?>
                        
                        <div class="form-actions">
                          <button type="submit" name="bulk_add_permissions" class="btn btn-primary">Ajouter Permissions Sélectionnées</button>
                        </div>
                      </form>
                    </div>
                  </div>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        
        <!-- Permissions Tab -->
        <div class="tab-pane" id="permission-tab">
          <div class="widget-box">
            <div class="widget-title"> <span class="icon"> <i class="icon-lock"></i> </span>
              <h5>Gestion des Permissions</h5>
            </div>
            <div class="widget-content">
              <!-- Create Permission Form -->
              <form method="post" class="form-horizontal" style="margin-bottom: 20px;">
                <div class="control-group">
                  <label class="control-label">Nouvelle Permission:</label>
                  <div class="controls">
                    <input type="text" name="permission_name" required placeholder="Nom de la permission" class="span6">
                  </div>
                </div>
                <div class="control-group">
                  <label class="control-label">Catégorie:</label>
                  <div class="controls">
                    <select name="permission_category" class="span6">
                      <option value="">-- Aucune Catégorie --</option>
                      <?php
                      // Get all unique categories
                      $categories_query = mysqli_query($con, "SELECT DISTINCT category FROM permissions WHERE category != '' ORDER BY category");
                      while($cat = mysqli_fetch_array($categories_query)) {
                        echo '<option value="'.$cat['category'].'">'.$cat['category'].'</option>';
                      }
                      ?>
                      <option value="new">-- Nouvelle Catégorie --</option>
                    </select>
                    <input type="text" id="new-category" name="new_category" placeholder="Nouvelle catégorie" class="span6" style="display: none; margin-top: 5px;">
                  </div>
                </div>
                <div class="form-actions">
                  <button type="submit" name="create_permission" class="btn btn-primary">Créer Permission</button>
                </div>
              </form>
              
              <!-- Search permissions -->
              <input type="text" id="permission-search" class="search-box" placeholder="Rechercher une permission...">
              
              <!-- Permissions Table -->
              <table class="role-table" id="permission-table">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Nom de la Permission</th>
                    <th>Catégorie</th>
                    <th>Utilisée par</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $permissions_query = mysqli_query($con, "SELECT * FROM permissions ORDER BY category, permission_name");
                  while($perm = mysqli_fetch_array($permissions_query)) {
                  ?>
                  <tr>
                    <td><?php echo $perm['permission_id']; ?></td>
                    <td><?php echo $perm['permission_name']; ?></td>
                    <td>
                      <?php 
                      if(!empty($perm['category'])) {
                        echo '<span class="category-pill">' . $perm['category'] . '</span>';
                      } else {
                        echo '<em>Non catégorisé</em>';
                      }
                      ?>
                    </td>
                    <td>
                      <?php
                      // Count roles using this permission
                      $roles_query = mysqli_query($con, 
                        "SELECT r.role_name 
                         FROM roles r 
                         JOIN role_permissions rp ON r.role_id = rp.role_id 
                         WHERE rp.permission_id = '".$perm['permission_id']."'");
                      
                      $role_count = mysqli_num_rows($roles_query);
                      if($role_count > 0) {
                        echo '<strong>' . $role_count . ' rôle(s)</strong>: ';
                        $roles = array();
                        while($role = mysqli_fetch_array($roles_query)) {
                          $roles[] = $role['role_name'];
                        }
                        echo implode(', ', $roles);
                      } else {
                        echo '<em>Non utilisée</em>';
                      }
                      ?>
                    </td>
                    <td>
                      <a href="profile.php?delete_permission=<?php echo $perm['permission_id']; ?>" 
                         onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette permission?');" 
                         class="btn btn-danger btn-sm">
                        <i class="icon-trash"></i> Supprimer
                      </a>
                    </td>
                  </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        
        <!-- User Role Assignment Tab -->
        <div class="tab-pane" id="user-role-tab">
          <div class="widget-box">
            <div class="widget-title"> <span class="icon"> <i class="icon-user"></i> </span>
              <h5>Attribution des Rôles aux Utilisateurs</h5>
            </div>
            <div class="widget-content">
              <!-- Search users -->
              <input type="text" id="user-search" class="search-box" placeholder="Rechercher un utilisateur...">
              
              <!-- Users Table -->
              <table class="role-table" id="user-table">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Utilisateur</th>
                    <th>Email</th>
                    <th>Rôles</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $users_query = mysqli_query($con, "SELECT * FROM tbladmin ORDER BY ID");
                  while($user = mysqli_fetch_array($users_query)) {
                  ?>
                  <tr>
                    <td><?php echo $user['ID']; ?></td>
                    <td><?php echo $user['AdminName']; ?></td>
                    <td><?php echo $user['UserName']; ?></td>
                    <td><?php echo $user['Email']; ?></td>
                    <td>
                      <?php
                      // Get roles for this user
                      $roles_query = mysqli_query($con, 
                        "SELECT r.role_name, r.role_id 
                         FROM roles r 
                         JOIN user_roles ur ON r.role_id = ur.role_id 
                         WHERE ur.user_id = '".$user['ID']."'");
                      
                      while($role = mysqli_fetch_array($roles_query)) {
                        echo '<span class="permission-badge">' . $role['role_name'] . 
                             '<a href="profile.php?remove_user_role='.$user['ID'].'_'.$role['role_id'].'" 
                                onclick="return confirm(\'Êtes-vous sûr de vouloir supprimer ce rôle?\');">×</a></span> ';
                      }
                      ?>
                    </td>
                    <td>
                      <!-- Assign role button -->
                      <a href="#" data-toggle="modal" data-target="#assignRoleModal<?php echo $user['ID']; ?>" class="btn btn-info btn-sm">
                        <i class="icon-plus"></i> Assigner Rôle
                      </a>
                    </td>
                  </tr>
                  
                  <!-- Assign Role Modal for each user -->
                  <div id="assignRoleModal<?php echo $user['ID']; ?>" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="assignRoleLabel" aria-hidden="true">
                    <div class="modal-header">
                      <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                      <h3 id="assignRoleLabel">Assigner Rôle à: <?php echo $user['AdminName']; ?></h3>
                    </div>
                    <div class="modal-body">
                      <form method="post">
                        <input type="hidden" name="user_id" value="<?php echo $user['ID']; ?>">
                        <div class="control-group">
                          <label class="control-label">Sélectionner Rôle:</label>
                          <div class="controls">
                            <select name="role_id" class="span12">
                              <?php
                              // Get roles not already assigned to this user
                              $available_roles = mysqli_query($con, 
                                "SELECT * FROM roles 
                                 WHERE role_id NOT IN 
                                 (SELECT role_id FROM user_roles WHERE user_id = '".$user['ID']."')
                                 ORDER BY role_name");
                              
                              while($role = mysqli_fetch_array($available_roles)) {
                                echo '<option value="'.$role['role_id'].'">'.$role['role_name'].'</option>';
                              }
                              ?>
                            </select>
                          </div>
                        </div>
                        <div class="form-actions">
                          <button type="submit" name="assign_role" class="btn btn-primary">Assigner Rôle</button>
                        </div>
                      </form>
                    </div>
                  </div>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</div>
<?php include_once('includes/footer.php');?>
<?php include_once('includes/js.php');?>
<script>
$(document).ready(function(){
  // Activate Bootstrap tabs
  $('a[data-toggle="tab"]').on('click', function (e) {
    e.preventDefault();
    $(this).tab('show');
  });
  
  // Set active tab based on URL hash
  if (window.location.hash) {
    $('a[href="' + window.location.hash + '"]').tab('show');
  }
  
  // Category selection toggle
  $('select[name="permission_category"]').change(function(){
    if($(this).val() === 'new') {
      $('#new-category').show();
    } else {
      $('#new-category').hide();
    }
  });
  
  // Role search functionality
  $("#role-search").on("keyup", function() {
    var value = $(this).val().toLowerCase();
    $("#role-table tbody tr").filter(function() {
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
    });
  });
  
  // Permission search functionality
  $("#permission-search").on("keyup", function() {
    var value = $(this).val().toLowerCase();
    $("#permission-table tbody tr").filter(function() {
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
    });
  });
  
  // User search functionality
  $("#user-search").on("keyup", function() {
    var value = $(this).val().toLowerCase();
    $("#user-table tbody tr").filter(function() {
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
    });
  });
  
  // Bulk permission search for each role
  $('[id^="permission-search-"]').each(function() {
    var roleId = $(this).attr('id').split('-')[2];
    $(this).on("keyup", function() {
      var value = $(this).val().toLowerCase();
      $(".permission-item-" + roleId).filter(function() {
        $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
      });
    });
  });
});
</script>
</body>
</html>
<?php } ?>