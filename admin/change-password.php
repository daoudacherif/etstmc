<?php
session_start();
error_reporting(0);
include('includes/dbconnection.php');
if (strlen($_SESSION['imsaid']==0)) {
  header('location:logout.php');
} else {
    // Initialize messages
    $error_message = "";
    $success_message = "";
    
    // Generate CSRF token if not exists
    if(!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    if(isset($_POST['submit'])) {
        // Verify CSRF token
        if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error_message = "Erreur de validation du formulaire. Veuillez réessayer.";
        } else {
            $adminid = $_SESSION['imsaid'];
            $currentpassword = $_POST['currentpassword'];
            $newpassword = $_POST['newpassword'];
            $confirmpassword = $_POST['confirmpassword'];
            
            // Password complexity validation
            if(strlen($newpassword) < 8) {
                $error_message = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
            } else if($newpassword !== $confirmpassword) {
                $error_message = "Le nouveau mot de passe et sa confirmation ne correspondent pas.";
            } else {
                // Get current password from database
                $query = mysqli_query($con, "SELECT Password FROM tbladmin WHERE ID='$adminid'");
                $row = mysqli_fetch_array($query);
                $db_password = $row['Password'];
                
                // Check if the current password is correct
                $password_verified = false;
                
                // If password is still in MD5 format
                if(strlen($db_password) == 32) { // MD5 hash is 32 characters
                    $md5_current = md5($currentpassword);
                    if($md5_current == $db_password) {
                        $password_verified = true;
                    }
                } else {
                    // If password is already in newer format using password_hash
                    if(password_verify($currentpassword, $db_password)) {
                        $password_verified = true;
                    }
                }
                
                if($password_verified) {
                    // Hash the new password with password_hash
                    $hashed_password = password_hash($newpassword, PASSWORD_DEFAULT);
                    
                    // Update the password
                    $ret = mysqli_query($con, "UPDATE tbladmin SET Password='$hashed_password', password_changed_at=NOW() WHERE ID='$adminid'");
                    
                    if($ret) {
                        $success_message = "Votre mot de passe a été changé avec succès.";
                        
                        // Log password change activity
                        $activity = "Mot de passe modifié";
                        mysqli_query($con, "INSERT INTO activity_log (user_id, activity, ip_address, timestamp) 
                                         VALUES ('$adminid', '$activity', '".$_SERVER['REMOTE_ADDR']."', NOW())");
                    } else {
                        $error_message = "Erreur lors de la mise à jour du mot de passe.";
                    }
                } else {
                    $error_message = "Votre mot de passe actuel est incorrect.";
                }
            }
        }
    }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<title>Système de gestion d'inventaire || Changer le mot de passe</title>
<?php include_once('includes/cs.php');?>
<?php include_once('includes/responsive.php'); ?>
<style>
    .password-strength {
        margin-top: 5px;
        height: 5px;
        width: 100%;
        background-color: #eee;
        position: relative;
    }
    
    .password-strength-bar {
        height: 100%;
        width: 0;
        transition: width 0.3s ease;
    }
    
    .weak { background-color: #dc3545; }
    .medium { background-color: #ffc107; }
    .strong { background-color: #28a745; }
    
    .password-requirements {
        margin-top: 5px;
        font-size: 12px;
        color: #666;
    }
    
    .requirement {
        display: flex;
        align-items: center;
        margin-bottom: 3px;
    }
    
    .requirement-icon {
        margin-right: 5px;
        font-size: 10px;
    }
    
    .requirement-met .requirement-icon {
        color: #28a745;
    }
    
    .requirement-unmet .requirement-icon {
        color: #dc3545;
    }
    
    .alert {
        padding: 8px 15px;
        margin-bottom: 15px;
        border-radius: 4px;
    }
    
    .alert-danger {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .match-indicator {
        margin-top: 5px;
        font-size: 12px;
    }
    
    .match-yes {
        color: #28a745;
    }
    
    .match-no {
        color: #dc3545;
    }
</style>

<!--Header-part-->
<?php include_once('includes/header.php');?>
<?php include_once('includes/sidebar.php');?>

<div id="content">
<div id="content-header">
  <div id="breadcrumb"> <a href="dashboard.php" title="Aller à l'accueil" class="tip-bottom"><i class="icon-home"></i> Accueil</a> <a href="change-password.php" class="tip-bottom">Changer le mot de passe</a></div>
  <h1>Changer le mot de passe</h1>
</div>
<div class="container-fluid">
  <hr>
  <div class="row-fluid">
    <div class="span12">
      <div class="widget-box">
        <div class="widget-title"> <span class="icon"> <i class="icon-lock"></i> </span>
          <h5>Changer le mot de passe</h5>
        </div>
        <div class="widget-content nopadding">
          <?php if($error_message): ?>
          <div class="alert alert-danger">
            <i class="icon-warning-sign"></i> <?php echo $error_message; ?>
          </div>
          <?php endif; ?>
          
          <?php if($success_message): ?>
          <div class="alert alert-success">
            <i class="icon-ok"></i> <?php echo $success_message; ?>
          </div>
          <?php endif; ?>
          
          <form method="post" class="form-horizontal" name="changepassword" id="password-form">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="control-group">
              <label class="control-label">Mot de passe actuel :</label>
              <div class="controls">
                <input type="password" class="span11" name="currentpassword" id="currentpassword" required='true' />
              </div>
            </div>
            
            <div class="control-group">
              <label class="control-label">Nouveau mot de passe :</label>
              <div class="controls">
                <input type="password" class="span11" name="newpassword" id="newpassword" required='true' minlength="8" />
                <div class="password-strength">
                  <div class="password-strength-bar" id="password-strength-bar"></div>
                </div>
                <div class="password-requirements">
                  <div class="requirement" id="req-length">
                    <span class="requirement-icon">⬤</span> Au moins 8 caractères
                  </div>
                  <div class="requirement" id="req-lowercase">
                    <span class="requirement-icon">⬤</span> Au moins une lettre minuscule
                  </div>
                  <div class="requirement" id="req-uppercase">
                    <span class="requirement-icon">⬤</span> Au moins une lettre majuscule
                  </div>
                  <div class="requirement" id="req-number">
                    <span class="requirement-icon">⬤</span> Au moins un chiffre
                  </div>
                  <div class="requirement" id="req-special">
                    <span class="requirement-icon">⬤</span> Au moins un caractère spécial
                  </div>
                </div>
              </div>
            </div>
            
            <div class="control-group">
              <label class="control-label">Confirmer le mot de passe :</label>
              <div class="controls">
                <input type="password" class="span11" name="confirmpassword" id="confirmpassword" required='true' />
                <div class="match-indicator" id="match-indicator"></div>
              </div>
            </div>
            
            <div class="form-actions">
              <button type="submit" class="btn btn-success" name="submit" id="submit-button">Mettre à jour</button>
            </div>
          </form>
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
    // Password strength checker
    $('#newpassword').on('input', function(){
        var password = $(this).val();
        var strength = 0;
        var requirements = {
            length: password.length >= 8,
            lowercase: /[a-z]/.test(password),
            uppercase: /[A-Z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[^A-Za-z0-9]/.test(password)
        };
        
        // Update requirements indicators
        Object.keys(requirements).forEach(function(req){
            if(requirements[req]){
                $('#req-' + req).removeClass('requirement-unmet').addClass('requirement-met');
                strength++;
            } else {
                $('#req-' + req).removeClass('requirement-met').addClass('requirement-unmet');
            }
        });
        
        // Calculate percentage (0-5 requirements)
        var percentage = (strength / 5) * 100;
        
        // Update strength bar
        $('#password-strength-bar').css('width', percentage + '%');
        
        // Update strength class
        if(strength <= 2){
            $('#password-strength-bar').removeClass('medium strong').addClass('weak');
        } else if(strength <= 3){
            $('#password-strength-bar').removeClass('weak strong').addClass('medium');
        } else {
            $('#password-strength-bar').removeClass('weak medium').addClass('strong');
        }
        
        // Check if passwords match
        checkPasswordMatch();
    });
    
    // Password match checker
    $('#confirmpassword').on('input', function(){
        checkPasswordMatch();
    });
    
    function checkPasswordMatch(){
        var password = $('#newpassword').val();
        var confirmPassword = $('#confirmpassword').val();
        
        if(confirmPassword.length > 0){
            if(password === confirmPassword){
                $('#match-indicator').text('Les mots de passe correspondent').removeClass('match-no').addClass('match-yes');
            } else {
                $('#match-indicator').text('Les mots de passe ne correspondent pas').removeClass('match-yes').addClass('match-no');
            }
        } else {
            $('#match-indicator').text('');
        }
    }
    
    // Form submission validation
    $('#password-form').on('submit', function(e){
        var password = $('#newpassword').val();
        var confirmPassword = $('#confirmpassword').val();
        
        if(password.length < 8){
            e.preventDefault();
            alert('Le mot de passe doit comporter au moins 8 caractères.');
            return false;
        }
        
        if(password !== confirmPassword){
            e.preventDefault();
            alert('Les mots de passe ne correspondent pas.');
            return false;
        }
        
        return true;
    });
});
</script>
</body>
</html>
<?php } ?>