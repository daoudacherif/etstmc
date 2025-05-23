<?php
// File: ajax/get-product-details.php
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
    
    // Get customer ID
    $customerQuery = "SELECT ID FROM tblcustomer WHERE BillingNumber = '$billingNumber'";
    $customerResult = mysqli_query($con, $customerQuery);
    
    if (mysqli_num_rows($customerResult) > 0) {
        $customer = mysqli_fetch_assoc($customerResult);
        $customerID = $customer['ID'];
        
        // Get original sale details
        $originalQty = 0;
        $originalPrice = 0;
        $saleType = '';
        
        // Check cash sales
        $cashQuery = "SELECT ProductQty, Price FROM tblcart 
                      WHERE BillingId = '$customerID' AND ProductId = '$productID'";
        $cashResult = mysqli_query($con, $cashQuery);
        
        if (mysqli_num_rows($cashResult) > 0) {
            $cashRow = mysqli_fetch_assoc($cashResult);
            $originalQty = $cashRow['ProductQty'];
            $originalPrice = $cashRow['Price'];
            $saleType = 'Cash';
        } else {
            // Check credit sales
            $creditQuery = "SELECT ProductQty, Price FROM tblcreditcart 
                            WHERE BillingId = '$customerID' AND ProductId = '$productID'";
            $creditResult = mysqli_query($con, $creditQuery);
            
            if (mysqli_num_rows($creditResult) > 0) {
                $creditRow = mysqli_fetch_assoc($creditResult);
                $originalQty = $creditRow['ProductQty'];
                $originalPrice = $creditRow['Price'];
                $saleType = 'Crédit';
            }
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
        $details .= "<strong>Prix unitaire original:</strong> " . number_format($originalPrice, 2) . " FCFA<br>";
        $details .= "<strong>Déjà retourné:</strong> $alreadyReturned<br>";
        $details .= "<strong>Disponible pour retour:</strong> <span class='badge badge-" . 
                    ($availableToReturn > 0 ? "success" : "important") . "'>$availableToReturn</span>";
        
        echo json_encode([
            'valid' => true,
            'details' => $details,
            'maxReturn' => $availableToReturn,
            'originalPrice' => $originalPrice
        ]);
    } else {
        echo json_encode([
            'valid' => false,
            'message' => 'Erreur lors de la récupération des détails.'
        ]);
    }
} else {
    echo json_encode([
        'valid' => false,
        'message' => 'Paramètres manquants.'
    ]);
}