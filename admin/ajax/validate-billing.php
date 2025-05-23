<?php
// File: ajax/validate-billing.php - VERSION CORRIGÉE
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
        
        // Build customer info display
        $customerInfo = "<strong>Client:</strong> " . $customer['CustomerName'] . "<br>";
        $customerInfo .= "<strong>Téléphone:</strong> " . $customer['MobileNumber'] . "<br>";
        $customerInfo .= "<strong>Date de facture:</strong> " . $customer['BillingDate'] . "<br>";
        $customerInfo .= "<strong>Montant total:</strong> " . number_format($customer['FinalAmount'], 2) . " GNF";
        
        // Get products from this sale - IMPORTANT: BillingId contient BillingNumber, pas l'ID!
        $productOptions = '<option value="">-- Choisir un produit --</option>';
        
        // From cash sales - BillingId = BillingNumber
        $cashQuery = "SELECT DISTINCT c.ProductId, p.ProductName, c.ProductQty, c.Price 
                      FROM tblcart c
                      JOIN tblproducts p ON p.ID = c.ProductId
                      WHERE c.BillingId = '$billingNumber' AND c.IsCheckOut = 1";
        $cashResult = mysqli_query($con, $cashQuery);
        
        while ($row = mysqli_fetch_assoc($cashResult)) {
            $productOptions .= '<option value="'.$row['ProductId'].'">'.$row['ProductName'].' (Qté vendue: '.$row['ProductQty'].' - Prix: '.number_format($row['Price'], 2).' GNF)</option>';
        }
        
        // Note: tblcreditcart n'est pas utilisé dans votre cart.php, donc on ne le vérifie pas ici
        
        // Si aucun produit trouvé, vérifier si c'est une vente crédit
        if (mysqli_num_rows($cashResult) == 0) {
            $customerInfo .= "<br><small style='color: orange;'>Note: Aucun produit trouvé. Cette facture pourrait être une vente crédit.</small>";
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