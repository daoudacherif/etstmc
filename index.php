<!doctype html>
<html lang="fr">
<head>
    <!-- Meta tags de base -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title>Gesetstmc - Aluminium & Matériaux de Construction</title>
    <meta name="description"
          content="Italal est une entreprise spécialisée dans l'importation et la vente de matériaux de construction en aluminium en République de Guinée." />

    <!-- Inter UI font -->
    <link href="https://rsms.me/inter/inter-ui.css" rel="stylesheet">

    <!-- Styles externes (vendors) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" 
          integrity="sha512-Sf6f3hhO8mHWT8/98dA7jPL3BGwW3LM7MVGkZbf5A70q3av/HbhOcY9W1qVRFZpyJy8oAswc4fe8Z7nX3OKl4g==" 
          crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" 
          href="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.css" 
          integrity="sha512-WFwC4fWDawR+pRxoLLK+Rwh99eqQlsN/tuFUE/1B8HJ9fY1BbzD2ZVHVXylo0Jm/3KZH5Cb7EnnZ+f40kUjmw==" 
          crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" 
          href="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick-theme.min.css" 
          integrity="sha512-17EgCFQE6F75YM+tQvDy6WQ28EoFGU4PFGmdfruN8qOG3C+WP3C3e5S3erouUfygU7Eh7CKZB6P7FG4wM7pGug==" 
          crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Bootstrap CSS / Couleurs -->
    <!-- Remplace "css/default.css" par tes propres styles ou un autre thème si besoin -->
    <link rel="stylesheet" href="css/default.css" id="theme-color">
