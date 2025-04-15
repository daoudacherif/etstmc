<?php
// This file contains CSS media queries and JavaScript
// tweaks for mobile responsiveness.
?>
<!-- Responsive CSS -->
<style>
  /* Mobile responsiveness for screen sizes up to 767px */
  @media only screen and (max-width: 767px) {
    /* Make the sidebar full width, and hide it by default */
    .sidebar {
      width: 100%;
      height: auto;
      position: relative;
      display: none;
    }
    /* When active, the sidebar will be displayed */
    .sidebar.active {
      display: block;
    }
    /* Remove left margin from the main content area */
    #content {
      margin-left: 0;
      padding: 10px;
    }
    /* Show the menu toggle button */
    .menu-toggle {
      display: block;
    }
    /* Stack wrapper children vertically */
    .wrapper {
      flex-direction: column;
    }
  }
</style>

<!-- Responsive JavaScript -->
<script>
  // Ensure the DOM is loaded before attaching listeners
  document.addEventListener('DOMContentLoaded', function() {
    // Check if the menu toggle element exists on the page
    const menuToggle = document.querySelector('.menu-toggle');
    if (menuToggle) {
      menuToggle.addEventListener('click', function() {
        // Toggle the "active" class on the sidebar to show/hide it
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) {
          sidebar.classList.toggle('active');
        }
      });
    }
  });
</script>
