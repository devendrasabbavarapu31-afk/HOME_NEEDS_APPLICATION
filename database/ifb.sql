-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 20, 2026 at 06:30 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ifb`
--

-- --------------------------------------------------------

--
-- Table structure for table `counter_sales`
--

CREATE TABLE `counter_sales` (
  `id` int(11) NOT NULL,
  `bill_no` varchar(50) NOT NULL,
  `customer_name` varchar(150) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_mode` enum('cash','upi','card','mixed') NOT NULL DEFAULT 'upi',
  `cashier_name` varchar(100) NOT NULL DEFAULT 'Counter Staff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `counter_sales`
--

INSERT INTO `counter_sales` (`id`, `bill_no`, `customer_name`, `customer_phone`, `subtotal`, `discount`, `grand_total`, `payment_mode`, `cashier_name`, `created_at`) VALUES
(1, 'SGHN-260910-3280', 'devendra', '7893282348', 186700.00, 0.00, 186700.00, 'cash', 'Desk Counter', '2026-09-18 16:22:46'),
(2, 'SGHN-260910-2319', 'devendra', '7893282348', 186700.00, 0.00, 186700.00, 'cash', 'Desk Counter', '2026-09-18 16:24:37'),
(3, 'SGHN-260910-6447', 'devendra', '7893282348', 186700.00, 0.00, 186700.00, 'cash', 'Desk Counter', '2026-09-18 05:25:01'),
(4, 'SGHN-260918-6593', 'Walk-in Customer', '', 31999.91, 3199.99, 28799.92, 'upi', '1001', '2026-09-18 17:22:52'),
(5, 'SGHN-260918-1827', 'Walk-in Customer', '', 31999.91, 3199.99, 28799.92, 'upi', '1001', '2026-09-18 17:25:20'),
(6, 'SGHN-260918-1961', 'devendra', '7893282348', 21999.00, 0.00, 21999.00, 'cash', '1001', '2026-09-18 17:26:06'),
(7, 'SGHN-260918-7767', 'devendra', '7893282348', 21999.00, 0.00, 21999.00, 'cash', '1001', '2026-09-18 17:29:18'),
(8, 'SGHN-260918-1388', 'devendra', '7893282348', 31999.91, 3199.99, 28799.92, 'upi', '1001', '2026-09-18 17:36:01'),
(9, 'SGHN-260918-5527', 'devendra', '7893282348', 31999.91, 3199.99, 28799.92, 'upi', '1001', '2026-09-18 17:39:57'),
(10, 'SGHN-260918-1203', 'devendra', '7893282348', 18000.00, 0.00, 18000.00, 'cash', '123', '2026-09-18 18:39:33'),
(11, 'SGHN-260918-6892', 'devendra', '7893282348', 18000.00, 0.00, 18000.00, 'cash', '123', '2026-09-18 19:08:37');

-- --------------------------------------------------------

--
-- Table structure for table `counter_sale_items`
--

CREATE TABLE `counter_sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `intake_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `model` varchar(150) NOT NULL,
  `company` varchar(150) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `counter_sale_items`
--

INSERT INTO `counter_sale_items` (`id`, `sale_id`, `intake_id`, `product_name`, `model`, `company`, `quantity`, `unit_price`, `line_total`) VALUES
(1, 1, 5, 'WASHING MACHINE', 'FRONT LOAD', 'IFB', 1, 66700.00, 66700.00),
(2, 1, 6, 'TV', 'LG 3FD', 'LG', 1, 120000.00, 120000.00),
(3, 2, 5, 'WASHING MACHINE', 'FRONT LOAD', 'IFB', 1, 66700.00, 66700.00),
(4, 2, 6, 'TV', 'LG 3FD', 'LG', 1, 120000.00, 120000.00),
(5, 3, 5, 'WASHING MACHINE', 'FRONT LOAD', 'IFB', 1, 66700.00, 66700.00),
(6, 3, 6, 'TV', 'LG 3FD', 'LG', 1, 120000.00, 120000.00),
(7, 4, 5, 'WASHING MACHINE', 'FRONT LOAD', 'IFB', 1, 28799.92, 28799.92),
(8, 5, 5, 'WASHING MACHINE', 'FRONT LOAD', 'IFB', 1, 28799.92, 28799.92),
(9, 6, 13, 'SOFA', 'FURNY Woodswave 5 Seater Sofa Set', 'FURNY Woodswave', 1, 21999.00, 21999.00),
(10, 7, 13, 'SOFA', 'FURNY Woodswave 5 Seater Sofa Set', 'FURNY Woodswave', 1, 21999.00, 21999.00),
(11, 8, 5, 'WASHING MACHINE', 'FRONT LOAD', 'IFB', 1, 28799.92, 28799.92),
(12, 9, 5, 'WASHING MACHINE', 'FRONT LOAD', 'IFB', 1, 28799.92, 28799.92),
(13, 10, 7, 'single doar refregirator', 'LG GL-B281BSAX', 'LG', 1, 18000.00, 18000.00),
(14, 11, 7, 'single doar refregirator', 'LG GL-B281BSAX', 'LG', 1, 18000.00, 18000.00);

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `phone`, `password`, `created_at`) VALUES
(1, 'DEVENDRA', '7893282348', '$2y$10$AVhjg6lmweBjli0eAhBdn.zDGw6QwiACn6yyKQoFT2xzYzJ9TPQba', '2026-09-07 15:46:49'),
(3, 'SK. NOGOORVALLI', '9652487042', '$2y$10$HVqLW7tGogi6fMNcadcJYeyYYx3uiHW9IN2HnkLKfgtKEHUGof2HW', '2026-09-18 19:41:35');

-- --------------------------------------------------------

--
-- Table structure for table `customer_wishlist`
--

CREATE TABLE `customer_wishlist` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `public_product_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_wishlist`
--

INSERT INTO `customer_wishlist` (`id`, `customer_id`, `public_product_id`, `created_at`) VALUES
(4, 3, 15, '2026-09-18 19:41:44');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `category` enum('Staff Salary','Showroom Rent','Electricity / Power','Labour / Unloading','Store Maintenance','Website & Tech','Transport / Freight','Other') NOT NULL,
  `title` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `expense_date` date NOT NULL,
  `payment_mode` enum('cash','upi','bank_transfer','cheque') NOT NULL DEFAULT 'upi',
  `recipient_name` varchar(150) DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `receipt_photo` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `category`, `title`, `amount`, `expense_date`, `payment_mode`, `recipient_name`, `reference_no`, `receipt_photo`, `notes`, `created_at`) VALUES
(1, 'Staff Salary', 'september salary for staff', 30000.00, '2026-09-18', 'cash', 'eveyone', 'df', NULL, 'lkciuw', '2026-09-18 06:22:26');

-- --------------------------------------------------------

--
-- Table structure for table `logins`
--

