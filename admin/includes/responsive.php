  <!-- Optionally include additional responsive overrides -->
  <style>
    /* Example: Orientation overlay to prompt users to rotate device */
    /**************************************************************
 * CSS Variables & Base Styles
 **************************************************************/
:root {
  /* Color Palette */
  --primary-color: #28b779;
  --secondary-color: #2E363F;
  --accent-color: #41BEDD;
  --background-light: #eeeeee;
  --background-white: #ffffff;
  --text-color: #666;
  --text-light: #AAAAAA;
  --text-dark: #333;
  --border-color: #DDDDDD;
  
  /* Spacing */
  --spacing-xs: 4px;
  --spacing-sm: 8px;
  --spacing-md: 10px;
  --spacing-lg: 15px;
  --spacing-xl: 20px;
  
  /* Typography */
  --font-size-xs: 10px;
  --font-size-sm: 12px;
  --font-size-md: 14px;
  --font-size-lg: 16px;
  --font-size-xl: 18px;
  
  /* Layout */
  --sidebar-width-collapsed: 43px;
  --sidebar-width-expanded: 180px;
  
  /* Transitions */
  --transition-speed: 0.3s;
}

/* Reset & Global Styles */
html, body {
  height: 100%;
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  font-family: 'Open Sans', sans-serif;
  font-size: var(--font-size-sm);
  color: var(--text-color);
  overflow-x: hidden;
  line-height: 1.5;
}

*, *::before, *::after {
  box-sizing: inherit;
  outline: none !important;
}

a {
  color: var(--text-color);
  text-decoration: none;
  transition: color var(--transition-speed) ease;
}

a:hover, a:focus {
  color: var(--primary-color);
}

.container-fluid {
  width: 100%;
  padding: 0 var(--spacing-md);
}

/* Dropdowns & UI Elements */
.dropdown-menu {
  min-width: 180px;
  border-radius: 4px;
  box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.dropdown-menu > li > a {
  padding: var(--spacing-xs) var(--spacing-md);
  font-size: var(--font-size-sm);
}

/**************************************************************
 * Layout Components
 **************************************************************/
/* HEADER */
#header {
  width: 100%;
  position: relative;
  padding: var(--spacing-md);
  background: var(--background-white);
  box-shadow: 0 2px 5px rgba(0,0,0,0.05);
  display: flex;
  flex-direction: column;
  align-items: center;
}

#header h1 {
  background: url("../img/logo.png") no-repeat left center transparent;
  background-size: contain;
  max-width: 80%;
  height: 40px;
  margin: 0 auto;
  font-size: var(--font-size-xl);
  display: flex;
  align-items: center;
  padding-left: 45px;
}

/* SEARCH */
#search {
  position: relative;
  margin-top: var(--spacing-md);
  text-align: center;
  width: 100%;
}

#search input[type=text] {
  padding: var(--spacing-xs) var(--spacing-md);
  width: 80%;
  border: 1px solid var(--border-color);
  border-radius: 4px;
  transition: all var(--transition-speed) ease;
}

#search input[type=text]:focus {
  border-color: var(--accent-color);
  box-shadow: 0 0 5px rgba(65, 190, 221, 0.3);
}

/* Hamburger Menu For Mobile */
#my_menu_input {
  display: none;
  font-size: 24px;
  cursor: pointer;
  text-align: center;
  padding: var(--spacing-xs);
  border: none;
  background: transparent;
  color: var(--text-color);
}

/* TOP USER NAVIGATION */
#user-nav {
  width: 100%;
  background: var(--secondary-color);
  text-align: center;
  margin: 0;
  padding: var(--spacing-xs) 0;
}

#user-nav > ul {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
}

#user-nav > ul > li {
  border-bottom: 1px solid rgba(255,255,255,0.1);
}

#user-nav > ul > li:last-child {
  border-bottom: none;
}

#user-nav > ul > li > a {
  display: flex;
  align-items: center;
  padding: var(--spacing-xs) var(--spacing-md);
  font-size: var(--font-size-sm);
  color: #fff;
  transition: background-color var(--transition-speed) ease;
}

#user-nav > ul > li > a:hover {
  background-color: rgba(255,255,255,0.1);
}

#user-nav > ul > li > a > span.text {
  margin-left: var(--spacing-xs);
}

/* SIDEBAR */
#sidebar {
  width: 100%;
  background: var(--secondary-color);
  display: none;
  padding: var(--spacing-md);
  transition: all var(--transition-speed) ease;
}

