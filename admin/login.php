<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');

// Fonction pour nettoyer les entrées
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

if(isset($_POST['login'])) {
    $adminuser = cleanInput($_POST['username']);
    $password = md5(cleanInput($_POST['password']));
    
    // Utilisation de requête préparée pour éviter les injections SQL
    $stmt = $con->prepare("SELECT ID FROM tbladmin WHERE UserName=? AND Password=?");
    $stmt->bind_param("ss", $adminuser, $password);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $_SESSION['imsaid'] = $row['ID'];
        header('location: dashboard.php');
        exit();
    } else {
        $error_message = "Identifiants invalides. Veuillez réessayer.";
    }
}

if(isset($_POST['submit'])) {
    $contactno = cleanInput($_POST['contactno']);
    $email = cleanInput($_POST['email']);
    $password = md5(cleanInput($_POST['newpassword']));
    
    // Utilisation de requête préparée
    $stmt = $con->prepare("SELECT ID FROM tbladmin WHERE Email=? AND MobileNumber=?");
    $stmt->bind_param("ss", $email, $contactno);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        $_SESSION['contactno'] = $contactno;
        $_SESSION['email'] = $email;
        
        $stmt = $con->prepare("UPDATE tbladmin SET Password=? WHERE Email=? AND MobileNumber=?");
        $stmt->bind_param("sss", $password, $email, $contactno);
        $stmt->execute();
        
        if($stmt->affected_rows > 0) {
            $success_message = "Mot de passe changé avec succès!";
        }
    } else {
        $reset_error = "Détails invalides. Veuillez réessayer.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Système de gestion d'inventaire || Connexion</title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="css/bootstrap.min.css" />
    <link rel="stylesheet" href="css/bootstrap-responsive.min.css" />
   
    <link href="font-awesome/css/font-awesome.css" rel="stylesheet" />
    <link href='https://fonts.googleapis.com/css?family=Open+Sans:400,600,700,800' rel='stylesheet' type='text/css'>
    <style>
        :root {
            --primary-color: #28b779;
            --secondary-color: #2E363F;
            --accent-color: #27a9e3;
            --light-bg: #f5f5f5;
            --dark-text: #333;
            --light-text: #fff;
            --danger: #f74f57;
            --warning: #ffbb44;
        }
        
        body {
            background: linear-gradient(135deg, var(--secondary-color), #1a2129);
            font-family: 'Open Sans', sans-serif;
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        #loginbox {
            max-width: 400px;
            width: 90%;
            margin: 0 auto;
            overflow: hidden;
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            position: relative;
        }
        
        .form-vertical {
            padding: 30px;
        }
        
        .control-group.normal_text {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .control-group.normal_text h3 {
            color: var(--secondary-color);
            font-weight: 600;
            font-size: 24px;
            margin: 0;
        }
        
        .controls {
            margin-bottom: 20px;
        }
        
        .main_input_box {
            display: flex;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .main_input_box:focus-within {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px rgba(39, 169, 227, 0.2);
        }
        
        .main_input_box .add-on {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px 15px;
            background: var(--light-bg);
            color: #888;
        }
        
        .main_input_box input {
            flex: 1;
            border: none;
            padding: 12px 15px;
            outline: none;
            font-size: 14px;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 25px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 4px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-success {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-success:hover {
            background: #239c66;
        }
        
        .btn-info {
            background: var(--accent-color);
            color: white;
        }
        
        .btn-info:hover {
            background: #1e96cc;
        }
        
        .flip-link {
            text-decoration: none;
            font-size: 13px;
        }
        
        #to-recover, #to-login {
            font-weight: 600;
        }
        
        .home-button {
            text-align: center;
            margin-top: 15px;
        }
        
        .alert {
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            color: white;
            font-size: 14px;
        }
        
        .alert-danger {
            background-color: var(--danger);
        }
        
        .alert-success {
            background-color: var(--primary-color);
        }
        
        p.normal_text {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        #recoverform {
            display: none;
        }
        
        @media (max-width: 480px) {
            #loginbox {
                width: 95%;
            }
            
            .form-vertical {
                padding: 20px;
            }
            
            .form-actions {
                flex-direction: column;
                gap: 15px;
            }
            
            .pull-left, .pull-right {
                text-align: center;
                width: 100%;
            }
            
            .btn {
                width: 100%;
            }
        }
        
        /* Animation d'entrée */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-vertical {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>

<body>
    <div id="loginbox">
        <!-- Formulaire de connexion -->
        <form id="loginform" class="form-vertical" method="post">
            <div class="control-group normal_text">
                <h3><span style="color: var(--secondary-color)">Inventaire</span> <span style="color: var(--primary-color)">Système</span></h3>
            </div>
            
            <?php if(isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="icon-warning-sign"></i> <?php echo $error_message; ?>
            </div>
            <?php endif; ?>
            
            <div class="controls">
                <div class="main_input_box">
                    <span class="add-on"><i class="icon-user"></i></span>
                    <input type="text" placeholder="Nom d'utilisateur" name="username" required />
                </div>
            </div>
            
            <div class="controls">
                <div class="main_input_box">
                    <span class="add-on"><i class="icon-lock"></i></span>
                    <input type="password" placeholder="Mot de passe" name="password" required />
                </div>
            </div>
            
            <div class="form-actions">
                <span class="pull-left"><a href="#" class="flip-link btn btn-info" id="to-recover">Mot de passe oublié?</a></span>
                <span class="pull-right"><input type="submit" class="btn btn-success" name="login" value="Se connecter"></span>
            </div>
        </form>
        
        <!-- Bouton de retour à l'accueil -->
        <div class="home-button">
            <a href="../index.php" class="btn btn-info"><i class="icon-home"></i> Retour à l'accueil</a>
        </div>
        
        <!-- Formulaire de récupération de mot de passe -->
        <form id="recoverform" class="form-vertical" method="post" name="changepassword" onsubmit="return checkpass();">
            <div class="control-group normal_text">
                <h3><span style="color: var(--secondary-color)">Récupération</span> <span style="color: var(--primary-color)">Mot de passe</span></h3>
            </div>
            
            <?php if(isset($reset_error)): ?>
            <div class="alert alert-danger">
                <i class="icon-warning-sign"></i> <?php echo $reset_error; ?>
            </div>
            <?php endif; ?>
            
            <?php if(isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="icon-ok"></i> <?php echo $success_message; ?>
            </div>
            <?php endif; ?>
            
            <p class="normal_text">Entrez vos coordonnées pour réinitialiser votre mot de passe</p>
            
            <div class="controls">
                <div class="main_input_box">
                    <span class="add-on"><i class="icon-envelope"></i></span>
                    <input type="email" placeholder="Adresse e-mail" name="email" required />
                </div>
            </div>
            
            <div class="controls">
                <div class="main_input_box">
                    <span class="add-on"><i class="icon-phone-sign"></i></span>
                    <input type="text" placeholder="Numéro de contact" name="contactno" required />
                </div>
            </div>
            
            <div class="controls">
                <div class="main_input_box">
                    <span class="add-on"><i class="icon-lock"></i></span>
                    <input type="password" name="newpassword" placeholder="Nouveau mot de passe" required />
                </div>
            </div>
            
            <div class="controls">
                <div class="main_input_box">
                    <span class="add-on"><i class="icon-lock"></i></span>
                    <input type="password" name="confirmpassword" placeholder="Confirmer le mot de passe" required />
                </div>
            </div>
            
            <div class="form-actions">
                <span class="pull-left"><a href="#" class="flip-link btn btn-info" id="to-login">&laquo; Retour à la connexion</a></span>
                <span class="pull-right"><input type="submit" class="btn btn-success" name="submit" value="Réinitialiser"></span>
            </div>
        </form>
    </div>
    
    <script src="js/jquery.min.js"></script>
    <script>
    function checkpass() {
        if(document.changepassword.newpassword.value != document.changepassword.confirmpassword.value) {
            alert('Le nouveau mot de passe et le champ de confirmation du mot de passe ne correspondent pas');
            document.changepassword.confirmpassword.focus();
            return false;
        }
        return true;
    }
    
    $(document).ready(function() {
        // Animations pour basculer entre les formulaires
        $('.flip-link').click(function(e) {
            e.preventDefault();
            
            if($(this).attr('id') == 'to-recover') {
                $('#loginform').hide('flip', function() {
                    $('#recoverform').show('flip');
                });
            } else if($(this).attr('id') == 'to-login') {
                $('#recoverform').hide('flip', function() {
                    $('#loginform').show('flip');
                });
            }
        });
    });
    </script>
</body>
</html>