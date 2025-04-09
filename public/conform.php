<?php
session_start();
ob_start();
include("header.php");
require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';
require '../tcpdf/tcpdf.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

isAuthenticated();

if (!isset($_SESSION['userId'])) {
    echo "You need to be logged in to confirm.";
    exit();
}

$user_id = $_SESSION['userId'];
$auction_id = $_GET['id'] ?? null;

if (!$auction_id) {
    echo "Invalid auction ID.";
    exit();
}

// Get auction details
$auction = getAuctionById($auction_id);
$farmer_id = $auction["auctionCreatedBy"]; // Farmer (auction creator)
$vendor_id = getHighestBidderId($auction_id); // Vendor (highest bidder)
$highest_bid = getHighestBid($auction_id);
$farmer = getUserById($farmer_id);
$vendor = getUserById($vendor_id);

// Load transactions from JSON
$json_dir = '../public/InfoData';
$json_file = $json_dir . '/transactions.json';
if (!file_exists($json_file)) {
    echo "Transaction file not found.";
    exit();
}

$data = json_decode(file_get_contents($json_file), true);
$transactions = $data['transactions'] ?? [];
$transaction = null;

foreach ($transactions as $t) {
    if ($t['auction_id'] === $auction_id && $t['status'] === 'success') {
        $transaction = $t;
        break;
    }
}