#sidebar > ul {
  list-style: none;
  margin: 0;
  padding: 0;
}

#sidebar > ul > li {
  padding: var(--spacing-md);
  border-top: 1px solid var(--accent-color);
  color: var(--text-light);
  transition: background-color var(--transition-speed) ease;
}

#sidebar > ul > li:hover {
  background-color: rgba(255,255,255,0.05);
}

#sidebar > ul > li > a {
  color: var(--text-light);
  display: block;
  transition: color var(--transition-speed) ease;
}

#sidebar > ul > li > a:hover {
  color: #fff;
}

#sidebar > ul li ul {
  list-style: none;
  margin: var(--spacing-md) 0 0;
  padding: 0 0 0 var(--spacing-md);
}

#sidebar .content {
  padding: var(--spacing-xs);
}

/* Show sidebar when active */
#sidebar.active {
  display: block;
}

/* MAIN CONTENT */
#content {
  width: 100%;
  margin: 0;
  padding: var(--spacing-lg);
  background: var(--background-light);
  box-sizing: border-box;
  min-height: calc(100vh - 120px); /* Adjust based on header/footer */
  transition: margin var(--transition-speed) ease;
}

#content-header {
  text-align: center;
  margin-top: 0;
  margin-bottom: var(--spacing-lg);
}

#content-header h1 {
  font-size: var(--font-size-xl);
  padding-top: var(--spacing-lg);
  margin: 0;
  color: var(--text-dark);
}

/* Widget & Panel Styles */
.widget {
  background: var(--background-white);
  border-radius: 4px;
  margin-bottom: var(--spacing-lg);
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  overflow: hidden;
}

.widget-title {
  background: #f9f9f9;
  padding: var(--spacing-sm) var(--spacing-md);
  border-bottom: 1px solid var(--border-color);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.widget-content {
  padding: var(--spacing-md);
}

.panel-right {
  border-left: 1px solid var(--border-color);
  padding: var(--spacing-md);
}

/* Table & DataTables Styles */
.table {
  width: 100%;
  border-collapse: collapse;
}

.table th {
  padding: var(--spacing-xs) var(--spacing-xs);
  font-size: var(--font-size-xs);
  text-align: center;
  background: #f5f5f5;
}

.table td {
  padding: var(--spacing-xs);
  border-top: 1px solid var(--border-color);
}

.dataTables_wrapper .ui-widget-header {
  padding: var(--spacing-sm);
}

.dataTables_filter,
.dataTables_length {
  width: 100%;
  text-align: center;
  margin-bottom: var(--spacing-sm);
}

.dataTables_paginate {
  text-align: center;
  margin-top: var(--spacing-sm);
}

.dataTables_paginate .ui-button,
.pagination.alternate li a {
  padding: var(--spacing-xs) !important;
  font-size: var(--font-size-sm);
  margin: 0 2px;
  border-radius: 3px;
}

/**************************************************************
 * Responsive Breakpoints
 **************************************************************/
/* Mobile-Specific Adjustments (max-width: 480px) */
@media (max-width: 480px) {
  /* Show hamburger menu icon */
  #my_menu {
    display: none;
  }
  
  #my_menu_input {
    display: block;
  }
  
  /* Center header elements */
  #header {
    flex-direction: column;
    align-items: center;
  }
  
  #header h1 {
    margin-bottom: var(--spacing-md);
  }
  
  /* Stack user nav items */
  #user-nav > ul {
    flex-direction: column;
    text-align: left;
  }
  
  /* Ensure sidebar remains collapsed */
  #sidebar .content {
    display: none;
  }
  
  /* Content occupies full width */
  #content {
    margin-left: 0 !important;
  }
  
  /* Content header adjustments */
  #content-header .btn-group {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    margin: var(--spacing-md) auto;
  }
  
  /* DataTables tweaks */
  .dataTables_length {
    width: 100%;
    text-align: center;
  }
  
  /* Improve table readability on small screens */
  .table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
}

/* Small Tablets (481px to 767px) */
@media (min-width: 481px) and (max-width: 767px) {
  /* Layout tweaks */
  #search {
    margin-top: var(--spacing-sm);
    text-align: center;
  }
  
  #header {
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
  }
  
  #header h1 {
    margin-bottom: 0;
  }
  
  /* User nav adjustments */
  #user-nav > ul {
    flex-direction: row;
    justify-content: center;
  }
  
  #user-nav > ul > li {
    border-bottom: none;
    border-right: 1px solid rgba(255,255,255,0.1);
  }
  
  #user-nav > ul > li:last-child {
    border-right: none;
  }
  
  /* Panel adjustments */
  .panel-right {
    border-top: 1px solid var(--border-color);
    border-left: none;
  }
}