</head>

  <!-- CSS AVANCÉ -->
  <style>
    /* Reset & Base */
    * {
      margin: 0; padding: 0; box-sizing: border-box;
    }
    body {
      font-family: 'Poppins', sans-serif;
      color: #333;
      background-color: #f8f9fa;
      scroll-behavior: smooth;
    }
    a {
      text-decoration: none;
      color: inherit;
    }
    ul { list-style: none; }

    /* Header & Navigation */
    header {
      background: linear-gradient(135deg, #005f73, #0a9396);
      padding: 1rem 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 1000;
      box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    .navbar-brand {
      font-size: 1.5rem;
      font-weight: 600;
      color: #fff;
      transition: transform 0.3s ease;
    }
    .navbar-brand:hover {
      transform: scale(1.05);
    }
    nav ul {
      display: flex;
      gap: 1.5rem;
    }
    nav ul li a {
      color: #fff;
      font-weight: 500;
      padding: 0.5rem 0.8rem;
      transition: background-color 0.3s ease;
      border-radius: 4px;
    }
    nav ul li a:hover {
      background-color: rgba(255,255,255,0.2);
    }

    /* Hero Section */
    .hero {
      min-height: 90vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #0a9396, #94d2bd);
      position: relative;
      overflow: hidden;
      text-align: center;
      color: #fff;
    }
    .hero h1 {
      font-size: 2.5rem;
      font-weight: 600;
      margin-bottom: 1rem;
      animation: fadeInDown 1s ease forwards;
    }
    .hero p {
      font-size: 1.1rem;
      max-width: 600px;
      margin: 0 auto 2rem;
      animation: fadeInUp 1s ease forwards;
    }
    .hero .btn-hero {
      background-color: #ee9b00;
      color: #fff;
      padding: 0.8rem 1.5rem;
      font-size: 1rem;
      font-weight: 500;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      transition: background-color 0.3s ease;
    }
    .hero .btn-hero:hover {
      background-color: #ca6702;
    }

    /* Animations */
    @keyframes fadeInDown {
      0% { opacity: 0; transform: translateY(-20px); }
      100% { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeInUp {
      0% { opacity: 0; transform: translateY(20px); }
      100% { opacity: 1; transform: translateY(0); }
    }

    /* Container */
    .container {
      width: 100%;
      max-width: 1200px;
      margin: 0 auto;
      padding: 2rem;
    }

    /* Sections */
    section {
      padding: 3rem 2rem;
    }
    .section-title {
      text-align: center;
      margin-bottom: 2rem;
      font-size: 2rem;
      color: #005f73;
      position: relative;
      display: inline-block;
    }
    .section-title::after {
      content: "";
      display: block;
      width: 50px;
      height: 3px;
      background: #0a9396;
      margin: 0.5rem auto 0;
    }

    /* Features */
    .features {
      display: flex;
      flex-wrap: wrap;
      gap: 2rem;
      justify-content: center;
    }
    .feature-card {
      background-color: #fff;
      flex: 1 1 250px;
      max-width: 350px;
      text-align: center;
      border-radius: 8px;
      padding: 2rem 1rem;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .feature-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 6px 15px rgba(0,0,0,0.15);
    }
    .feature-card i {
      font-size: 2rem;
      color: #0a9396;
      margin-bottom: 1rem;
    }
    .feature-card h4 {
      margin-bottom: 1rem;
      font-weight: 600;
    }

    /* Pricing */
    .pricing-plans {
      display: flex;
      flex-wrap: wrap;
      gap: 2rem;
      justify-content: center;
    }
    .pricing-card {
      background-color: #fff;
      flex: 1 1 250px;
      max-width: 300px;
      text-align: center;
      border-radius: 8px;
      padding: 2rem 1rem;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .pricing-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 6px 15px rgba(0,0,0,0.15);
    }
    .pricing-card h4 {
      font-weight: 600;
      margin-bottom: 1rem;
    }
    .pricing-card .price {
      font-size: 2rem;
      margin: 1rem 0;
      color: #ee9b00;
    }
    .btn-pricing {
      background-color: #0a9396;
      color: #fff;
      padding: 0.6rem 1.2rem;
      border-radius: 4px;
      transition: background-color 0.3s ease;
      text-decoration: none;
      font-weight: 500;
    }
    .btn-pricing:hover {
      background-color: #0a8396;
    }

    /* Footer */
    footer {
      background: #005f73;
      color: #fff;
      text-align: center;
      padding: 2rem;
      margin-top: 2rem;
    }
    footer p {
      margin-bottom: 0.5rem;
      font-size: 0.95rem;
    }

    /* Responsivité basique */
    @media (max-width: 768px) {
      .features, .pricing-plans {
        flex-direction: column;
        align-items: center;
      }
      .feature-card, .pricing-card {
        max-width: 100%;
      }
    }
  </style>
<body>

<!-- Header -->
<header>
  <div class="navbar-brand">GESCOMETSTMC </div>
  <nav>
    <ul>
      <li><a href="#features">Caractéristiques</a></li>
      <li><a href="#pricing">Tarifs</a></li>
      <li><a href="#faq">FAQ</a></li>
      <li><a href="#blog">Blog</a></li>
      <li><a href="admin/login.php">connexion</a></li>
    </ul>
  </nav>
</header>

<!-- Hero Section -->
<section class="hero">
  <div>
    <h1>Matériaux en Aluminium pour la Guinée</h1>
    <p>
      Italal est une entreprise spécialisée dans l'importation et la vente 
      de matériaux de construction en aluminium. Bénéficiez de notre expertise 
      pour tous vos projets.
    </p>
    <button class="btn-hero">
      Découvrir nos offres
    </button>
  </div>
</section>

<!-- Features Section -->
<section id="features">
  <h2 class="section-title">Caractéristiques Clés</h2>
  <div class="container features">
    <div class="feature-card">
      <i class="fa fa-check"></i>
      <h4>Qualité Supérieure</h4>
      <p>Nos produits sont sélectionnés pour leur durabilité et leur résistance aux conditions climatiques locales.</p>
    </div>
    <div class="feature-card">
      <i class="fa fa-wrench"></i>
      <h4>Expertise Technique</h4>
      <p>Une équipe de professionnels à votre écoute pour vous conseiller et vous accompagner dans vos choix.</p>
    </div>
    <div class="feature-card">
      <i class="fa fa-truck"></i>
      <h4>Livraison Rapide</h4>
      <p>Nous assurons un acheminement efficace en République de Guinée pour répondre à vos besoins rapidement.</p>
    </div>
  </div>
</section>

<!-- Pricing Section -->
<section id="pricing" style="background: #f0f0f0;">
  <h2 class="section-title">Nos Tarifs</h2>
  <div class="container pricing-plans">
    <div class="pricing-card">
      <h4>Standard</h4>
      <p class="price">$$</p>
      <p>Profilés basiques, livraison standard, support commercial.</p>
      <a href="#" class="btn-pricing">Choisir</a>
    </div>
    <div class="pricing-card">
      <h4>Premium</h4>
      <p class="price">$$$</p>
      <p>Profilés haut de gamme, finitions personnalisées, livraison prioritaire.</p>
      <a href="#" class="btn-pricing">Choisir</a>
    </div>
    <div class="pricing-card">
      <h4>Sur Mesure</h4>
      <p class="price">$$$+</p>
      <p>Solutions entièrement personnalisées, support technique dédié.</p>
      <a href="#" class="btn-pricing">Choisir</a>
    </div>
  </div>
</section>

<!-- FAQ Section -->
<section id="faq">
  <h2 class="section-title">FAQ</h2>
  <div class="container">
    <div style="max-width: 800px; margin: 0 auto;">
      <h4>Comment passer commande ?</h4>
      <p>
        Vous pouvez nous contacter via le formulaire de contact ou par téléphone 
        pour discuter de vos besoins et obtenir un devis personnalisé.
      </p>
      <h4>Quels délais de livraison proposez-vous ?</h4>
      <p>
        Les délais varient selon la disponibilité des stocks et la localisation. 
        Nous faisons le maximum pour assurer une livraison rapide en Guinée.
      </p>
      <h4>Proposez-vous des finitions spécifiques ?</h4>
      <p>
        Oui, nous pouvons vous proposer diverses finitions (laquage, anodisation, etc.) 
        selon vos besoins.
      </p>
    </div>
  </div>
</section>

<!-- Blog Section (Exemple) -->
<section id="blog" style="background: #f0f0f0;">
  <h2 class="section-title">Blog</h2>
  <div class="container" style="display:flex; flex-wrap:wrap; gap:2rem; justify-content:center;">
    <!-- Article 1 -->
    <div style="background:#fff; width:300px; border-radius:8px; box-shadow:0 4px 10px rgba(0,0,0,0.1);">
      <img src="https://via.placeholder.com/300x150" alt="Aluminium" style="width:100%; border-radius:8px 8px 0 0;">
      <div style="padding:1rem;">
        <h5 style="margin-bottom:0.5rem;">L'aluminium, un allié durable</h5>
        <p style="font-size:0.9rem;">
          Découvrez pourquoi l'aluminium est un matériau de choix pour la construction 
          en Guinée.
        </p>
        <a href="#" style="color:#0a9396; font-weight:500;">Lire la suite</a>
      </div>
    </div>
    <!-- Article 2 -->
    <div style="background:#fff; width:300px; border-radius:8px; box-shadow:0 4px 10px rgba(0,0,0,0.1);">
      <img src="https://via.placeholder.com/300x150" alt="Profilés" style="width:100%; border-radius:8px 8px 0 0;">
      <div style="padding:1rem;">
        <h5 style="margin-bottom:0.5rem;">Bien choisir ses profilés</h5>
        <p style="font-size:0.9rem;">
          Quelques conseils pour sélectionner les bons profilés en aluminium 
          selon vos besoins.
        </p>
        <a href="#" style="color:#0a9396; font-weight:500;">Lire la suite</a>
      </div>
    </div>
  </div>
</section>

<!-- Footer -->
<footer>
  <p>&copy; 2023 Italal - Aluminium & Matériaux de Construction</p>
  <p>Tous droits réservés.</p>
</footer>

<!-- Activation des icônes Feather -->
<script>
  feather.replace();
</script>
</body>
</html>
