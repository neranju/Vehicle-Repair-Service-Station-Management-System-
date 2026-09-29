-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 20, 2026 at 10:20 PM
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
-- Database: `vehicle_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `customer_id` varchar(10) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `reg_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `customer_id`, `name`, `phone`, `email`, `reg_date`) VALUES
(1, 'CUS001', 'Kamal Perera', '0771234567', 'kamal@gmail.com', '2026-06-10 18:02:00'),
(2, 'CUS002', 'Nimal Silva', '0719876543', 'nimal@yahoo.com', '2026-06-10 18:02:00'),
(3, 'CUS003', 'Neranju', '0767869694', 'neranju456@gmail.com', '2026-07-08 05:50:07'),
(5, 'CUS005', 'kavindu', '0764851264', 'kavindu@gmail.com', '2026-07-16 05:51:36'),
(6, 'CUS006', 'kavindu', '0764851264', 'kavindu@gmail.com', '2026-07-16 06:09:54'),
(7, 'CUS007', 'Chinthaka', '0785642513', 'Chinthaka@gmail.com', '2026-07-16 07:03:47'),
(8, 'CUS008', 'chandana', '0764562315', 'chandana@gmail.com', '2026-07-16 08:49:25'),
(9, 'CUS009', 'chinthaka', '0795612345', 'chinthaka@gmail.com', '2026-07-16 09:14:14');

-- --------------------------------------------------------

--
-- Table structure for table `customer_addresses`
--

