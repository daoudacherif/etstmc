-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : sam. 14 juin 2025 à 23:11
-- Version du serveur : 10.11.10-MariaDB
-- Version de PHP : 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `u451994146_etstmc`
--

-- --------------------------------------------------------

--
-- Structure de la table `tbladmin`
--

CREATE TABLE `tbladmin` (
  `ID` int(10) NOT NULL,
  `AdminName` varchar(200) DEFAULT NULL,
  `UserName` varchar(200) DEFAULT NULL,
  `MobileNumber` bigint(10) DEFAULT NULL,
  `Email` varchar(200) DEFAULT NULL,
  `Password` varchar(200) DEFAULT NULL,
  `AdminRegdate` timestamp NULL DEFAULT current_timestamp(),
  `login_attempts` int(11) DEFAULT 0,
  `account_locked_until` datetime DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `token_expires` datetime DEFAULT NULL,
  `password_changed_at` datetime DEFAULT NULL,
  `Status` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `tbladmin`
--

INSERT INTO `tbladmin` (`ID`, `AdminName`, `UserName`, `MobileNumber`, `Email`, `Password`, `AdminRegdate`, `login_attempts`, `account_locked_until`, `remember_token`, `token_expires`, `password_changed_at`, `Status`) VALUES
(1, 'Admin', 'admin', 1234567890, 'admin@gmail.com', '$2y$10$LcNdrL5YsilS.Z9Y7aW2x.cMM5unz/3SSo5U4E5EP6rDY2SccbqIa', '2020-06-25 07:19:31', 0, NULL, NULL, NULL, NULL, 1),
(2, 'cherif', 'saler', 787368793, 'daoudacherif4321@gmail.Com', '$2y$10$hy0.1KnPknkH0WrxA2ZACO99oN1biDOJtAH8Oy0rYCcuiq1dJPCgS', '2025-05-11 04:02:59', 0, NULL, NULL, NULL, NULL, 1),
(6, 'rocha ', 'saler', 787368793, 'rocha@gmail.com', '$2y$10$kAvfK1B2Q/BfkYPKFxK/.ejE5yC0fxPYoNzgroiCOiX1iSnjG437S', '2025-05-13 11:44:15', 0, NULL, NULL, NULL, NULL, 1),
(8, 'abdoulaye', 'saler', 621723646, 'ABDOULAYE@GMAIL.COM', '$2y$10$xtwEAR96diqnnCg4c1S8yeHJRta5b3k2/JxXTehK6kbOCBMigjxZi', '2025-05-18 10:25:05', 0, NULL, NULL, NULL, NULL, 1),
(9, 'rougui', 'saler', 787368793, 'rougui@gmail.com', '$2y$10$jLLshRCxlvVuwACCszwKNuH9AaxMIuUee1QPhoLQTfpU9uy0DaoW6', '2025-05-18 12:34:29', 0, NULL, NULL, NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Structure de la table `tblbrand`
--

CREATE TABLE `tblbrand` (
  `ID` int(10) NOT NULL,
  `BrandName` varchar(200) DEFAULT NULL,
  `Status` int(2) DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tblcart`
--

CREATE TABLE `tblcart` (
  `ID` int(10) NOT NULL,
  `ProductId` int(5) DEFAULT NULL,
  `BillingId` int(11) DEFAULT NULL,
  `ProductQty` int(11) DEFAULT NULL,
  `Price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `IsCheckOut` int(5) DEFAULT NULL,
  `CartDate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `CartType` varchar(20) DEFAULT 'regular',
  `AdminID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `tblcart`
--

INSERT INTO `tblcart` (`ID`, `ProductId`, `BillingId`, `ProductQty`, `Price`, `IsCheckOut`, `CartDate`, `CartType`, `AdminID`) VALUES
(1, 2, 213295440, 1, 200000.00, 1, '2025-05-14 19:31:44', 'regular', 0),
(2, 2, 502921410, 1, 200000.00, 1, '2025-05-14 20:04:54', 'regular', 2),
(3, 2, 572623956, 1, 200000.00, 1, '2025-05-14 20:04:54', 'regular', 1),
(4, 2, 458252212, 1, 200000.00, 1, '2025-05-14 20:19:57', 'regular', 2),
(6, 2, 137074347, 1, 200000.00, 1, '2025-05-14 20:24:19', 'regular', 2),
(7, 2, 604905308, 1, 200000.00, 1, '2025-05-14 20:24:19', 'regular', 1),
(14, 190, 416113919, 1, 625000.00, 1, '2025-05-18 12:11:05', 'regular', 1),
(16, 124, 728691734, 1, 26000.00, 1, '2025-05-19 15:13:47', 'regular', 1),
(17, 190, 403101375, 1, 625000.00, 1, '2025-05-29 02:24:45', 'regular', 1),
(18, 157, 876907521, 5, 10000.00, 1, '2025-05-29 09:35:58', 'regular', 1),
(19, 195, 708670710, 3, 115000.00, 1, '2025-06-08 16:23:45', 'regular', 1),
(20, 195, 1939, 1, 115000.00, 1, '2025-06-08 16:58:34', 'regular', 1),
(21, 195, NULL, 1, 115000.00, 0, '2025-06-08 18:14:40', 'regular', 0);

-- --------------------------------------------------------

--
-- Structure de la table `tblcashtransactions`
--

CREATE TABLE `tblcashtransactions` (
  `ID` int(11) NOT NULL,
  `TransDate` datetime NOT NULL,
  `TransType` enum('IN','OUT') NOT NULL,
  `Amount` decimal(10,2) NOT NULL,
  `BalanceAfter` decimal(10,2) NOT NULL,
  `Comments` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `tblcashtransactions`
--

INSERT INTO `tblcashtransactions` (`ID`, `TransDate`, `TransType`, `Amount`, `BalanceAfter`, `Comments`) VALUES
(7, '2025-05-18 00:00:00', 'OUT', 10000.00, 90000.00, 'Paiement fournisseur: SODEFA - TEST'),
(8, '2025-05-19 00:00:00', 'OUT', 20000.00, 6000.00, 'Paiement fournisseur: SODEFA'),
(9, '2025-06-14 22:06:52', 'IN', 10000.00, 10000.00, 'Ventes du jour');

-- --------------------------------------------------------

--
-- Structure de la table `tblcategory`
--

CREATE TABLE `tblcategory` (
  `ID` int(10) NOT NULL,
  `CategoryName` varchar(200) DEFAULT NULL,
  `Status` int(2) DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `tblcategory`
--

INSERT INTO `tblcategory` (`ID`, `CategoryName`, `Status`, `CreationDate`) VALUES
(1, 'test product ', 1, '2025-04-25 13:52:50'),
(2, 'peinture', 1, '2025-05-04 10:21:35'),
(3, 'Peintures', 0, '2025-05-18 05:17:01'),
(4, 'Vernis', 0, '2025-05-18 05:17:01'),
(5, 'Enduits', 0, '2025-05-18 05:17:01'),
(6, 'Quincaillerie', 0, '2025-05-18 05:17:01'),
(7, 'Panneaux', 0, '2025-05-18 05:17:01'),
(8, 'Accessoires', 0, '2025-05-18 05:17:01'),
(9, 'Colles', 1, '2025-05-18 05:17:01'),
(10, 'Étanchéité', 1, '2025-05-18 05:17:01');

-- --------------------------------------------------------

--
-- Structure de la table `tblcreditcart`
--

CREATE TABLE `tblcreditcart` (
  `ID` int(10) NOT NULL,
  `ProductId` int(5) DEFAULT NULL,
  `BillingId` int(11) DEFAULT NULL,
  `ProductQty` int(11) DEFAULT NULL,
  `Price` decimal(10,2) DEFAULT NULL,
  `IsCheckOut` int(5) DEFAULT 0,
  `CartDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `AdminID` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Déchargement des données de la table `tblcreditcart`
--

INSERT INTO `tblcreditcart` (`ID`, `ProductId`, `BillingId`, `ProductQty`, `Price`, `IsCheckOut`, `CartDate`, `AdminID`) VALUES
(1, 2, 332976631, 10, 200000.00, 1, '2025-05-14 12:52:18', 1),
(4, 2, 476796619, 1, 200000.00, 1, '2025-05-14 21:13:12', 1),
(7, 2, 433955845, 1, 200000.00, 1, '2025-05-18 01:41:05', 1),
(8, 190, 864017419, 1, 625000.00, 1, '2025-05-18 12:17:18', 1),
(9, 124, 385973758, 1, 26000.00, 1, '2025-05-23 14:31:44', 1),
(10, 157, 918353658, 5, 7500.00, 1, '2025-05-29 01:54:22', 1),
(11, 157, 211048521, 1, 7500.00, 1, '2025-05-29 02:04:28', 1),
(14, 195, 311824763, 2, 115000.00, 1, '2025-06-08 16:10:21', 1),
(15, 195, 1630, 1, 115000.00, 1, '2025-06-08 17:24:09', 1);

-- --------------------------------------------------------

--
-- Structure de la table `tblcustomer`
--

CREATE TABLE `tblcustomer` (
  `ID` int(10) NOT NULL,
  `BillingNumber` varchar(120) DEFAULT NULL,
  `CustomerName` varchar(120) DEFAULT NULL,
  `MobileNumber` varchar(15) DEFAULT NULL,
  `ModeofPayment` varchar(50) DEFAULT NULL,
  `BillingDate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `FinalAmount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `Paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `Dues` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `tblcustomer`
--

INSERT INTO `tblcustomer` (`ID`, `BillingNumber`, `CustomerName`, `MobileNumber`, `ModeofPayment`, `BillingDate`, `FinalAmount`, `Paid`, `Dues`) VALUES
(1, '332976631', 'daouda', '+224628763535', 'Carte de crédit', '2025-05-16 19:36:22', 2000000.00, 2000000.00, 0.00),
(3, '213295440', 'daouda', '+224628763535', 'cash', '2025-05-14 19:31:44', 200000.00, 0.00, 0.00),
(4, '880002263', 'daouda', '+224628763535', 'cash', '2025-05-14 20:19:03', 0.00, 0.00, 0.00),
(5, '458252212', 'daouda', '+224628763535', 'cash', '2025-05-14 20:19:57', 200000.00, 0.00, 0.00),
(6, '590880084', 'rocha', '+224628763535', 'cash', '2025-05-14 20:22:28', 0.00, 0.00, 0.00),
(7, '604905308', 'rocha', '+224628763535', 'cash', '2025-05-14 20:24:19', 200000.00, 0.00, 0.00),
(8, '137074347', 'daouda', '+224628763535', 'cash', '2025-05-14 20:24:19', 200000.00, 0.00, 0.00),
(9, '476796619', 'rocha', '+224628763535', 'cash', '2025-05-17 09:57:54', 200000.00, 20000.00, 180000.00),
(10, '433955845', 'daouda', '+224628763535', 'cash', '2025-05-18 03:08:49', 200000.00, 100000.00, 100000.00),
(11, '413607041', 'SALIMATOU', '+224621723646', 'cash', '2025-05-18 10:46:06', 6500000.00, 0.00, 0.00),
(12, '416113919', 'Mr djibril', '+224622157492', 'cash', '2025-05-18 12:11:05', 593750.00, 0.00, 0.00),
(13, '864017419', 'Mr djibril', '+224622157492', 'cash', '2025-05-25 12:45:04', 625000.00, 525000.00, 0.00),
(14, '686798958', 'mr djibril', '+224622157492', 'credit', '2025-05-18 14:46:48', 0.00, 0.00, 0.00),
(15, '390862400', 'mr djibril', '+224622157492', 'cash', '2025-05-18 14:49:46', 0.00, 0.00, 0.00),
(16, '728691734', 'daouda', '+224628763535', 'cash', '2025-05-19 15:13:47', 26000.00, 0.00, 0.00),
(17, '385973758', 'daouda', '+224628763535', 'cash', '2025-05-25 12:40:49', 26000.00, 0.00, 0.00),
(18, '918353658', 'daouda', '+224628763535', 'cash', '2025-05-29 01:59:56', 37500.00, 22500.00, 0.00),
(19, '211048521', 'daouda', '+224628763535', 'cash', '2025-05-29 02:04:52', 7500.00, 0.00, 7500.00),
(20, '551107300', 'daouda', '+224628763535', 'cash', '2025-05-29 02:22:30', 625000.00, 0.00, 625000.00),
(21, '981275232', 'daouda', '+224628763535', 'cash', '2025-06-14 22:05:28', 625000.00, 625000.00, 0.00),
(22, '403101375', 'daouda', '+224628763535', 'cash', '2025-05-29 02:24:45', 625000.00, 0.00, 0.00),
(23, '876907521', 'SALIMATOU', '+224621723646', 'cash', '2025-05-29 09:35:58', 50000.00, 0.00, 0.00),
(24, '311824763', 'daouda', '+224628763535', 'cash', '2025-06-08 16:18:01', 230000.00, 115000.00, 0.00),
(25, '708670710', 'daouda', '+224628763535', 'cash', '2025-06-08 16:23:45', 345000.00, 0.00, 0.00),
(26, '1939', 'daouda', '+224628763535', 'cash', '2025-06-08 16:58:34', 115000.00, 0.00, 0.00),
(27, '1630', 'daouda', '+224628763535', 'cash', '2025-06-14 21:57:19', 115000.00, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Structure de la table `tblpayments`
--

CREATE TABLE `tblpayments` (
  `ID` int(11) NOT NULL,
  `CustomerID` int(11) NOT NULL,
  `BillingNumber` varchar(100) NOT NULL,
  `PaymentAmount` decimal(10,2) NOT NULL,
  `PaymentDate` datetime NOT NULL DEFAULT current_timestamp(),
  `PaymentMethod` varchar(50) DEFAULT 'Cash',
  `ReferenceNumber` varchar(100) DEFAULT NULL,
  `Comments` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `tblpayments`
--

INSERT INTO `tblpayments` (`ID`, `CustomerID`, `BillingNumber`, `PaymentAmount`, `PaymentDate`, `PaymentMethod`, `ReferenceNumber`, `Comments`) VALUES
(1, 13, '864017419', 100000.00, '2025-05-19 17:21:52', 'Cash', '864017419', ''),
(2, 18, '918353658', 22500.00, '2025-05-29 01:59:56', 'Cash', '918353658', ''),
(3, 21, '981275232', 625000.00, '2025-06-14 22:05:28', 'Cash', '', '');

-- --------------------------------------------------------

--
-- Structure de la table `tblproductarrivals`
--

CREATE TABLE `tblproductarrivals` (
  `ID` int(11) NOT NULL,
  `ProductID` int(11) NOT NULL,
  `SupplierID` int(11) NOT NULL,
  `ArrivalDate` date NOT NULL,
  `Quantity` int(11) NOT NULL,
  `Cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `Paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `Dues` decimal(10,2) NOT NULL DEFAULT 0.00,
  `Comments` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `tblproductarrivals`
--

INSERT INTO `tblproductarrivals` (`ID`, `ProductID`, `SupplierID`, `ArrivalDate`, `Quantity`, `Cost`, `Paid`, `Dues`, `Comments`) VALUES
(14, 124, 2, '2025-05-19', 5, 130000.00, 0.00, 0.00, ''),
(15, 174, 2, '2025-05-19', 5, 16000.00, 0.00, 0.00, ''),
(16, 157, 1, '2025-05-29', 10, 75000.00, 0.00, 0.00, ''),
(17, 193, 1, '2025-05-29', 10, 60000.00, 0.00, 0.00, '');

-- --------------------------------------------------------

--
-- Structure de la table `tblproducts`
--

CREATE TABLE `tblproducts` (
  `ID` int(10) NOT NULL,
  `ProductName` varchar(200) DEFAULT NULL,
  `CatID` int(5) DEFAULT NULL,
  `SubcatID` int(5) DEFAULT NULL,
  `BrandName` varchar(200) DEFAULT NULL,
  `ModelNumber` varchar(200) DEFAULT NULL,
  `Stock` int(10) DEFAULT NULL,
  `Price` decimal(10,0) DEFAULT NULL,
  `Status` int(2) DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `tblproducts`
--

INSERT INTO `tblproducts` (`ID`, `ProductName`, `CatID`, `SubcatID`, `BrandName`, `ModelNumber`, `Stock`, `Price`, `Status`, `CreationDate`) VALUES
(99, 'FOM INTERIEURE 25KG', 3, NULL, NULL, '25KG', NULL, 200000, 0, '2025-05-18 05:45:37'),
(100, 'FOM INTERIEURE 10KG', 3, NULL, NULL, '10KG', NULL, 95000, 0, '2025-05-18 05:45:37'),
(101, 'FOM INTERIEURE 5KG', 3, NULL, NULL, '5KG', NULL, 48000, 0, '2025-05-18 05:45:37'),
(102, 'FOM EXTERIEURE 25KG', 3, NULL, NULL, '25KG', NULL, 310000, 0, '2025-05-18 05:45:37'),
(103, 'FOM BRITISH', 3, NULL, NULL, '18KG', NULL, 135000, 0, '2025-05-18 05:45:37'),
(104, 'ENDUIT CIM', 5, NULL, NULL, '25KG', NULL, 210000, 0, '2025-05-18 05:45:37'),
(105, 'ENDUIT CIM', 5, NULL, NULL, '5KG', NULL, 48000, 0, '2025-05-18 05:45:37'),
(106, 'ENDUIT CIM+', 5, NULL, NULL, '25KG', NULL, 250000, 0, '2025-05-18 05:45:37'),
(107, 'MASTIQUE TOPAZ', 5, NULL, NULL, '25KG', NULL, 185000, 0, '2025-05-18 05:45:37'),
(108, 'TOPEC BLAC BIDON', 3, NULL, NULL, '20L', NULL, 650000, 0, '2025-05-18 05:45:37'),
(109, 'VERNIS CLAIRE BIDON', 4, NULL, NULL, '18L', NULL, 485000, 0, '2025-05-18 05:45:37'),
(110, 'VERNIS ACAJOU BIDON', 4, NULL, NULL, '18L', NULL, 485000, 0, '2025-05-18 05:45:37'),
(111, 'TOPEC BLANC', 3, NULL, NULL, '1KG', NULL, 35000, 0, '2025-05-18 05:45:37'),
(112, 'TOPEC ORNGE', 3, NULL, NULL, '1KG', NULL, 39167, 0, '2025-05-18 05:45:37'),
(113, 'TOPEC NOIR', 3, NULL, NULL, '1KG', NULL, 34167, 0, '2025-05-18 05:45:37'),
(114, 'TOPEC JAUNE', 3, NULL, NULL, '1KG', NULL, 34167, 0, '2025-05-18 05:45:37'),
(115, 'TOPEC BLEU', 3, NULL, NULL, '1KG', NULL, 34167, 0, '2025-05-18 05:45:37'),
(116, 'TOPEC ROUGE', 3, NULL, NULL, '1KG', NULL, 34167, 0, '2025-05-18 05:45:37'),
(117, 'TOPEC GRIS FONCE', 3, NULL, NULL, '1KG', NULL, 34167, 0, '2025-05-18 05:45:37'),
(118, 'TOPEC VERT', 3, NULL, NULL, '1KG', NULL, 34167, 0, '2025-05-18 05:45:37'),
(119, 'TOPEC GRIS CLIRE', 3, NULL, NULL, '1KG', NULL, 34167, 0, '2025-05-18 05:45:37'),
(120, 'VERNIS BRILLANT', 4, NULL, NULL, '1L', NULL, 27500, 0, '2025-05-18 05:45:37'),
(121, 'VERNIS ACAJOU', 4, NULL, NULL, '1L', NULL, 27500, 0, '2025-05-18 05:45:37'),
(122, 'VERNIS BRILLANT', 4, NULL, NULL, '250ML', NULL, 9167, 0, '2025-05-18 05:45:37'),
(123, 'VERNIS BRILLANT', 4, NULL, NULL, '500ML', NULL, 17500, 0, '2025-05-18 05:45:37'),
(124, 'ARDOISINE', 3, NULL, NULL, '1KG', 4, 26000, 0, '2025-05-18 05:45:37'),
(125, 'VERNIS BRILLANT', 4, NULL, NULL, '4L', NULL, 115000, 0, '2025-05-18 05:45:37'),
(126, 'VERNIS ACAJOU', 4, NULL, NULL, '4L', NULL, 115000, 0, '2025-05-18 05:45:37'),
(127, 'TOPEC BLANC', 3, NULL, NULL, '4KG', NULL, 137500, 0, '2025-05-18 05:45:37'),
(128, 'TOPEC ORANGE', 3, NULL, NULL, '4KG', NULL, 147500, 0, '2025-05-18 05:45:37'),
(129, 'TOPEC JAUNE', 3, NULL, NULL, '4KG', NULL, 135000, 0, '2025-05-18 05:45:37'),
(130, 'TOPEC NOIRE', 3, NULL, NULL, '4KG', NULL, 135000, 0, '2025-05-18 05:45:37'),
(131, 'TOPEC GRIS FONCE', 3, NULL, NULL, '4KG', NULL, 135000, 0, '2025-05-18 05:45:37'),
(132, 'TOPEC GRIS CLAIRE', 3, NULL, NULL, '4KG', NULL, 135000, 0, '2025-05-18 05:45:37'),
(133, 'TOPEC ROUGE', 3, NULL, NULL, '4KG', NULL, 135000, 0, '2025-05-18 05:45:37'),
(134, 'TOPEC ROUGE BRUN', 3, NULL, NULL, '4KG', NULL, 135000, 0, '2025-05-18 05:45:37'),
(135, 'TOPEC MARON', 3, NULL, NULL, '4KG', NULL, 135000, 0, '2025-05-18 05:45:37'),
(136, 'TOPEC VERT FONCE', 3, NULL, NULL, '4KG', NULL, 135000, 0, '2025-05-18 05:45:37'),
(137, 'TOPEC BLEU FONCE', 3, NULL, NULL, '4KG', NULL, 135000, 0, '2025-05-18 05:45:37'),
(138, 'TOPAZ ANTI-ROUILLE ROUGE BRUN', 3, NULL, NULL, '4KG', NULL, 101250, 0, '2025-05-18 05:45:37'),
(139, 'TOPAZ ANTI-ROUILLE GRIS', 3, NULL, NULL, '4KG', NULL, 101250, 0, '2025-05-18 05:45:37'),
(140, 'TOPAZ ANTI-GUALVA', 3, NULL, NULL, '4KG', NULL, 260000, 0, '2025-05-18 05:45:37'),
(141, 'TOP-HYDRO', 3, NULL, NULL, '1KG', NULL, 33400, 0, '2025-05-18 05:45:37'),
(142, 'TOP-ALLU', 3, NULL, NULL, '4KG', NULL, 158750, 0, '2025-05-18 05:45:37'),
(143, 'COLORANT JAUNE CITRON', 3, NULL, NULL, '200ML', NULL, 14600, 0, '2025-05-18 05:45:37'),
(144, 'COLORANT CREME', 3, NULL, NULL, '200ML', NULL, 13000, 0, '2025-05-18 05:45:37'),
(145, 'COLORANT ROUGE', 3, NULL, NULL, '200ML', NULL, 13000, 0, '2025-05-18 05:45:37'),
(146, 'COLORANT NOIR', 3, NULL, NULL, '200ML', NULL, 13000, 0, '2025-05-18 05:45:37'),
(147, 'COLORANT BLEU', 3, NULL, NULL, '200ML', NULL, 13000, 0, '2025-05-18 05:45:37'),
(148, 'COLORANT VERT', 3, NULL, NULL, '200ML', NULL, 13000, 0, '2025-05-18 05:45:37'),
(149, 'COLORANT MARON', 3, NULL, NULL, '200ML', NULL, 13000, 0, '2025-05-18 05:45:37'),
(150, 'DILUANT', 9, NULL, NULL, '1L', NULL, 18400, 0, '2025-05-18 05:45:37'),
(151, 'D\'ORE', 9, NULL, NULL, '1KG', NULL, 77500, 0, '2025-05-18 05:45:37'),
(152, 'COLE PAPIER', 9, NULL, NULL, 'PCS', NULL, 2700, 0, '2025-05-18 05:45:37'),
(153, 'PAPIER VERRE 40', 8, NULL, NULL, 'M', NULL, 3200, 0, '2025-05-18 05:45:37'),
(154, 'PAPIER VERRE 120', 8, NULL, NULL, 'M', NULL, 3200, 0, '2025-05-18 05:45:37'),
(155, 'PAPIER VERRE FEUILLE 150', 8, NULL, NULL, 'PCS', NULL, 2000, 0, '2025-05-18 05:45:37'),
(156, 'PAPIER VERRE FEUILLE 220', 8, NULL, NULL, 'PCS', NULL, 2000, 0, '2025-05-18 05:45:37'),
(157, 'ROULEAUX GRAND', 8, NULL, NULL, 'PCS', 5, 7500, 0, '2025-05-18 05:45:37'),
(158, 'ROULEAUX CREPITEXTE', 8, NULL, NULL, 'PCS', NULL, 60000, 0, '2025-05-18 05:45:37'),
(159, 'ROULETTES', 8, NULL, NULL, 'PCS', NULL, 2500, 0, '2025-05-18 05:45:37'),
(160, 'PINSEAUX N°2', 8, NULL, NULL, 'PCS', NULL, 2100, 0, '2025-05-18 05:45:37'),
(161, 'PINSEAUX N°3', 8, NULL, NULL, 'PCS', NULL, 3000, 0, '2025-05-18 05:45:37'),
(162, 'POINTE N°2', 6, NULL, NULL, '1KG', NULL, 15300, 0, '2025-05-18 05:45:37'),
(163, 'POINTE N°3', 6, NULL, NULL, '1KG', NULL, 15300, 0, '2025-05-18 05:45:37'),
(164, 'POINTE N°4', 6, NULL, NULL, '1KG', NULL, 14500, 0, '2025-05-18 05:45:37'),
(165, 'POINTE N°5', 6, NULL, NULL, '1KG', NULL, 14500, 0, '2025-05-18 05:45:37'),
(166, 'POINTE N°6', 6, NULL, NULL, '1KG', NULL, 14500, 0, '2025-05-18 05:45:37'),
(167, 'POINTE N°7', 6, NULL, NULL, '1KG', NULL, 14500, 0, '2025-05-18 05:45:37'),
(168, 'POINTE N°8', 6, NULL, NULL, '1KG', NULL, 14500, 0, '2025-05-18 05:45:37'),
(169, 'POINTE N°10', 6, NULL, NULL, '1KG', NULL, 14500, 0, '2025-05-18 05:45:37'),
(170, 'POINTE N°12', 6, NULL, NULL, '1KG', NULL, 14500, 0, '2025-05-18 05:45:37'),
(171, 'POINTE N°15', 6, NULL, NULL, '1KG', NULL, 15300, 0, '2025-05-18 05:45:37'),
(172, 'ROULAU FIL N°4', 6, NULL, NULL, '20KG', NULL, 305000, 0, '2025-05-18 05:45:37'),
(173, 'ROULAU FIL N°3', 6, NULL, NULL, '20KG', NULL, 305000, 0, '2025-05-18 05:45:37'),
(174, 'BAGUETTE PLAT', 8, NULL, NULL, 'PCS', 5, 3200, 0, '2025-05-18 05:45:37'),
(175, 'BAGUETTE CORNIERE', 8, NULL, NULL, 'PCS', NULL, 16000, 0, '2025-05-18 05:45:37'),
(176, 'BROUETTE', 6, NULL, NULL, 'PCS', NULL, 405000, 0, '2025-05-18 05:45:37'),
(177, 'CONTRE PLAQUE 4MM', 7, NULL, NULL, 'FEUILLES', NULL, 78500, 0, '2025-05-18 05:45:37'),
(178, 'CONTRE PLAQUE 6MM', 7, NULL, NULL, 'FEUILLES', NULL, 130100, 0, '2025-05-18 05:45:37'),
(179, 'CONTRE PLAQUE 9MM', 7, NULL, NULL, 'FEUILLES', NULL, 220000, 0, '2025-05-18 05:45:37'),
(180, 'CONTRE PLAQUE 12MM', 7, NULL, NULL, 'FEUILLES', NULL, 295000, 0, '2025-05-18 05:45:37'),
(181, 'CONTRE PLAQUE 15MM', 7, NULL, NULL, 'FEUILLES', NULL, 340000, 0, '2025-05-18 05:45:37'),
(182, 'CONTRE PLAQUE 18 MARINE', 7, NULL, NULL, 'FEUILLES', NULL, 365000, 0, '2025-05-18 05:45:37'),
(183, 'ISORELLE', 7, NULL, NULL, 'FEUILLES', NULL, 48000, 0, '2025-05-18 05:45:37'),
(184, 'COLE CARTON', 9, NULL, NULL, 'PCS', NULL, 8500, 0, '2025-05-18 05:45:37'),
(185, 'COLE CARTON', 9, NULL, NULL, 'PCS', NULL, 4200, 0, '2025-05-18 05:45:37'),
(186, 'FIXATEUR', 5, NULL, NULL, '25KG', NULL, 215000, 0, '2025-05-18 05:45:37'),
(187, 'POLUANT', 8, NULL, NULL, 'M', NULL, 3000, 0, '2025-05-18 05:45:37'),
(188, 'DOCTEUR HUMIDITY', 10, NULL, NULL, '20KG', NULL, 880000, 0, '2025-05-18 05:45:37'),
(189, 'DOCTEUR HUMIDITE', 10, NULL, NULL, '5KG', NULL, 220000, 0, '2025-05-18 05:45:37'),
(190, 'ACRISOL', 5, NULL, NULL, '25KG', 19, 625000, 0, '2025-05-18 05:45:37'),
(191, 'CREPITEX', 5, NULL, NULL, '25KG', NULL, 475000, 0, '2025-05-18 05:45:37'),
(192, 'MASTIQUE EN POUDRE', 5, NULL, NULL, '20KG', NULL, 155000, 0, '2025-05-18 05:45:37'),
(193, 'ROULEAUX MOYEN', 8, NULL, NULL, 'PCS', 10, 6000, 0, '2025-05-18 05:45:37'),
(194, '', 0, 0, '', '', 0, 0, 0, '2025-05-18 05:45:37'),
(195, 'Butee', 9, NULL, NULL, 'Alu027', 98, 115000, 1, '2025-05-18 20:43:31');

--
-- Déclencheurs `tblproducts`
--
DELIMITER $$
CREATE TRIGGER `after_product_stock_update` AFTER UPDATE ON `tblproducts` FOR EACH ROW BEGIN
    IF OLD.Stock != NEW.Stock THEN
        INSERT INTO tblstock_movements (
            ProductID, 
            MovementType, 
            Quantity, 
            Reason, 
            CreatedBy
        ) VALUES (
            NEW.ID,
            IF(NEW.Stock > OLD.Stock, 'entree', 'sortie'),
            ABS(NEW.Stock - OLD.Stock),
            'Mise à jour directe du stock',
            1
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `tblreturns`
--

CREATE TABLE `tblreturns` (
  `ID` int(11) NOT NULL,
  `BillingNumber` varchar(50) NOT NULL,
  `ReturnDate` date NOT NULL,
  `ProductID` int(11) NOT NULL,
  `Quantity` int(11) NOT NULL,
  `Reason` varchar(255) DEFAULT NULL,
  `ReturnPrice` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `tblreturns`
--

INSERT INTO `tblreturns` (`ID`, `BillingNumber`, `ReturnDate`, `ProductID`, `Quantity`, `Reason`, `ReturnPrice`) VALUES
(1, '385973758', '2025-05-25', 124, 1, 'Erreur de commande', 26000.00),
(2, '864017419', '2025-05-25', 190, 1, 'Erreur de commande', 625000.00),
(3, '918353658', '2025-05-29', 157, 2, 'Produit défectueux', 15000.00),
(4, '876907521', '2025-05-29', 157, 2, 'Autre', 15000.00),
(5, '876907521', '2025-06-08', 157, 2, 'Client insatisfait', 15000.00),
(6, '311824763', '2025-06-08', 195, 1, 'Produit défectueux', 115000.00),
(7, '708670710', '2025-06-08', 195, 3, 'Erreur de commande', 345000.00),
(8, '1630', '2025-06-14', 195, 1, 'Produit défectueux', 115000.00);

-- --------------------------------------------------------

--
-- Structure de la table `tblstock_alerts`
--

CREATE TABLE `tblstock_alerts` (
  `ID` int(11) NOT NULL,
  `ProductID` int(11) NOT NULL,
  `MinimumStock` int(11) DEFAULT 5,
  `ReorderLevel` int(11) DEFAULT 10,
  `MaximumStock` int(11) DEFAULT 100,
  `CreatedDate` datetime DEFAULT current_timestamp(),
  `UpdatedDate` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tblstock_forecasts`
--

CREATE TABLE `tblstock_forecasts` (
  `ID` int(11) NOT NULL,
  `ProductID` int(11) NOT NULL,
  `ForecastDate` date NOT NULL,
  `PredictedDemand` int(11) DEFAULT 0,
  `RecommendedOrder` int(11) DEFAULT 0,
  `CreatedDate` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tblstock_movements`
--

CREATE TABLE `tblstock_movements` (
  `ID` int(11) NOT NULL,
  `ProductID` int(11) NOT NULL,
  `MovementType` enum('entree','sortie','inventaire','ajustement') NOT NULL,
  `Quantity` int(11) NOT NULL,
  `Reason` text DEFAULT NULL,
  `MovementDate` datetime DEFAULT current_timestamp(),
  `CreatedBy` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `tblstock_movements`
--

INSERT INTO `tblstock_movements` (`ID`, `ProductID`, `MovementType`, `Quantity`, `Reason`, `MovementDate`, `CreatedBy`) VALUES
(1, 157, 'sortie', 5, 'Mise à jour directe du stock', '2025-05-29 01:54:36', 1),
(2, 157, 'entree', 2, 'Mise à jour directe du stock', '2025-05-29 01:59:01', 1),
(3, 157, 'sortie', 1, 'Mise à jour directe du stock', '2025-05-29 02:04:52', 1),
(4, 190, 'sortie', 1, 'Mise à jour directe du stock', '2025-05-29 02:22:30', 1),
(5, 190, 'sortie', 1, 'Mise à jour directe du stock', '2025-05-29 02:23:23', 1),
(6, 190, 'sortie', 1, 'Mise à jour directe du stock', '2025-05-29 02:24:45', 1),
(7, 190, 'entree', 1, 'Mise à jour directe du stock', '2025-05-29 02:37:33', 1),
(8, 190, 'entree', 1, 'Mise à jour directe du stock', '2025-05-29 02:37:48', 1),
(9, 157, 'sortie', 5, 'Mise à jour directe du stock', '2025-05-29 09:35:58', 1),
(10, 157, 'entree', 2, 'Mise à jour directe du stock', '2025-05-29 09:40:21', 1),
(11, 157, 'entree', 2, 'Mise à jour directe du stock', '2025-06-08 16:04:41', 1),
(12, 195, 'sortie', 2, 'Mise à jour directe du stock', '2025-06-08 16:11:06', 1),
(13, 195, 'entree', 1, 'Mise à jour directe du stock', '2025-06-08 16:18:01', 1),
(14, 195, 'sortie', 3, 'Mise à jour directe du stock', '2025-06-08 16:23:45', 1),
(15, 195, 'entree', 3, 'Mise à jour directe du stock', '2025-06-08 16:25:07', 1),
(16, 195, 'sortie', 1, 'Mise à jour directe du stock', '2025-06-08 16:58:34', 1),
(17, 195, 'sortie', 1, 'Mise à jour directe du stock', '2025-06-08 17:24:34', 1),
(18, 195, 'entree', 1, 'Mise à jour directe du stock', '2025-06-14 21:57:19', 1);

-- --------------------------------------------------------

--
-- Structure de la table `tblsubcategory`
--

CREATE TABLE `tblsubcategory` (
  `ID` int(10) NOT NULL,
  `CatID` int(5) DEFAULT NULL,
  `SubCategoryname` varchar(200) DEFAULT NULL,
  `Status` int(2) DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tblsupplier`
--

CREATE TABLE `tblsupplier` (
  `ID` int(11) NOT NULL,
  `SupplierName` varchar(200) NOT NULL,
  `Phone` varchar(50) DEFAULT NULL,
  `Email` varchar(100) DEFAULT NULL,
  `Address` text DEFAULT NULL,
  `Comments` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `tblsupplier`
--

INSERT INTO `tblsupplier` (`ID`, `SupplierName`, `Phone`, `Email`, `Address`, `Comments`) VALUES
(1, 'cherif Cherif daouda', '787368793', 'daoudacherif4321@gmail.com', 'medina rue 25', ''),
(2, 'SODEFA', '621598780', '', 'GOMBONYA', '');

-- --------------------------------------------------------

--
-- Structure de la table `tblsupplierpayments`
--

CREATE TABLE `tblsupplierpayments` (
  `ID` int(11) NOT NULL,
  `SupplierID` int(11) NOT NULL,
  `PaymentDate` date NOT NULL,
  `Amount` decimal(10,2) NOT NULL,
  `Comments` varchar(255) DEFAULT NULL,
  `PaymentMode` varchar(20) DEFAULT 'espece'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `tblsupplierpayments`
--

INSERT INTO `tblsupplierpayments` (`ID`, `SupplierID`, `PaymentDate`, `Amount`, `Comments`, `PaymentMode`) VALUES
(4, 2, '2025-05-19', 20000.00, '', 'espece');

-- --------------------------------------------------------

--
-- Structure de la table `tbl_sms_logs`
--

CREATE TABLE `tbl_sms_logs` (
  `id` int(11) NOT NULL,
  `recipient` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `status` tinyint(1) DEFAULT 0,
  `send_date` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `tbl_sms_logs`
--

INSERT INTO `tbl_sms_logs` (`id`, `recipient`, `message`, `status`, `send_date`) VALUES
(1, '+224628763535', 'Bonjour daouda, votre commande est enregistrée. Solde dû: 200 000 GNF.', 0, '2025-05-04 15:27:15'),
(2, '+224628763535', 'Bonjour daouda, votre commande est confirmée. Merci pour votre confiance !', 0, '2025-05-04 15:46:50'),
(3, '+224628763535', 'Bonjour daouda, votre commande est enregistrée. Solde dû: 200 000 GNF.', 0, '2025-05-04 15:48:12'),
(4, '+224628763535', 'Bonjour daouda, votre commande est enregistrée. Solde dû: 200 000 GNF.', 0, '2025-05-04 16:11:40'),
(5, '+224628763535', 'Bonjour daouda, votre commande est enregistrée. Solde dû: 200 000 GNF.', 1, '2025-05-04 16:20:02'),
(6, '+224628763535', 'Bonjour daouda, votre commande est enregistrée. Solde dû: 190 000 GNF.', 1, '2025-05-04 17:34:57'),
(7, '+224628763535', 'Bonjour daouda, votre commande est enregistrée. Solde dû: 200 000 GNF.', 1, '2025-05-05 23:49:16'),
(8, '+224628763535', 'Bonjour daouda, votre commande est enregistrée. Solde dû: 200 000 GNF.', 1, '2025-05-10 18:37:53'),
(9, '+224628763535', 'Bonjour daouda, votre commande est enregistrée. Solde dû: 400 000 GNF.', 1, '2025-05-10 18:44:24'),
(10, '+224628763535', 'Bonjour cherif daouda, votre commande est enregistrée. Solde dû: 1 000 GNF.', 1, '2025-05-11 11:15:38'),
(11, '+224628763535', 'Bonjour cherif daouda, votre commande (Facture: 378973934) est enregistrée. Solde dû: 4 000 000 GNF.', 1, '2025-05-11 14:06:38'),
(12, '+224628763535', 'Bonjour cherif daouda, votre commande (Facture: 711706533) est enregistrée. Solde dû: 200 000 GNF.', 1, '2025-05-11 14:18:37'),
(13, '+224628763535', 'Bonjour daouda, votre commande est enregistrée. Solde dû: 200 000 GNF.', 1, '2025-05-11 14:33:51'),
(14, '+224628763535', 'Bonjour cherif daouda, votre commande est confirmée. Merci pour votre confiance !', 1, '2025-05-11 14:36:35'),
(15, '+224628763535', 'Bonjour daouda, votre commande est enregistrée. Solde dû: 18 000 GNF.', 1, '2025-05-11 14:38:29'),
(16, '+224628763535', 'Bonjour daouda, votre commande est enregistrée. Solde dû: 2 000 000 GNF.', 1, '2025-05-11 15:08:56'),
(17, '+224628763535', 'Bonjour Cherif, votre commande est enregistrée. Solde dû: 180 000 GNF.', 1, '2025-05-11 21:28:55'),
(18, '+224621723646', 'Bonjour THIERNO, votre commande est enregistrée. Solde dû: 1 454 000 GNF.', 1, '2025-05-12 11:24:01'),
(19, '+224628763535', 'Bonjour daouda, votre commande est enregistrée. Solde dû: 1 000 000 GNF.', 1, '2025-05-14 12:52:57'),
(20, '+224628763535', 'Bonjour rocha, votre commande est enregistrée. Solde dû: 200 000 GNF.', 1, '2025-05-14 21:19:20'),
(21, '+224628763535', 'Bonjour daouda, votre commande est enregistrée. Solde dû: 200 000 GNF.', 1, '2025-05-18 01:41:22'),
(22, '+224622157492', 'Bonjour Mr djibril, votre commande est enregistrée. Solde dû: 425 000 GNF.', 1, '2025-05-18 12:17:53'),
(23, '+224622157492', 'Bonjour mr djibril, votre commande est confirmée. Merci pour votre confiance !', 1, '2025-05-18 14:49:46'),
(24, '+224628763535', 'Bonjour daouda, votre commande est enregistrée. Solde dû: 26 000 GNF.', 1, '2025-05-23 14:32:00'),
(25, '+224628763535', 'Bonjour daouda, votre commande est enregistrée. Solde dû: 37 500 GNF.', 1, '2025-05-29 01:54:36'),
(26, '+224628763535', 'Bonjour daouda, votre commande est enregistrée. Solde dû: 7 500 GNF.', 1, '2025-05-29 02:04:52'),
(27, '+224628763535', 'Bonjour daouda, votre commande est enregistrée. Solde dû: 625 000 GNF.', 1, '2025-05-29 02:23:23');

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `view_stock_summary`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `view_stock_summary` (
`ID` int(10)
,`ProductName` varchar(200)
,`CategoryName` varchar(200)
,`BrandName` varchar(200)
,`CurrentStock` int(10)
,`TotalSold` decimal(32,0)
,`TotalReturned` decimal(32,0)
,`InitialStock` decimal(34,0)
,`MinStock` int(11)
,`ReorderLevel` int(11)
,`StockStatus` varchar(12)
,`StockValue` decimal(20,0)
);

-- --------------------------------------------------------

--
-- Structure de la vue `view_stock_summary`
--
DROP TABLE IF EXISTS `view_stock_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`u451994146_root`@`127.0.0.1` SQL SECURITY DEFINER VIEW `view_stock_summary`  AS SELECT `p`.`ID` AS `ID`, `p`.`ProductName` AS `ProductName`, `c`.`CategoryName` AS `CategoryName`, `p`.`BrandName` AS `BrandName`, `p`.`Stock` AS `CurrentStock`, coalesce((select sum(`tblcart`.`ProductQty`) from `tblcart` where `tblcart`.`ProductId` = `p`.`ID` and `tblcart`.`IsCheckOut` = 1),0) AS `TotalSold`, coalesce((select sum(`tblreturns`.`Quantity`) from `tblreturns` where `tblreturns`.`ProductID` = `p`.`ID`),0) AS `TotalReturned`, `p`.`Stock`+ coalesce((select sum(`tblcart`.`ProductQty`) from `tblcart` where `tblcart`.`ProductId` = `p`.`ID` and `tblcart`.`IsCheckOut` = 1),0) - coalesce((select sum(`tblreturns`.`Quantity`) from `tblreturns` where `tblreturns`.`ProductID` = `p`.`ID`),0) AS `InitialStock`, coalesce(`sa`.`MinimumStock`,5) AS `MinStock`, coalesce(`sa`.`ReorderLevel`,10) AS `ReorderLevel`, CASE WHEN `p`.`Stock` = 0 THEN 'Out of Stock' WHEN `p`.`Stock` <= coalesce(`sa`.`MinimumStock`,5) THEN 'Critical' WHEN `p`.`Stock` <= coalesce(`sa`.`ReorderLevel`,10) THEN 'Low' ELSE 'Normal' END AS `StockStatus`, `p`.`Price`* `p`.`Stock` AS `StockValue` FROM ((`tblproducts` `p` left join `tblcategory` `c` on(`p`.`CatID` = `c`.`ID`)) left join `tblstock_alerts` `sa` on(`p`.`ID` = `sa`.`ProductID`)) WHERE `p`.`Status` = 1 ;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `tbladmin`
--
ALTER TABLE `tbladmin`
  ADD PRIMARY KEY (`ID`);

--
-- Index pour la table `tblbrand`
--
ALTER TABLE `tblbrand`
  ADD PRIMARY KEY (`ID`);

--
-- Index pour la table `tblcart`
--
ALTER TABLE `tblcart`
  ADD PRIMARY KEY (`ID`);

--
-- Index pour la table `tblcashtransactions`
--
ALTER TABLE `tblcashtransactions`
  ADD PRIMARY KEY (`ID`);

--
-- Index pour la table `tblcategory`
--
ALTER TABLE `tblcategory`
  ADD PRIMARY KEY (`ID`);

--
-- Index pour la table `tblcreditcart`
--
ALTER TABLE `tblcreditcart`
  ADD PRIMARY KEY (`ID`);

--
-- Index pour la table `tblcustomer`
--
ALTER TABLE `tblcustomer`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `BillingNumber` (`BillingNumber`);

--
-- Index pour la table `tblpayments`
--
ALTER TABLE `tblpayments`
  ADD PRIMARY KEY (`ID`);

--
-- Index pour la table `tblproductarrivals`
--
ALTER TABLE `tblproductarrivals`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `ProductID` (`ProductID`),
  ADD KEY `SupplierID` (`SupplierID`);

--
-- Index pour la table `tblproducts`
--
ALTER TABLE `tblproducts`
  ADD PRIMARY KEY (`ID`);

--
-- Index pour la table `tblreturns`
--
ALTER TABLE `tblreturns`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `idx_billing` (`BillingNumber`),
  ADD KEY `idx_product` (`ProductID`),
  ADD KEY `idx_billing_product` (`BillingNumber`,`ProductID`);

--
-- Index pour la table `tblstock_alerts`
--
ALTER TABLE `tblstock_alerts`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `unique_product` (`ProductID`);

--
-- Index pour la table `tblstock_forecasts`
--
ALTER TABLE `tblstock_forecasts`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `unique_product_date` (`ProductID`,`ForecastDate`);

--
-- Index pour la table `tblstock_movements`
--
ALTER TABLE `tblstock_movements`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `CreatedBy` (`CreatedBy`),
  ADD KEY `idx_product_date` (`ProductID`,`MovementDate`);

--
-- Index pour la table `tblsubcategory`
--
ALTER TABLE `tblsubcategory`
  ADD PRIMARY KEY (`ID`);

--
-- Index pour la table `tblsupplier`
--
ALTER TABLE `tblsupplier`
  ADD PRIMARY KEY (`ID`);

--
-- Index pour la table `tblsupplierpayments`
--
ALTER TABLE `tblsupplierpayments`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `SupplierID` (`SupplierID`);

--
-- Index pour la table `tbl_sms_logs`
--
ALTER TABLE `tbl_sms_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_recipient` (`recipient`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_date` (`send_date`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `tbladmin`
--
ALTER TABLE `tbladmin`
  MODIFY `ID` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `tblbrand`
--
ALTER TABLE `tblbrand`
  MODIFY `ID` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `tblcart`
--
ALTER TABLE `tblcart`
  MODIFY `ID` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT pour la table `tblcashtransactions`
--
ALTER TABLE `tblcashtransactions`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `tblcategory`
--
ALTER TABLE `tblcategory`
  MODIFY `ID` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `tblcreditcart`
--
ALTER TABLE `tblcreditcart`
  MODIFY `ID` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT pour la table `tblcustomer`
--
ALTER TABLE `tblcustomer`
  MODIFY `ID` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT pour la table `tblpayments`
--
ALTER TABLE `tblpayments`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `tblproductarrivals`
--
ALTER TABLE `tblproductarrivals`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT pour la table `tblproducts`
--
ALTER TABLE `tblproducts`
  MODIFY `ID` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=196;

--
-- AUTO_INCREMENT pour la table `tblreturns`
--
ALTER TABLE `tblreturns`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `tblstock_alerts`
--
ALTER TABLE `tblstock_alerts`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `tblstock_forecasts`
--
ALTER TABLE `tblstock_forecasts`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `tblstock_movements`
--
ALTER TABLE `tblstock_movements`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT pour la table `tblsubcategory`
--
ALTER TABLE `tblsubcategory`
  MODIFY `ID` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `tblsupplier`
--
ALTER TABLE `tblsupplier`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `tblsupplierpayments`
--
ALTER TABLE `tblsupplierpayments`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `tbl_sms_logs`
--
ALTER TABLE `tbl_sms_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `tblproductarrivals`
--
ALTER TABLE `tblproductarrivals`
  ADD CONSTRAINT `tblproductarrivals_ibfk_1` FOREIGN KEY (`ProductID`) REFERENCES `tblproducts` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tblproductarrivals_ibfk_2` FOREIGN KEY (`SupplierID`) REFERENCES `tblsupplier` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `tblstock_alerts`
--
ALTER TABLE `tblstock_alerts`
  ADD CONSTRAINT `tblstock_alerts_ibfk_1` FOREIGN KEY (`ProductID`) REFERENCES `tblproducts` (`ID`) ON DELETE CASCADE;

--
-- Contraintes pour la table `tblstock_forecasts`
--
ALTER TABLE `tblstock_forecasts`
  ADD CONSTRAINT `tblstock_forecasts_ibfk_1` FOREIGN KEY (`ProductID`) REFERENCES `tblproducts` (`ID`) ON DELETE CASCADE;

--
-- Contraintes pour la table `tblstock_movements`
--
ALTER TABLE `tblstock_movements`
  ADD CONSTRAINT `tblstock_movements_ibfk_1` FOREIGN KEY (`ProductID`) REFERENCES `tblproducts` (`ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `tblstock_movements_ibfk_2` FOREIGN KEY (`CreatedBy`) REFERENCES `tbladmin` (`ID`);

--
-- Contraintes pour la table `tblsupplierpayments`
--
ALTER TABLE `tblsupplierpayments`
  ADD CONSTRAINT `tblsupplierpayments_ibfk_1` FOREIGN KEY (`SupplierID`) REFERENCES `tblsupplier` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
