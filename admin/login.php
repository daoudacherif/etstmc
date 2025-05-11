<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

// Initialize variables
$error_message = "";
$success_message = "";

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is already logged in
if(isset($_SESSION['imsaid']) && !empty($_SESSION['imsaid'])) {
    header('location:dashboard.php');
    exit();
}

// Login Process
if(isset($_POST['login'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Erreur de validation du formulaire. Veuillez réessayer.";
    } else {
        $adminuser = mysqli_real_escape_string($con, $_POST['username']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']) ? 1 : 0;
        
        // Check if account is locked
        $check_lock = mysqli_query($con, "SELECT login_attempts, account_locked_until FROM tbladmin WHERE UserName='$adminuser'");
        if(mysqli_num_rows($check_lock) > 0) {
            $lock_info = mysqli_fetch_assoc($check_lock);
            
            // Check if account is locked
            if($lock_info['account_locked_until'] && strtotime($lock_info['account_locked_until']) > time()) {
                $unlock_time = date('H:i:s', strtotime($lock_info['account_locked_until']));
                $error_message = "Compte temporairement verrouillé. Réessayez après $unlock_time.";
            } else {
                // Proceed with login attempt
                $query = mysqli_query($con, "SELECT ID, Password, AdminName FROM tbladmin WHERE UserName='$adminuser'");
                if(mysqli_num_rows($query) > 0) {
                    $row = mysqli_fetch_assoc($query);
                    
                    // For first-time migration from MD5 to password_hash
                    $md5_password = md5($password);
                    
                    if($row['Password'] == $md5_password || password_verify($password, $row['Password'])) {
                        // If still using MD5, update to password_hash
                        if($row['Password'] == $md5_password) {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            mysqli_query($con, "UPDATE tbladmin SET Password='$hashed_password', login_attempts=0, account_locked_until=NULL WHERE ID='".$row['ID']."'");
                        } else {
                            // Reset login attempts on successful login
                            mysqli_query($con, "UPDATE tbladmin SET login_attempts=0, account_locked_until=NULL WHERE ID='".$row['ID']."'");
                        }
                        
                        // Set session variables
                        $_SESSION['imsaid'] = $row['ID'];
                        $_SESSION['imsname'] = $row['AdminName'];
                        
                        // Load user roles and permissions
                        $user_id = $row['ID'];
                        $roles_query = mysqli_query($con, "SELECT r.role_id, r.role_name FROM roles r 
                                                          JOIN user_roles ur ON r.role_id = ur.role_id 
                                                          WHERE ur.user_id = '$user_id'");
                        
                        $user_roles = array();
                        $user_permissions = array();
                        
                        while($role = mysqli_fetch_assoc($roles_query)) {
                            $user_roles[] = $role;
                            
                            // Get permissions for this role
                            $permissions_query = mysqli_query($con, "SELECT p.permission_id, p.permission_name 
                                                                   FROM permissions p 
                                                                   JOIN role_permissions rp ON p.permission_id = rp.permission_id 
                                                                   WHERE rp.role_id = '".$role['role_id']."'");
                            
                            while($perm = mysqli_fetch_assoc($permissions_query)) {
                                $user_permissions[$perm['permission_name']] = true;
                            }
                        }
                        
                        $_SESSION['user_roles'] = $user_roles;
                        $_SESSION['user_permissions'] = $user_permissions;
                        
                        // Set remember me cookie if selected
                        if($remember) {
                            $token = bin2hex(random_bytes(32));
                            $expires = time() + (30 * 24 * 60 * 60); // 30 days
                            
                            setcookie('ims_remember', $token, $expires, '/');
                            
                            // Store token in database (hashed)
                            $hashed_token = password_hash($token, PASSWORD_DEFAULT);
                            mysqli_query($con, "UPDATE tbladmin SET remember_token='$hashed_token', token_expires='".date('Y-m-d H:i:s', $expires)."' WHERE ID='".$row['ID']."'");
                        }
                        
                        // Redirect to dashboard
                        header('location:dashboard.php');
                        exit();
                    } else {
                        // Increment login attempts
                        $attempts = $lock_info['login_attempts'] + 1;
                        
                        if($attempts >= 5) {
                            // Lock account for 15 minutes
                            $locked_until = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                            mysqli_query($con, "UPDATE tbladmin SET login_attempts='$attempts', account_locked_until='$locked_until' WHERE UserName='$adminuser'");
                            $error_message = "Trop de tentatives échouées. Compte verrouillé pendant 15 minutes.";
                        } else {
                            mysqli_query($con, "UPDATE tbladmin SET login_attempts='$attempts' WHERE UserName='$adminuser'");
                            $error_message = "Identifiants invalides. Tentatives restantes: " . (5 - $attempts);
                        }
                    }
                } else {
                    $error_message = "Identifiants invalides.";
                }
            }
        } else {
            $error_message = "Identifiants invalides.";
        }
    }
}

// Password Reset Process
if(isset($_POST['reset'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Erreur de validation du formulaire. Veuillez réessayer.";
    } else {
        $email = mysqli_real_escape_string($con, $_POST['email']);
        $contactno = mysqli_real_escape_string($con, $_POST['contactno']);
        $newpassword = $_POST['newpassword'];
        $confirm_password = $_POST['confirmpassword'];
        
        // Validate password complexity
        if(strlen($newpassword) < 8) {
            $error_message = "Le mot de passe doit contenir au moins 8 caractères.";
        } else if($newpassword !== $confirm_password) {
            $error_message = "Les mots de passe ne correspondent pas.";
        } else {
            $query = mysqli_query($con, "SELECT ID FROM tbladmin WHERE Email='$email' AND MobileNumber='$contactno'");
            
            if(mysqli_num_rows($query) > 0) {
                $row = mysqli_fetch_assoc($query);
                $user_id = $row['ID'];
                
                // Hash the new password
                $hashed_password = password_hash($newpassword, PASSWORD_DEFAULT);
                
                $update_query = mysqli_query($con, "UPDATE tbladmin SET 
                                                  Password='$hashed_password', 
                                                  login_attempts=0, 
                                                  account_locked_until=NULL, 
                                                  password_changed_at=NOW() 
                                                  WHERE ID='$user_id'");
                
                if($update_query) {
                    $success_message = "Mot de passe réinitialisé avec succès. Vous pouvez maintenant vous connecter.";
                } else {
                    $error_message = "Erreur lors de la réinitialisation du mot de passe. Veuillez réessayer.";
                }
            } else {
                $error_message = "Les informations fournies ne correspondent à aucun compte.";
            }
        }
    }
}

// Check "Remember Me" cookie
if(!isset($_SESSION['imsaid']) && isset($_COOKIE['ims_remember'])) {
    $token = $_COOKIE['ims_remember'];
    
    $query = mysqli_query($con, "SELECT ID, AdminName, remember_token, token_expires FROM tbladmin 
                                WHERE token_expires > NOW()");
    
    while($row = mysqli_fetch_assoc($query)) {
        if(password_verify($token, $row['remember_token'])) {
            // Token matches, auto login the user
            $_SESSION['imsaid'] = $row['ID'];
            $_SESSION['imsname'] = $row['AdminName'];
            
            // Load user roles and permissions
            $user_id = $row['ID'];
            $roles_query = mysqli_query($con, "SELECT r.role_id, r.role_name FROM roles r 
                                              JOIN user_roles ur ON r.role_id = ur.role_id 
                                              WHERE ur.user_id = '$user_id'");
            
            $user_roles = array();
            $user_permissions = array();
            
            while($role = mysqli_fetch_assoc($roles_query)) {
                $user_roles[] = $role;
                
                // Get permissions for this role
                $permissions_query = mysqli_query($con, "SELECT p.permission_id, p.permission_name 
                                                       FROM permissions p 
                                                       JOIN role_permissions rp ON p.permission_id = rp.permission_id 
                                                       WHERE rp.role_id = '".$role['role_id']."'");
                
                while($perm = mysqli_fetch_assoc($permissions_query)) {
                    $user_permissions[$perm['permission_name']] = true;
                }
            }
            
            $_SESSION['user_roles'] = $user_roles;
            $_SESSION['user_permissions'] = $user_permissions;
            
            header('location:dashboard.php');
            exit();
        }
    }
    
    // If we got here, the cookie is invalid or expired
    setcookie('ims_remember', '', time() - 3600, '/'); // Delete the cookie
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Système de gestion d'inventaire || Page de connexion</title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="css/bootstrap.min.css" />
    <link rel="stylesheet" href="css/bootstrap-responsive.min.css" />
    <link rel="stylesheet" href="css/matrix-login.css" />
    <link href="font-awesome/css/font-awesome.css" rel="stylesheet" />
    <link href='http://fonts.googleapis.com/css?family=Open+Sans:400,700,800' rel='stylesheet' type='text/css'>
   
 
</head>
<body>
    <div id="loginbox">
        <div class="login-form-container">
            <div class="form-title">
                <h2><strong>Inventaire</strong> <span>Système</span></h2>
            </div>
            
            <!-- Alert Messages -->
            <?php if($error_message): ?>
                <div class="alert alert-error">
                    <i class="icon-warning-sign"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if($success_message): ?>
                <div class="alert alert-success">
                    <i class="icon-ok"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <div id="form-container">
                <!-- Login Form -->
                <form id="loginform" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="controls">
                        <div class="main_input_box">
                            <span><i class="icon-user"></i></span>
                            <input type="text" placeholder="Nom d'utilisateur" name="username" required>
                        </div>
                    </div>
                    
                    <div class="controls">
                        <div class="main_input_box">
                            <span><i class="icon-lock"></i></span>
                            <input type="password" placeholder="Mot de passe" name="password" required>
                        </div>
                    </div>
                    
                    <div class="remember-me">
                        <input type="checkbox" name="remember" id="remember">
                        <label for="remember">Se souvenir de moi</label>
                    </div>
                    
                    <div class="form-actions">
                        <div class="pull-left">
                            <a href="#" class="flip-link btn btn-info" id="to-recover">Mot de passe oublié?</a>
                        </div>
                        <div class="pull-right">
                            <input type="submit" class="btn btn-success" name="login" value="Se connecter">
                        </div>
                    </div>
                    
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="../index.php" class="btn btn-info">
                            <i class="icon-home"></i> Retour à l'accueil
                        </a>
                    </div>
                </form>
                
                <!-- Password Reset Form -->
                <form id="recoverform" method="post" style="display: none;">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <p style="text-align: center; margin-bottom: 20px;">
                        Entrez votre adresse e-mail et numéro de téléphone associés à votre compte pour réinitialiser votre mot de passe.
                    </p>
                    
                    <div class="controls">
                        <div class="main_input_box">
                            <span><i class="icon-envelope"></i></span>
                            <input type="email" placeholder="Adresse e-mail" name="email" required>
                        </div>
                    </div>
                    
                    <div class="controls">
                        <div class="main_input_box">
                            <span><i class="icon-phone"></i></span>
                            <input type="text" placeholder="Numéro de contact" name="contactno" required>
                        </div>
                    </div>
                    
                    <div class="controls">
                        <div class="main_input_box">
                            <span><i class="icon-lock"></i></span>
                            <input type="password" id="newpassword" name="newpassword" placeholder="Nouveau mot de passe" required minlength="8">
                        </div>
                    </div>
                    
                    <div id="progress-bar">
                        <div id="password-strength"></div>
                    </div>
                    
                    <div class="password-requirements">
                        Le mot de passe doit contenir au moins 8 caractères. Une combinaison de lettres, chiffres et caractères spéciaux est recommandée.
                    </div>
                    
                    <div class="controls">
                        <div class="main_input_box">
                            <span><i class="icon-lock"></i></span>
                            <input type="password" name="confirmpassword" placeholder="Confirmer le mot de passe" required>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <div class="pull-left">
                            <a href="#" class="flip-link btn btn-info" id="to-login">&laquo; Retour à la connexion</a>
                        </div>
                        <div class="pull-right">
                            <input type="submit" class="btn btn-success" name="reset" value="Réinitialiser">
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="js/jquery.min.js"></script>
    <script>
    $(document).ready(function() {
        // Flip animation between forms
        $('.flip-link').click(function(e) {
            e.preventDefault();
            
            if($(this).attr('id') === 'to-recover') {
                $('#loginform').slideUp();
                $('#recoverform').slideDown();
            } else {
                $('#recoverform').slideUp();
                $('#loginform').slideDown();
            }
        });
        
        // Password strength meter
        $('#newpassword').keyup(function() {
            var password = $(this).val();
            var strength = 0;
            
            if(password.length >= 8) strength += 1;
            if(password.match(/[a-z]+/)) strength += 1;
            if(password.match(/[A-Z]+/)) strength += 1;
            if(password.match(/[0-9]+/)) strength += 1;
            if(password.match(/[^a-zA-Z0-9]+/)) strength += 1;
            
            var width = (strength * 20) + '%';
            
            $('#password-strength').css('width', width);
            
            if(strength <= 2) {
                $('#password-strength').removeClass().addClass('progress-weak');
            } else if(strength <= 3) {
                $('#password-strength').removeClass().addClass('progress-medium');
            } else {
                $('#password-strength').removeClass().addClass('progress-strong');
            }
        });
        
        // Password match validation
        $('input[name="confirmpassword"]').keyup(function() {
            var password = $('#newpassword').val();
            var confirm = $(this).val();
            
            if(password === confirm) {
                $(this).css('border-color', '#28a745');
            } else {
                $(this).css('border-color', '#dc3545');
            }
        });
        
        // Show the correct form based on URL hash
        if(window.location.hash === '#recover') {
            $('#loginform').hide();
            $('#recoverform').show();
        }
        
        <?php if($success_message): ?>
        // Auto show login form if password reset was successful
        $('#recoverform').hide();
        $('#loginform').show();
        <?php endif; ?>
    });
    </script>
</body>
</html>