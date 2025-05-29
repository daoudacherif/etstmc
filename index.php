<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ETSTMC - Matériaux de Construction à Bailobaya</title>
    <base target="_self">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/@preline/preline@2.0.0/dist/preline.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#04BBFF',
                        'primary-dark': '#0594D0',
                        'secondary': '#007198',
                        'dark': '#003C58',
                        'darker': '#003C58',
                        'darkest': '#051C24',
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .hero-bg {
            background: linear-gradient(135deg, rgba(4, 187, 255, 0.1) 0%, rgba(5, 148, 208, 0.2) 100%);
        }
        .btn-primary {
            background-color: #04BBFF;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #0594D0;
            transform: translateY(-2px);
        }
        .dropdown:hover .dropdown-menu {
            display: block;
        }
        .dropdown-menu {
            transform: scale(0.95);
            opacity: 0;
            transition: all 0.1s ease-out;
            pointer-events: none;
        }
        .dropdown-menu.show {
            transform: scale(1);
            opacity: 1;
            pointer-events: auto;
        }
        
        /* Mobile dropdown animations */
        #mobile-shops-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        
        #mobile-shops-menu:not(.hidden) {
            max-height: 200px;
        }
        
        #mobile-shops-toggle i {
            transition: transform 0.3s ease;
        }
    </style>
