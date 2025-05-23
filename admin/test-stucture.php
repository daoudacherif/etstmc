<?php
// test-structure.php - Placez ce fichier à la racine de votre projet et exécutez-le
session_start();
include('includes/dbconnection.php');

// Vérifier la connexion
if (!$con) {
    die("Erreur de connexion: " . mysqli_connect_error());
}

echo "<h2>Test de la structure de la base de données</h2>";

// 1. Vérifier la structure de tblcart
echo "<h3>Structure de tblcart:</h3>";
$result = mysqli_query($con, "SHOW COLUMNS FROM tblcart");
echo "<table border='1'>";
echo "<tr><th>Colonne</th><th>Type</th></tr>";
while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr><td>" . $row['Field'] . "</td><td>" . $row['Type'] . "</td></tr>";
}
echo "</table><br>";

// 2. Vérifier la structure de tblcreditcart
echo "<h3>Structure de tblcreditcart:</h3>";
$result = mysqli_query($con, "SHOW COLUMNS FROM tblcreditcart");
echo "<table border='1'>";
echo "<tr><th>Colonne</th><th>Type</th></tr>";
while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr><td>" . $row['Field'] . "</td><td>" . $row['Type'] . "</td></tr>";
}
echo "</table><br>";

// 3. Vérifier la structure de tblcustomer
echo "<h3>Structure de tblcustomer:</h3>";
$result = mysqli_query($con, "SHOW COLUMNS FROM tblcustomer");
echo "<table border='1'>";
echo "<tr><th>Colonne</th><th>Type</th></tr>";
while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr><td>" . $row['Field'] . "</td><td>" . $row['Type'] . "</td></tr>";
}
echo "</table><br>";

// 4. Test d'un numéro de facture
echo "<h3>Test avec un numéro de facture:</h3>";
$testQuery = "SELECT * FROM tblcustomer ORDER BY ID DESC LIMIT 1";
$testResult = mysqli_query($con, $testQuery);
if ($testRow = mysqli_fetch_assoc($testResult)) {
    echo "Numéro de facture test: <strong>" . $testRow['BillingNumber'] . "</strong><br>";
    echo "ID Client: <strong>" . $testRow['ID'] . "</strong><br>";
    echo "Nom: <strong>" . $testRow['CustomerName'] . "</strong><br><br>";
    
    // Chercher les produits de cette facture
    $customerID = $testRow['ID'];
    
    echo "<h4>Produits dans tblcart pour ce client (ID: $customerID):</h4>";
    $cartQuery = "SELECT c.*, p.ProductName 
                  FROM tblcart c
                  LEFT JOIN tblproducts p ON p.ID = c.ProductId
                  WHERE c.BillingId = '$customerID'";
    $cartResult = mysqli_query($con, $cartQuery);
    
    if (mysqli_num_rows($cartResult) > 0) {
        echo "<table border='1'>";
        $first = true;
        while ($row = mysqli_fetch_assoc($cartResult)) {
            if ($first) {
                echo "<tr>";
                foreach (array_keys($row) as $key) {
                    echo "<th>$key</th>";
                }
                echo "</tr>";
                $first = false;
            }
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>$value</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "Aucun produit trouvé dans tblcart.<br>";
    }
    
    echo "<h4>Produits dans tblcreditcart pour ce client (ID: $customerID):</h4>";
    $creditQuery = "SELECT cc.*, p.ProductName 
                    FROM tblcreditcart cc
                    LEFT JOIN tblproducts p ON p.ID = cc.ProductId
                    WHERE cc.BillingId = '$customerID'";
    $creditResult = mysqli_query($con, $creditQuery);
    
    if (mysqli_num_rows($creditResult) > 0) {
        echo "<table border='1'>";
        $first = true;
        while ($row = mysqli_fetch_assoc($creditResult)) {
            if ($first) {
                echo "<tr>";
                foreach (array_keys($row) as $key) {
                    echo "<th>$key</th>";
                }
                echo "</tr>";
                $first = false;
            }
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>$value</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "Aucun produit trouvé dans tblcreditcart.<br>";
    }
}

echo "<br><hr><p>Test terminé. Vérifiez les noms de colonnes ci-dessus.</p>";
?>