/* Medium Tablets (768px to 970px) */
@media (min-width: 768px) and (max-width: 970px) {
  body {
    background: var(--background-light);
  }
  
  /* Adjust header layout */
  #header {
    padding: var(--spacing-md) var(--spacing-xl);
    flex-direction: row;
    justify-content: space-between;
  }
  
  #header h1 {
    margin: 0;
  }
  
  /* Search repositioned */
  #search {
    position: relative;
    top: 0;
    width: 40%;
    text-align: right;
    margin-top: 0;
  }
  
  /* Collapsed Sidebar with hover effect */
  #sidebar {
    width: var(--sidebar-width-collapsed);
    display: block;
    position: fixed;
    height: 100%;
    z-index: 1;
    left: 0;
    top: 0;
    padding-top: 60px; /* Space for header */
  }
  
  #sidebar > ul {
    width: var(--sidebar-width-collapsed);
  }
  
  #sidebar > ul > li {
    text-align: center;
    position: relative;
    padding: var(--spacing-md) 0;
  }
  
  #sidebar > ul > li > a {
    padding: 0;
    text-align: center;
  }
  
  #sidebar > ul > li > a > span {
    display: none;
  }
  
  #sidebar > ul li ul {
    position: absolute;
    left: var(--sidebar-width-collapsed);
    top: 0;
    min-width: 150px;
    background: var(--secondary-color);
    display: none;
    border-radius: 0 4px 4px 0;
    box-shadow: 2px 0 5px rgba(0,0,0,0.1);
  }
  
  #sidebar > ul li:hover ul {
    display: block;
  }
  
  /* Content shifts to accommodate sidebar */
  #content {
    margin-left: var(--sidebar-width-collapsed);
  }
  
  /* User nav becomes horizontal */
  #user-nav {
    position: absolute;
    right: var(--spacing-xl);
    top: var(--spacing-sm);
    width: auto;
    background: transparent;
  }
  
  #user-nav > ul {
    flex-direction: row;
  }
  
  #user-nav > ul > li {
    border-bottom: none;
    margin-left: var(--spacing-sm);
  }
  
  #user-nav > ul > li > a {
    color: var(--text-color);
    padding: var(--spacing-xs);
  }
}

/* Larger Screens (971px and above) */
@media (min-width: 971px) {
  /* Layout for larger screens */
  #header {
    padding: var(--spacing-md) var(--spacing-xl);
    flex-direction: row;
    justify-content: space-between;
  }
  
  #header h1 {
    margin: 0;
    flex: 1;
  }
  
  /* Search repositioned */
  #search {
    position: relative;
    width: 30%;
    text-align: right;
    margin-top: 0;
  }
  
  /* Full sidebar */
  #sidebar {
    width: var(--sidebar-width-expanded);
    display: block;
    position: fixed;
    height: 100%;
    z-index: 1;
    left: 0;
    top: 0;
    padding-top: 60px; /* Space for header */
  }
  
  #sidebar > ul > li > a {
    display: flex;
    align-items: center;
  }
  
  #sidebar > ul > li > a > span {
    margin-left: var(--spacing-sm);
  }
  
  #sidebar .content {
    display: block;
  }
  
  /* Content shifts to accommodate sidebar */
  #content {
    margin-left: var(--sidebar-width-expanded);
  }
  
  /* User nav becomes horizontal */
  #user-nav {
    position: absolute;
    right: var(--spacing-xl);
    top: var(--spacing-sm);
    width: auto;
    background: transparent;
  }
  
  #user-nav > ul {
    flex-direction: row;
  }
  
  #user-nav > ul > li {
    border-bottom: none;
    margin-left: var(--spacing-sm);
  }
  
  #user-nav > ul > li > a {
    color: var(--text-color);
    padding: var(--spacing-xs);
  }
  
  /* DataTables improvements */
  .dataTables_filter {
    text-align: right;
    width: 50%;
    float: right;
  }
  
  .dataTables_length {
    text-align: left;
    width: 50%;
    float: left;
  }
  
  .dataTables_paginate {
    text-align: right;
  }
}

/* Print Styles */
@media print {
  #header, #user-nav, #sidebar, #search, .no-print {
    display: none !important;
  }
  
  #content {
    margin: 0 !important;
    padding: 0 !important;
  }
  
  a[href]:after {
    content: " (" attr(href) ")";
  }
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
  