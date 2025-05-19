  <!-- Optionally include additional responsive overrides -->
  <style>
    /* Example: Orientation overlay to prompt users to rotate device */
    #rotate-overlay {
      display: none;
      position: fixed;
      z-index: 9999;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.8);
      color: #fff;
      text-align: center;
      padding-top: 40%;
      font-size: 18px;
    }
    /* Show overlay when device is in portrait */
    @media screen and (orientation: portrait) {
      #rotate-overlay {
        display: block;
      }
      /* Optionally, hide main app container if you want to force landscape */
      #app-container {
        display: none;
      }
    }
    /* Landscape - hide overlay and show app */
    @media screen and (orientation: landscape) {
      #rotate-overlay {
        display: none;
      }
      #app-container {
        display: block;
      }
    }
    .control-label{
      font-size: 15px;
      font-weight: bold;
      color: black;
    }
   /* Fixed Header Styles */
#header {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  z-index: 1001;
  background: #2E363F; /* Match your header background */
}

#user-nav {
  position: fixed;
  top: 60px; /* Adjust based on your #header height */
  left: 0;
  width: 100%;
  z-index: 1000;
  background: #2E363F; /* Match your navbar background */
}

/* Adjust main content to prevent overlap with fixed header */
#content {
  margin-top: 120px; /* Adjust based on combined height of header + user-nav */
}

/* If you have a sidebar, adjust it too */
#sidebar {
  position: fixed;
  top: 120px; /* Same as content margin-top */
  left: 0;
  height: calc(100vh - 120px); /* Viewport height minus header height */
  width: 250px; /* Adjust as needed */
  overflow-y: auto;
  z-index: 999;
  background: #2E363F;
}

/* If you have a sidebar, adjust the main content area */
body:has(#sidebar) #content {
  margin-left: 250px; /* Should match sidebar width */
}
    /* The rest of your responsive CSS code (as provided in the previous integration)
       can either be here or in your external responsive.css file included via cs.php */
  </style>
  </head>
<body>
     <!-- Orientation overlay -->
  <div id="rotate-overlay">
    <p>Pour une meilleure expérience, veuillez tourner votre appareil.</p>
  </div>
  