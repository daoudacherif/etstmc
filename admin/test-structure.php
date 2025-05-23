<?php
// Script de test pour débugger les problèmes de validation de facture
// Placez ce fichier dans votre dossier racine et appelez-le via test-billing.php?billing=NUMERO_FACTURE

session_start();
include('includes/dbconnection.php');

// Vérification simple de la session (pour les tests)
if (!isset($_SESSION['imsaid'])) {
    echo "<div style='color: red;'>⚠️ Session admin non définie. Connectez-vous d'abord.</div>";
}

$billingNumber = isset($_GET['billing']) ? trim($_GET['billing']) : '';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test de Validation de Facture</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: blue; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { color: orange; background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>🔍 Test de Validation de Facture</h1>
    
    <form method="GET">
        <label>Numéro de facture à tester :</label>
        <input type="text" name="billing" value="<?php echo htmlspecialchars($billingNumber); ?>" placeholder="ex: 385973758" style="padding: 5px; width: 200px;">
        <button type="submit" style="padding: 5px 15px;">Tester</button>
    </form>
    
    <?php if (!empty($billingNumber)): ?>
    
    <h2>📋 Résultats pour le numéro : <?php echo htmlspecialchars($billingNumber); ?></h2>
    
    <?php
    // Test 1: Vérifier dans tblcustomer
    echo "<h3>1️⃣ Vérification dans tblcustomer</h3>";
    $customerQuery = "SELECT * FROM tblcustomer WHERE BillingNumber = ?";
    $stmt = $con->prepare($customerQuery);
    $stmt->bind_param("s", $billingNumber);
    $stmt->execute();
    $customerResult = $stmt->get_result();
    
    if ($customerResult->num_rows > 0) {
        $customer = $customerResult->fetch_assoc();
        echo "<div class='success'>✅ Facture trouvée dans tblcustomer</div>";
        echo "<table>";
        echo "<tr><th>Champ</th><th>Valeur</th></tr>";
        foreach ($customer as $key => $value) {
            echo "<tr><td>{$key}</td><td>" . htmlspecialchars($value) . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='error'>❌ Aucune facture trouvée dans tblcustomer</div>";
        $stmt->close();
        goto end_test;
    }
    $stmt->close();
    
    // Test 2: Vérifier dans tblcart
    echo "<h3>2️⃣ Vérification dans tblcart</h3>";
    $cartQuery = "SELECT COUNT(*) as count FROM tblcart WHERE BillingId = ?";
    $stmt = $con->prepare($cartQuery);
    $stmt->bind_param("s", $billingNumber);
    $stmt->execute();
    $cartResult = $stmt->get_result();
    $cartData = $cartResult->fetch_assoc();
    $regularItems = $cartData['count'];
    $stmt->close();
    
    if ($regularItems > 0) {
        echo "<div class='success'>✅ {$regularItems} produit(s) trouvé(s) dans tblcart</div>";
        
        // Afficher les produits de tblcart
        $cartDetailQuery = "SELECT c.*, p.ProductName FROM tblcart c LEFT JOIN tblproducts p ON c.ProductId = p.ID WHERE c.BillingId = ?";
        $stmt = $con->prepare($cartDetailQuery);
        $stmt->bind_param("s", $billingNumber);
        $stmt->execute();
        $cartDetailResult = $stmt->get_result();
        
        echo "<table>";
        echo "<tr><th>ProductId</th><th>ProductName</th><th>ProductQty</th><th>Price</th><th>IsCheckOut</th></tr>";
        while ($row = $cartDetailResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['ProductId']}</td>";
            echo "<td>" . htmlspecialchars($row['ProductName']) . "</td>";
            echo "<td>{$row['ProductQty']}</td>";
            echo "<td>{$row['Price']}</td>";
            echo "<td>" . ($row['IsCheckOut'] ? 'Oui' : 'Non') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        $stmt->close();
    } else {
        echo "<div class='warning'>⚠️ Aucun produit trouvé dans tblcart</div>";
    }
    
    // Test 3: Vérifier dans tblcreditcart
    echo "<h3>3️⃣ Vérification dans tblcreditcart</h3>";
    $creditQuery = "SELECT COUNT(*) as count FROM tblcreditcart WHERE BillingId = ?";
    $stmt = $con->prepare($creditQuery);
    $stmt->bind_param("s", $billingNumber);
    $stmt->execute();
    $creditResult = $stmt->get_result();
    $creditData = $creditResult->fetch_assoc();
    $creditItems = $creditData['count'];
    $stmt->close();
    
    if ($creditItems > 0) {
        echo "<div class='success'>✅ {$creditItems} produit(s) trouvé(s) dans tblcreditcart</div>";
        
        // Afficher les produits de tblcreditcart
        $creditDetailQuery = "SELECT c.*, p.ProductName FROM tblcreditcart c LEFT JOIN tblproducts p ON c.ProductId = p.ID WHERE c.BillingId = ?";
        $stmt = $con->prepare($creditDetailQuery);
        $stmt->bind_param("s", $billingNumber);
        $stmt->execute();
        $creditDetailResult = $stmt->get_result();
        
        echo "<table>";
        echo "<tr><th>ProductId</th><th>ProductName</th><th>ProductQty</th><th>Price</th></tr>";
        while ($row = $creditDetailResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['ProductId']}</td>";
            echo "<td>" . htmlspecialchars($row['ProductName']) . "</td>";
            echo "<td>{$row['ProductQty']}</td>";
            echo "<td>{$row['Price']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        $stmt->close();
    } else {
        echo "<div class='warning'>⚠️ Aucun produit trouvé dans tblcreditcart</div>";
    }
    
    // Test 4: Logique de détermination de table
    echo "<h3>4️⃣ Logique de détermination de table</h3>";
    $useTable = ($creditItems > 0) ? 'tblcreditcart' : 'tblcart';
    $saleType = ($creditItems > 0) ? 'Vente à Terme' : 'Vente Cash';
    
    if ($creditItems > 0 || $regularItems > 0) {
        echo "<div class='info'>";
        echo "📊 <strong>Résumé :</strong><br>";
        echo "• Table à utiliser : <strong>{$useTable}</strong><br>";
        echo "• Type de vente : <strong>{$saleType}</strong><br>";
        echo "• Produits dans tblcart : {$regularItems}<br>";
        echo "• Produits dans tblcreditcart : {$creditItems}<br>";
        echo "</div>";
    } else {
        echo "<div class='error'>❌ Aucun produit trouvé dans aucune des deux tables</div>";
    }
    
    // Test 5: Vérifier les retours existants
    echo "<h3>5️⃣ Vérification des retours existants</h3>";
    $returnsQuery = "SELECT * FROM tblreturns WHERE BillingNumber = ?";
    $stmt = $con->prepare($returnsQuery);
    $stmt->bind_param("s", $billingNumber);
    $stmt->execute();
    $returnsResult = $stmt->get_result();
    
    if ($returnsResult->num_rows > 0) {
        echo "<div class='warning'>⚠️ {$returnsResult->num_rows} retour(s) déjà effectué(s)</div>";
        echo "<table>";
        echo "<tr><th>ID</th><th>ProductID</th><th>Quantity</th><th>ReturnPrice</th><th>ReturnDate</th><th>Reason</th></tr>";
        while ($row = $returnsResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['ID']}</td>";
            echo "<td>{$row['ProductID']}</td>";
            echo "<td>{$row['Quantity']}</td>";
            echo "<td>{$row['ReturnPrice']}</td>";
            echo "<td>{$row['ReturnDate']}</td>";
            echo "<td>" . htmlspecialchars($row['Reason']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='info'>ℹ️ Aucun retour encore effectué pour cette facture</div>";
    }
    $stmt->close();
    
    // Test 6: Test de l'AJAX
    echo "<h3>6️⃣ Test de l'AJAX validate-billing.php</h3>";
    echo "<div class='code'>";
    echo "Pour tester l'AJAX manuellement, utilisez :<br>";
    echo "URL : ajax/validate-billing.php<br>";
    echo "Méthode : POST<br>";
    echo "Données : billingnumber={$billingNumber}<br><br>";
    echo "Ou utilisez cette commande curl :<br>";
    echo "curl -X POST -d 'billingnumber={$billingNumber}' http://votre-domaine/ajax/validate-billing.php";
    echo "</div>";
    
    end_test:
    ?>
    
    <h3>🔧 Actions recommandées</h3>
    <div class='info'>
        <?php if (isset($regularItems) && isset($creditItems)): ?>
            <?php if ($regularItems == 0 && $creditItems == 0): ?>
                <strong>Problème détecté :</strong> La facture existe mais aucun produit n'est trouvé.<br>
                • Vérifiez que les produits ont été correctement ajoutés lors de la vente<br>
                • Vérifiez la cohérence des données entre tblcustomer et tblcart/tblcreditcart<br>
                • Assurez-vous que BillingId dans les tables cart correspond bien à BillingNumber dans tblcustomer
            <?php else: ?>
                <strong>Tout semble correct !</strong> Le système devrait fonctionner.<br>
                • Si l'AJAX ne fonctionne toujours pas, vérifiez les logs JavaScript dans la console du navigateur (F12)<br>
                • Vérifiez que les fichiers ajax/validate-billing.php et ajax/get-product-details.php existent<br>
                • Testez l'AJAX manuellement avec la commande curl ci-dessus
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <?php endif; ?>
    
    <hr>
    <p><small>🛠️ Script de debug pour le système de retours | Créé automatiquement</small></p>
</body>
</html>