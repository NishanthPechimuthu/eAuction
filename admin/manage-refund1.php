<?php
ob_start();
include("header.php");
include("navbar.php");
require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';
require '../tcpdf/tcpdf.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

isAuthenticatedAsAdmin();

$json_dir = '../public/InfoData';
$refund_file = $json_dir . '/refund.json';
$transactions_file = $json_dir . '/transactions.json';
$report_file = $json_dir . '/report.json';

// Load refund data
$refund_data = file_exists($refund_file) ? json_decode(file_get_contents($refund_file), true) : ['refunds' => []];
$refunds = $refund_data['refunds'] ?? [];

// Load transaction data
$transaction_data = file_exists($transactions_file) ? json_decode(file_get_contents($transactions_file), true) : ['transactions' => []];

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $refund_id = $_POST['refund_id'] ?? null;
    $action = $_POST['action'] ?? null;

    // Track if refund was found and updated
    $refund_updated = false;

    foreach ($refunds as &$refund) {
        if ($refund['transaction_id'] === $refund_id) {
            $auction_id = $refund['auction_id'];
            $vendor_id = $refund['vendor_id'];
            $farmer_id = $refund['farmer_id'];
            $amount = $refund['amount'];
            $vendor = getUserById($vendor_id);
            $farmer = getUserById($farmer_id);
            $auction = getAuctionById($auction_id);

            if ($action === 'approve') {
                // Update refund status
                $refund['status'] = 'approved';

                // Update transaction status
                $transaction_updated = false;
                foreach ($transaction_data['transactions'] as &$t) {
                    if ($t['transaction_id'] === $refund_id) {
                        $t['status'] = 'refunded';
                        $transaction_updated = true;
                        break;
                    }
                }

                if (!$transaction_updated) {
                    error_log("Transaction not found for refund_id: $refund_id");
                    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                    echo "<script>
                            document.addEventListener('DOMContentLoaded', function() {
                                Swal.fire({
                                    title: 'Error',
                                    text: 'Transaction not found for this refund request.',
                                    icon: 'error',
                                    confirmButtonColor: '#dc3545'
                                });
                            });
                          </script>";
                    exit();
                }

                // Create new transaction: Middleman to Vendor
                $new_transaction = [
                    'transaction_id' => uniqid('txn_', true),
                    'vendor_id' => $vendor_id,
                    'farmer_id' => $farmer_id,
                    'auction_id' => $auction_id,
                    'amount' => $amount * 0.95,
                    'from' => 'middleman_admin',
                    'to' => $vendor_id,
                    'timestamp' => date('Y-m-d H:i:s'),
                    'status' => 'refunded'
                ];
                $transaction_data['transactions'][] = $new_transaction;

                // Update report.json
                if (!file_exists($report_file)) {
                    file_put_contents($report_file, json_encode(['reports' => []], JSON_PRETTY_PRINT));
                }
                $report_data = json_decode(file_get_contents($report_file), true);
                $report_entry = [
                    'report_id' => uniqid('rep_', true),
                    'user_id' => $farmer_id,
                    'user_name' => $farmer['userName'],
                    'auction_id' => $auction_id,
                    'reason' => 'Vendor reported unsatisfactory product quality',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                $report_data['reports'][] = $report_entry;
                file_put_contents($report_file, json_encode($report_data, JSON_PRETTY_PRINT));

                // Generate Refund Invoice for Vendor
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
<td><h1>REFUND INVOICE: #' . htmlspecialchars(explode('.', $new_transaction['transaction_id'])[1]) . '</h1></td>
                        <td align="right">
                            <img src="./logos/logo.png" height="50px"/><br>
                            1/283, Somvarapatti, Udumalpet, Tiruppur, Tamil Nadu - 642205<br>
                            <strong>+91-8015864344</strong> | <strong>eagri.ct.ws@gmail.com</strong>
                        </td>
                    </tr>
                </table>
                <table>
                    <tr>
                        <td>Invoice to<br><strong>' . htmlspecialchars($vendor["userFirstName"] . " " . $vendor["userLastName"]) . '</strong></td>
                        <td align="right">
                            <strong>Total Refunded: ₹' . ($amount * 0.95) . '</strong><br>
                            Invoice Date: ' . date("d-m-Y") . '
                        </td>
                    </tr>
                </table>
                <table>
                    <tr>
                        <td><strong>Transaction:</strong><br>
                            From: Middleman Admin<br>
                            To: ' . htmlspecialchars($vendor["userName"]) . '<br>
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
                            <td>₹' . $amount . '</td>
                            <td>1</td>
                            <td>₹' . $amount . '</td>
                        </tr>
                    </tbody>
                </table>
                <p><strong>Platform Fees:</strong> 5% (₹' . ($amount * 0.05) . ') deducted.</p>
                <p class="total">Net Refunded: ₹' . ($amount * 0.95) . '</p>
                <p style="text-align: center;"><h2>Refund Processed</h2></p>';

                $pdf->writeHTML($html, true, false, true, false, '');
                $pdf_file = 'invoice_refunded_' . $auction_id . '.pdf';
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
                    $mail->Subject = 'Refund Approved for Auction ID: ' . $auction_id;
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
                                <h1>Refund Approved</h1>
                            </div>
                            <div class="content">
                                <p>Dear ' . htmlspecialchars($vendor["userFirstName"] . " " . $vendor["userLastName"]) . ',</p>
                                <p>Your refund request for the following auction has been approved, and the amount has been refunded to your account.</p>
                                <div class="details">
                                    <p><strong>Auction ID:</strong> ' . $auction_id . '</p>
                                    <p><strong>Amount Refunded:</strong> ₹' . ($amount * 0.95) . '</p>
                                    <p><strong>Platform Fees:</strong> 5% (₹' . ($amount * 0.05) . ') deducted</p>
                                    <p><strong>Transaction ID:</strong> ' . htmlspecialchars(explode('_', explode('.', $new_transaction['transaction_id'])[0])[1]) . '</p>
                                </div>
                                <p>Please find the refund invoice attached for your records.</p>
                            </div>
                            <div class="footer">
                                <p>eAgri Auction | 1/283, Somvarapatti, Udumalpet, Tiruppur, Tamil Nadu - 642205</p>
                                <p><a href="mailto:eagri.ct.ws@gmail.com">eagri.ct.ws@gmail.com</a> | +91-8015864344</p>
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
                    $mail->Subject = 'Refund Approved for Auction ID: ' . $auction_id;
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
                                <h1>Refund Approved</h1>
                            </div>
                            <div class="content">
                                <p>Dear ' . htmlspecialchars($farmer["userFirstName"] . " " . $farmer["userLastName"]) . ',</p>
                                <p>The refund request for your auction has been approved due to unsatisfactory product quality. The amount has been refunded to the vendor.</p>
                                <div class="details">
                                    <p><strong>Auction ID:</strong> ' . $auction_id . '</p>
                                    <p><strong>Amount Refunded:</strong> ₹' . ($amount * 0.95) . '</p>
                                    <p><strong>Transaction ID:</strong> ' . htmlspecialchars(explode('_', explode('.', $new_transaction['transaction_id'])[0])[1]) . '</p>
                                </div>
                                <p>Please ensure product quality in future transactions to avoid such issues.</p>
                            </div>
                            <div class="footer">
                                <p>eAgri Auction | 1/283, Somvarapatti, Udumalpet, Tiruppur, Tamil Nadu - 642205</p>
                                <p><a href="mailto:eagri.ct.ws@gmail.com">eagri.ct.ws@gmail.com</a> | +91-8015864344</p>
                            </div>
                        </div>
                    </body>
                    </html>';
                    $mail->send();
                } catch (Exception $e) {
                    error_log("Farmer refund notification email failed: " . $e->getMessage());
                }

            } elseif ($action === 'decline') {
                // Update refund status
                $refund['status'] = 'declined';

                // Update transaction status
                $transaction_updated = false;
                foreach ($transaction_data['transactions'] as &$t) {
                    if ($t['transaction_id'] === $refund_id) {
                        $t['status'] = 'completed';
                        $transaction_updated = true;
                        break;
                    }
                }

                if (!$transaction_updated) {
                    error_log("Transaction not found for refund_id: $refund_id");
                    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                    echo "<script>
                            document.addEventListener('DOMContentLoaded', function() {
                                Swal.fire({
                                    title: 'Error',
                                    text: 'Transaction not found for this refund request.',
                                    icon: 'error',
                                    confirmButtonColor: '#dc3545'
                                });
                            });
                          </script>";
                    exit();
                }

                // Create new transaction: Middleman to Farmer
                $new_transaction = [
                    'transaction_id' => uniqid('txn_', true),
                    'vendor_id' => $vendor_id,
                    'farmer_id' => $farmer_id,
                    'auction_id' => $auction_id,
                    'amount' => $amount * 0.95,
                    'from' => 'middleman_admin',
                    'to' => $farmer_id,
                    'timestamp' => date('Y-m-d H:i:s'),
                    'status' => 'completed'
                ];
                $transaction_data['transactions'][] = $new_transaction;

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
                            <strong>+91-8015864344</strong> | <strong>eagri.ct.ws@gmail.com</strong>
                        </td>
                    </tr>
                </table>
                <table>
                    <tr>
                        <td>Invoice to<br><strong>' . htmlspecialchars($farmer["userFirstName"] . " " . $farmer["userLastName"]) . '</strong></td>
                        <td align="right">
                            <strong>Total Received: ₹' . ($amount * 0.95) . '</strong><br>
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
                            <td>₹' . $amount . '</td>
                            <td>1</td>
                            <td>₹' . $amount . '</td>
                        </tr>
                    </tbody>
                </table>
                <p><strong>Platform Fees:</strong> 5% (₹' . ($amount * 0.05) . ') deducted.</p>
                <p class="total">Net Amount: ₹' . ($amount * 0.95) . '</p>
                <p style="text-align: center;"><h2>Payment Processed</h2></p>';

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
                                <p>The refund request for your auction has been declined, and the payment has been transferred to your account.</p>
                                <div class="details">
                                    <p><strong>Auction ID:</strong> ' . $auction_id . '</p>
                                    <p><strong>Amount Received:</strong> ₹' . ($amount * 0.95) . '</p>
                                    <p><strong>Platform Fees:</strong> 5% (₹' . ($amount * 0.05) . ') deducted</p>
                                    <p><strong>Transaction ID:</strong> ' . htmlspecialchars(explode('_', explode('.', $new_transaction['transaction_id'])[0])[1]) . '</p>
                                </div>
                                <p>Please find the invoice attached for your records.</p>
                            </div>
                            <div class="footer">
                                <p>eAgri Auction | 1/283, Somvarapatti, Udumalpet, Tiruppur, Tamil Nadu - 642205</p>
                                <p><a href="mailto:eagri.ct.ws@gmail.com">eagri.ct.ws@gmail.com</a> | +91-8015864344</p>
                            </div>
                        </div>
                    </body>
                    </html>';
                    $mail->addStringAttachment($pdf_content, $pdf_file, 'base64', 'application/pdf');
                    $mail->send();
                } catch (Exception $e) {
                    error_log("Farmer payment email failed: " . $e->getMessage());
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
                    $mail->Subject = 'Refund Declined for Auction ID: ' . $auction_id;
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
                                <h1>Refund Declined</h1>
                            </div>
                            <div class="content">
                                <p>Dear ' . htmlspecialchars($vendor["userFirstName"] . " " . $vendor["userLastName"]) . ',</p>
                                <p>Your refund request for the following auction has been declined, and the payment has been transferred to the farmer.</p>
                                <div class="details">
                                    <p><strong>Auction ID:</strong> ' . $auction_id . '</p>
                                    <p><strong>Amount:</strong> ₹' . $amount . '</p>
                                    <p><strong>Transaction ID:</strong> ' . htmlspecialchars(explode('_', explode('.', $new_transaction['transaction_id'])[0])[1]) . '</p>
                                </div>
                                <p>Please contact support for further assistance.</p>
                            </div>
                            <div class="footer">
                                <p>eAgri Auction | 1/283, Somvarapatti, Udumalpet, Tiruppur, Tamil Nadu - 642205</p>
                                <p><a href="mailto:eagri.ct.ws@gmail.com">eagri.ct.ws@gmail.com</a> | +91-8015864344</p>
                            </div>
                        </div>
                    </body>
                    </html>';
                    $mail->send();
                } catch (Exception $e) {
                    error_log("Vendor refund declined email failed: " . $e->getMessage());
                }
            }

            $refund_updated = true;
            break; // Exit loop after updating the refund
        }
    }

    if ($refund_updated) {
        // Save updated refund and transaction data
        if (file_put_contents($refund_file, json_encode($refund_data, JSON_PRETTY_PRINT)) === false) {
            error_log("Failed to write to refund.json");
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            title: 'Error',
                            text: 'Failed to update refund data.',
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    });
                  </script>";
            exit();
        }

        if (file_put_contents($transactions_file, json_encode($transaction_data, JSON_PRETTY_PRINT)) === false) {
            error_log("Failed to write to transactions.json");
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            title: 'Error',
                            text: 'Failed to update transaction data.',
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    });
                  </script>";
            exit();
        }

        // Update transStatus in MySQL
        $query = "UPDATE trans SET transStatus = :status WHERE transAuctionId = :auction_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':status' => $action === 'approve' ? 'activate' : 'deactivate',
            ':auction_id' => $auction_id
        ]);

        // Check if MySQL update was successful
        if ($stmt->rowCount() === 0) {
            error_log("No rows updated in trans table for auction_id: $auction_id");
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            title: 'Error',
                            text: 'Failed to update transaction status in database.',
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    });
                  </script>";
            exit();
        }

        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Refund request has been " . ($action === 'approve' ? 'approved' : 'declined') . " successfully.',
                        icon: 'success',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#28a745'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = 'manage-refund.php';
                        }
                    });
                });
              </script>";
        exit();
    } else {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Error',
                        text: 'Refund request not found.',
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                });
              </script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Refunds</title>
    <?php include_once("../assets/link.html"); ?>
    <link href="../assets/css/table-styles.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        td {
            height: 50px;
            line-height: 50px;
        }
        td, th {
            min-width: 100px;
            max-width: 140px;
            text-align: center;
            vertical-align: middle;
            white-space: nowrap;
            overflow: auto;
            padding: 10px;
        }
        td img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        @media (max-width: 768px) {
            td {
                height: 40px;
                line-height: 40px;
            }
            th, td {
                font-size: 12px;
                padding: 5px;
            }
            td img {
                width: 40px;
                height: 40px;
            }
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-money-bill-wave me-1"></i> Manage Refunds
            </div>
            <div class="card-body">
                <table id="refundsTable">
                    <thead>
                        <tr>
                            <th>S/No</th>
                            <th>Auction ID</th>
                            <th>Vendor</th>
                            <th>Farmer</th>
                            <th>Amount</th>
                            <th>Proof Images</th>
                            <th>Status</th>
                            <th>Approve</th>
                            <th>Decline</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; ?>
                        <?php foreach ($refunds as $refund): ?>
                            <tr>
                                <td><?= $counter++ ?></td>
                                <td><?= htmlspecialchars($refund['auction_id']) ?></td>
                                <td><?= htmlspecialchars(getUserName($refund['vendor_id'])) ?></td>
                                <td>
                                    <a href="./manage-report.php?user_id=<?= htmlspecialchars($refund['farmer_id']) ?>">
                                        <?= htmlspecialchars(getUserName($refund['farmer_id'])) ?>
                                    </a>
                                </td>
                                <td>₹<?= $refund['amount'] ?></td>
                                <td>
                                    <?php foreach ($refund['image_paths'] as $img): ?>
                                        <img src="<?= htmlspecialchars($img) ?>" alt="Proof Image">
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <p class="badge rounded-pill <?= $refund['status'] === 'approved' ? 'bg-success text-white' : ($refund['status'] === 'declined' ? 'bg-danger text-white' : 'bg-warning text-dark') ?> m-0">
                                        <?= htmlspecialchars(ucfirst($refund['status'])) ?>
                                    </p>
                                </td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="refund_id" value="<?= htmlspecialchars($refund['transaction_id']) ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" 
                                                <?= $refund['status'] !== 'pending_admin_review' ? 'disabled' : '' ?> 
                                                class="btn btn-success btn-sm fw-bold text-white" 
                                                onclick="return confirm('Are you sure you want to approve this refund request?')">Approve</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="refund_id" value="<?= htmlspecialchars($refund['transaction_id']) ?>">
                                        <input type="hidden" name="action" value="decline">
                                        <button type="submit" 
                                                <?= $refund['status'] !== 'pending_admin_review' ? 'disabled' : '' ?> 
                                                class="btn btn-danger btn-sm fw-bold text-white" 
                                                onclick="return confirm('Are you sure you want to decline this refund request?')">Decline</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="text-center mt-4">
            <p>eAgri Auction | 1/283, Somvarapatti, Udumalpet, Tiruppur, Tamil Nadu - 642205</p>
            <p><a href="mailto:eagri.ct.ws@gmail.com">eagri.ct.ws@gmail.com</a> | +91-8015864344</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', event => {
            const datatablesSimple = document.getElementById('refundsTable');
            if (datatablesSimple) {
                new simpleDatatables.DataTable(datatablesSimple);
            }
        });
    </script>
</body>
</html>
<?php
include_once("./footer.php");
ob_end_flush();
?>