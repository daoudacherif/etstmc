<script src="js/excanvas.min.js"></script> 
<script src="js/jquery.min.js"></script> 
<script src="js/jquery.ui.custom.js"></script> 
<script src="js/bootstrap.min.js"></script> 
<script src="js/jquery.flot.min.js"></script> 
<script src="js/jquery.flot.resize.min.js"></script> 
<script src="js/jquery.peity.min.js"></script> 
<script src="js/fullcalendar.min.js"></script> 
<script src="js/matrix.js"></script> 
<script src="js/matrix.dashboard.js"></script> 
<script src="js/jquery.gritter.min.js"></script> 
<script src="js/matrix.interface.js"></script> 
<script src="js/matrix.chat.js"></script> 
<script src="js/jquery.validate.js"></script> 
<script src="js/matrix.form_validation.js"></script> 
<script src="js/jquery.wizard.js"></script> 
<script src="js/jquery.uniform.js"></script> 
<script src="js/select2.min.js"></script> 
<script src="js/matrix.popover.js"></script> 
<script src="js/jquery.dataTables.min.js"></script> 
<script src="js/matrix.tables.js"></script> 

<script type="text/javascript">
  // This function is called from the pop-up menus to transfer to
  // a different page. Ignore if the value returned is a null string:
  function goPage (newURL) {

      // if url is empty, skip the menu dividers and reset the menu selection to default
      if (newURL != "") {
      
          // if url is "-", it is this page -- reset the menu:
          if (newURL == "-" ) {
              resetMenu();            
          } 
          // else, send page to designated URL            
          else {  
            document.location.href = newURL;
          }
      }
  }

// resets the menu selection upon entry to this page:
function resetMenu() {
   document.gomenu.selector.selectedIndex = 2;
}
document.getElementById('my_menu_input').addEventListener('click', function () {
  var nav = document.getElementById('user-nav');
  nav.classList.toggle('active');
});
document.addEventListener('click', function (event) {
  var nav = document.getElementById('user-nav');
  if (!nav.contains(event.target)) {
    nav.classList.remove('active');
  }
});
</script>
<!-- Script pour gérer le menu sidebar accordéon -->
<script type="text/javascript">
$(document).ready(function() {
  // Gestionnaire pour les éléments .submenu
  $('.submenu > a').on('click', function(e) {
    e.preventDefault(); // Empêcher le comportement par défaut qui ajoute #
    
    var $li = $(this).parent('li');
    var $ul = $(this).next('ul');
    
    if ($li.hasClass('open')) {
      $ul.slideUp(350);
      $li.removeClass('open');
    } else {
      // Fermer les autres sous-menus ouverts (optionnel)
      $('.submenu > ul').slideUp(350);
      $('.submenu').removeClass('open');
      
      // Ouvrir ce sous-menu
      $ul.slideDown(350);
      $li.addClass('open');
    }
  });
  
  // Empêcher la navigation vers # pour les liens de sous-menu parents
  $('.submenu > a').click(function(e) {
    // Déjà géré ci-dessus
  });
  
  // Permettre à tous les autres liens de fonctionner normalement
  $('#sidebar a:not(.submenu > a)').click(function(e) {
    // Laisser le comportement par défaut pour ces liens
  });
});
</script>