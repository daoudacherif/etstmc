<?php
session_start();
require_once('includes/config.php');
require_once('includes/dbconnection.php');

// Function to validate password strength
function validatePassword($password) {
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return false;
    }
    if (PASSWORD_REQUIRE_SPECIAL && !preg_match('/[^A-Za-z0-9]/', $password)) {
        return false;
    }
    if (PASSWORD_REQUIRE_NUMBERS && !preg_match('/[0-9]/', $password)) {
        return false;
    }
    if (PASSWORD_REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
        return false;
    }
    return true;
}

// Function to sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

if(isset($_POST['login'])) {
    $adminname = sanitizeInput($_POST['adminname']);
    $password = $_POST['password'];
    
    if(empty($adminname) || empty($password)) {
        echo '<script>alert("Veuillez remplir tous les champs.")</script>';
    } else {
        $stmt = $con->prepare("SELECT ID, UserName, Password, Status FROM tbladmin WHERE AdminName=?");
        $stmt->bind_param("s", $adminname);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            if($user['Status'] == 0) {
                echo '<script>alert("Votre compte a été désactivé. Veuillez contacter l\'administrateur.")</script>';
            } else {
                if(password_verify($password, $user['Password'])) {
                    // Regenerate session ID to prevent session fixation
                    session_regenerate_id(true);
                    
                    $_SESSION['imsaid'] = $user['ID'];
                    $_SESSION['last_activity'] = time();
                    
                    // Log successful login
                    error_log("Successful login for user: " . $adminname);
                    
                    header('location:dashboard.php');
                    exit();
                } else {
                    // Log failed login attempt
                    error_log("Failed login attempt for user: " . $adminname);
                    echo '<script>alert("Mot de passe incorrect. Veuillez réessayer.")</script>';
                }
            }
        } else {
            echo '<script>alert("Nom d\'administrateur invalide. Veuillez réessayer.")</script>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Système de gestion d'inventaire || Page de connexion</title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="css/bootstrap.min.css" />
    <link rel="stylesheet" href="css/bootstrap-responsive.min.css" />
    <link rel="stylesheet" href="css/matrix-login.css" />
    <link href="font-awesome/css/font-awesome.css" rel="stylesheet" />
    <link href='http://fonts.googleapis.com/css?family=Open+Sans:400,700,800' rel='stylesheet' type='text/css'>
    <?php include_once('includes/responsive.php'); ?>
</head>
<body>
    <div id="loginbox">            
        <form id="loginform" class="form-vertical" method="post">
            <div class="control-group normal_text"> <h3>Inventaire</strong> <strong style="color: orange">Système</strong></h3></div>
            <div class="control-group">
                <div class="controls">
                    <div class="main_input_box">
                        <span class="add-on bg_lg"><i class="icon-user"> </i></span>
                        <input type="text" placeholder="Nom d'administrateur" name="adminname" required="true" />
                    </div>
                </div>
            </div>
            <div class="control-group">
                <div class="controls">
                    <div class="main_input_box">
                        <span class="add-on bg_ly"><i class="icon-lock"></i></span>
                        <input type="password" placeholder="Mot de passe" name="password" required="true"/>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <span class="pull-right"><input type="submit" class="btn btn-success" name="login" value="Se connecter"></span>
            </div>
        </form>
        <div style="padding-left: 180px;">
            <a href="../index.php" class="flip-link btn btn-info"><i class="icon-home"></i> Retour à l'accueil</a>
        </div>
    </div>
    
    <script src="js/jquery.min.js"></script>  
    <script src="js/matrix.login.js"></script> 
</body>
</html>