CREATE TABLE `logins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','counter') NOT NULL DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `logins`
--

INSERT INTO `logins` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(3, '143', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'admin', '2026-09-05 08:26:16'),
(5, '123', '$2y$10$8L/DFdsuKT9X/t9w39raeeznDIc1YeZfzVXNuM9Szei/VGpg/f.aC', 'admin', '2026-09-05 08:30:56'),
(7, '1001', '$2y$10$Cltn3orLRf5sNfD2cY3dZ.PEIMIszjWnHZEltm63p1JpWemvVT2se', 'counter', '2026-09-18 05:39:21');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `model` varchar(150) NOT NULL,
  `category` varchar(100) NOT NULL DEFAULT 'Appliances',
  `specifications` text DEFAULT NULL,
  `company` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `model`, `category`, `specifications`, `company`, `created_at`) VALUES
(3, 'TV', 'LG 3FD', 'Appliances', NULL, 'LG', '2026-09-05 08:50:14'),
(4, 'WASHING MACHINE', 'FRONT LOAD', 'Appliances', NULL, 'IFB', '2026-09-05 09:14:53'),
(5, 'WASHING MACHINE', 'FRONT LOAD', 'Appliances', NULL, 'WHIRPOOL', '2026-09-05 09:33:04'),
(6, 'HOME THEATRE', 'IG480', 'Other', ',MCNKJCBASBC', 'SAMSAUNG', '2026-09-11 05:19:40'),
(7, 'single doar refregirator', 'LG GL-B281BSAX', 'Refrigerators', 'CAPACITY-261-L\r\nFINISH-SCARLET AURORA\r\nSMART INNOVATOR COMPRESSOR\r\nRATING :3 Star\r\n\r\ncolour :floaral /abstract pink red design', 'LG', '2026-09-18 06:26:47'),
(8, 'SINGLE DOOR REFRIGIRATOR', 'DAS9246IAGVMC', 'Refrigerators', '192L DIRECT COOL SINGLE DOOR ,4 STAR,SMART UI PANEL', 'WHIRLPOOL', '2026-09-18 06:39:53'),
(9, 'SOFA', 'L SHAPE SOFA SET', 'Other', 'Adorn India Premium Luster 8 Seater L Shape Sofa Set with 2 Puffy & 1 Center Table | Velvet Suede Fabric | 3-Year Warranty | RHS | Grey with Golden Legs & Striped Pattern Cushion\r\nStyle Name:\r\nLuster', 'ADORN INDIA PREMIUM LUSTER 8 SEATER', '2026-09-18 09:40:08'),
(10, 'GAS STOVE', '4 BURNER GLASS TOP', 'Small Appliances', 'Butterfly Alpha Max 4 Burner Glass Top Gas Stove | Removable Drip Tray | Brass Burner Gas Stove with Tri‑Flow Jumbo Burner| 5 Years Warranty', 'BUTTERFLY ALFA', '2026-09-18 09:45:09'),
(11, 'SOFA', 'RX7 DESIGNED BY DUROFLEX', 'Other', 'Brand	Sleepyhead\r\nColour	Egyptian Brown\r\nMaterial	Polyester\r\nProduct Dimensions	81.2D x 88.9W x 99H Centimeters\r\nSize	Motorized\r\nItem Weight	60.5 Kilograms\r\nBack Style	Solid Back\r\nFrame Material	Wood\r\nProduct Care Instructions	Dry Clean\r\nNet Quantity	1.00 Piece', 'Sleepyhead', '2026-09-18 09:54:35'),
(12, 'TV', '43NANO83A6A', 'Other', 'Screen Size	43 Inches\r\nBrand	LG\r\nDisplay Technology	LED\r\nResolution	4K\r\nRefresh Rate	60 Hz\r\nSpecial Feature	4K Super Upscaling, AI HDR Remastering, AI Sound Pro, Dolby Atmos, LG Shield\r\nIncluded Components	1 NANO 4K UHD TV, 1 Standard Remote with 2 AAA Battery, 1 User Manual, 1 Warranty Card\r\nConnectivity Technology	Ethernet, HDMI, RF, USB, Wi-Fi\r\nAspect Ratio	16:9\r\nProduct Dimensions	7.1D x 95.9W x 56.3H Centimeters', 'LG', '2026-09-18 09:59:59'),
(13, 'SOFA', 'FURNY Woodswave 5 Seater Sofa Set', 'Other', 'Brand	FURNY\r\nAssembly Required	No\r\nSeat Depth	76.2 Centimeters\r\nSeat Height	45 Inches\r\nWeight Limit	500 Kilograms\r\nSeating Capacity	5.0\r\nProduct Dimensions	78.7D x 188W x 81.3H Centimeters\r\nItem Weight	85 Kilograms\r\nLeg Length	2 Inches\r\nType	Standard', 'FURNY Woodswave', '2026-09-18 10:04:11'),
(14, 'TV', 'UA50UE83AHULXL', 'Other', 'Screen Size	50 Inches\r\nBrand	Samsung\r\nDisplay Technology	LED\r\nResolution	4K\r\nRefresh Rate	50 Hz\r\nSpecial Feature	Color Booster | Contrast Enhancer | PurColor, Crystal Processor 4K | Vision AI Companion | Football Mode | 4K Upscaling | MetalStream Design | Samsung Knox Security | Over 150+ Free Channels | Voice AssistantColor Booster | Contrast Enhancer | PurColor, Crystal Processor 4K | Vision AI Companion | Football Mode | 4K Upscaling | MetalStream Design | Samsung Knox Security | Over 150+ Free Channels | Voice Assistant\r\nIncluded Components	1Number LED TV, 2Numbers BATTERY (AAA Size), 1Number POWER CORD, 1Number REMOCON\r\nConnectivity Technology	Ethernet, HDMI, USB, Wi-Fi\r\nAspect Ratio	16:9\r\nSupported Internet Services	Hotstar, Netflix, Prime Video, SonyLiv, YouTube', 'SAMSAUNG', '2026-09-18 15:35:53'),
(15, 'TV', 'L55MB-AIN', 'Other', 'Xiaomi (139 cm) 55 inch X 4K Ultra HD Smart | Dolby Vision | 120Hz Game Booster | Eye Care Mode | Google TV | L55MB-AIN', 'Xiaomi', '2026-09-18 15:39:12'),
(16, 'WASHING MACHINE', 'T80VBMB4Z', 'Washing Machines', 'Product Dimensions	56D x 54W x 92.5H Centimeters\r\nBrand	LG\r\nCapacity	8 kg\r\nSpecial Feature	Auto Restart, Child Lock, High Efficiency, Inverter, LED Display\r\nAccess Location	Top Load', 'LG', '2026-09-18 16:43:34'),
(17, 'HOME THEATRE', 'Nex 450', 'Small Appliances', 'Mivi Nex 450 Soundbar [New Launch], 450W 5.1 Channel System with 3 in-Built Speakers, 2 Satellite Speakers and a Subwoofer, Nex Surround Feel Technology, Multiple Input Modes, Made in India', 'Mivi', '2026-09-18 16:51:06'),
(18, 'HOME THEATRE', 'Nex 450', 'Washing Machines', 'full specified for home', 'boat', '2026-09-19 05:26:29'),
(19, 'HOME THEATRE', 'Nex 450', 'Washing Machines', '', 'IFB', '2026-09-19 09:31:18');

-- --------------------------------------------------------

--
-- Table structure for table `public_products`
--

CREATE TABLE `public_products` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `company` varchar(150) NOT NULL,
  `name` varchar(255) NOT NULL,
  `model` varchar(150) NOT NULL,
  `selling_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(5,2) NOT NULL DEFAULT 0.00,
  `offer` varchar(255) DEFAULT NULL,
  `specifications` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT 'default.jpg',
  `status` enum('active','hidden') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `public_products`
