-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 30, 2025 at 04:03 PM
-- Server version: 10.4.6-MariaDB
-- PHP Version: 8.3.8

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `auction28`
--

DELIMITER $$
--
-- Functions
--
CREATE DEFINER=`blk`@`%` FUNCTION `LEVENSHTEIN2` (`s1` VARCHAR(255), `s2` VARCHAR(255)) RETURNS INT(11) DETERMINISTIC BEGIN

    DECLARE s1_len, s2_len, i, j, c, c_temp INT;

    DECLARE cost INT;

    DECLARE s1_char CHAR;

    DECLARE cv0, cv1 VARBINARY(256);

    

    SET s1_len = CHAR_LENGTH(s1);

    SET s2_len = CHAR_LENGTH(s2);

    IF s1_len = 0 THEN

        RETURN s2_len;

    END IF;

    IF s2_len = 0 THEN

        RETURN s1_len;

    END IF;

    

    SET cv1 = 0x00;

    SET j = 1;

    WHILE j <= s2_len DO

        SET cv1 = CONCAT(cv1, UNHEX(HEX(j)));

        SET j = j + 1;

    END WHILE;

    

    SET i = 1;

    WHILE i <= s1_len DO

        SET s1_char = SUBSTRING(s1, i, 1);

        SET c = i;

        SET cv0 = UNHEX(HEX(i));

        SET j = 1;

        WHILE j <= s2_len DO

            SET c_temp = c;

            SET c = CONV(HEX(SUBSTRING(cv1, j, 1)), 16, 10);

            IF s1_char = SUBSTRING(s2, j, 1) THEN

                SET cost = 0;

            ELSE

                SET cost = 1;

            END IF;

            SET cv0 = CONCAT(cv0, UNHEX(HEX(LEAST(c + 1, c_temp + 1, c + cost))));

            SET j = j + 1;

        END WHILE;

        SET cv1 = cv0;

        SET i = i + 1;

    END WHILE;

    RETURN CONV(HEX(SUBSTRING(cv1, s2_len, 1)), 16, 10);

END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `auctions`
--

