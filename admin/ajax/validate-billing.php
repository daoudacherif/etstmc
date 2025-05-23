<?php
// File: ajax/validate-billing.php
session_start();
include('../includes/dbconnection.php');

header('Content-Type: application/json');

if (!isset($_SESSION['imsaid']) || strlen($_SESSION['imsaid']) == 0) {
    echo json_encode(['valid' => false, 'message' => 'Non autorisé']);
    exit;
}

if (isset($_POST['billingnumber'])) {
    $billingNumber = mysqli_real_escape_string($con, $_POST['billingnumber']);
    
    // Get customer info
    $customerQuery = "SELECT * FROM tblcustomer WHERE BillingNumber = '$billingNumber'";
    $customerResult = mysqli_query($con, $customerQuery);
    
    if (mysqli_num_rows($customerResult) > 0) {
        $customer = mysqli_fetch_assoc($customerResult);
        $customerID = $customer['ID'];
        
        // Build customer info display
        $customerInfo = "<strong>Client:</strong> " . $customer['CustomerName'] . "<br>";
        $customerInfo .= "<strong>Téléphone:</strong> " . $customer['MobileNumber'] . "<br>";
        $customerInfo .= "<strong>Date de facture:</strong> " . $customer['BillingDate'] . "<br>";
        $customerInfo .= "<strong>Montant total:</strong> " . number_format($customer['FinalAmount'], 2) . " FCFA";
        
        // Get products from this sale (both cash and credit)
        $productOptions = '<option value="">-- Choisir un produit --</option>';
        
        // From cash sales
        $cashQuery = "SELECT DISTINCT c.ProductId, p.ProductName, c.ProductQty, c.Price 
                      FROM tblcart c
                      JOIN tblproducts p ON p.ID = c.ProductId
                      WHERE c.BillingId = '$customerID'";
        $cashResult = mysqli_query($con, $cashQuery);
        
        while ($row = mysqli_fetch_assoc($cashResult)) {
            $productOptions .= '<option value="'.$row['ProductId'].'">'.$row['ProductName'].' (Cash - Qté: '.$row['ProductQty'].')</option>';
        }
        
        // From credit sales
        $creditQuery = "SELECT DISTINCT cc.ProductId, p.ProductName, cc.ProductQty, cc.Price 
                        FROM tblcreditcart cc
                        JOIN tblproducts p ON p.ID = cc.ProductId
                        WHERE cc.BillingId = '$customerID'";
        $creditResult = mysqli_query($con, $creditQuery);
        
        while ($row = mysqli_fetch_assoc($creditResult)) {
            $productOptions .= '<option value="'.$row['ProductId'].'">'.$row['ProductName'].' (Crédit - Qté: '.$row['ProductQty'].')</option>';
        }
        
        echo json_encode([
            'valid' => true,
            'customerInfo' => $customerInfo,
            'productOptions' => $productOptions
        ]);
    } else {
        echo json_encode([
            'valid' => false,
            'message' => 'Numéro de facture non trouvé.'
        ]);
    }
} else {
    echo json_encode([
        'valid' => false,
        'message' => 'Numéro de facture requis.'
    ]);
}