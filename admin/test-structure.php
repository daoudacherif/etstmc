<?php
// test-billing-system.php - Script pour vérifier le système de facturation
session_start();
include('includes/dbconnection.php');

echo "<h2>Vérification du Système de Facturation</h2>";

// 1. Afficher quelques factures récentes
echo "<h3>Dernières factures créées:</h3>";
$recentBills = mysqli_query($con, "
    SELECT c.BillingNumber, c.CustomerName, c.BillingDate, c.FinalAmount,
           COUNT(cart.ProductId) as NbProduits
    FROM tblcustomer c
    LEFT JOIN tblcart cart ON cart.BillingId = c.BillingNumber AND cart.IsCheckOut = 1
    GROUP BY c.BillingNumber
    ORDER BY c.ID DESC
    LIMIT 10
");

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Numéro de Facture</th><th>Client</th><th>Date</th><th>Montant</th><th>Nb Produits</th></tr>";
while ($row = mysqli_fetch_assoc($recentBills)) {
    $highlight = $row['NbProduits'] > 0 ? 'style="background-color: #90EE90;"' : '';
    echo "<tr $highlight>";
    echo "<td><strong>" . $row['BillingNumber'] . "</strong></td>";
    echo "<td>" . $row['CustomerName'] . "</td>";
    echo "<td>" . $row['BillingDate'] . "</td>";
    echo "<td>" . number_format($row['FinalAmount'], 2) . " GNF</td>";
    echo "<td>" . $row['NbProduits'] . "</td>";
    echo "</tr>";
}
echo "</table>";
echo "<p><em>Les lignes en vert ont des produits associés</em></p>";

// 2. Vérifier la structure de BillingId dans tblcart
echo "<h3>Vérification de la structure BillingId:</h3>";
$sampleCart = mysqli_query($con, "
    SELECT BillingId, ProductId, ProductQty, IsCheckOut 
    FROM tblcart 
    WHERE IsCheckOut = 1 
    LIMIT 5
");

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>BillingId</th><th>ProductId</th><th>Quantité</th><th>IsCheckOut</th></tr>";
while ($row = mysqli_fetch_assoc($sampleCart)) {
    echo "<tr>";
    echo "<td>" . $row['BillingId'] . "</td>";
    echo "<td>" . $row['ProductId'] . "</td>";
    echo "<td>" . $row['ProductQty'] . "</td>";
    echo "<td>" . $row['IsCheckOut'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// 3. Exemple complet d'une facture avec produits
echo "<h3>Exemple détaillé d'une facture avec produits:</h3>";
$exampleQuery = mysqli_query($con, "
    SELECT c.BillingNumber, c.CustomerName, cart.ProductId, p.ProductName, 
           cart.ProductQty, cart.Price
    FROM tblcustomer c
    JOIN tblcart cart ON cart.BillingId = c.BillingNumber
    JOIN tblproducts p ON p.ID = cart.ProductId
    WHERE cart.IsCheckOut = 1
    GROUP BY c.BillingNumber
    LIMIT 1
");

if (mysqli_num_rows($exampleQuery) > 0) {
    $example = mysqli_fetch_assoc($exampleQuery);
    $billNum = $example['BillingNumber'];
    
    echo "<div style='background-color: #FFFFE0; padding: 10px; border: 2px solid #FFD700;'>";
    echo "<h4>Facture à tester: <span style='color: red;'>" . $billNum . "</span></h4>";
    echo "<p>Client: " . $example['CustomerName'] . "</p>";
    
    // Afficher tous les produits de cette facture
    $productsQuery = mysqli_query($con, "
        SELECT p.ProductName, cart.ProductQty, cart.Price
        FROM tblcart cart
        JOIN tblproducts p ON p.ID = cart.ProductId
        WHERE cart.BillingId = '$billNum' AND cart.IsCheckOut = 1
    ");
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Produit</th><th>Quantité</th><th>Prix</th></tr>";
    while ($prod = mysqli_fetch_assoc($productsQuery)) {
        echo "<tr>";
        echo "<td>" . $prod['ProductName'] . "</td>";
        echo "<td>" . $prod['ProductQty'] . "</td>";
        echo "<td>" . number_format($prod['Price'], 2) . " GNF</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
    
    echo "<br><p style='font-size: 18px; color: green;'><strong>✓ Utilisez le numéro de facture <span style='background-color: yellow;'>" . $billNum . "</span> pour tester le système de retour</strong></p>";
} else {
    echo "<p style='color: red;'>Aucune facture avec des produits trouvée!</p>";
}

// 4. Vérifier les retours existants
echo "<h3>Retours existants:</h3>";
$returnsQuery = mysqli_query($con, "
    SELECT r.BillingNumber, r.ProductID, p.ProductName, r.Quantity, r.ReturnDate
    FROM tblreturns r
    LEFT JOIN tblproducts p ON p.ID = r.ProductID
    ORDER BY r.ID DESC
    LIMIT 5
");

if (mysqli_num_rows($returnsQuery) > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Numéro Facture</th><th>Produit</th><th>Qté Retournée</th><th>Date</th></tr>";
    while ($row = mysqli_fetch_assoc($returnsQuery)) {
        echo "<tr>";
        echo "<td>" . $row['BillingNumber'] . "</td>";
        echo "<td>" . $row['ProductName'] . "</td>";
        echo "<td>" . $row['Quantity'] . "</td>";
        echo "<td>" . $row['ReturnDate'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Aucun retour trouvé.</p>";
}

echo "<hr>";
echo "<p><strong>Conclusion:</strong> Dans votre système, BillingId dans tblcart contient le BillingNumber (pas l'ID du customer).</p>";
?>