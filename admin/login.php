<?php
session_start();
// Set secure session parameters
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);

// Better to use specific error reporting in production
// error_reporting(E_ALL);
// ini_set('display_errors', 0);
include('includes/dbconnection.php');

// CSRF Protection
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed");
    }
}

// Function to limit login attempts
function checkLoginAttempts($username) {
    global $con;
    
    // Check if user is already locked out
    $stmt = $con->prepare("SELECT login_attempts, last_attempt FROM tbladmin_login_attempts WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $attempts = $row['login_attempts'];
        $lastAttempt = strtotime($row['last_attempt']);
        
        // If locked out for 15 minutes (900 seconds)
        if ($attempts >= 5 && (time() - $lastAttempt) < 900) {
            return false;
        }
        
        // Reset attempts after 15 minutes
        if ((time() - $lastAttempt) > 900) {
            $stmt = $con->prepare("UPDATE tbladmin_login_attempts SET login_attempts = 1, last_attempt = NOW() WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            return true;
        }
        
        // Increment attempts
        $attempts++;
        $stmt = $con->prepare("UPDATE tbladmin_login_attempts SET login_attempts = ?, last_attempt = NOW() WHERE username = ?");
        $stmt->bind_param("is", $attempts, $username);
        $stmt->execute();
        
        if ($attempts >= 5) {
            return false;
        }
    } else {
        // First attempt
        $stmt = $con->prepare("INSERT INTO tbladmin_login_attempts (username, login_attempts, last_attempt) VALUES (?, 1, NOW())");
        $stmt->bind_param("s", $username);
        $stmt->execute();
    }
    
    return true;
}

// Login Process
if(isset($_POST['login'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        echo '<script>alert("Erreur de validation. Veuillez réessayer.")</script>';
        exit();
    }
    
    $adminuser = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    
    // Check if account is locked
    if (!checkLoginAttempts($adminuser)) {
        echo '<script>alert("Compte temporairement verrouillé. Veuillez réessayer après 15 minutes.")</script>';
    } else {
        // Use prepared statements to prevent SQL injection
        $stmt = $con->prepare("SELECT ID, Password FROM tbladmin WHERE UserName = ?");
        $stmt->bind_param("s", $adminuser);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $hashed_password = $row['Password'];
            
            // Verify password with password_verify() - requires passwords to be stored with password_hash()
            if (password_verify($_POST['password'], $hashed_password)) {
                // Reset login attempts on successful login
                $reset_stmt = $con->prepare("DELETE FROM tbladmin_login_attempts WHERE username = ?");
                $reset_stmt->bind_param("s", $adminuser);
                $reset_stmt->execute();
                
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                $_SESSION['imsaid'] = $row['ID'];
                
                // Set last login time
                $update_stmt = $con->prepare("UPDATE tbladmin SET last_login = NOW() WHERE ID = ?");
                $update_stmt->bind_param("i", $row['ID']);
                $update_stmt->execute();
                
                header('location:dashboard.php');
                exit();
            } else {
                echo '<script>alert("Détails invalides.")</script>';
            }
        } else {
            echo '<script>alert("Détails invalides.")</script>';
        }
    }
}

// Password Reset with Secure Token Method
if(isset($_POST['submit'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        echo '<script>alert("Erreur de validation. Veuillez réessayer.")</script>';
        exit();
    }
    
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $contactno = filter_input(INPUT_POST, 'contactno', FILTER_SANITIZE_STRING);
    
    if (!$email) {
        echo "<script>alert('Adresse email invalide.');</script>";
        exit();
    }
    
    // Check if email and phone exist
    $stmt = $con->prepare("SELECT ID FROM tbladmin WHERE Email = ? AND MobileNumber = ?");
    $stmt->bind_param("ss", $email, $contactno);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Generate a secure token
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Store the token in the database
        $stmt = $con->prepare("INSERT INTO password_reset_tokens (email, token, expiry) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $email, $token, $expiry);
        $stmt->execute();
        
        // In a real application, you would send an email with a link containing the token
        // For this example, we'll just display the token (in production, never do this)
        echo "<script>alert('Un lien de réinitialisation a été envoyé à votre adresse email.');</script>";
        
        // Redirect to a page where they can enter the token and new password
        // header('location: reset_password.php');
        // exit();
    } else {
        echo "<script>alert('Détails invalides. Veuillez réessayer.');</script>";
    }
}

// This function would be used in the reset_password.php page
function resetPassword($token, $newPassword) {
    global $con;
    
    // Check if token exists and is valid
    $stmt = $con->prepare("SELECT email, expiry FROM password_reset_tokens WHERE token = ? AND used = 0");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $email = $row['email'];
        $expiry = strtotime($row['expiry']);
        
        // Check if token has expired
        if (time() > $expiry) {
            return "Le lien de réinitialisation a expiré.";
        }
        
        // Hash the new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update the password
        $update_stmt = $con->prepare("UPDATE tbladmin SET Password = ? WHERE Email = ?");
        $update_stmt->bind_param("ss", $hashedPassword, $email);
        
        if ($update_stmt->execute()) {
            // Mark the token as used
            $used_stmt = $con->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
            $used_stmt->bind_param("s", $token);
            $used_stmt->execute();
            
            return "Mot de passe changé avec succès.";
        }
    }
    
    return "Erreur lors de la réinitialisation du mot de passe.";
}
?>
<!DOCTYPE html>
<html lang="fr">
        
<head>
    <title>Système de gestion d'inventaire || Page de connexion</title>
    <meta charset="UTF-8" />
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com">
    <link rel="stylesheet" href="css/bootstrap.min.css" />
    <link rel="stylesheet" href="css/bootstrap-responsive.min.css" />
    <link rel="stylesheet" href="css/matrix-login.css" />
    <link href="font-awesome/css/font-awesome.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,700,800" rel="stylesheet" type="text/css">
    <?php include_once('includes/responsive.php'); ?>
</head>
<body>
    <div id="loginbox">            
        <form id="loginform" class="form-vertical" method="post">
            <div class="control-group normal_text"> <h3>Inventaire</strong> <strong style="color: orange">Système</strong></h3></div>
            <!-- Add CSRF token -->
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <div class="control-group">
                <div class="controls">
                    <div class="main_input_box">
                        <span class="add-on bg_lg"><i class="icon-user"> </i></span><input type="text" placeholder="Nom d'utilisateur" name="username" required="true" />
                    </div>
                </div>
            </div>
            <div class="control-group">
                <div class="controls">
                    <div class="main_input_box">
                        <span class="add-on bg_ly"><i class="icon-lock"></i></span><input type="password" placeholder="Mot de passe" name="password" required="true"/>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <span class="pull-left"><a href="#" class="flip-link btn btn-info" id="to-recover">Mot de passe oublié?</a></span>
                <span class="pull-right"><input type="submit" class="btn btn-success" name="login" value="Se connecter"></span>
            </div>
        </form>
        <div style="padding-left: 180px;">
            <a href="../index.php" class="flip-link btn btn-info" id="to-recover"><i class="icon-home"></i>  Retour à l'accueil</a>
        </div>
        <br />
        <form id="recoverform" class="form-vertical" method="post" name="changepassword">
            <p class="normal_text">Entrez votre adresse e-mail et numéro de téléphone ci-dessous et nous vous enverrons des instructions pour récupérer un mot de passe.</p>
            
            <!-- Add CSRF token -->
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="controls">
                <div class="main_input_box">
                    <span class="add-on bg_lo"><i class="icon-envelope"></i></span><input type="email" placeholder="Adresse e-mail" name="email" required="true" />
                </div>
            </div>
            <br />
            <div class="controls">
                <div class="main_input_box">
                    <span class="add-on bg_lo"><i class="icon-phone-sign"></i></span><input type="text" placeholder="Numéro de contact" name="contactno" required="true" />
                </div>
            </div>
            <br />
            <div class="form-actions">
                <span class="pull-left"><a href="#" class="flip-link btn btn-success" id="to-login">&laquo; Retour à la connexion</a></span>
                <span class="pull-right"><input type="submit" class="btn btn-success" name="submit" value="Réinitialiser"></span>
            </div>
        </form>
    </div>
    
    <script src="js/jquery.min.js"></script>  
    <script src="js/matrix.login.js"></script> 
</body>
</html>