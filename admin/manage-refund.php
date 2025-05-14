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

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $refund_id = $_POST['refund_id'] ?? null;
    $action = $_POST['action'] ?? null;

    // Track if refund was found and updated
    $refund_updated = false;

    // Reload refund data for consistency
    $refund_data = file_exists($refund_file) ? json_decode(file_get_contents($refund_file), true) : ['refunds' => []];
    $refunds = $refund_data['refunds'] ?? [];

    // Load transaction data
    $transaction_data = file_exists($transactions_file) ? json_decode(file_get_contents($transactions_file), true) : ['transactions' => []];

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
                $refund['status'] = 'approve';

                // Update transaction status in transactions.json
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

                // Create new transaction: Middleman to Vendor (Refund Amount)
                $new_transaction = [
                    'transaction_id' => uniqid('txn_', true),
                    'vendor_id' => $vendor_id,
                    'farmer_id' => $farmer_id,
                    'auction_id' => $auction_id,
                    'amount' => $amount * 0.95, // Refund amount after deducting fees
                    'from' => 'middleman_admin',
                    'to' => $vendor_id,
                    'timestamp' => date('Y-m-d H:i:s'),
                    'status' => 'refunded'
                ];
                $transaction_data['transactions'][] = $new_transaction;

                // Update report.json (Reason for refund)
                if (!file_exists($report_file)) {
                    file_put_contents($report_file, json_encode(['reports' => []], JSON_PRETTY_PRINT));
                }
                $report_data = json_decode(file_get_contents($report_file), true);
                $report_entry = [
                    'report_id' => uniqid('rep_', true),
                    'user_id' => $farmer_id,
                    'user_name' => $farmer['userName'],
                    'auction_id' => $auction_id,
                    'reason' => 'Refund approved due to unsatisfactory product quality reported by vendor',
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

                // Email to Vendor (Refund Approved)
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

                // Email to Farmer (Refund Approved)
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
                $refund['status'] = 'unapprove';

                // Update transaction status in transactions.json
                $transaction_updated = false;
                foreach ($transaction_data['transactions'] as &$t) {
                    if ($t['transaction_id'] === $refund_id) {
                        $t['status'] = 'completed'; // If refund is declined, set as completed
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

                // Create new transaction: Middleman to Farmer (Original Payment - Fees)
                $new_transaction = [
                    'transaction_id' => uniqid('txn_', true),
                    'vendor_id' => $vendor_id,
                    'farmer_id' => $farmer_id,
                    'auction_id' => $auction_id,
                    'amount' => $amount * 0.95, // Original payment to farmer after fees
                    'from' => 'middleman_admin',
                    'to' => $farmer_id,
                    'timestamp' => date('Y-m-d H:i:s'),
                    'status' => 'completed'
                ];
                $transaction_data['transactions'][] = $new_transaction;

                // Generate Invoice for Farmer (Payment after declined refund)
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

                // Email to Farmer (Payment after declined refund)
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

                // Email to Vendor (Refund Declined)
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
                                <p>Your refund request for the following auction has been declined. The payment has been processed to the farmer.</p>
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
        // Save updated refund and transaction data back to JSON files
        $refund_data['refunds'] = $refunds;
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
        $status = $action === 'approve' ? 'activate' : 'suspend';
        $stmt->execute([
            ':status' => $status,
            ':auction_id' => $auction_id
        ]);

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

// Separate refunds into pending and non-pending
$pending_refunds = array_filter($refunds, fn($refund) => $refund['status'] === 'pending');
$non_pending_refunds = array_filter($refunds, fn($refund) => $refund['status'] !== 'pending');

// Sort pending refunds in FIFO (ascending by timestamp)
usort($pending_refunds, fn($a, $b) => strtotime($a['timestamp']) <=> strtotime($b['timestamp']));

// Sort non-pending refunds in LIFO (descending by timestamp)
usort($non_pending_refunds, fn($a, $b) => strtotime($b['timestamp']) <=> strtotime($a['timestamp']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Refunds</title>
    <?php include_once("../assets/link.html"); ?>
    <link href="../assets/css/table-styles.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .table-responsive { overflow-x: auto; }
        td, th {
            min-width: 100px;
            text-align: center;
            vertical-align: middle;
            padding: 12px;
            white-space: nowrap;
        }
        td img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            margin: 2px;
            cursor: pointer;
        }
        .btn-sm { min-width: 90px; padding: 6px 12px; }
        .badge { font-size: 0.9rem; }
        .card { border-radius: 8px; }
        .card-header { font-size: 1.25rem; }
        @media (max-width: 992px) {
            th, td { font-size: 0.9rem; padding: 10px; }
            td img { width: 50px; height: 50px; }
            .btn-sm { font-size: 0.85rem; padding: 5px 10px; }
            .badge { font-size: 0.85rem; }
        }
        @media (max-width: 768px) {
            th, td { font-size: 0.85rem; padding: 8px; }
            td img { width: 40px; height: 40px; }
            .btn-sm { font-size: 0.8rem; padding: 4px 8px; min-width: 80px; }
            .badge { font-size: 0.8rem; }
            .card-header { font-size: 1.1rem; }
        }
        @media (max-width: 576px) {
            th, td { font-size: 0.75rem; padding: 6px; min-width: 80px; }
            td img { width: 30px; height: 30px; }
            .btn-sm { font-size: 0.7rem; padding: 3px 6px; min-width: 70px; }
            .badge { font-size: 0.7rem; }
            .card-header { font-size: 1rem; }
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Pending Refunds Table -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-money-bill-wave me-2"></i> Pending Refund Requests (FIFO)
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="pendingRefundsTable" class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>S/No</th>
                                <th>Auction ID</th>
                                <th>Vendor</th>
                                <th>Farmer</th>
                                <th>Amount</th>
                                <th>Proof Images</th>
                                <th>Approve</th>
                                <th>Decline</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($pending_refunds as $refund): ?>
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
                                            <img src="<?= htmlspecialchars($img) ?>" alt="Proof Image" onclick="openImageModal('<?= htmlspecialchars($img) ?>')">
                                        <?php endforeach; ?>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="refund_id" value="<?= htmlspecialchars($refund['transaction_id']) ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-success btn-sm fw-bold text-white">Approve</button>
                                        </form>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="refund_id" value="<?= htmlspecialchars($refund['transaction_id']) ?>">
                                            <input type="hidden" name="action" value="decline">
                                            <button type="submit" class="btn btn-danger btn-sm fw-bold text-white">Decline</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Non-Pending Refunds Table -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-secondary text-white">
                <i class="fas fa-history me-2"></i> Processed Refund Requests (LIFO)
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="nonPendingRefundsTable" class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>S/No</th>
                                <th>Auction ID</th>
                                <th>Vendor</th>
                                <th>Farmer</th>
                                <th>Amount</th>
                                <th>Proof Images</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($non_pending_refunds as $refund): ?>
                                <?php
                                    $badge_class = $refund['status'] === 'approve' ? 'bg-success text-white' : 'bg-danger text-white';
                                    $display_status = $refund['status'] === 'approve' ? 'Approved' : 'Declined';
                                ?>
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
                                            <img src="<?= htmlspecialchars($img) ?>" alt="Proof Image" onclick="openImageModal('<?= htmlspecialchars($img) ?>')">
                                        <?php endforeach; ?>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill <?= $badge_class ?>">
                                            <?= htmlspecialchars($display_status) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="modal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Proof Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" alt="Proof Image" class="img-fluid" style="max-height: 400px;">
                </div>
            </div>
        </div>
    </div>

    <script>
        function openImageModal(imageSrc) {
            const modalImage = document.getElementById('modalImage');
            modalImage.src = imageSrc;
            const imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
            imageModal.show();
        }
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            new simpleDatatables.DataTable(document.getElementById('pendingRefundsTable'), {
                perPage: 10,
                perPageSelect: [5, 10, 20],
                searchable: true,
                sortable: true
            });
            new simpleDatatables.DataTable(document.getElementById('nonPendingRefundsTable'), {
                perPage: 10,
                perPageSelect: [5, 10, 20],
                searchable: true,
                sortable: true
            });
        });
    </script>
</body>
</html>
<?php
include_once("./footer.php");
ob_end_flush();
?>