--

INSERT INTO `public_products` (`id`, `product_id`, `company`, `name`, `model`, `selling_price`, `discount`, `offer`, `specifications`, `photo`, `status`, `created_at`) VALUES
(4, 7, 'LG', 'single doar refregirator', 'LG GL-B281BSAX', 18000.00, 8.00, '2 YEARS WARRANTY', 'SINGLE DOOR,3 STAR ,23-L', 'uploads/1789713121_LGSINGLEDOOR1.jpeg', 'active', '2026-09-18 06:32:01'),
(5, 8, 'WHIRLPOOL', 'SINGLE DOOR REFRIGIRATOR', 'DAS9246IAGVMC', 26000.00, 10.00, 'VINAYAKA CHAVITHI OFFER', '2+2 YEARS WARRANTY', 'uploads/1789713784_WHIRLPOOLFRIDGE-2.jpeg', 'active', '2026-09-18 06:43:04'),
(6, 9, 'ADORN INDIA PREMIUM LUSTER 8 SEATER', 'SOFA', 'L SHAPE SOFA SET', 25000.00, 8.00, 'BIG BILLION SALE', '6 LAYERS WOOL', 'uploads/1789724632_SOFA1.jpeg', 'active', '2026-09-18 09:43:52'),
(7, 10, 'BUTTERFLY ALFA', 'GAS STOVE', '4 BURNER GLASS TOP', 18999.98, 5.00, '2years warranty', 'Butterfly Alpha Max 4 Burner Glass Top Gas Stove | Removable Drip Tray | Brass Burner Gas Stove with Tri‑Flow Jumbo Burner| 5 Years Warranty', 'uploads/1789725158_gasstove-1.jpeg', 'active', '2026-09-18 09:48:11'),
(9, 11, 'Sleepyhead', 'SOFA', 'RX7 DESIGNED BY DUROFLEX', 14000.00, 12.00, 'LUXURY COMFORT', 'Brand	Sleepyhead\r\nColour	Egyptian Brown\r\nMaterial	Polyester\r\nProduct Dimensions	81.2D x 88.9W x 99H Centimeters\r\nSize	Motorized\r\nItem Weight	60.5 Kilograms\r\nBack Style	Solid Back\r\nFrame Material	Wood\r\nProduct Care Instructions	Dry Clean', 'uploads/1789725456_SOFA2.jpeg', 'active', '2026-09-18 09:57:36'),
(10, 12, 'LG', 'TV', '43NANO83A6A', 48000.00, 0.00, '2 YEAR WARRANTY + CUSTOMER CARE', 'Screen Size	43 Inches\r\nBrand	LG\r\nDisplay Technology	LED\r\nResolution	4K\r\nRefresh Rate	60 Hz\r\nSpecial Feature	4K Super Upscaling, AI HDR Remastering, AI Sound Pro, Dolby Atmos, LG Shield\r\nIncluded Components	1 NANO 4K UHD TV, 1 Standard Remote with 2 AAA Battery, 1 User Manual, 1 Warranty Card\r\nConnectivity Technology	Ethernet, HDMI, RF, USB, Wi-Fi\r\nAspect Ratio	16:9\r\nProduct Dimensions	7.1D x 95.9W x 56.3H Centimeters', 'uploads/1789725783_TV-1.jpeg', 'active', '2026-09-18 10:03:03'),
(11, 13, 'FURNY Woodswave', 'SOFA', 'FURNY Woodswave 5 Seater Sofa Set', 21999.00, 7.00, '2 YEARS WARRANTY AND CUSTOMER SUPPORT', 'Brand	FURNY\r\nAssembly Required	No\r\nSeat Depth	76.2 Centimeters\r\nSeat Height	45 Inches\r\nWeight Limit	500 Kilograms\r\nSeating Capacity	5.0\r\nProduct Dimensions	78.7D x 188W x 81.3H Centimeters\r\nItem Weight	85 Kilograms\r\nLeg Length	2 Inches\r\nType	Standard', 'uploads/1789726014_SOFA-3.jpeg', 'active', '2026-09-18 10:06:54'),
(12, 14, 'SAMSAUNG', 'TV', 'UA50UE83AHULXL', 56982.00, 5.00, '2 YEARS WARRANTY AND CUSTOMER SUPPORT', 'Screen Size	50 Inches\r\nBrand	Samsung\r\nDisplay Technology	LED\r\nResolution	4K\r\nRefresh Rate	50 Hz\r\nSpecial Feature	Color Booster | Contrast Enhancer | PurColor, Crystal Processor 4K | Vision AI Companion | Football Mode | 4K Upscaling | MetalStream Design | Samsung Knox Security | Over 150+ Free Channels | Voice AssistantColor Booster | Contrast Enhancer | PurColor, Crystal Processor 4K | Vision AI Companion | Football Mode | 4K Upscaling | MetalStream Design | Samsung Knox Security | Over 150+ Free Channels | Voice Assistant\r\nIncluded Components	1Number LED TV, 2Numbers BATTERY (AAA Size), 1Number POWER CORD, 1Number REMOCON\r\nConnectivity Technology	Ethernet, HDMI, USB, Wi-Fi\r\nAspect Ratio	16:9\r\nSupported Internet Services	Hotstar, Netflix, Prime Video, SonyLiv, YouTube', 'uploads/1789745870_TV-2.jpeg', 'active', '2026-09-18 15:37:50'),
(13, 15, 'Xiaomi', 'TV', 'L55MB-AIN', 53000.00, 2.00, '2 YEARS WARRANTY AND CUSTOMER SUPPORT', 'Screen Size	55 Inches\r\nBrand	XIAOMI\r\nDisplay Technology	LED\r\nResolution	4K\r\nRefresh Rate	120 Hz, 60 Hz\r\nSpecial Feature	Filmmaker Mode, MEMC, Eye Comfort Mode\r\nIncluded Components	‎1 LED TV, 1 Remote Control, 2 Stands Unit, 4 Screws, 2 x AAA batteries, 1 Power Cord\r\nConnectivity Technology	Ethernet, HDMI, USB, Wi-Fi\r\nAspect Ratio	16:9\r\nProduct Dimensions	31.2D x 122.6W x 77H Centimeters', 'uploads/1789746074_TV-3.jpeg', 'active', '2026-09-18 15:41:14'),
(14, 6, 'SAMSAUNG', 'HOME THEATRE', 'IG480', 19000.00, 7.00, '1year warranty festive offer', 'Brand	Samsung\r\nSpeaker Maximum Output Power	300 Watts\r\nConnectivity Technology	Bluetooth, HDMI, Optical, USB\r\nAudio Output Mode	Surround\r\nInput Voltage	240 Volts', 'uploads/1789747699_hometheatre-1.jpeg', 'active', '2026-09-18 16:08:19'),
(15, 4, 'IFB', 'WASHING MACHINE', 'FRONT LOAD', 31999.91, 10.00, '1year warranty+customer care support', 'Product Dimensions	51.8D x 59.8W x 87.5H Centimeters\r\nBrand	IFB\r\nCapacity	7 kg\r\nSpecial Feature\r\n\r\n9 Swirl Wash (Mimic Handwashing), Aqua Energie (Treats hard water), Auto-Load Sensing, Eco Inverter, Power Steam®, Powered by Ai, Steam Refresh (Rejuvenates clothes, no detergent, no water required), WiFi / Voice Enabled\r\nAccess Location	Front Load', 'uploads/1789748128_wash-1.jpeg', 'active', '2026-09-18 16:15:28'),
(16, 16, 'LG', 'WASHING MACHINE', 'T80VBMB4Z', 34876.00, 0.00, '2 year warranty', 'LG 8 Kg 5 Star Smart Inverter Technology Fully Automatic Top Load Washing Machine (T80VBMB4Z, Turbodrum, Auto Prewash, Stainless Steel drum, LED Display, Smart Diagnosis, Middle Black)', 'uploads/1789750110_wash-2.jpeg', 'active', '2026-09-18 16:48:30'),
(17, 17, 'Mivi', 'HOME THEATRE', 'Nex 450', 12000.00, 2.00, '2 year warranty', 'Brand	Mivi\r\nSpeaker Maximum Output Power	450 Watts\r\nFrequency Response	35 KHz\r\nConnectivity Technology	Bluetooth, Coaxial, HDMI, Optical, USB\r\nAudio Output Mode	Surround', 'uploads/1789750405_hometheatre-2jpeg.jpeg', 'active', '2026-09-18 16:53:25');

