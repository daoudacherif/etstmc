<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('includes/dbconnection.php');

// Check if admin is logged in
if (!isset($_SESSION['imsaid']) || empty($_SESSION['imsaid'])) {
    echo "Please log in as admin first";
    exit;
}

$admin_id = $_SESSION['imsaid'];

// Create default categories
$categories = [
    'menu' => 'Menu Navigation',
    'inventory' => 'Gestion d\'Inventaire',
    'admin' => 'Administration',
    'users' => 'Gestion d\'Utilisateurs',
    'sales' => 'Ventes',
    'reports' => 'Rapports'
];

// Insert categories as permissions
foreach ($categories as $key => $value) {
    $category = mysqli_real_escape_string($con, $key);
    $name = mysqli_real_escape_string($con, $value);
    mysqli_query($con, "INSERT IGNORE INTO permissions (permission_name, category) 
                        VALUES ('access_$category', '$name')");
}

// Create all needed permissions
$permissions = [
    // Menu permissions
    ['access_dashboard', 'menu'],
    ['manage_categories', 'menu'],
    ['add_categories', 'menu'],
    ['manage_products', 'menu'],
    ['add_products', 'menu'],
    ['manage_sales', 'menu'],
    ['use_search', 'menu'],
    ['manage_suppliers', 'menu'],
    ['manage_clients', 'menu'],
    ['view_reports', 'menu'],
    ['admin_access', 'menu'],
    
    // Inventory permissions
    ['view_inventory', 'inventory'],
    ['edit_inventory', 'inventory'],
    ['manage_inventory', 'inventory'],
    
    // Admin permissions
    ['manage_roles', 'admin'],
    ['manage_permissions', 'admin'],
    ['manage_users', 'admin'],
    ['create_users', 'users'],
    ['edit_users', 'users'],
    ['delete_users', 'users'],
    
    // Sales permissions
    ['process_sales', 'sales'],
    ['view_sales', 'sales'],
    ['manage_returns', 'sales'],
    
    // Reports permissions
    ['view_sales_reports', 'reports'],
    ['view_inventory_reports', 'reports'],
    ['view_client_reports', 'reports']
];

// Insert all permissions
foreach ($permissions as $perm) {
    $perm_name = mysqli_real_escape_string($con, $perm[0]);
    $perm_category = mysqli_real_escape_string($con, $perm[1]);
    mysqli_query($con, "INSERT IGNORE INTO permissions (permission_name, category) 
                        VALUES ('$perm_name', '$perm_category')");
}

// Create Admin role if it doesn't exist
$admin_role_query = mysqli_query($con, "SELECT * FROM roles WHERE role_name = 'Admin'");
if (mysqli_num_rows($admin_role_query) == 0) {
    mysqli_query($con, "INSERT INTO roles (role_name, role_description) 
                        VALUES ('Admin', 'Full system administrator with all permissions')");
}

// Get Admin role ID
$admin_role_query = mysqli_query($con, "SELECT role_id FROM roles WHERE role_name = 'Admin'");
$admin_role = mysqli_fetch_assoc($admin_role_query);
$admin_role_id = $admin_role['role_id'];

// Assign all permissions to Admin role
$all_perms_query = mysqli_query($con, "SELECT permission_id FROM permissions");
while ($perm = mysqli_fetch_assoc($all_perms_query)) {
    $perm_id = $perm['permission_id'];
    mysqli_query($con, "INSERT IGNORE INTO role_permissions (role_id, permission_id) 
                        VALUES ('$admin_role_id', '$perm_id')");
}

// Assign Admin role to current admin user
mysqli_query($con, "INSERT IGNORE INTO user_roles (user_id, role_id) 
                    VALUES ('$admin_id', '$admin_role_id')");

// Cache permissions in session for immediate use
$_SESSION['user_permissions'] = [];
$perms_query = mysqli_query($con, "
    SELECT p.permission_name
    FROM tbladmin a
    JOIN user_roles ur ON a.ID = ur.user_id
    JOIN role_permissions rp ON ur.role_id = rp.role_id
    JOIN permissions p ON rp.permission_id = p.permission_id
    WHERE a.ID = '$admin_id'
");

while ($perm = mysqli_fetch_assoc($perms_query)) {
    $_SESSION['user_permissions'][$perm['permission_name']] = true;
}

echo "<div style='max-width: 800px; margin: 50px auto; padding: 20px; background: #fff; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1);'>";
echo "<h1>Permission Setup Complete</h1>";
echo "<p>All permissions have been created and assigned to your admin account.</p>";
echo "<p>The Admin role has been created (if it didn't exist) and all permissions have been assigned to it.</p>";
echo "<p>Your user account (ID: $admin_id) has been assigned the Admin role.</p>";

echo "<h2>Quick Checks:</h2>";
echo "<ul>";
echo "<li>Roles table: " . (mysqli_query($con, "SELECT 1 FROM roles LIMIT 1") ? "✅ Exists" : "❌ Missing") . "</li>";
echo "<li>Permissions table: " . (mysqli_query($con, "SELECT 1 FROM permissions LIMIT 1") ? "✅ Exists" : "❌ Missing") . "</li>";
echo "<li>user_roles table: " . (mysqli_query($con, "SELECT 1 FROM user_roles LIMIT 1") ? "✅ Exists" : "❌ Missing") . "</li>";
echo "<li>role_permissions table: " . (mysqli_query($con, "SELECT 1 FROM role_permissions LIMIT 1") ? "✅ Exists" : "❌ Missing") . "</li>";
echo "</ul>";

echo "<p>Your permissions have been cached in your session. You should now see all menu items.</p>";

echo "<p><a href='dashboard.php' style='display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px;'>Return to Dashboard</a></p>";
echo "</div>";
?>