CREATE TABLE `auctions` (
  `auctionId` int(11) NOT NULL,
  `auctionTitle` varchar(200) NOT NULL,
  `auctionStartPrice` decimal(10,2) NOT NULL,
  `auctionStartDate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `auctionEndDate` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `auctionProductImg` varchar(300) NOT NULL,
  `auctionProductType` enum('organic','hybrid') NOT NULL,
  `auctionProductQuantity` decimal(10,4) NOT NULL,
  `auctionProductUnit` enum('kg','ton','nos') NOT NULL,
  `auctionAddress` varchar(300) NOT NULL,
  `auctionDescription` longtext NOT NULL,
  `auctionCategoryId` int(11) NOT NULL,
  `auctionCreatedBy` int(11) NOT NULL,
  `auctionStatus` enum('activate','deactivate','suspend') DEFAULT 'activate',
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `bids`
--

CREATE TABLE `bids` (
  `bidId` int(11) NOT NULL,
  `bidAuctionId` int(11) NOT NULL,
  `bidUserId` int(11) NOT NULL,
  `bidAmount` decimal(10,2) NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `categoryId` int(11) NOT NULL,
  `categoryName` varchar(100) NOT NULL,
  `categoryImg` varchar(300) NOT NULL,
  `categoryStatus` enum('activate','deactivate','suspend') NOT NULL DEFAULT 'activate',
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `heroes`
--

CREATE TABLE `heroes` (
  `heroId` int(11) NOT NULL,
  `heroTitle` varchar(100) NOT NULL,
  `heroImg` varchar(100) NOT NULL,
  `heroMessage` varchar(50) NOT NULL,
  `heroContent` longtext NOT NULL,
  `heroStatus` enum('activate','deactivate','suspend') NOT NULL DEFAULT 'deactivate',
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `interests`
--

CREATE TABLE `interests` (
  `interestId` int(11) NOT NULL,
  `interestUserId` int(11) NOT NULL,
  `interestCategoryId` int(11) NOT NULL,
  `interestProductType` enum('organic','hybrid','both') DEFAULT NULL,
  `interestKeywords` varchar(255) DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `moments`
--

CREATE TABLE `moments` (
  `momentId` int(11) NOT NULL,
  `momentUserId` int(11) NOT NULL,
  `momentImg` varchar(200) NOT NULL,
  `momentStatus` enum('activate','deactivate','suspend') NOT NULL DEFAULT 'deactivate',
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `passResets`
--

CREATE TABLE `passResets` (
  `passResetId` int(11) NOT NULL,
  `passResetUserId` int(11) NOT NULL,
  `passResetToken` varchar(64) NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `reviewId` int(11) NOT NULL,
  `reviewUserId` int(11) NOT NULL,
  `reviewMessage` mediumtext NOT NULL,
  `reviewStatus` enum('activate','deactivate','suspend') NOT NULL DEFAULT 'activate',
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `trans`
--

CREATE TABLE `trans` (
  `transId` int(11) NOT NULL,
  `transTrackingId` varchar(30) NOT NULL,
  `transCardNo` varchar(30) NOT NULL,
  `transAccountNo` varchar(30) NOT NULL,
  `transUserId` int(11) NOT NULL,
  `transAmount` decimal(10,2) NOT NULL,
  `transAuctionId` int(11) NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `userActivate`
--

CREATE TABLE `userActivate` (
  `userActivateId` int(11) NOT NULL,
  `userActivateUserId` int(11) NOT NULL,
  `userActivateToken` varchar(64) NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `userId` int(11) NOT NULL,
  `userName` varchar(100) NOT NULL,
  `userFirstName` varchar(100) DEFAULT NULL,
  `userLastName` varchar(100) DEFAULT NULL,
  `userEmail` varchar(100) NOT NULL,
  `userPassword` varchar(255) NOT NULL,
  `userPhone` varchar(100) DEFAULT NULL,
  `userAddress` varchar(250) DEFAULT NULL,
  `userProfileImg` varchar(300) DEFAULT 'profile.webp',
  `userAccountNo` varchar(30) DEFAULT NULL,
  `userRole` enum('user','admin') NOT NULL DEFAULT 'user',
  `userStatus` enum('activate','deactivate','suspend') DEFAULT 'deactivate',
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `auctions`
--
ALTER TABLE `auctions`
  ADD PRIMARY KEY (`auctionId`),
  ADD KEY `auctionCategoryId` (`auctionCategoryId`),
  ADD KEY `auctionCreatedBy` (`auctionCreatedBy`);

--
-- Indexes for table `bids`
--
ALTER TABLE `bids`
  ADD PRIMARY KEY (`bidId`),
  ADD KEY `bidAuctionId` (`bidAuctionId`),
  ADD KEY `bidUserId` (`bidUserId`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`categoryId`),
  ADD UNIQUE KEY `categoryName` (`categoryName`);

--
-- Indexes for table `heroes`
--
ALTER TABLE `heroes`
  ADD PRIMARY KEY (`heroId`);

--
-- Indexes for table `interests`
--
ALTER TABLE `interests`
  ADD PRIMARY KEY (`interestId`),
  ADD KEY `interests_interestUserId_users_userId` (`interestUserId`),
  ADD KEY `interests_interestCategoryId_categories_categoryId` (`interestCategoryId`);

--
-- Indexes for table `moments`
--
ALTER TABLE `moments`
  ADD PRIMARY KEY (`momentId`),
  ADD KEY `moments_momentUserId_users_userId` (`momentUserId`);

--
-- Indexes for table `passResets`
--
ALTER TABLE `passResets`
  ADD PRIMARY KEY (`passResetId`),
  ADD KEY `passResets_passRestUserId_users_userId` (`passResetUserId`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`reviewId`),
  ADD KEY `reviews_reviewUserId_users_userId` (`reviewUserId`);

--
-- Indexes for table `trans`
--
ALTER TABLE `trans`
  ADD PRIMARY KEY (`transId`),
  ADD KEY `trans_transAccountNo_users_userAccountNo` (`transAccountNo`),
  ADD KEY `trans_transUserId_users_userId` (`transUserId`),
  ADD KEY `trans_transAuctionId_auctions_auctionId` (`transAuctionId`);

--
-- Indexes for table `userActivate`
--
ALTER TABLE `userActivate`
  ADD PRIMARY KEY (`userActivateId`),
  ADD KEY `userActivate_userActivateUserId_users_userId` (`userActivateUserId`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`userId`),
  ADD UNIQUE KEY `userEmail` (`userEmail`),
  ADD UNIQUE KEY `userName` (`userName`),
  ADD UNIQUE KEY `userAccountNo` (`userAccountNo`),
  ADD UNIQUE KEY `userAccountNo_2` (`userAccountNo`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `auctions`
--
ALTER TABLE `auctions`
  MODIFY `auctionId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bids`
--
ALTER TABLE `bids`
  MODIFY `bidId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `categoryId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `heroes`
--
ALTER TABLE `heroes`
  MODIFY `heroId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `interests`
--
ALTER TABLE `interests`
  MODIFY `interestId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `moments`
--
ALTER TABLE `moments`
  MODIFY `momentId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `passResets`
--
ALTER TABLE `passResets`
  MODIFY `passResetId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `reviewId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trans`
--
ALTER TABLE `trans`
  MODIFY `transId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `userActivate`
--
ALTER TABLE `userActivate`
  MODIFY `userActivateId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `userId` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `auctions`
--
ALTER TABLE `auctions`
  ADD CONSTRAINT `auctions_ibfk_1` FOREIGN KEY (`auctionCategoryId`) REFERENCES `categories` (`categoryId`),
  ADD CONSTRAINT `auctions_ibfk_2` FOREIGN KEY (`auctionCreatedBy`) REFERENCES `users` (`userId`);

--
-- Constraints for table `bids`
--
ALTER TABLE `bids`
  ADD CONSTRAINT `bids_ibfk_1` FOREIGN KEY (`bidAuctionId`) REFERENCES `auctions` (`auctionId`),
  ADD CONSTRAINT `bids_ibfk_2` FOREIGN KEY (`bidUserId`) REFERENCES `users` (`userId`);

--
-- Constraints for table `interests`
--
ALTER TABLE `interests`
  ADD CONSTRAINT `interests_interestCategoryId_categories_categoryId` FOREIGN KEY (`interestCategoryId`) REFERENCES `categories` (`categoryId`),
  ADD CONSTRAINT `interests_interestUserId_users_userId` FOREIGN KEY (`interestUserId`) REFERENCES `users` (`userId`);

--
-- Constraints for table `moments`
--
ALTER TABLE `moments`
  ADD CONSTRAINT `moments_momentUserId_users_userId` FOREIGN KEY (`momentUserId`) REFERENCES `users` (`userId`);

--
-- Constraints for table `passResets`
--
ALTER TABLE `passResets`
  ADD CONSTRAINT `passResets_passRestUserId_users_userId` FOREIGN KEY (`passResetUserId`) REFERENCES `users` (`userId`);

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_reviewUserId_users_userId` FOREIGN KEY (`reviewUserId`) REFERENCES `users` (`userId`);

--
-- Constraints for table `trans`
--
ALTER TABLE `trans`
  ADD CONSTRAINT `trans_transAccountNo_users_userAccountNo` FOREIGN KEY (`transAccountNo`) REFERENCES `users` (`userAccountNo`),
  ADD CONSTRAINT `trans_transAuctionId_auctions_auctionId` FOREIGN KEY (`transAuctionId`) REFERENCES `auctions` (`auctionId`),
  ADD CONSTRAINT `trans_transUserId_users_userId` FOREIGN KEY (`transUserId`) REFERENCES `users` (`userId`);

--
-- Constraints for table `userActivate`
--
ALTER TABLE `userActivate`
  ADD CONSTRAINT `userActivate_userActivateUserId_users_userId` FOREIGN KEY (`userActivateUserId`) REFERENCES `users` (`userId`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