-- --------------------------------------------------------

--
-- Table structure for table `staff_attendance`
--

CREATE TABLE `staff_attendance` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `punch_type` enum('in','out') NOT NULL,
  `punch_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `location_verified` tinyint(1) NOT NULL DEFAULT 1,
  `is_concession` tinyint(1) NOT NULL DEFAULT 0,
  `concession_reason` varchar(255) DEFAULT NULL,
  `captured_photo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_attendance`
--

INSERT INTO `staff_attendance` (`id`, `staff_id`, `punch_type`, `punch_time`, `latitude`, `longitude`, `location_verified`, `is_concession`, `concession_reason`, `captured_photo`) VALUES
(1, 1, 'in', '2026-09-11 08:16:52', 17.66900000, 82.61200000, 1, 0, NULL, NULL),
(2, 3, 'in', '2026-09-18 05:58:34', 17.66900000, 82.61200000, 1, 0, NULL, NULL),
(3, 3, 'out', '2026-09-18 06:14:48', 17.66900000, 82.61200000, 1, 0, NULL, NULL),
(4, 1, 'in', '2026-09-18 06:14:58', 17.66900000, 82.61200000, 1, 1, '', NULL),
(5, 4, 'in', '2026-09-19 10:47:53', 17.66900000, 82.61200000, 1, 0, NULL, NULL),
(6, 1, 'in', '2026-09-19 10:48:23', 17.66900000, 82.61200000, 1, 0, NULL, NULL),
(7, 5, 'in', '2026-09-19 10:49:38', 17.66900000, 82.61200000, 1, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `staff_members`
--

CREATE TABLE `staff_members` (
  `id` int(11) NOT NULL,
  `staff_code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `designation` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `face_descriptor` longtext DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_members`
--

INSERT INTO `staff_members` (`id`, `staff_code`, `name`, `designation`, `phone`, `face_descriptor`, `is_active`, `created_at`) VALUES
(1, '56105', 'ADITYA', 'counter', '9100584368', '[-0.12024681270122528,0.09948507696390152,0.11558375507593155,0.02410859987139702,-0.09148487448692322,-0.01761256530880928,-0.039866287261247635,-0.019780224189162254,0.13171440362930298,-0.04107724130153656,0.2072092592716217,-0.004164140205830336,-0.2059858739376068,-0.07070954889059067,-0.018663354218006134,0.09107854962348938,-0.09701205790042877,-0.11588548123836517,-0.15405409038066864,-0.1437164843082428,0.059400659054517746,0.036990515887737274,-0.02361319214105606,-0.03376167267560959,-0.15499579906463623,-0.3254874348640442,-0.05722171440720558,-0.09716805070638657,0.12862801551818848,-0.1415017545223236,-0.01491908822208643,-0.017072943970561028,-0.1392287015914917,-0.029380572959780693,-0.0005950857885181904,0.07275993376970291,-0.028473634272813797,-0.06905483454465866,0.17835257947444916,-0.033857543021440506,-0.178656667470932,0.011694359593093395,0.00843131449073553,0.26436111330986023,0.21355582773685455,0.026943964883685112,0.03067593462765217,-0.08305767178535461,0.13437899947166443,-0.23669128119945526,0.07421784847974777,0.20649749040603638,0.06935086846351624,0.09957055747509003,0.12340793013572693,-0.1315084993839264,-0.010190931148827076,0.15465085208415985,-0.11846201866865158,0.06320073455572128,0.06885778158903122,-0.04837076738476753,-0.06460042297840118,-0.1191968023777008,0.12174401432275772,0.08947142213582993,-0.10217328369617462,-0.15807844698429108,0.09432142227888107,-0.1948716789484024,-0.04330937936902046,0.06346386671066284,-0.10135795921087265,-0.1593666672706604,-0.27393168210983276,0.09182881563901901,0.44616466760635376,0.1912360042333603,-0.23040303587913513,0.012111379764974117,-0.04147227481007576,-0.002234421903267503,0.15018221735954285,0.048959266394376755,-0.11632252484560013,-0.010043736547231674,-0.10414494574069977,0.010020057670772076,0.188692107796669,-0.001212600152939558,0.008075232617557049,0.15233777463436127,0.010598591528832912,0.04531484469771385,0.016201330348849297,0.004870118107646704,-0.1359480619430542,-0.0016596124041825533,-0.08354300260543823,-0.01879238523542881,0.06117827817797661,-0.08871524780988693,0.023109043017029762,0.06503012776374817,-0.11432978510856628,0.18706223368644714,0.013929752632975578,0.03996620327234268,0.010886003263294697,0.012124519795179367,-0.082049660384655,-0.009050896391272545,0.19001516699790955,-0.18574313819408417,0.19933362305164337,0.1836240589618683,0.011071094311773777,0.1455545276403427,0.14112085103988647,0.12052248418331146,-0.03392387926578522,0.02755759097635746,-0.13567538559436798,-0.04004938155412674,-0.0031708008609712124,-0.051175806671381,0.015700746327638626,-0.004710386041551828]', 1, '2026-09-11 08:10:16'),
(3, '1001', 'hemanth', 'manager', '9618080704', '[-0.06997162848711014,0.13974471390247345,0.08420286327600479,0.026391657069325447,-0.04320899769663811,-0.04321548715233803,-0.04197082668542862,-0.10350397974252701,0.1349543184041977,-0.09911122918128967,0.2136816531419754,-0.019569646567106247,-0.3073766827583313,-0.03432587534189224,0.02911279909312725,0.07392401993274689,-0.19711941480636597,-0.11488793790340424,-0.129628524184227,-0.10794443637132645,0.02883673459291458,0.039825547486543655,0.0052996291778981686,-0.0075987898744642735,-0.10453425347805023,-0.29975414276123047,-0.05853598192334175,-0.05533690005540848,0.08981306850910187,-0.07884692400693893,-0.01487458311021328,0.04145457223057747,-0.1678910255432129,-0.02014087326824665,0.012339407578110695,0.05267186835408211,-0.021097766235470772,-0.08240096271038055,0.17703360319137573,-0.027505094185471535,-0.1418994963169098,-0.07900934666395187,0.031327247619628906,0.25578573346138,0.1840820610523224,0.029555179178714752,0.016605790704488754,-0.06733348220586777,0.10685427486896515,-0.26287248730659485,0.016522279009222984,0.21968954801559448,0.09453658014535904,0.1493118852376938,0.03496073558926582,-0.2255735844373703,0.009980907663702965,0.12876534461975098,-0.10793765634298325,0.0878322571516037,0.06567033380270004,-0.11546198278665543,-0.06545824557542801,-0.07568652182817459,0.22432270646095276,0.06186849996447563,-0.1345352977514267,-0.1745162159204483,0.1105155274271965,-0.16904360055923462,-0.1074029728770256,0.10979622602462769,-0.1334279179573059,-0.16699007153511047,-0.2596077620983124,0.008178513497114182,0.42122897505760193,0.1349114626646042,-0.16950686275959015,0.01927434653043747,-0.13160935044288635,-0.0366620309650898,0.06285380572080612,0.09040888398885727,-0.0648965835571289,-0.014818646013736725,-0.09642494469881058,0.028688324615359306,0.2417660653591156,-0.024912714958190918,-0.06166909262537956,0.20577923953533173,-0.007981196977198124,0.047427039593458176,-0.021028460934758186,-0.009142892435193062,-0.053063541650772095,-0.009817192330956459,-0.08688075095415115,-0.03585435822606087,0.05703696981072426,-0.13023024797439575,-0.03399984538555145,0.07824226468801498,-0.19889727234840393,0.1906181275844574,0.05580158159136772,0.06151866540312767,0.07611998170614243,-0.014031896367669106,-0.09823299944400787,0.0016262891003862023,0.2047271728515625,-0.32245662808418274,0.24926280975341797,0.2318372279405594,0.08285295963287354,0.15886235237121582,0.03249223902821541,0.11327154189348221,0.0051327841356396675,-0.0264228954911232,-0.16213926672935486,-0.10522180050611496,0.00893369410187006,-0.01954120211303234,-0.009226329624652863,0.011347216553986073]', 1, '2026-09-18 05:26:05'),
(4, '1002', 'devendra', 'ceo', '948978895', '[-0.1573941558599472,0.12964960932731628,0.09063214808702469,-0.032404374331235886,0.007464989088475704,-0.06048397719860077,0.03716375678777695,-0.07046995311975479,0.13556495308876038,-0.0310523584485054,0.23155620694160461,-0.05905807018280029,-0.24606865644454956,-0.1897880882024765,0.026101820170879364,0.13259540498256683,-0.11565078049898148,-0.1562279462814331,-0.10668177902698517,-0.11117716878652573,0.0041716499254107475,0.01274841371923685,0.02481846511363983,0.05073387920856476,-0.07701016962528229,-0.4153912365436554,-0.09696616232395172,-0.09521015733480453,0.028510551899671555,-0.05963757634162903,-0.012993626296520233,0.0638452097773552,-0.17161186039447784,-0.04838735610246658,-0.06438127160072327,0.11252868920564651,0.027849791571497917,0.023483315482735634,0.1342460662126541,0.037545543164014816,-0.14370933175086975,-0.09666179120540619,-0.02745332196354866,0.3039255142211914,0.2080337256193161,0.02636501006782055,0.06461160629987717,0.0480932854115963,-0.004551100544631481,-0.25102633237838745,-0.00004780710878549144,0.14741353690624237,0.14107747375965118,0.026217887178063393,0.056575607508420944,-0.13633540272712708,-0.04567033424973488,0.05151114612817764,-0.1456584334373474,0.006229093298316002,-0.022945908829569817,-0.12460533529520035,-0.05310898274183273,0.00995224341750145,0.2778538763523102,0.09113786369562149,-0.10830432176589966,-0.08240201324224472,0.2241698056459427,-0.11936824768781662,0.03274539113044739,0.0826299712061882,-0.1065322607755661,-0.13533110916614532,-0.2791428864002228,0.14140981435775757,0.4396180212497711,0.07165107131004333,-0.19226768612861633,-0.006007045041769743,-0.20331218838691711,0.025003822520375252,0.028758658096194267,-0.028901178389787674,-0.13922494649887085,0.04860275238752365,-0.23096995055675507,0.03063751757144928,0.1468755006790161,-0.04213304445147514,-0.02797212451696396,0.15705841779708862,-0.018141375854611397,0.05651480704545975,0.05846139043569565,0.051162201911211014,-0.050598304718732834,0.0214601531624794,-0.10290735214948654,-0.010492630302906036,0.11517468839883804,-0.14074823260307312,-0.00550493411719799,0.06465496122837067,-0.13986220955848694,0.10756514966487885,0.03305232524871826,-0.03734154999256134,-0.03861512988805771,0.031340133398771286,-0.09568021446466446,-0.08072312921285629,0.1841716319322586,-0.21535742282867432,0.13056641817092896,0.21614518761634827,-0.06334307789802551,0.18685762584209442,0.052500106394290924,0.11631114035844803,-0.06975310295820236,-0.08968418836593628,-0.057416386902332306,-0.04997545853257179,0.04688079655170441,0.010483813472092152,0.014880307950079441,0.04495590180158615]', 1, '2026-09-19 10:47:40'),
(5, '12345', 'ram', 's', '32423543243', '[-0.13574671745300293,0.15523168444633484,0.07175932079553604,-0.008703137747943401,-0.003766494570299983,-0.009211698547005653,0.05005432292819023,-0.10495342314243317,0.1637921929359436,-0.0724998340010643,0.32633650302886963,-0.09771508723497391,-0.1957421749830246,-0.22989879548549652,0.03140195459127426,0.1600024253129959,-0.14557822048664093,-0.1979231983423233,-0.061634309589862823,-0.10918589681386948,0.0521443746984005,-0.036480873823165894,0.014768976718187332,0.0406830720603466,-0.17654845118522644,-0.4214712977409363,-0.07936976850032806,-0.1322755217552185,0.016167934983968735,-0.16031427681446075,-0.009883817285299301,0.07427927106618881,-0.19544142484664917,-0.06691838800907135,-0.0501883402466774,0.07604943215847015,-0.023694388568401337,-0.033678121864795685,0.16886469721794128,0.020985854789614677,-0.14917497336864471,-0.05339096486568451,0.019137544557452202,0.31863051652908325,0.18617010116577148,-0.01854747161269188,0.021099276840686798,0.017619125545024872,0.03778911381959915,-0.21630212664604187,0.066599041223526,0.12815484404563904,0.11682736128568649,0.08242794871330261,0.0965246856212616,-0.10257503390312195,-0.05973506718873978,0.03688831254839897,-0.15638110041618347,0.015629924833774567,-0.0026013024616986513,-0.06989430636167526,-0.05154912918806076,-0.024367183446884155,0.25738948583602905,0.15722361207008362,-0.07478825747966766,-0.13068757951259613,0.17556023597717285,-0.07940617948770523,-0.028805211186408997,0.06434265524148941,-0.11917001008987427,-0.16435033082962036,-0.2502208650112152,0.13663926720619202,0.4171375632286072,0.08335014432668686,-0.22499226033687592,0.016014447435736656,-0.10434070229530334,-0.026489853858947754,0.08400236070156097,0.0694308876991272,-0.05533045530319214,0.06781720370054245,-0.1380905658006668,0.05593932420015335,0.11629226803779602,-0.021437060087919235,-0.09263310581445694,0.21342764794826508,-0.07650110125541687,0.033981066197156906,0.09548565745353699,0.004811967723071575,-0.08014064282178879,0.07396216690540314,-0.11442330479621887,-0.06356014311313629,0.1039639487862587,-0.0530194528400898,-0.016652150079607964,0.081485316157341,-0.09128823131322861,0.11465184390544891,0.029918495565652847,0.0383593775331974,-0.021506203338503838,0.03548760339617729,-0.0895327478647232,-0.06346976011991501,0.0979723185300827,-0.2632756531238556,0.1632400006055832,0.12702704966068268,-0.054298363626003265,0.16624990105628967,0.08769788593053818,0.08129286020994186,0.02440457046031952,-0.046810634434223175,-0.10558662563562393,0.006468650884926319,0.07426418364048004,0.028887875378131866,0.02570730447769165,0.015661397948861122]', 1, '2026-09-19 10:49:05');

-- --------------------------------------------------------

--
-- Table structure for table `stock_intake`
--

CREATE TABLE `stock_intake` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `company` varchar(150) NOT NULL,
  `name` varchar(255) NOT NULL,
  `model` varchar(150) NOT NULL,
  `vendor_name` varchar(150) NOT NULL DEFAULT 'Main Distributor',
  `invoice_number` varchar(100) DEFAULT NULL,
  `bill_photo` varchar(255) DEFAULT NULL,
  `specifications` text DEFAULT NULL,
  `stock_pieces` int(11) NOT NULL DEFAULT 0,
  `remaining_pieces` int(11) NOT NULL DEFAULT 0,
  `per_piece_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `counter_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('paid','partial','pending') NOT NULL DEFAULT 'pending',
  `status` enum('in_stock','out_of_stock') NOT NULL DEFAULT 'in_stock',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_intake`
