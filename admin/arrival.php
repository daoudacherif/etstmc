// Change text content in alerts
echo "<script>alert('$successCount arrivages de produits enregistrés avec succès! $errorCount ont eu des erreurs.');</script>";
echo "<script>alert('Erreur lors de l\'enregistrement des arrivages!');</script>";
echo "<script>alert('Aucun produit sélectionné!');</script>";
echo "<script>alert('Produit ajouté à la liste d\'arrivage!');</script>";
echo "<script>alert('Produit retiré de la liste d\'arrivage!');</script>";
echo "<script>alert('Liste d\'arrivage effacée!');</script>";

// Change page titles and headers
<title>Gestion des Stocks | Arrivages de Produits</title>
<h1>Gestion des Arrivages de Produits (Entrées Stock)</h1>

// Change breadcrumb
<a href="dashboard.php" class="tip-bottom"><i class="icon-home"></i> Accueil</a>
<a href="arrival.php" class="current">Arrivages de Produits</a>

// Change form labels and placeholders
<label>Rechercher Produits:</label>
<input type="text" name="searchTerm" class="span3" placeholder="Nom du produit..." list="productsList" />

// Change table headers
<th>Nom du Produit</th>
<th>Catégorie</th>
<th>Prix</th>
<th>Stock</th>
<th>Quantité</th>
<th>Ajouter à l'Arrivage</th>

// Change stock status labels
$stockStatus = '<span class="stock-status stock-danger">Rupture de stock</span>';
$stockStatus = '<span class="stock-status stock-warning">Faible</span>';
$stockStatus = '<span class="stock-status stock-ok">Disponible</span>';

// Change button text
<button type="submit" class="btn btn-primary">Rechercher</button>
<button type="submit" name="addtoarrival" class="btn btn-success btn-small">
  <i class="icon-plus"></i> Ajouter
</button>

// Change section titles
<h5>Arrivages de Produits en Attente</h5>
<a href="arrival.php?clear=1" class="btn btn-small btn-danger">
  <i class="icon-remove"></i> Tout Effacer
</a>

// Change form labels
<label class="control-label">Date d'Arrivage:</label>
<label class="control-label">Sélectionner Fournisseur:</label>
<option value="">-- Choisir Fournisseur --</option>

// Change empty state message
<td colspan="7" style="text-align:center;">Aucun arrivage en attente. Utilisez la recherche ci-dessus pour ajouter des produits.</td>

// Change submit button
<button type="submit" name="submit" class="btn btn-success btn-large">
  <i class="icon-check"></i> Enregistrer Tous les Arrivages
</button>

// Change quick add form title and labels
<h5>Ajout Rapide d'un Arrivage Unique</h5>
<label class="control-label">Sélectionner Produit:</label>
<option value="">-- Choisir Produit --</option>
<label class="control-label">Quantité:</label>
<label class="control-label">Coût Total (auto):</label>
<label class="control-label">Commentaires (optionnel):</label>
<input type="text" name="comments" placeholder="N° de facture, notes..." />

// Change recent arrivals section
<h5>Arrivages Récents</h5>
<th>Date d'Arrivage</th>
<th>Produit</th>
<th>Fournisseur</th>
<th>Qté</th>
<th>Prix Unitaire</th>
<th>Prix Total</th>
<th>Coût (Saisi)</th>
<th>Commentaires</th>
