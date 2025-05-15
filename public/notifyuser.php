<?php
ob_start();
session_start();
include("header.php");

require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log folder and JSON files
$logFolder = 'log';
$auctionJsonFile = "{$logFolder}/auctionReminder.json";
$paymentJsonFile = "{$logFolder}/paymentReminder.json";

// Ensure log folder exists
if (!file_exists($logFolder)) {
    mkdir($logFolder, 0777, true);
}

// Initialize or load JSON data for auction notifications
$sentAuctionNotifications = file_exists($auctionJsonFile) ? json_decode(file_get_contents($auctionJsonFile), true) : [];
$sentPaymentReminders = file_exists($paymentJsonFile) ? json_decode(file_get_contents($paymentJsonFile), true) : [];

try {

    $currentDate = date('Y-m-d H:i:s');

    /*** 1. SEND AUCTION NOTIFICATIONS ***/
    $query = "
        SELECT i.interestUserId AS userId, 
               u.userEmail, 
               c.categoryName, 
               a.auctionId, 
               a.auctionTitle, 
               a.auctionDescription, 
               a.auctionEndDate
        FROM interests i
        JOIN users u ON i.interestUserId = u.userId
        JOIN categories c ON i.interestCategoryId = c.categoryId
        JOIN auctions a ON a.auctionCategoryId = c.categoryId
        WHERE a.auctionStatus = 'activate'
          AND a.auctionStartDate <= :currentDate
          AND a.auctionEndDate >= :currentDate
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute(['currentDate' => $currentDate]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $userNotifications = [];
    foreach ($notifications as $notify) {
        $userId = $notify['userId'];
        $auctionId = $notify['auctionId'];

        if (isset($sentAuctionNotifications[$userId]) && in_array($auctionId, $sentAuctionNotifications[$userId])) {
            continue;
        }

        if (!isset($userNotifications[$userId])) {
            $userNotifications[$userId] = [
                'email' => $notify['userEmail'],
                'auctions' => []
            ];
        }

        $userNotifications[$userId]['auctions'][] = [
            'auctionId' => $auctionId,
            'categoryName' => $notify['categoryName'],
            'auctionTitle' => $notify['auctionTitle'],
            'auctionDescription' => $notify['auctionDescription'],
            'auctionEndDate' => $notify['auctionEndDate']
        ];

        $sentAuctionNotifications[$userId][] = $auctionId;
    }

    $emailCount = 0;
    foreach ($userNotifications as $userId => $userData) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'eagri.ct.ws@gmail.com';
            $mail->Password = 'xnfkhjazsdjlsrsg';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('eagri.ct.ws@gmail.com', 'eAgri Auction Notification');
            $mail->addAddress($userData['email']);

            $auctionList = "";
            foreach ($userData['auctions'] as $auction) {
                $auctionList .= "
                    <div style='background: linear-gradient(to right, #98fb98, #adff2f); padding: 15px; margin: 10px 0; border-radius: 10px; box-shadow: 2px 2px 10px rgba(0,0,0,0.2);'>
                        <h3 style='color:#fff;'>{$auction['auctionTitle']}</h3>
                        <p><strong>Category:</strong> {$auction['categoryName']}</p>
                        <p><strong>Description:</strong> {$auction['auctionDescription']}</p>
                        <p><strong>Ends On:</strong> {$auction['auctionEndDate']}</p>
                        <a href='http://localhost/eAuction/public/bid.php?id={$auction['auctionId']}' 
                           style='display:inline-block; background:#228B22; color:#fff; padding:8px 16px; border-radius:50px; text-decoration:none;'>
                           View Auction
                        </a>
                    </div>";
            }

            $mail->isHTML(true);
            $mail->Subject = 'New Auctions Matching Your Interests';
            $mail->Body = "<html><body>{$auctionList}</body></html>";

            if ($mail->send()) {
                $emailCount++;
            }
        } catch (Exception $e) {
            echo "Mailer Error for User ID {$userId}: {$mail->ErrorInfo}<br>";
        }
    }

    file_put_contents($auctionJsonFile, json_encode($sentAuctionNotifications, JSON_PRETTY_PRINT));

    /*** 2. SEND PAYMENT REMINDERS TO HIGHEST BIDDERS ***/
    $query = "
        SELECT b.bidUserId, u.userName, u.userEmail, a.auctionId, a.auctionTitle, b.bidAmount
        FROM bids b
        JOIN users u ON b.bidUserId = u.userId
        JOIN auctions a ON b.bidAuctionId = a.auctionId
        WHERE a.auctionEndDate < :currentDate
          AND a.auctionStatus = 'activate'
          AND b.bidAmount = (SELECT MAX(bidAmount) FROM bids WHERE bidAuctionId = a.auctionId)
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute(['currentDate' => $currentDate]);
    $highestBidders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $paymentEmailCount = 0;
    foreach ($highestBidders as $bid) {
        $userId = $bid['bidUserId'];
        $auctionId = $bid['auctionId'];

        if (isset($sentPaymentReminders[$userId]) && in_array($auctionId, $sentPaymentReminders[$userId])) {
            continue;
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'eagri.ct.ws@gmail.com';
            $mail->Password = 'xnfkhjazsdjlsrsg';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('eagri.ct.ws@gmail.com', 'eAgri Payment Reminder');
            $mail->addAddress($bid['userEmail']);

            $mail->isHTML(true);
            $mail->Subject = 'Payment Reminder for Your Auction Win';
$mail->Body = "
    <html>
    <body style='background: lightgray; padding: 20px; font-family: Arial, sans-serif;'>
        <div style='background: #333; color: #fff; padding: 20px; border-radius: 10px; text-align: center;'>
            <h2>Congratulations, {$bid['userName']}!</h2>
            <p>You are the winner of the auction.</p>
            <h3>{$bid['auctionTitle']}</h3>
            <p>
                <span style='background: #333; padding: 10px 20px; border-radius: 50px; display: inline-block; font-weight: bold; color: gold;'>
                    Winning Bid: ₹{$bid['bidAmount']}
                </span>
            </p>
            <p>
                <a href='http://localhost/eAuction/public/bid.php?id={$auctionId}' 
                   style='display: inline-block; background: #228B22; color: #fff; padding: 10px 20px; border-radius: 50px; text-decoration: none; font-weight: bold;'>
                   Pay Now
                </a>
            </p>
        </div>
    </body>
    </html>";

            if ($mail->send()) {
                $paymentEmailCount++;
                $sentPaymentReminders[$userId][] = $auctionId;
            }
        } catch (Exception $e) {
            echo "Mailer Error for Payment Reminder (User ID {$userId}): {$mail->ErrorInfo}<br>";
        }
    }

    file_put_contents($paymentJsonFile, json_encode($sentPaymentReminders, JSON_PRETTY_PRINT));

    echo "<h2>Summary</h2>";
    echo "<p>Auction Notifications Sent: <strong>{$emailCount}</strong></p>";
    echo "<p>Payment Reminders Sent: <strong>{$paymentEmailCount}</strong></p>";

} catch (Exception $e) {
    echo "Error: {$e->getMessage()}<br>";
}
?>