--

INSERT INTO `stock_intake` (`id`, `product_id`, `company`, `name`, `model`, `vendor_name`, `invoice_number`, `bill_photo`, `specifications`, `stock_pieces`, `remaining_pieces`, `per_piece_price`, `counter_price`, `total_amount`, `paid_amount`, `payment_status`, `status`, `created_at`) VALUES
(5, 4, 'IFB', 'WASHING MACHINE', 'FRONT LOAD', 'IFB INFO .IN', 'FSGSG', NULL, 'CXSBB', 10, 3, 58000.00, 0.00, 580000.00, 100000.00, 'partial', 'in_stock', '2026-09-10 15:56:47'),
(6, 3, 'LG', 'TV', 'LG 3FD', 'IFB INFO .IN', 'uihuhu', 'uploads/bills/bill_1789056854_WhatsAppImage2026-09-08at9.28.46AM.jpeg', 'jvjhvuvgv', 10, 7, 10000.00, 120000.00, 100000.00, 100000.00, 'paid', 'in_stock', '2026-09-10 16:14:14'),
(7, 7, 'LG', 'single doar refregirator', 'LG GL-B281BSAX', 'rajesh enterprises', '56822484', NULL, '18/09/2026 paid  to vendor', 10, 8, 16500.00, 18000.00, 165000.00, 100000.00, 'partial', 'in_stock', '2026-09-18 06:28:28'),
(8, 8, 'WHIRLPOOL', 'SINGLE DOOR REFRIGIRATOR', 'DAS9246IAGVMC', 'rajesh enterprises', '5659269', NULL, '4 STAR SILVER', 7, 7, 180000.00, 26000.00, 1260000.00, 100000.00, 'partial', 'in_stock', '2026-09-18 06:41:12'),
(9, 9, 'ADORN INDIA PREMIUM LUSTER 8 SEATER', 'SOFA', 'L SHAPE SOFA SET', 'rajesh enterprises', '6559565', NULL, 'SOFA SET WOOLEAN', 3, 3, 20000.00, 25000.00, 60000.00, 40000.00, 'partial', 'in_stock', '2026-09-18 09:41:19'),
(10, 10, 'BUTTERFLY ALFA', 'GAS STOVE', '4 BURNER GLASS TOP', 'SMART HOME APPLICANCIES', '899564', NULL, 'Butterfly Alpha Max 4 Burner Glass Top Gas Stove | Removable Drip Tray | Brass Burner Gas Stove with Tri‑Flow Jumbo Burner| 5 Years Warranty', 8, 8, 12000.00, 18999.98, 96000.00, 10000.00, 'partial', 'in_stock', '2026-09-18 09:47:09'),
(11, 11, 'Sleepyhead', 'SOFA', 'RX7 DESIGNED BY DUROFLEX', 'SMART HOME APPLICANCIES', '232165164', NULL, 'Brand	Sleepyhead\r\nColour	Egyptian Brown\r\nMaterial	Polyester\r\nProduct Dimensions	81.2D x 88.9W x 99H Centimeters\r\nSize	Motorized\r\nItem Weight	60.5 Kilograms\r\nBack Style	Solid Back\r\nFrame Material	Wood\r\nProduct Care Instructions	Dry Clean', 8, 8, 12800.00, 14000.00, 102400.00, 59000.00, 'partial', 'in_stock', '2026-09-18 09:55:41'),
(12, 12, 'LG', 'TV', '43NANO83A6A', 'rajesh enterprises', '89515', NULL, '1 LAKH PAID', 3, 3, 42651.00, 48000.00, 127953.00, 127953.00, 'paid', 'in_stock', '2026-09-18 10:01:07'),
(13, 13, 'FURNY Woodswave', 'SOFA', 'FURNY Woodswave 5 Seater Sofa Set', 'rajesh enterprises', '5849', NULL, 'Brand	FURNY\r\nAssembly Required	No\r\nSeat Depth	76.2 Centimeters\r\nSeat Height	45 Inches\r\nWeight Limit	500 Kilograms\r\nSeating Capacity	5.0\r\nProduct Dimensions	78.7D x 188W x 81.3H Centimeters\r\nItem Weight	85 Kilograms\r\nLeg Length	2 Inches\r\nType	Standard', 2, 0, 18000.00, 21999.00, 36000.00, 10000.00, 'partial', 'out_of_stock', '2026-09-18 10:05:00'),
(14, 14, 'SAMSAUNG', 'TV', 'UA50UE83AHULXL', 'SAMSAUNG  ENTERPRISES', '989554', NULL, 'Screen Size	50 Inches\r\nBrand	Samsung\r\nDisplay Technology	LED\r\nResolution	4K\r\nRefresh Rate	50 Hz\r\nSpecial Feature	Color Booster | Contrast Enhancer | PurColor, Crystal Processor 4K | Vision AI Companion | Football Mode | 4K Upscaling | MetalStream Design | Samsung Knox Security | Over 150+ Free Channels | Voice AssistantColor Booster | Contrast Enhancer | PurColor, Crystal Processor 4K | Vision AI Companion | Football Mode | 4K Upscaling | MetalStream Design | Samsung Knox Security | Over 150+ Free Channels | Voice Assistant\r\nIncluded Components	1Number LED TV, 2Numbers BATTERY (AAA Size), 1Number POWER CORD, 1Number REMOCON\r\nConnectivity Technology	Ethernet, HDMI, USB, Wi-Fi\r\nAspect Ratio	16:9\r\nSupported Internet Services	Hotstar, Netflix, Prime Video, SonyLiv, YouTube', 3, 3, 48000.00, 56982.00, 144000.00, 144000.00, 'paid', 'in_stock', '2026-09-18 15:36:36'),
(15, 15, 'Xiaomi', 'TV', 'L55MB-AIN', 'rajesh enterprises', '216489', NULL, 'Screen Size	55 Inches\r\nBrand	XIAOMI\r\nDisplay Technology	LED\r\nResolution	4K\r\nRefresh Rate	120 Hz, 60 Hz\r\nSpecial Feature	Filmmaker Mode, MEMC, Eye Comfort Mode\r\nIncluded Components	‎1 LED TV, 1 Remote Control, 2 Stands Unit, 4 Screws, 2 x AAA batteries, 1 Power Cord\r\nConnectivity Technology	Ethernet, HDMI, USB, Wi-Fi\r\nAspect Ratio	16:9\r\nProduct Dimensions	31.2D x 122.6W x 77H Centimeters', 2, 2, 48000.00, 53000.00, 96000.00, 88000.00, 'partial', 'in_stock', '2026-09-18 15:40:11'),
(16, 6, 'SAMSAUNG', 'HOME THEATRE', 'IG480', 'rajesh enterprises', '05484', NULL, 'Samsung 300 W 2.1 ch Soundbar with Dolby Audio | DTS Virtual:X | Bass Boost | 3D surround sound | HDMI ARC | Optical In | Bluetooth | USB Music Playback | Wireless Subwoofer (HW-B45EF/XL, Titan Black)', 4, 4, 18000.00, 19000.00, 72000.00, 10000.00, 'partial', 'in_stock', '2026-09-18 16:07:08'),
(17, 4, 'IFB', 'WASHING MACHINE', 'FRONT LOAD', 'IFB INFO .IN', '16502', NULL, 'IFB 7 Kg 5 Star, DeepClean® Technology, AI Powered, WiFi, Fully Automatic Front Load Washing Machine (SERENA GXN 7012 CMS, PowerSteam®, 9 Swirl, Steam Refresh, Inbuilt Heater, Eco Inverter, Grey)', 3, 3, 28000.00, 31999.91, 84000.00, 50000.00, 'partial', 'in_stock', '2026-09-18 16:13:20'),
(18, 16, 'LG', 'WASHING MACHINE', 'T80VBMB4Z', 'rajesh enterprises', '87851', NULL, 'Product Dimensions	56D x 54W x 92.5H Centimeters\r\nBrand	LG\r\nCapacity	8 kg\r\nSpecial Feature	Auto Restart, Child Lock, High Efficiency, Inverter, LED Display\r\nAccess Location	Top Load', 6, 6, 28000.00, 34876.00, 168000.00, 85900.00, 'partial', 'in_stock', '2026-09-18 16:46:54'),
(19, 17, 'Mivi', 'HOME THEATRE', 'Nex 450', 'rajesh enterprises', '265194', NULL, 'Brand	Mivi\r\nSpeaker Maximum Output Power	450 Watts\r\nFrequency Response	35 KHz\r\nConnectivity Technology	Bluetooth, Coaxial, HDMI, Optical, USB\r\nAudio Output Mode	Surround', 2, 2, 4800.00, 12000.00, 9600.00, 9600.00, 'paid', 'in_stock', '2026-09-18 16:52:17'),
(20, 6, 'SAMSAUNG', 'HOME THEATRE', 'IG480', 'rajesh enterprises', '85988', NULL, '', 2, 2, 10000.00, 11999.89, 20000.00, 10000.00, 'partial', 'in_stock', '2026-09-18 19:27:40'),
(21, 7, 'LG', 'single doar refregirator', 'LG GL-B281BSAX', 'rajesh enterprises', '5484', NULL, '', 2, 2, 10000.00, 18000.00, 20000.00, 10000.00, 'partial', 'in_stock', '2026-09-19 05:28:10'),
(22, 6, 'SAMSAUNG', 'HOME THEATRE', 'IG480', 'rajesh enterprises', '65899', NULL, '', 11, 11, 12000.00, 18000.00, 132000.00, 25822.00, 'partial', 'in_stock', '2026-09-19 09:33:14');