if (!$transaction) {
    echo "No successful transaction found for this auction.";
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $transaction_id = $transaction['transaction_id'];

    if ($user_id === $vendor_id) { // Vendor (bidder) actions
        if ($action === 'proceed') {
            // Update existing transaction status
            foreach ($data['transactions'] as &$t) {
                if ($t['transaction_id'] === $transaction_id) {
                    $t['status'] = 'completed';
                    break;
                }
            }

            // New transaction: Middleman to Farmer
            $new_transaction = [
                'transaction_id' => uniqid('txn_', true),
                'vendor_id' => $vendor_id,
                'farmer_id' => $farmer_id,
                'auction_id' => $auction_id,
                'amount' => $highest_bid * 0.95, // 95% after 5% fee
                'from' => 'middleman_admin',
                'to' => $farmer_id,
                'timestamp' => date('Y-m-d H:i:s'),
                'status' => 'completed'
            ];
            $data['transactions'][] = $new_transaction;

            file_put_contents($json_file, json_encode($data, JSON_PRETTY_PRINT));

            // Update transStatus in MySQL
            $query = "UPDATE trans SET transStatus = 'completed' WHERE transAuctionId = :auction_id";
            $stmt = $pdo->prepare($query);
            $stmt->execute([':auction_id' => $auction_id]);

            // Generate Invoice for Farmer
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->SetMargins(10, 10, 10);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
            $pdf->SetFont('dejavusans', '', 10);
            $pdf->AddPage();

            $html = '
            <style>
                table, tr, td { padding: 10px; }
                .header { background-color: #222222; color: #fff; }
                .total { text-align: right; font-weight: bold; }
            </style>
            <table class="header">
                <tr>
                    <td><h1>INVOICE: #' . htmlspecialchars(explode('.', $new_transaction['transaction_id'])[1]) . '</h1></td>
                    <td align="right">
                        <img src="./logos/logo.png" height="50px"/><br>
                        1/283, Somvarapatti, Udumalpet, Tiruppur, Tamil Nadu - 642205<br>
                        <strong>+91-8015864344</strong> | <strong>22ct19nishanth@gmail.com</strong>
                    </td>
                </tr>
            </table>
            <table>
                <tr>
                    <td>Invoice to<br><strong>' . htmlspecialchars($farmer["userFirstName"] . " " . $farmer["userLastName"]) . '</strong></td>
                    <td align="right">
                        <strong>Total Received: ₹' . ($highest_bid * 0.95) . '</strong><br>
                        Invoice Date: ' . date("d-m-Y") . '
                    </td>
                </tr>
            </table>
            <table>
                <tr>
                    <td><strong>Transaction:</strong><br>
                        From: Middleman Admin<br>
                        To: ' . htmlspecialchars($farmer["userName"]) . '<br>
                        Transaction ID: ' . htmlspecialchars(explode('_', explode('.', $new_transaction['transaction_id'])[0])[1]) . '
                    </td>
                </tr>
            </table>
            <table>
                <thead>
                    <tr style="font-weight: bold;">
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>' . htmlspecialchars($auction["auctionTitle"]) . '</td>
                        <td>' . htmlspecialchars(getCategoryById($auction["auctionCategoryId"])) . '</td>
                        <td>₹' . $highest_bid . '</td>
                        <td>1</td>
                        <td>₹' . $highest_bid . '</td>
                    </tr>
                </tbody>
            </table>
            <p><strong>Platform Fees:</strong> 5% (₹' . ($highest_bid * 0.05) . ') - 3% (₹' . ($highest_bid * 0.03) . ') for maintenance, 2% (₹' . ($highest_bid * 0.02) . ') for security.</p>
            <p class="total">Net Amount: ₹' . ($highest_bid * 0.95) . '</p>
            <p style="text-align: center;"><h2>Thank you for your business!</h2></p>';

            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf_file = 'invoice_payment_' . $auction_id . '.pdf';
            $pdf_content = $pdf->Output($pdf_file, 'S');

            // Email to Farmer
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'eagri.ct.ws@gmail.com';
                $mail->Password = 'xnfkhjazsdjlsrsg';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                $mail->setFrom('eagri.ct.ws@gmail.com', 'eAgri Auction');
                $mail->addAddress($farmer['userEmail']);
                $mail->isHTML(true);
                $mail->Subject = 'Payment Received for Auction ID: ' . $auction_id;
                $mail->Body = '
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <style>
                        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
                        .container { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
                        .header { background: #28a745; color: #fff; padding: 20px; text-align: center; }
                        .header h1 { margin: 0; font-size: 24px; }
                        .content { padding: 20px; color: #333; }
                        .content p { margin: 10px 0; line-height: 1.6; }
                        .details { background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 15px 0; }
                        .details strong { color: #2c3e50; }
                        .footer { background: #ecf0f1; padding: 15px; text-align: center; font-size: 12px; color: #777; }
                        .footer a { color: #28a745; text-decoration: none; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>Payment Received</h1>
                        </div>
                        <div class="content">
                            <p>Dear ' . htmlspecialchars($farmer["userFirstName"] . " " . $farmer["userLastName"]) . ',</p>
                            <p>We are pleased to inform you that the payment for your auction has been successfully transferred to your account.</p>
                            <div class="details">
                                <p><strong>Auction ID:</strong> ' . $auction_id . '</p>
                                <p><strong>Amount Received:</strong> ₹' . ($highest_bid * 0.95) . '</p>
                                <p><strong>Platform Fees:</strong> 5% (₹' . ($highest_bid * 0.05) . ') - 3% maintenance, 2% security</p>
                                <p><strong>Transaction ID:</strong> ' . htmlspecialchars(explode('_', explode('.', $new_transaction['transaction_id'])[0])[1]) . '</p>
                            </div>
                            <p>Please find the invoice attached for your records.</p>
                            <p>Thank you for your business!</p>
                        </div>
                        <div class="footer">
                            <p>eAgri Auction | 1/283, Somvarapatti, Udumalpet, Tiruppur, Tamil Nadu - 642205</p>
                            <p><a href="mailto:support@eagriauction.com">support@eagriauction.com</a> | +91-8015864344</p>
                        </div>
                    </div>
                </body>
                </html>';
                $mail->addStringAttachment($pdf_content, $pdf_file, 'base64', 'application/pdf');
                $mail->send();
            } catch (Exception $e) {
                error_log("Farmer email failed: " . $e->getMessage());
            }

            // Email to Vendor
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'eagri.ct.ws@gmail.com';
                $mail->Password = 'xnfkhjazsdjlsrsg';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                $mail->setFrom('eagri.ct.ws@gmail.com', 'eAgri Auction');
                $mail->addAddress($vendor['userEmail']);
                $mail->isHTML(true);
                $mail->Subject = 'Payment Processed for Auction ID: ' . $auction_id;
                $mail->Body = '
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <style>
                        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
                        .container { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
                        .header { background: #28a745; color: #fff; padding: 20px; text-align: center; }
                        .header h1 { margin: 0; font-size: 24px; }
                        .content { padding: 20px; color: #333; }
                        .content p { margin: 10px 0; line-height: 1.6; }
                        .details { background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 15px 0; }
                        .details strong { color: #2c3e50; }
                        .footer { background: #ecf0f1; padding: 15px; text-align: center; font-size: 12px; color: #777; }
                        .footer a { color: #28a745; text-decoration: none; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>Payment Processed</h1>
                        </div>
                        <div class="content">
                            <p>Dear ' . htmlspecialchars($vendor["userFirstName"] . " " . $vendor["userLastName"]) . ',</p>
                            <p>Thank you for confirming receipt of the product. The payment for the auction has been successfully processed and transferred to the farmer.</p>
                            <div class="details">
                                <p><strong>Auction ID:</strong> ' . $auction_id . '</p>
                                <p><strong>Amount Paid:</strong> ₹' . ($highest_bid * 0.95) . '</p>
                                <p><strong>Transaction ID:</strong> ' . htmlspecialchars(explode('_', explode('.', $new_transaction['transaction_id'])[0])[1]) . '</p>
                            </div>
                            <p>We appreciate your cooperation!</p>
                        </div>
                        <div class="footer">
                            <p>eAgri Auction | 1/283, Somvarapatti, Udumalpet, Tiruppur, Tamil Nadu - 642205</p>
                            <p><a href="mailto:support@eagriauction.com">support@eagriauction.com</a> | +91-8015864344</p>
                        </div>
                    </div>
                </body>
                </html>';
                $mail->send();
            } catch (Exception $e) {
                error_log("Vendor email failed: " . $e->getMessage());
            }

            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                    Swal.fire({
                        title: 'Success!',
                        text: 'Transaction payment settled.',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.href = 'index.php';
                    });
                  </script>";
            exit();

        } elseif ($action === 'refund') {
            $image_count = isset($_FILES['proof']) ? count($_FILES['proof']['name']) : 0;
            if ($image_count === 0 || $image_count > 3) {
                echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                echo "<script>Swal.fire('Error', 'Please upload 1 to 3 proof images.', 'error');</script>";
            } else {
                $upload_dir = '../images/refund/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0775, true);
                }

                $image_paths = [];
                for ($i = 0; $i < $image_count; $i++) {
                    if ($_FILES['proof']['error'][$i] === UPLOAD_ERR_OK) {
                        $image_file = $upload_dir . $transaction_id . '_proof_' . $i . '_' . basename($_FILES['proof']['name'][$i]);
                        move_uploaded_file($_FILES['proof']['tmp_name'][$i], $image_file);
                        $image_paths[] = $image_file;
                    }
                }

                // Store refund details in refund.json
                $refund_file = $json_dir . '/refund.json';
                if (!file_exists($refund_file)) {
                    file_put_contents($refund_file, json_encode(['refunds' => []], JSON_PRETTY_PRINT));
                }
                $refund_data = json_decode(file_get_contents($refund_file), true);
                $refund_entry = [
                    'transaction_id' => $transaction_id,
                    'auction_id' => $auction_id,
                    'vendor_id' => $vendor_id,
                    'farmer_id' => $farmer_id,
                    'amount' => $highest_bid,
                    'image_paths' => $image_paths,
                    'status' => 'pending_admin_review',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                $refund_data['refunds'][] = $refund_entry;
                file_put_contents($refund_file, json_encode($refund_data, JSON_PRETTY_PRINT));

                // Update transaction status to 'refund_requested'
                foreach ($data['transactions'] as &$t) {
                    if ($t['transaction_id'] === $transaction_id) {
                        $t['status'] = 'refund_requested';
                        break;
                    }
                }
                file_put_contents($json_file, json_encode($data, JSON_PRETTY_PRINT));

                // Update transStatus in MySQL
                $query = "UPDATE trans SET transStatus = 'refund_requested' WHERE transAuctionId = :auction_id";
                $stmt = $pdo->prepare($query);
                $stmt->execute([':auction_id' => $auction_id]);

                // Generate Refund Request Invoice for Vendor
                $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
                $pdf->SetMargins(10, 10, 10);
                $pdf->setPrintHeader(false);
                $pdf->setPrintFooter(false);
                $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
                $pdf->SetFont('dejavusans', '', 10);
                $pdf->AddPage();

                $html = '
                <style>
                    table, tr, td { padding: 10px; }
                    .header { background-color: #222222; color: #fff; }
                    .total { text-align: right; font-weight: bold; }
                </style>
                <table class="header">
                    <tr>
                        <td><h1>REFUND REQUEST INVOICE: #' . htmlspecialchars(explode('.', $transaction_id)[1]) . '</h1></td>
                        <td align="right">
                            <img src="./logos/logo.png" height="50px"/><br>
                            1/283, Somvarapatti, Udumalpet, Tiruppur, Tamil Nadu - 642205<br>
                            <strong>+91-8015864344</strong> | <strong>22ct19nishanth@gmail.com</strong>
                        </td>
                    </tr>
                </table>
                <table>
                    <tr>
                        <td>Invoice to<br><strong>' . htmlspecialchars($vendor["userFirstName"] . " " . $vendor["userLastName"]) . '</strong></td>
                        <td align="right">
                            <strong>Total Requested: ₹' . $highest_bid . '</strong><br>
                            Invoice Date: ' . date("d-m-Y") . '
                        </td>
                    </tr>
                </table>
                <table>
                    <tr>
                        <td><strong>Transaction:</strong><br>
                            From: Middleman Admin<br>
                            To: ' . htmlspecialchars($vendor["userName"]) . '<br>
                            Transaction ID: ' . htmlspecialchars(explode('_', explode('.', $transaction_id)[0])[1]) . '
                        </td>
                    </tr>
                </table>
                <table>
                    <thead>
                        <tr style="font-weight: bold;">
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>' . htmlspecialchars($auction["auctionTitle"]) . '</td>
                            <td>' . htmlspecialchars(getCategoryById($auction["auctionCategoryId"])) . '</td>
                            <td>₹' . $highest_bid . '</td>
                            <td>1</td>
                            <td>₹' . $highest_bid . '</td>
                        </tr>
                    </tbody>
                </table>
                <p><strong>Note:</strong> Refund is pending admin review. If approved, 5% platform fees (₹' . ($highest_bid * 0.05) . ') will be deducted.</p>
                <p style="text-align: center;"><h2>Refund Request Submitted</h2></p>';

                $pdf->writeHTML($html, true, false, true, false, '');
                $pdf_file = 'invoice_refund_request_' . $auction_id . '.pdf';
                $pdf_content = $pdf->Output($pdf_file, 'S');

                // Email to Vendor
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'eagri.ct.ws@gmail.com';
                    $mail->Password = 'xnfkhjazsdjlsrsg';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;
                    $mail->setFrom('eagri.ct.ws@gmail.com', 'eAgri Auction');
                    $mail->addAddress($vendor['userEmail']);
                    $mail->isHTML(true);
                    $mail->Subject = 'Refund Request Submitted for Auction ID: ' . $auction_id;
                    $mail->Body = '
                    <!DOCTYPE html>
                    <html lang="en">
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <style>
                            body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
                            .container { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
                            .header { background: #e74c3c; color: #fff; padding: 20px; text-align: center; }
                            .header h1 { margin: 0; font-size: 24px; }
                            .content { padding: 20px; color: #333; }
                            .content p { margin: 10px 0; line-height: 1.6; }
                            .details { background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 15px 0; }
                            .details strong { color: #2c3e50; }
                            .footer { background: #ecf0f1; padding: 15px; text-align: center; font-size: 12px; color: #777; }
                            .footer a { color: #e74c3c; text-decoration: none; }
                        </style>
                    </head>
                    <body>
                        <div class="container">
                            <div class="header">
                                <h1>Refund Request Submitted</h1>
                            </div>
                            <div class="content">
                                <p>Dear ' . htmlspecialchars($vendor["userFirstName"] . " " . $vendor["userLastName"]) . ',</p>
                                <p>Your refund request for the following auction has been successfully submitted and is pending admin review.</p>
                                <div class="details">
                                    <p><strong>Auction ID:</strong> ' . $auction_id . '</p>
                                    <p><strong>Amount Requested:</strong> ₹' . $highest_bid . '</p>
                                    <p><strong>Transaction ID:</strong> ' . htmlspecialchars(explode('_', explode('.', $transaction_id)[0])[1]) . '</p>
                                    <p><strong>Note:</strong> If approved, 5% platform fees (₹' . ($highest_bid * 0.05) . ') will be deducted, and ₹' . ($highest_bid * 0.95) . ' will be refunded.</p>
                                </div>
                                <p>Please find the invoice attached for your records.</p>
                            </div>
                            <div class="footer">
                                <p>eAgri Auction | 1/283, Somvarapatti, Udumalpet, Tiruppur, Tamil Nadu - 642205</p>
                                <p><a href="mailto:support@eagriauction.com">support@eagriauction.com</a> | +91-8015864344</p>
                            </div>
                        </div>
                    </body>
                    </html>';
                    $mail->addStringAttachment($pdf_content, $pdf_file, 'base64', 'application/pdf');
                    $mail->send();
                } catch (Exception $e) {
                    error_log("Vendor refund email failed: " . $e->getMessage());
                }

                // Email to Farmer
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'eagri.ct.ws@gmail.com';
                    $mail->Password = 'xnfkhjazsdjlsrsg';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;
                    $mail->setFrom('eagri.ct.ws@gmail.com', 'eAgri Auction');
                    $mail->addAddress($farmer['userEmail']);
                    $mail->isHTML(true);
                    $mail->Subject = 'Refund Request Received for Auction ID: ' . $auction_id;
                    $mail->Body = '
                    <!DOCTYPE html>
                    <html lang="en">
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <style>
                            body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
                            .container { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
                            .header { background: #f39c12; color: #fff; padding: 20px; text-align: center; }
                            .header h1 { margin: 0; font-size: 24px; }
                            .content { padding: 20px; color: #333; }
                            .content p { margin: 10px 0; line-height: 1.6; }
                            .details { background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 15px 0; }
                            .details strong { color: #2c3e50; }
                            .footer { background: #ecf0f1; padding: 15px; text-align: center; font-size: 12px; color: #777; }
                            .footer a { color: #f39c12; text-decoration: none; }
                        </style>
                    </head>
                    <body>
                        <div class="container">
                            <div class="header">
                                <h1>Refund Request Received</h1>
                            </div>
                            <div class="content">
                                <p>Dear ' . htmlspecialchars($farmer["userFirstName"] . " " . $farmer["userLastName"]) . ',</p>
                                <p>The vendor has submitted a refund request for your auction due to unsatisfactory product quality.</p>
                                <div class="details">
                                    <p><strong>Auction ID:</strong> ' . $auction_id . '</p>
                                    <p><strong>Amount:</strong> ₹' . $highest_bid . '</p>
                                    <p><strong>Transaction ID:</strong> ' . htmlspecialchars(explode('_', explode('.', $transaction_id)[0])[1]) . '</p>
                                    <p><strong>Status:</strong> Pending admin review</p>
                                </div>
                                <p>We will notify you once the review is complete.</p>
                            </div>
                            <div class="footer">
                                <p>eAgri Auction | 1/283, Somvarapatti, Udumalpet, Tiruppur, Tamil Nadu - 642205</p>
                                <p><a href="mailto:support@eagriauction.com">support@eagriauction.com</a> | +91-8015864344</p>
                            </div>
                        </div>
                    </body>
                    </html>';
                    $mail->send();
                } catch (Exception $e) {
                    error_log("Farmer refund notification email failed: " . $e->getMessage());
                }

                echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                echo "<script>
                        Swal.fire({
                            title: 'Refund Requested',
                            text: 'Refund request submitted. Waiting for admin review.',
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            window.location.href = 'index.php';
                        });
                      </script>";
                exit();
            }
        }
    } else {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>Swal.fire('Error', 'You are not authorized to confirm this transaction.', 'error');</script>";
        exit();
    }
}

include("navbar.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Transaction - Auction #<?php echo $auction_id; ?></title>
    <?php include("../assets/link.html"); ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f5f5f5; }
        .container { max-width: 600px; margin: 40px auto; padding: 20px; background: #fff; border-radius: 10px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); }
        h1 { font-size: 1.8rem; color: #2c3e50; text-align: center; }
        .card { border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); }
        .card-header { background: #28a745; color: #fff; padding: 10px; text-align: center; }
        .card-body { padding: 20px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; color: #34495e; margin-bottom: 5px; }
        .btn { padding: 10px; border: none; border-radius: 5px; color: #fff; width: 100%; cursor: pointer; transition: background 0.3s; }
        .btn-proceed { background: #28a745; }
        .btn-refund { background: #e74c3c; }
        .btn:hover { opacity: 0.9; }
        input[type="file"] { width: 100%; padding: 10px; border: 1px solid #dcdcdc; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Confirm Transaction - Auction #<?php echo $auction_id; ?></h1>
        <div class="card">
            <div class="card-header">Transaction Details</div>
            <div class="card-body">
                <p><strong>Auction Title:</strong> <?php echo htmlspecialchars($auction["auctionTitle"]); ?></p>
                <p><strong>Amount:</strong> ₹<?php echo $highest_bid; ?></p>
                <p><strong>Farmer:</strong> <?php echo htmlspecialchars($farmer["userName"]); ?></p>
                <p><strong>Vendor:</strong> <?php echo htmlspecialchars($vendor["userName"]); ?></p>

                <?php if ($user_id === $vendor_id): ?>
                    <form method="POST" enctype="multipart/form-data" id="confirmForm">
                        <div class="form-group">
                            <button type="button" class="btn btn-proceed" onclick="confirmProceed()">Proceed Payment to Farmer</button>
                        </div>
                        <div class="form-group">
                            <label for="proof">Request Refund (Upload up to 3 Proof Images):</label>
                            <input type="file" name="proof[]" id="proof" accept="image/*" multiple>
                            <button type="button" class="btn btn-refund" onclick="confirmRefund()">Request Refund</button>
                        </div>
                        <input type="hidden" name="action" id="formAction">
                    </form>
                <?php else: ?>
                    <p>Please wait for the vendor to confirm the transaction.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function confirmProceed() {
            Swal.fire({
                title: 'Confirm Receipt',
                text: 'Have you received the product from the farmer?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes',
                cancelButtonText: 'No'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('formAction').value = 'proceed';
                    document.getElementById('confirmForm').submit();
                }
            });
        }

        function confirmRefund() {
            const files = document.getElementById('proof').files;
            if (files.length === 0 || files.length > 3) {
                Swal.fire('Error', 'Please upload 1 to 3 proof images.', 'error');
            } else {
                Swal.fire({
                    title: 'Confirm Refund',
                    text: 'Are you sure the product quality is unsatisfactory?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes',
                    cancelButtonText: 'No'
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('formAction').value = 'refund';
                        document.getElementById('confirmForm').submit();
                    }
                });
            }
        }
    </script>
</body>
</html>
<?php
ob_end_flush();
?>