<div class="row-fluid">
  <div id="footer" class="span12"> Systeme de gestion de ETSTMC </div>
</div>

<!-- JavaScript for responsive functionality -->
<script>
  // Mobile sidebar toggle functionality
  document.getElementById('mobile-nav-toggle').addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('active');
    document.body.classList.toggle('sidebar-active');
  });

  // Close sidebar when clicking outside of it (on mobile)
  document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const mobileToggle = document.getElementById('mobile-nav-toggle');
    
    if (window.innerWidth <= 767 && 
        sidebar.classList.contains('active') && 
        !sidebar.contains(event.target) && 
        event.target !== mobileToggle) {
      sidebar.classList.remove('active');
      document.body.classList.remove('sidebar-active');
    }
  });

  // Responsive sidebar behavior based on window size
  window.addEventListener('resize', function() {
    const sidebar = document.getElementById('sidebar');
    
    if (window.innerWidth > 767 && sidebar.classList.contains('active')) {
      sidebar.classList.remove('active');
      document.body.classList.remove('sidebar-active');
    }
  });

  // Add year dynamically to the footer
  document.addEventListener('DOMContentLoaded', function() {
    const footerYear = document.getElementById('footer');
    const currentYear = new Date().getFullYear();
    
    if (footerYear) {
      footerYear.innerHTML = currentYear + ' &copy; Inventory Management System';
    }
  });
</script>