-- --------------------------------------------------------

--
-- Table structure for table `vendors`
--

CREATE TABLE `vendors` (
  `id` int(11) NOT NULL,
  `vendor_name` varchar(150) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendors`
--

INSERT INTO `vendors` (`id`, `vendor_name`, `contact_person`, `phone`, `email`, `address`, `created_at`) VALUES
(1, 'IFB INFO .IN', 'RAKESH GARU', '7893282348', 'devendrasabbavarapu31@gmail.com', 'kkd ,mainroad', '2026-09-10 15:39:56'),
(2, 'SAMSAUNG  ENTERPRISES', 'SURESH', '9848155348', 'devendrasabbavarapu@gmail.com', '3-25,YUSAFGUDA,HYD,TG,8965622', '2026-09-11 05:21:21'),
(3, 'rajesh enterprises', 'Rajesh', '9658421863', 'devendrasabbavarapu31@gmail.com', 'kakinada ,korangi', '2026-09-18 06:23:57'),
(4, 'SMART HOME APPLICANCIES', 'RAHUL SETPAUL', '8966265412', 'smartapplications@gmail.com', 'kolkata ,west bengal-155461', '2026-09-18 09:46:18');

-- --------------------------------------------------------

--
-- Table structure for table `vendor_payment_ledger`
--

CREATE TABLE `vendor_payment_ledger` (
  `id` int(11) NOT NULL,
  `intake_id` int(11) NOT NULL,
  `payment_amount` decimal(12,2) NOT NULL,
  `payment_mode` enum('cash','upi','bank_transfer','cheque') NOT NULL DEFAULT 'upi',
  `note` varchar(255) DEFAULT NULL,
  `paid_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendor_payment_ledger`
--

INSERT INTO `vendor_payment_ledger` (`id`, `intake_id`, `payment_amount`, `payment_mode`, `note`, `paid_at`) VALUES
(3, 5, 100000.00, 'cash', 'Initial Payment at Intake', '2026-09-10 15:56:47'),
(4, 6, 100000.00, 'cash', 'Initial Payment at Intake', '2026-09-10 16:14:14'),
(5, 7, 100000.00, 'cash', 'Initial Payment at Intake', '2026-09-18 06:28:28'),
(6, 8, 100000.00, 'bank_transfer', 'Initial Payment at Intake', '2026-09-18 06:41:12'),
(7, 9, 40000.00, 'upi', 'Initial Payment at Intake', '2026-09-18 09:41:19'),
(8, 10, 10000.00, 'cheque', 'Initial Payment at Intake', '2026-09-18 09:47:09'),
(9, 11, 59000.00, 'cheque', 'Initial Payment at Intake', '2026-09-18 09:55:41'),
(10, 12, 127953.00, 'bank_transfer', 'Initial Payment at Intake', '2026-09-18 10:01:07'),
(11, 13, 10000.00, 'cash', 'Initial Payment at Intake', '2026-09-18 10:05:00'),
(12, 14, 144000.00, 'bank_transfer', 'Initial Payment at Intake', '2026-09-18 15:36:36'),
(13, 15, 88000.00, 'cash', 'Initial Payment at Intake', '2026-09-18 15:40:11'),
(14, 16, 10000.00, 'bank_transfer', 'Initial Payment at Intake', '2026-09-18 16:07:08'),
(15, 17, 50000.00, 'cash', 'Initial Payment at Intake', '2026-09-18 16:13:20'),
(16, 18, 85900.00, 'bank_transfer', 'Initial Payment at Intake', '2026-09-18 16:46:54'),
(17, 19, 9600.00, 'cash', 'Initial Payment at Intake', '2026-09-18 16:52:17'),
(18, 20, 10000.00, 'upi', 'Initial Payment at Intake', '2026-09-18 19:27:40'),
(19, 21, 10000.00, 'upi', 'Initial Payment at Intake', '2026-09-19 05:28:10'),
(20, 22, 25822.00, 'cash', 'Initial Payment at Intake', '2026-09-19 09:33:14');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `counter_sales`
--
ALTER TABLE `counter_sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bill_no` (`bill_no`);

--
-- Indexes for table `counter_sale_items`
--
ALTER TABLE `counter_sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sale_parent` (`sale_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_customer_phone` (`phone`);

--
-- Indexes for table `customer_wishlist`
--
ALTER TABLE `customer_wishlist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_cust_prod` (`customer_id`,`public_product_id`),
  ADD KEY `fk_wish_product` (`public_product_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `logins`
--
ALTER TABLE `logins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `public_products`
--
ALTER TABLE `public_products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_store_product` (`product_id`);

--
-- Indexes for table `staff_attendance`
--
ALTER TABLE `staff_attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_staff_att` (`staff_id`);

--
-- Indexes for table `staff_members`
--
ALTER TABLE `staff_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `staff_code` (`staff_code`);

--
-- Indexes for table `stock_intake`
--
ALTER TABLE `stock_intake`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_intake_product` (`product_id`);

--
-- Indexes for table `vendors`
--
ALTER TABLE `vendors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `vendor_name` (`vendor_name`);

--
-- Indexes for table `vendor_payment_ledger`
--
ALTER TABLE `vendor_payment_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_vendor_intake` (`intake_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `counter_sales`
--
ALTER TABLE `counter_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `counter_sale_items`
--
ALTER TABLE `counter_sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `customer_wishlist`
--
ALTER TABLE `customer_wishlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `logins`
--
ALTER TABLE `logins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `public_products`
--
ALTER TABLE `public_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `staff_attendance`
--
ALTER TABLE `staff_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `staff_members`
--
ALTER TABLE `staff_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `stock_intake`
--
ALTER TABLE `stock_intake`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `vendors`
--
ALTER TABLE `vendors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `vendor_payment_ledger`
--
ALTER TABLE `vendor_payment_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `counter_sale_items`
--
ALTER TABLE `counter_sale_items`
  ADD CONSTRAINT `fk_sale_parent` FOREIGN KEY (`sale_id`) REFERENCES `counter_sales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `customer_wishlist`
--
ALTER TABLE `customer_wishlist`
  ADD CONSTRAINT `fk_wish_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_wish_product` FOREIGN KEY (`public_product_id`) REFERENCES `public_products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `public_products`
--
ALTER TABLE `public_products`
  ADD CONSTRAINT `fk_store_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `staff_attendance`
--
ALTER TABLE `staff_attendance`
  ADD CONSTRAINT `fk_staff_att` FOREIGN KEY (`staff_id`) REFERENCES `staff_members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_intake`
--
ALTER TABLE `stock_intake`
  ADD CONSTRAINT `fk_intake_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `vendor_payment_ledger`
--
ALTER TABLE `vendor_payment_ledger`
  ADD CONSTRAINT `fk_vendor_intake` FOREIGN KEY (`intake_id`) REFERENCES `stock_intake` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
