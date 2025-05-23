<?php
// File: ajax/get-product-details.php - VERSION CORRIGÉE
session_start();
include('../includes/dbconnection.php');

header('Content-Type: application/json');

if (!isset($_SESSION['imsaid']) || strlen($_SESSION['imsaid']) == 0) {
    echo json_encode(['valid' => false, 'message' => 'Non autorisé']);
    exit;
}

if (isset($_POST['productid']) && isset($_POST['billingnumber'])) {
    $productID = intval($_POST['productid']);
    $billingNumber = mysqli_real_escape_string($con, $_POST['billingnumber']);
    
    // Get original sale details - BillingId = BillingNumber dans votre système
    $originalQty = 0;
    $originalPrice = 0;
    $saleType = 'Cash'; // Votre système utilise principalement tblcart
    
    // Check cash sales - BillingId contient le BillingNumber
    $cashQuery = "SELECT ProductQty, Price FROM tblcart 
                  WHERE BillingId = '$billingNumber' AND ProductId = '$productID' AND IsCheckOut = 1";
    $cashResult = mysqli_query($con, $cashQuery);
    
    if (mysqli_num_rows($cashResult) > 0) {
        $cashRow = mysqli_fetch_assoc($cashResult);
        $originalQty = $cashRow['ProductQty'];
        $originalPrice = $cashRow['Price'];
    }
    
    // Get already returned quantity
    $returnedQuery = "SELECT SUM(Quantity) as TotalReturned FROM tblreturns 
                      WHERE BillingNumber = '$billingNumber' AND ProductID = '$productID'";
    $returnedResult = mysqli_query($con, $returnedQuery);
    $returnedRow = mysqli_fetch_assoc($returnedResult);
    $alreadyReturned = $returnedRow['TotalReturned'] ? $returnedRow['TotalReturned'] : 0;
    
    $availableToReturn = $originalQty - $alreadyReturned;
    
    // Build details display
    $details = "<strong>Type de vente:</strong> $saleType<br>";
    $details .= "<strong>Quantité vendue:</strong> $originalQty<br>";
    $details .= "<strong>Prix unitaire appliqué:</strong> " . number_format($originalPrice, 2) . " GNF<br>";
    $details .= "<strong>Déjà retourné:</strong> $alreadyReturned<br>";
    $details .= "<strong>Disponible pour retour:</strong> <span class='badge badge-" . 
                ($availableToReturn > 0 ? "success" : "important") . "'>$availableToReturn</span>";
    
    if ($originalQty == 0) {
        $details = "<span style='color: red;'>Erreur: Ce produit n'a pas été trouvé dans cette facture.</span>";
    }
    
    echo json_encode([
        'valid' => true,
        'details' => $details,
        'maxReturn' => $availableToReturn,
        'originalPrice' => $originalPrice
    ]);
} else {
    echo json_encode([
        'valid' => false,
        'message' => 'Paramètres manquants.'
    ]);
}