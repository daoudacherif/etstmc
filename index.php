<!doctype html>
<html lang="fr">
<head>
    <!-- Meta tags de base -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title>Gescometstmc - Aluminium & Matériaux de Construction</title>
    <meta name="description"
          content="Gescometstmc est une entreprise spécialisée dans l'importation et la vente de matériaux de construction en aluminium en République de Guinée." />

    <!-- Inter UI font -->
    <link href="https://rsms.me/inter/inter-ui.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" />

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

    <style>
        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', sans-serif;
            color: #333;
            background-color: #f8f9fa;
            overflow-x: hidden;
        }

        header {
            background: linear-gradient(135deg, #005f73, #0a9396);
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .navbar-brand {
            color: #fff;
            font-weight: 700;
            transition: transform 0.3s ease;
        }

        .navbar-brand:hover { transform: scale(1.1); }

        .nav-link { color:  #0a9396 !important; }

        .hero {
            min-height: 90vh;
            background: linear-gradient(135deg, #0a9396, #94d2bd);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            text-align: center;
            padding: 0 1rem;
        }

        .hero h1, .hero p, .hero button {
            opacity: 0;
            animation: fadeIn 1s forwards;
        }

        .hero h1 { animation-delay: 0.3s; }
        .hero p { animation-delay: 0.6s; }
        .hero button { animation-delay: 0.9s; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .btn-hero, .btn-pricing {
            background-color: #ee9b00;
            color: #fff;
            transition: background-color 0.3s ease;
        }

        .btn-hero:hover, .btn-pricing:hover { background-color: #ca6702; }

   /* From Uiverse.io by barisdogansutcu */ 
/* From Uiverse.io by elijahgummer */ 
button {
  font: inherit;
  background-color: #f0f0f0;
  border: 0;
  color: #242424;
  border-radius: 0.5em;
  font-size: 1.35rem;
  padding: 0.375em 1em;
  font-weight: 600;
  text-shadow: 0 0.0625em 0 #fff;
  box-shadow: inset 0 0.0625em 0 0 #f4f4f4, 0 0.0625em 0 0 #efefef,
    0 0.125em 0 0 #ececec, 0 0.25em 0 0 #e0e0e0, 0 0.3125em 0 0 #dedede,
    0 0.375em 0 0 #dcdcdc, 0 0.425em 0 0 #cacaca, 0 0.425em 0.5em 0 #cecece;
  transition: 0.15s ease;
  cursor: pointer;
}
button:active {
  translate: 0 0.225em;
  box-shadow: inset 0 0.03em 0 0 #f4f4f4, 0 0.03em 0 0 #efefef,
    0 0.0625em 0 0 #ececec, 0 0.125em 0 0 #e0e0e0, 0 0.125em 0 0 #dedede,
    0 0.2em 0 0 #dcdcdc, 0 0.225em 0 0 #cacaca, 0 0.225em 0.375em 0 #cecece;
}


    </style>
</head>

<body>

<!-- Header -->
<header>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">GESCOMETSTMC</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item "><a class="nav-link button" href="admin/login.php">Connexion</a></li>
                </ul>
            </div>
        </div>
    </nav>
</header>

<!-- Hero Section -->
<section class="hero">
    <h1>Matériaux en Aluminium pour la Guinée</h1>
    <p>Gescometstmc est votre partenaire idéal pour l'importation et la vente de matériaux de construction en aluminium.</p>
    <button class="btn btn-hero">Découvrir nos offres</button>
</section>

<!-- Include Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