</head>
<body class="font-sans">
    <!-- Header -->
    <header class="bg-white shadow-md sticky top-0 z-50">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full bg-primary flex items-center justify-center text-white font-bold text-xl mr-3">ET</div>
                <h1 class="text-2xl font-bold text-darkest">ETSTMC</h1>
            </div>
            <div class="hidden md:flex space-x-6 items-center">
                <a href="#accueil" class="text-dark hover:text-primary transition">Accueil</a>
                
                <div class="relative inline-block text-left">
                    <div>
                        <button type="button" class="inline-flex w-full justify-center gap-x-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs ring-1 ring-gray-300 ring-inset hover:bg-gray-50 transition-colors" id="menu-button" aria-expanded="false" aria-haspopup="true">
                            Options
                            <svg class="-mr-1 size-5 text-gray-400 transition-transform" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" data-slot="icon" id="dropdown-arrow">
                                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>

                    <div class="dropdown-menu absolute right-0 z-10 mt-2 w-56 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black/5 focus:outline-hidden" role="menu" aria-orientation="vertical" aria-labelledby="menu-button" tabindex="-1" id="dropdown-menu">
                        <div class="py-1" role="none">
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 transition-colors" role="menuitem" tabindex="-1">Paramètres du compte</a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 transition-colors" role="menuitem" tabindex="-1">Support</a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 transition-colors" role="menuitem" tabindex="-1">Licence</a>
                            <div class="border-t border-gray-100"></div>
                            <button type="button" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 transition-colors" role="menuitem" tabindex="-1">Se déconnecter</button>
                        </div>
                    </div>
                </div>
                
                <a href="#produits" class="text-dark hover:text-primary transition">Produits</a>
                <a href="#services" class="text-dark hover:text-primary transition">Services</a>
                <a href="#contact" class="text-dark hover:text-primary transition">Contact</a>
            </div>
            <div class="flex items-center space-x-4">
                <a href="admin/login.php" class="hidden md:block text-dark hover:text-primary transition" id="login-btn">
                    <i class="fas fa-user mr-1"></i>
                    Connexion
                </a>
                <a href="tel:+224621598780" class="bg-primary text-white px-4 py-2 rounded-lg flex items-center">
                    <i class="fas fa-phone mr-2"></i>
                    <span>621 59 87 80</span>
                </a>
                <button class="md:hidden text-darkest" id="menu-toggle">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- Mobile Menu -->
    <div class="hidden bg-white shadow-lg" id="mobile-menu">
        <div class="container mx-auto px-4 py-4 flex flex-col space-y-4">
            <a href="#accueil" class="text-dark hover:text-primary transition py-2">Accueil</a>
            
            <!-- Mobile Dropdown Magasins -->
            <div class="relative">
                <button class="flex items-center justify-between w-full text-dark hover:text-primary transition py-2" id="mobile-shops-toggle">
                    <span>Magasins</span>
                    <i class="fas fa-chevron-down text-xs"></i>
                </button>
                <div class="hidden pl-4 mt-2 space-y-2" id="mobile-shops-menu">
                    <a href="https://second.etstmc.com/admin/login.php" class="block text-dark hover:text-primary transition py-1">Bailobaya</a>
                    <a href="#" class="block text-dark hover:text-primary transition py-1">Matam</a>
                </div>
            </div>
            
             <button class="flex items-center justify-between w-full text-dark hover:text-primary transition py-2" id="mobile-shops-toggle">
                    <span>Magasins</span>
                    <i class="fas fa-chevron-down text-xs"></i>
                </button>
                <div class="hidden pl-4 mt-2 space-y-2" id="mobile-shops-menu">
                    <a href="https://second.etstmc.com/admin/login.php" class="block text-dark hover:text-primary transition py-1">Bailobaya</a>
                    <a href="#" class="block text-dark hover:text-primary transition py-1">Matam</a>
                </div>
            <a href="#" class="text-dark hover:text-primary transition py-2">Connexion</a>
        </div>
    </div>

    <!-- Hero Section -->
    <section id="accueil" class="hero-bg py-16 md:py-24">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row items-center">
                <div class="md:w-1/2 mb-12 md:mb-0">
                    <h1 class="text-4xl md:text-5xl font-bold text-darkest mb-6">Votre fournisseur de matériaux de construction à Bailobaya</h1>
                    <p class="text-lg text-secondary mb-8">ETSTMC vous propose les meilleurs matériaux de construction pour tous vos projets, avec un service personnalisé et des prix compétitifs.</p>
                    <div class="flex flex-wrap gap-4">
                        <a href="#produits" class="btn-primary text-white px-8 py-3 rounded-lg font-medium shadow-lg">Nos produits</a>
                        <a href="#contact" class="border-2 border-primary text-primary px-8 py-3 rounded-lg font-medium hover:bg-primary hover:text-white transition">Contactez-nous</a>
                    </div>
                </div>
                <div class="md:w-1/2">
                    <img src="https://images.unsplash.com/photo-1600585152220-9035925d0d0f?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=800&q=80" 
                         alt="Matériaux de construction" 
                         class="rounded-lg shadow-xl w-full">
                </div>
            </div>
        </div>
    </section>

    <!-- Produits Section -->
    <section id="produits" class="py-16 bg-white">
        <div class="container mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-darkest mb-4">Nos Produits</h2>
                <p class="text-secondary max-w-2xl mx-auto">Découvrez notre large gamme de matériaux de construction de qualité</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Produit 1 -->
                <div class="bg-gray-50 rounded-lg overflow-hidden shadow-md hover:shadow-xl transition-shadow">
                    <div class="h-48 bg-primary flex items-center justify-center">
                        <i class="fas fa-cubes text-white text-6xl"></i>
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-bold mb-2 text-darkest">Ciments & Mortiers</h3>
                        <p class="text-secondary mb-4">Tous types de ciments et mortiers pour vos travaux de maçonnerie.</p>
                        <a href="#contact" class="text-primary font-medium hover:underline">Demander un devis</a>
                    </div>
                </div>
                
                <!-- Produit 2 -->
                <div class="bg-gray-50 rounded-lg overflow-hidden shadow-md hover:shadow-xl transition-shadow">
                    <div class="h-48 bg-primary flex items-center justify-center">
                        <i class="fas fa-bricks text-white text-6xl"></i>
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-bold mb-2 text-darkest">Briques & Parpaings</h3>
                        <p class="text-secondary mb-4">Large choix de briques et parpaings pour vos constructions.</p>
                        <a href="#contact" class="text-primary font-medium hover:underline">Demander un devis</a>
                    </div>
                </div>
                
                <!-- Produit 3 -->
                <div class="bg-gray-50 rounded-lg overflow-hidden shadow-md hover:shadow-xl transition-shadow">
                    <div class="h-48 bg-primary flex items-center justify-center">
                        <i class="fas fa-trowel text-white text-6xl"></i>
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-bold mb-2 text-darkest">Carrelages</h3>
                        <p class="text-secondary mb-4">Carrelages de qualité pour sols et murs, divers styles et coloris.</p>
                        <a href="#contact" class="text-primary font-medium hover:underline">Demander un devis</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="py-16 bg-gray-50">
        <div class="container mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-darkest mb-4">Nos Services</h2>
                <p class="text-secondary max-w-2xl mx-auto">Nous offrons des services complets pour vos projets de construction</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white p-8 rounded-lg shadow-md text-center">
                    <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-truck text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-darkest">Livraison sur site</h3>
                    <p class="text-secondary">Livraison rapide de vos matériaux directement sur votre chantier à Bailobaya et environs.</p>
                </div>
                
                <div class="bg-white p-8 rounded-lg shadow-md text-center">
                    <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-calculator text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-darkest">Conseils techniques</h3>
                    <p class="text-secondary">Nos experts vous conseillent pour choisir les meilleurs matériaux pour votre projet.</p>
                </div>
                
                <div class="bg-white p-8 rounded-lg shadow-md text-center">
                    <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-percentage text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-3 text-darkest">Prix compétitifs</h3>
                    <p class="text-secondary">Nous proposons les meilleurs prix pour les professionnels et particuliers.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-16 bg-primary text-white">
        <div class="container mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold mb-4">Contactez-nous</h2>
                <p class="max-w-2xl mx-auto">Besoin de matériaux de construction ? Contactez-nous dès maintenant.</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                <div>
                    <h3 class="text-xl font-bold mb-4">Coordonnées</h3>
                    <div class="space-y-4">
                        <div class="flex items-start">
                            <i class="fas fa-map-marker-alt mr-3 mt-1"></i>
                            <div>
                                <p class="font-medium">Adresse</p>
                                <p>Bailobaya, Conakry, Guinée</p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-phone mr-3 mt-1"></i>
                            <div>
                                <p class="font-medium">Téléphone</p>
                                <p>621 59 87 80</p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-clock mr-3 mt-1"></i>
                            <div>
                                <p class="font-medium">Horaires</p>
                                <p>Lundi - Samedi: 8h - 18h</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div>
                    <form class="space-y-4">
                        <div>
                            <label for="name" class="block mb-1">Nom</label>
                            <input type="text" id="name" class="w-full px-4 py-2 rounded-lg bg-white/20 border border-white/30 focus:outline-none focus:ring-2 focus:ring-white">
                        </div>
                        <div>
                            <label for="phone" class="block mb-1">Téléphone</label>
                            <input type="tel" id="phone" class="w-full px-4 py-2 rounded-lg bg-white/20 border border-white/30 focus:outline-none focus:ring-2 focus:ring-white">
                        </div>
                        <div>
                            <label for="message" class="block mb-1">Message</label>
                            <textarea id="message" rows="4" class="w-full px-4 py-2 rounded-lg bg-white/20 border border-white/30 focus:outline-none focus:ring-2 focus:ring-white"></textarea>
                        </div>
                        <button type="submit" class="bg-white text-primary px-6 py-3 rounded-lg font-bold hover:bg-gray-100 transition">Envoyer</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-darkest text-white py-12">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white font-bold text-lg mr-3">ET</div>
                        <h3 class="text-xl font-bold">ETSTMC</h3>
                    </div>
                    <p class="text-gray-400">Votre fournisseur de matériaux de construction à Bailobaya, Guinée.</p>
                </div>
                
                <div>
                    <h3 class="text-lg font-bold mb-4">Liens rapides</h3>
                    <ul class="space-y-2">
                        <li><a href="#accueil" class="text-gray-400 hover:text-white transition">Accueil</a></li>
                        <li><a href="#produits" class="text-gray-400 hover:text-white transition">Produits</a></li>
                        <li><a href="#services" class="text-gray-400 hover:text-white transition">Services</a></li>
                        <li><a href="#contact" class="text-gray-400 hover:text-white transition">Contact</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-lg font-bold mb-4">Contact</h3>
                    <p class="text-gray-400 mb-2">Bailobaya, Conakry, Guinée</p>
                    <p class="text-gray-400 mb-2">621 59 87 80</p>
                    <p class="text-gray-400">Lundi - Samedi: 8h - 18h</p>
                </div>
            </div>
            
            <div class="border-t border-gray-800 mt-12 pt-8 text-center text-gray-400">
                <p>&copy; 2023 ETSTMC. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <script>
        // Dropdown functionality
        const dropdownButton = document.getElementById('menu-button');
        const dropdownMenu = document.getElementById('dropdown-menu');
        const dropdownArrow = document.getElementById('dropdown-arrow');

        function toggleDropdown() {
            const isOpen = dropdownMenu.classList.contains('show');
            
            if (isOpen) {
                closeDropdown();
            } else {
                openDropdown();
            }
        }

        function openDropdown() {
            dropdownMenu.classList.add('show');
            dropdownButton.setAttribute('aria-expanded', 'true');
            dropdownArrow.style.transform = 'rotate(180deg)';
        }

        function closeDropdown() {
            dropdownMenu.classList.remove('show');
            dropdownButton.setAttribute('aria-expanded', 'false');
            dropdownArrow.style.transform = 'rotate(0deg)';
        }

        // Toggle dropdown on button click
        dropdownButton.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleDropdown();
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!dropdownButton.contains(e.target) && !dropdownMenu.contains(e.target)) {
                closeDropdown();
            }
        });

        // Close dropdown when pressing Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDropdown();
            }
        });

        // Handle dropdown menu item clicks
        dropdownMenu.addEventListener('click', function(e) {
            if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON') {
                closeDropdown();
            }
        });

        // Mobile menu elements
        const mobileMenu = document.getElementById('mobile-menu');
        const menuToggle = document.getElementById('menu-toggle');
        const mobileShopsToggle = document.getElementById('mobile-shops-toggle');
        const mobileShopsMenu = document.getElementById('mobile-shops-menu');
        const mobileChevron = mobileShopsToggle.querySelector('i');

        // Mobile menu toggle
        menuToggle.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
        });
        
        // Close mobile menu when clicking on links
        mobileMenu.addEventListener('click', function(e) {
            if (e.target.tagName === 'A' && e.target.getAttribute('href').startsWith('#')) {
                mobileMenu.classList.add('hidden');
                // Also close the shops submenu
                mobileShopsMenu.classList.add('hidden');
                mobileChevron.style.transform = 'rotate(0deg)';
            }
        });

        // Mobile shops menu toggle
        mobileShopsToggle.addEventListener('click', function() {
            const isHidden = mobileShopsMenu.classList.contains('hidden');
            
            if (isHidden) {
                mobileShopsMenu.classList.remove('hidden');
                mobileChevron.style.transform = 'rotate(180deg)';
            } else {
                mobileShopsMenu.classList.add('hidden');
                mobileChevron.style.transform = 'rotate(0deg)';
            }
        });

        // Smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Form submission
        document.querySelector('form').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Merci pour votre message ! Nous vous contacterons bientôt.');
            this.reset();
        });

        // Login button
        document.getElementById('login-btn').addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = 'admin/login.php';
        });
    </script>
</body>
</html>