CREATE TABLE `customer_addresses` (
  `id` int(11) NOT NULL,
  `customer_id` varchar(10) NOT NULL,
  `address_line1` varchar(255) NOT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_addresses`
--

INSERT INTO `customer_addresses` (`id`, `customer_id`, `address_line1`, `address_line2`, `city`, `is_primary`) VALUES
(1, 'CUS001', 'No. 123, Galle Road', 'Colombo 03', 'Colombo', 1),
(2, 'CUS002', '45/2, Kandy Road', 'Kiribathgoda', 'Gampaha', 1),
(3, 'CUS003', 'dikhenawaththa', 'gammana,Yagirala', 'Mathugama', 1),
(5, 'CUS005', 'temple rode,', 'Leuwanduwa.', 'Aluthgama', 1),
(6, 'CUS006', 'temple rode,', 'Leuwanduwa.', 'Aluthgama', 1),
(7, 'CUS007', 'No 12 main rode,', 'Aluthgama', 'ALuthgama', 1),
(8, 'CUS008', 'No 12/A udagepola', 'Dargatown', 'Aluthgama', 1),
(9, 'CUS009', 'No10/A Higurana', 'Ampara', 'Ampara', 1);

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `invoice_id` varchar(10) NOT NULL,
  `job_id` varchar(10) NOT NULL,
  `invoice_date` datetime NOT NULL DEFAULT current_timestamp(),
  `sub_total` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `tax` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `paid_status` enum('Paid','Unpaid','Partial') NOT NULL DEFAULT 'Unpaid',
  `method_id` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `invoice_id`, `job_id`, `invoice_date`, `sub_total`, `discount`, `tax`, `total_amount`, `paid_status`, `method_id`) VALUES
(4, 'INV004', 'JC005', '2026-07-16 11:42:01', 4300.00, 0.00, 0.00, 4300.00, 'Paid', 'PM001'),
(5, 'INV005', 'JC006', '2026-07-16 12:59:46', 4900.00, 0.00, 0.00, 4900.00, 'Paid', 'PM001'),
(6, 'INV006', 'JC009', '2026-07-16 14:20:31', 2500.00, 0.00, 0.00, 2500.00, 'Paid', 'PM001'),
(7, 'INV007', 'JC010', '2026-07-16 14:46:43', 7600.00, 0.00, 0.00, 7600.00, 'Paid', 'PM001');

-- --------------------------------------------------------

--
-- Table structure for table `jobcard_parts`
--

CREATE TABLE `jobcard_parts` (
  `id` int(11) NOT NULL,
  `job_part_id` varchar(10) NOT NULL,
  `job_id` varchar(10) NOT NULL,
  `part_id` varchar(10) NOT NULL,
  `quantity_used` int(11) NOT NULL,
  `price_at_time` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jobcard_parts`
--

INSERT INTO `jobcard_parts` (`id`, `job_part_id`, `job_id`, `part_id`, `quantity_used`, `price_at_time`) VALUES
(5, 'JP005', 'JC005', 'PRT002', 1, 1800.00),
(6, 'JP006', 'JC005', 'PRT001', 1, 2500.00),
(16, 'JP007', 'JC006', 'Part03', 1, 2500.00),
(17, 'JP008', 'JC006', 'PART04', 1, 2400.00),
(18, 'JP018', 'JC009', 'Part03', 1, 2500.00),
(19, 'JP019', 'JC010', 'Part03', 1, 2500.00),
(20, 'JP020', 'JC010', 'PRT002', 1, 1800.00),
(21, 'JP021', 'JC010', 'PRT003', 1, 800.00),
(22, 'JP022', 'JC010', 'Part03', 1, 2500.00);

-- --------------------------------------------------------

--
-- Table structure for table `job_cards`
--

CREATE TABLE `job_cards` (
  `id` int(11) NOT NULL,
  `job_id` varchar(10) NOT NULL,
  `vehicle_id` varchar(10) NOT NULL,
  `customer_id` varchar(10) NOT NULL,
  `mechanic_id` varchar(10) NOT NULL,
  `service_type_id` varchar(10) NOT NULL,
  `status_id` varchar(10) NOT NULL DEFAULT 'ST001',
  `date_in` datetime NOT NULL,
  `date_out` datetime DEFAULT NULL,
  `problem_description` text DEFAULT NULL,
  `labour_charge` decimal(10,2) DEFAULT 0.00,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_cards`
--

INSERT INTO `job_cards` (`id`, `job_id`, `vehicle_id`, `customer_id`, `mechanic_id`, `service_type_id`, `status_id`, `date_in`, `date_out`, `problem_description`, `labour_charge`, `remarks`) VALUES
(5, 'JC005', 'VEH002', 'CUS005', 'STF015', 'SV005', 'ST003', '2026-07-16 08:10:45', NULL, '', 0.00, ''),
(8, 'JC006', 'VEH007', 'CUS007', 'STF002', 'SV005', 'ST003', '2026-07-16 09:29:33', NULL, 'custemer bring', 0.00, ''),
(9, 'JC009', 'VEH008', 'CUS008', 'STF015', 'SV001', 'ST003', '2026-07-16 10:50:14', NULL, '', 0.00, ''),
(10, 'JC010', 'VEH009', 'CUS007', 'STF015', 'SV005', 'ST002', '2026-07-16 11:15:43', NULL, 'cabuleter ishu', 0.00, '');

-- --------------------------------------------------------

--
-- Table structure for table `job_card_history`
--

CREATE TABLE `job_card_history` (
  `id` int(11) NOT NULL,
  `job_id` varchar(20) DEFAULT NULL,
  `vehicle_id` varchar(20) DEFAULT NULL,
  `customer_id` varchar(20) DEFAULT NULL,
  `mechanic_id` varchar(20) DEFAULT NULL,
  `service_type_id` varchar(20) DEFAULT NULL,
  `status_id` varchar(20) DEFAULT NULL,
  `problem_description` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `edited_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_card_history`
--

INSERT INTO `job_card_history` (`id`, `job_id`, `vehicle_id`, `customer_id`, `mechanic_id`, `service_type_id`, `status_id`, `problem_description`, `remarks`, `edited_at`) VALUES
(1, 'JC005', 'VEH002', 'CUS005', 'STF015', 'SV005', 'ST001', '', '', '2026-07-16 11:43:18'),
(2, 'JC006', 'VEH007', 'CUS007', 'STF002', 'SV005', 'ST002', 'custemer bring', '', '2026-07-16 13:01:35'),
(3, 'JC009', 'VEH008', 'CUS008', 'STF015', 'SV001', 'ST001', '', '', '2026-07-16 14:21:05'),
(4, 'JC010', 'VEH009', 'CUS007', 'STF015', 'SV005', 'ST002', '', '', '2026-07-16 14:49:35');

-- --------------------------------------------------------

--
-- Table structure for table `job_statuses`
--

CREATE TABLE `job_statuses` (
  `id` int(11) NOT NULL,
  `status_id` varchar(10) NOT NULL,
  `status_name` enum('Pending','In Progress','Completed','Delivered','Cancelled') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_statuses`
--

INSERT INTO `job_statuses` (`id`, `status_id`, `status_name`) VALUES
(1, 'ST001', 'Pending'),
(2, 'ST002', 'In Progress'),
(3, 'ST003', 'Completed'),
(4, 'ST004', 'Delivered'),
(5, 'ST005', 'Cancelled');

-- --------------------------------------------------------

--
-- Table structure for table `parts`
--

CREATE TABLE `parts` (
  `id` int(11) NOT NULL,
  `part_id` varchar(10) NOT NULL,
  `part_name` varchar(100) NOT NULL,
  `part_code` varchar(50) NOT NULL,
  `category_id` varchar(10) NOT NULL,
  `qty_in_stock` int(11) NOT NULL DEFAULT 0,
  `unit_price` decimal(10,2) NOT NULL,
  `reorder_level` int(11) DEFAULT 0,
  `supplier_id` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parts`
--

INSERT INTO `parts` (`id`, `part_id`, `part_name`, `part_code`, `category_id`, `qty_in_stock`, `unit_price`, `reorder_level`, `supplier_id`) VALUES
(1, 'PRT001', 'Brake Pad Set', 'BP-HON-001', 'PC001', 25, 2500.00, 5, 'SUP001'),
(2, 'PRT002', 'Engine Oil 1L', 'EO-CAS-001', 'PC003', 49, 1800.00, 10, 'SUP002'),
(3, 'PRT003', 'Spark plug', '12345', 'PC001', 9, 800.00, 0, 'SUP001'),
(4, 'Part03', 'Brake cable', 'MN-JK-254', 'PC001', 16, 2500.00, 5, 'SUP003'),
(5, 'PART04', 'Brake liner', 'NX-NAJX-AJ45', 'PC001', 19, 2400.00, 5, 'SUP001');

-- --------------------------------------------------------

--
-- Table structure for table `part_categories`
--

CREATE TABLE `part_categories` (
  `id` int(11) NOT NULL,
  `category_id` varchar(10) NOT NULL,
  `category_name` enum('Spare Part','Lubricant','Oil','Other') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `part_categories`
--

INSERT INTO `part_categories` (`id`, `category_id`, `category_name`) VALUES
(1, 'PC001', 'Spare Part'),
(2, 'PC002', 'Lubricant'),
(3, 'PC003', 'Oil'),
(4, 'PC004', 'Other');

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL,
  `method_id` varchar(10) NOT NULL,
  `method_name` enum('Cash','Bank Transfer') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`id`, `method_id`, `method_name`) VALUES
(1, 'PM001', 'Cash'),
(2, 'PM002', 'Bank Transfer');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_id` varchar(10) NOT NULL,
  `role_name` enum('Admin','Receptionist','Mechanic') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_id`, `role_name`) VALUES
(1, 'RL001', 'Admin'),
(2, 'RL002', 'Receptionist'),
(3, 'RL003', 'Mechanic');

-- --------------------------------------------------------

--
-- Table structure for table `service_types`
--

CREATE TABLE `service_types` (
  `id` int(11) NOT NULL,
  `service_type_id` varchar(10) NOT NULL,
  `service_name` varchar(100) NOT NULL,
  `default_price` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_types`
--

INSERT INTO `service_types` (`id`, `service_type_id`, `service_name`, `default_price`) VALUES
(1, 'SV001', 'Full Service', 5000.00),
(2, 'SV002', 'Oil Change', 1500.00),
(3, 'SV003', 'Engine Repair', 10000.00),
(4, 'SV004', 'General Checkup', 1000.00),
(5, 'SV005', 'service', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `staff_id` varchar(10) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role_id` varchar(10) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `hourly_rate` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `staff_id`, `name`, `phone`, `email`, `role_id`, `password_hash`, `hourly_rate`) VALUES
(2, 'STF002', 'kamal', '0754445566', 'ruwan@garage.lk', 'RL003', '$2y$10$q77oAnnwOWBVK0.MkmBCpuA3bo8/0H1gVgqk9dusLTddCxFyZjzi2', 800.00),
(11, 'STF011', 'yasintha', '0715462385', 'yasi@gmail.com', 'RL002', '$2y$10$tQL57u.z08VjxRSy2QstMO16pbzL2uqVru7pdQ993r6reb6uIm4s2', 800.00),
(13, 'STF013', 'chathumina', '0712589631', 'chathu@gmail.com', 'RL001', '$2y$10$uvkjxsEI60JAkvA8351tg.bbkaurvW6qfa6BAZi5dI8fIrPAwXKTO', 600.00),
(14, 'STF014', 'sunil', '0751489623', 'sulin@gmail.com', 'RL002', '$2y$10$Ey0VCykr59KoVBdvopFWOO2JjNXSFqp7CLoZzno8U5DBERT6gE0aS', 400.00),
(15, 'STF015', 'kapila', '0712654895', 'kapila@gmail.com', 'RL003', '$2y$10$eSChWC42PfjU1dOuhDW/Je3/Xm4J7078BTUHq8GI2hna1/4MpXEgy', 1000.00);

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `supplier_id` varchar(10) NOT NULL,
  `supplier_name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(15) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `supplier_id`, `supplier_name`, `contact_person`, `phone`, `email`, `address`) VALUES
(1, 'SUP001', 'Lanka Auto Parts', 'Sunil Fernando', '0112345678', 'info@lankaauto.lk', 'No. 50, Panchikawatta, Colombo'),
(2, 'SUP002', 'Tokyo Motors', 'Hiroshi Tanaka', '0119876543', 'sales@tokyomotors.lk', 'Negombo Road, Ja-Ela'),
(3, 'SUP003', 'AMW', 'Kasun', '0785214563', 'kasun@gmail.com', '123 Galle rode,colombo');

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL,
  `vehicle_id` varchar(10) NOT NULL,
  `customer_id` varchar(10) NOT NULL,
  `model_id` varchar(10) NOT NULL,
  `reg_no` varchar(20) NOT NULL,
  `year` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`id`, `vehicle_id`, `customer_id`, `model_id`, `reg_no`, `year`) VALUES
(1, 'VEH001', 'CUS001', 'MD001', 'WP-BCA-1234', 2015),
(2, 'VEH002', 'CUS002', 'MD003', 'SP-BBI-5678', 2014),
(3, 'VEH003', 'CUS003', 'MD001', 'WP-BBI-0169', 2014),
(5, 'VEH005', 'CUS005', 'MD010', 'WP-BBN-4625', 2015),
(6, 'VEH006', 'CUS006', 'MD006', 'WP-BBA-1269', 2014),
(7, 'VEH007', 'CUS007', 'MD006', 'WP-BCN-1364', 2017),
(8, 'VEH008', 'CUS008', 'MD010', 'WP-BIC-1236', 2015),
(9, 'VEH009', 'CUS009', 'MD011', 'EP-UF-2561', 2014);

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_brands`
--

CREATE TABLE `vehicle_brands` (
  `id` int(11) NOT NULL,
  `brand_id` varchar(10) NOT NULL,
  `brand_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicle_brands`
--

INSERT INTO `vehicle_brands` (`id`, `brand_id`, `brand_name`) VALUES
(1, 'BR001', 'Hero'),
(2, 'BR002', 'Yamaha'),
(3, 'BR003', 'Bajaj'),
(4, 'BR004', 'TVS'),
(5, 'BR005', 'Honda'),
(6, 'BR006', 'Suzuki'),
(7, 'BR007', 'Senaro'),
(9, 'BR009', 'YamahaTest');

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_models`
--

CREATE TABLE `vehicle_models` (
  `id` int(11) NOT NULL,
  `model_id` varchar(10) NOT NULL,
  `brand_id` varchar(10) NOT NULL,
  `model_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicle_models`
--

INSERT INTO `vehicle_models` (`id`, `model_id`, `brand_id`, `model_name`) VALUES
(1, 'MD001', 'BR005', 'CD 70'),
(2, 'MD002', 'BR005', 'Dio'),
(3, 'MD003', 'BR002', 'FZ-S'),
(4, 'MD004', 'BR002', 'Ray ZR'),
(5, 'MD005', 'BR003', 'Pulsar 150'),
(6, 'MD006', 'BR003', 'CT 100'),
(9, 'MD009', 'BR009', 'FZ16Test'),
(10, 'MD010', 'BR002', 'Fz'),
(11, 'MD011', 'BR004', 'ntoc');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_id` (`customer_id`);

--
-- Indexes for table `customer_addresses`
--
ALTER TABLE `customer_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_address_customer` (`customer_id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_id` (`invoice_id`),
  ADD KEY `fk_invoices_job` (`job_id`),
  ADD KEY `fk_invoices_method` (`method_id`);

--
-- Indexes for table `jobcard_parts`
--
ALTER TABLE `jobcard_parts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `job_part_id` (`job_part_id`),
  ADD KEY `fk_jobparts_job` (`job_id`),
  ADD KEY `fk_jobparts_part` (`part_id`);

--
-- Indexes for table `job_cards`
--
ALTER TABLE `job_cards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `job_id` (`job_id`),
  ADD KEY `fk_jobcards_vehicle` (`vehicle_id`),
  ADD KEY `fk_jobcards_customer` (`customer_id`),
  ADD KEY `fk_jobcards_staff` (`mechanic_id`),
  ADD KEY `fk_jobcards_service` (`service_type_id`),
  ADD KEY `fk_jobcards_status` (`status_id`);

--
-- Indexes for table `job_card_history`
--
ALTER TABLE `job_card_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `job_statuses`
--
ALTER TABLE `job_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `status_id` (`status_id`),
  ADD UNIQUE KEY `status_name` (`status_name`);

--
-- Indexes for table `parts`
--
ALTER TABLE `parts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `part_id` (`part_id`),
  ADD UNIQUE KEY `part_code` (`part_code`),
  ADD KEY `fk_parts_category` (`category_id`),
  ADD KEY `fk_parts_supplier` (`supplier_id`);

--
-- Indexes for table `part_categories`
--
ALTER TABLE `part_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_id` (`category_id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `method_id` (`method_id`),
  ADD UNIQUE KEY `method_name` (`method_name`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_id` (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `service_types`
--
ALTER TABLE `service_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `service_type_id` (`service_type_id`),
  ADD UNIQUE KEY `service_name` (`service_name`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `staff_id` (`staff_id`),
  ADD KEY `fk_staff_role` (`role_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `vehicle_id` (`vehicle_id`),
  ADD UNIQUE KEY `reg_no` (`reg_no`),
  ADD KEY `fk_vehicles_customer` (`customer_id`),
  ADD KEY `fk_vehicles_model` (`model_id`);

--
-- Indexes for table `vehicle_brands`
--
ALTER TABLE `vehicle_brands`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `brand_id` (`brand_id`),
  ADD UNIQUE KEY `brand_name` (`brand_name`);

--
-- Indexes for table `vehicle_models`
--
ALTER TABLE `vehicle_models`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `model_id` (`model_id`),
  ADD UNIQUE KEY `unique_model_per_brand` (`brand_id`,`model_name`),
  ADD KEY `fk_model_brand` (`brand_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `customer_addresses`
--
ALTER TABLE `customer_addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `jobcard_parts`
--
ALTER TABLE `jobcard_parts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `job_cards`
--
ALTER TABLE `job_cards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `job_card_history`
--
ALTER TABLE `job_card_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `job_statuses`
--
ALTER TABLE `job_statuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `parts`
--
ALTER TABLE `parts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `part_categories`
--
ALTER TABLE `part_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `service_types`
--
ALTER TABLE `service_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `vehicle_brands`
--
ALTER TABLE `vehicle_brands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `vehicle_models`
--
ALTER TABLE `vehicle_models`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `customer_addresses`
--
ALTER TABLE `customer_addresses`
  ADD CONSTRAINT `fk_address_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `fk_invoices_job` FOREIGN KEY (`job_id`) REFERENCES `job_cards` (`job_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_invoices_method` FOREIGN KEY (`method_id`) REFERENCES `payment_methods` (`method_id`) ON DELETE SET NULL;

--
-- Constraints for table `jobcard_parts`
--
ALTER TABLE `jobcard_parts`
  ADD CONSTRAINT `fk_jobparts_job` FOREIGN KEY (`job_id`) REFERENCES `job_cards` (`job_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_jobparts_part` FOREIGN KEY (`part_id`) REFERENCES `parts` (`part_id`);

--
-- Constraints for table `job_cards`
--
ALTER TABLE `job_cards`
  ADD CONSTRAINT `fk_jobcards_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_jobcards_service` FOREIGN KEY (`service_type_id`) REFERENCES `service_types` (`service_type_id`),
  ADD CONSTRAINT `fk_jobcards_staff` FOREIGN KEY (`mechanic_id`) REFERENCES `staff` (`staff_id`),
  ADD CONSTRAINT `fk_jobcards_status` FOREIGN KEY (`status_id`) REFERENCES `job_statuses` (`status_id`),
  ADD CONSTRAINT `fk_jobcards_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`) ON DELETE CASCADE;

--
-- Constraints for table `parts`
--
ALTER TABLE `parts`
  ADD CONSTRAINT `fk_parts_category` FOREIGN KEY (`category_id`) REFERENCES `part_categories` (`category_id`),
  ADD CONSTRAINT `fk_parts_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`);

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `fk_staff_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`);

--
-- Constraints for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD CONSTRAINT `fk_vehicles_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vehicles_model` FOREIGN KEY (`model_id`) REFERENCES `vehicle_models` (`model_id`);

--
-- Constraints for table `vehicle_models`
--
ALTER TABLE `vehicle_models`
  ADD CONSTRAINT `fk_model_brand` FOREIGN KEY (`brand_id`) REFERENCES `vehicle_brands` (`brand_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
