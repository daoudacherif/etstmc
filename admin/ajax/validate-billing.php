<?php
// File: ajax/validate-billing.php - VERSION DEBUG
session_start();
include('../includes/dbconnection.php');

header('Content-Type: application/json');

// Activer les erreurs pour debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
        
        // DEBUG: Afficher l'ID du client
        $customerInfo .= "<br><small>Debug - Customer ID: " . $customerID . "</small>";
        
        // Get products from this sale (both cash and credit)
        $productOptions = '<option value="">-- Choisir un produit --</option>';
        
        // IMPORTANT: Vérifier les noms EXACTS des colonnes dans votre base de données
        // Les noms peuvent être avec des majuscules différentes
        
        // From cash sales - avec gestion d'erreur
        $cashQuery = "SELECT c.*, p.ProductName 
                      FROM tblcart c
                      LEFT JOIN tblproducts p ON p.ID = c.ProductId
                      WHERE c.BillingId = '$customerID'";
        
        $cashResult = mysqli_query($con, $cashQuery);
        
        if ($cashResult) {
            $cashCount = mysqli_num_rows($cashResult);
            $customerInfo .= "<br><small>Debug - Produits cash trouvés: " . $cashCount . "</small>";
            
            while ($row = mysqli_fetch_assoc($cashResult)) {
                // Debug: afficher la structure
                if ($cashCount > 0 && !isset($debugShown)) {
                    $customerInfo .= "<br><small>Debug - Colonnes tblcart: " . implode(', ', array_keys($row)) . "</small>";
                    $debugShown = true;
                }
                
                $productOptions .= '<option value="'.$row['ProductId'].'">'.$row['ProductName'].' (Cash - Qté: '.$row['ProductQty'].')</option>';
            }
        } else {
            $customerInfo .= "<br><small>Erreur SQL Cash: " . mysqli_error($con) . "</small>";
        }
        
        // From credit sales - avec gestion d'erreur
        $creditQuery = "SELECT cc.*, p.ProductName 
                        FROM tblcreditcart cc
                        LEFT JOIN tblproducts p ON p.ID = cc.ProductId
                        WHERE cc.BillingId = '$customerID'";
        
        $creditResult = mysqli_query($con, $creditQuery);
        
        if ($creditResult) {
            $creditCount = mysqli_num_rows($creditResult);
            $customerInfo .= "<br><small>Debug - Produits crédit trouvés: " . $creditCount . "</small>";
            
            while ($row = mysqli_fetch_assoc($creditResult)) {
                // Debug: afficher la structure
                if ($creditCount > 0 && !isset($debugCreditShown)) {
                    $customerInfo .= "<br><small>Debug - Colonnes tblcreditcart: " . implode(', ', array_keys($row)) . "</small>";
                    $debugCreditShown = true;
                }
                
                $productOptions .= '<option value="'.$row['ProductId'].'">'.$row['ProductName'].' (Crédit - Qté: '.$row['ProductQty'].')</option>';
            }
        } else {
            $customerInfo .= "<br><small>Erreur SQL Crédit: " . mysqli_error($con) . "</small>";
        }
        
        // Test direct pour voir s'il y a des données
        $testQuery = "SELECT COUNT(*) as total FROM tblcart WHERE BillingId = '$customerID'";
        $testResult = mysqli_query($con, $testQuery);
        $testRow = mysqli_fetch_assoc($testResult);
        $customerInfo .= "<br><small>Debug - Total tblcart pour ce client: " . $testRow['total'] . "</small>";
        
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