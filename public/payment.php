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
    echo "You need to be logged in to make a payment.";
    exit();
}

$user_id = $_SESSION['userId'];
$auction_id = $_GET['auction_id'] ?? null;

if (!$auction_id) {
    echo "Invalid auction ID.";
    exit();
}

// Get auction details
$sUserId = getHighestBidderId($auction_id);
$auction = getAuctionById($auction_id);
$sUser = getUserById($sUserId); // Buyer (payer)
$rUser = getUserById($auction["auctionCreatedBy"]); // Seller (farmer)
$highest_bid = getHighestBid($auction_id);
$accountNo = getUserAccountNo($auction["auctionCreatedBy"]);

// Check if user is the highest bidder
$is_highest_bidder = false;
$highest_bidder = getHighestBidder($auction_id);
if ($highest_bidder['bidUserId'] == $user_id) {
    $is_highest_bidder = true;
}

if (!$is_highest_bidder) {
    echo "You are not the highest bidder for this auction.";
    exit();
}

// Handle payment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $username = $_POST['username'] ?? '';
    $cardNumber = $_POST['cardNumber'] ?? '';
    $expiryMonth = $_POST['expiryMonth'] ?? '';
    $expiryYear = $_POST['expiryYear'] ?? '';
    $cvv = $_POST['cvv'] ?? '';
    $termsAccepted = isset($_POST['terms']) && $_POST['terms'] === 'on';

    // Validate inputs
    if (empty($username) || empty($cardNumber) || empty($expiryMonth) || empty($expiryYear) || empty($cvv)) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'All fields are required.']);
        exit();
    }

    if (!$termsAccepted) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'You must agree to the Terms and Conditions.']);
        exit();
    }

    if (strlen($username) < 2) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Card owner name must be at least 2 characters.']);
        exit();
    }

    if (!preg_match('/^\d{13,19}$/', $cardNumber)) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Card number must be 13-19 digits.']);
        exit();
    }

    $month = (int)$expiryMonth;
    $year = (int)$expiryYear;
    $currentDate = new DateTime();
    $currentMonth = (int)$currentDate->format('m');
    $currentYear = (int)$currentDate->format('y');

    if ($month < 1 || $month > 12 || $year < $currentYear || ($year === $currentYear && $month < $currentMonth)) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Invalid or expired date.']);
        exit();
    }

    if (!preg_match('/^\d{3}$/', $cvv)) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'CVV must be 3 digits.']);
        exit();
    }

    $transaction_tracking_id = uniqid('txn_', true);

    // Store in JSON file
    $json_dir = '../public/InfoData';
    $json_file = $json_dir . '/transactions.json';
    if (!file_exists($json_dir)) {
        mkdir($json_dir, 0775, true);
    }
    if (!file_exists($json_file)) {
        file_put_contents($json_file, json_encode(['transactions' => []], JSON_PRETTY_PRINT));
    }

    $data = json_decode(file_get_contents($json_file), true);
    $new_entry = [
        'transaction_id' => $transaction_tracking_id,
        'buyer_id' => $user_id,
        'vendor_id' => $auction["auctionCreatedBy"],
        'auction_id' => $auction_id,
        'card_number' => $cardNumber,
        'amount' => $highest_bid,
        'to' => 'middleman_admin',
        'timestamp' => date('Y-m-d H:i:s'),
        'status' => 'success'
    ];
    $data['transactions'][] = $new_entry;
    $json_success = file_put_contents($json_file, json_encode($data, JSON_PRETTY_PRINT));

    // Insert into trans table
    try {
        $query = "INSERT INTO trans 
                  (transTrackingId, transCardNo, transAccountNo, transUserId, transAmount, transAuctionId, transStatus) 
                  VALUES 
                  (:transTrackingId, :transCardNo, :transAccountNo, :transUserId, :transAmount, :transAuctionId, :transStatus)";
        $stmt = $pdo->prepare($query);
        $db_success = $stmt->execute([
            ':transTrackingId' => $transaction_tracking_id,
            ':transCardNo' => $cardNumber,
            ':transAccountNo' => $accountNo,
            ':transUserId' => $user_id,
            ':transAmount' => $highest_bid,
            ':transAuctionId' => $auction_id,
            ':transStatus' => 'deactivate'
        ]);

        if ($json_success && $db_success && $stmt->rowCount() > 0) {
            // Generate PDF Invoice for Buyer
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
                    <td><h1>INVOICE: #' . htmlspecialchars(explode('.', $transaction_tracking_id)[1]) . '</h1></td>
                    <td align="right">
                        <img src="./logos/logo.png" height="50px"/><br>
                        1/283, Somvarapatti, Udumalpet, Tiruppur, Tamil Nadu - 642205<br>
                        <strong>+91-8015864344</strong> | <strong>22ct19nishanth@gmail.com</strong>
                    </td>
                </tr>
            </table>
            <table>
                <tr>
                    <td>Invoice to<br><strong>' . htmlspecialchars($sUser["userFirstName"] . " " . $sUser["userLastName"]) . '</strong></td>
                    <td align="right">
                        <strong>Total Paid: ₹' . $highest_bid . '</strong><br>
                        Invoice Date: ' . date("d-m-Y") . '
                    </td>
                </tr>
                <tr>
                    <td><strong>Transaction:</strong><br>
                        From: ' . htmlspecialchars($sUser["userName"]) . ' (Buyer)<br>
                        Card No: ' . htmlspecialchars($cardNumber) . '<br>
                        To: Middleman Admin<br>
                        Transaction ID: ' . htmlspecialchars(explode('_', explode('.', $transaction_tracking_id)[0])[1]) . '
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
                        <td style="border-bottom: 1px solid #222">' . htmlspecialchars($auction["auctionTitle"]) . '</td>
                        <td style="border-bottom: 1px solid #222">' . htmlspecialchars(getCategoryById($auction["auctionCategoryId"])) . '</td>
                        <td style="border-bottom: 1px solid #222">₹' . $highest_bid . '</td>
                        <td style="border-bottom: 1px solid #222">1</td>
                        <td style="border-bottom: 1px solid #222">₹' . $highest_bid . '</td>
                    </tr>
                </tbody>
            </table>
            <p class="total">Grand Total: ₹' . $highest_bid . '</p>
            <p><strong>Platform Fees:</strong> 5% (₹' . ($highest_bid * 0.05) . ') - 3% (₹' . ($highest_bid * 0.03) . ') for maintenance, 2% (₹' . ($highest_bid * 0.02) . ') for security management.</p>
            <p><strong>Next Steps:</strong> Amount held by middleman. Will be transferred to farmer upon confirmation or refunded if issues arise.</p>
            <p><a href="http://localhost/eAuction/public/conform.php?id=' . $auction_id . '">Confirm Product Delivery/Quality</a></p>
            <p><strong>Disclaimer:</strong> Please confirm receipt and quality within 7 days using the link above. Failure to update status or report issues (e.g., non-delivery, poor quality) may result in forfeiture of refund rights.</p>
            <p style="text-align: center;"><h2>Thank you for your business!</h2></p>
            <hr><span>This is a digital invoice and does not require a physical signature.</span><hr>';

            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf_file = 'invoice_auction_' . $auction_id . '.pdf';
            $pdf_content = $pdf->Output($pdf_file, 'S');

            // Send email to buyer (payer)
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
                $mail->addAddress($sUser["userEmail"]);
                $mail->isHTML(true);
                $mail->Subject = 'Payment Confirmation for Auction ID: ' . $auction_id;
                $mail->Body = '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; background-color: #f4f4f4; color: #333; }
                        .container { max-width: 600px; margin: 20px auto; background-color: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
                        h3 { color: #2c3e50; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
                        th { background-color: #f8f8f8; }
                        .total { text-align: right; font-weight: bold; margin-top: 20px; }
                        .footer { text-align: center; margin-top: 30px; font-size: 14px; color: #777; }
                    </style>
                </head>
                <body>
                    <div class="container py-5">
                        <h3>Payment Confirmation for Auction ID: ' . $auction_id . '</h3>
                        <p>Dear <b>' . htmlspecialchars($sUser["userFirstName"] . " " . $sUser["userLastName"]) . '</b>,</p>
                        <p>Your payment for the auction has been successfully processed and is held by the middleman admin.</p>
                        <h3>Transaction Details</h3>
                        <table>
                            <tr>
                                <td><b>From:</b><br>Name: ' . htmlspecialchars($sUser["userName"]) . '<br>Card No: ' . htmlspecialchars($cardNumber) . '<br>Transaction ID: ' . htmlspecialchars(explode('_', explode('.', $transaction_tracking_id)[0])[1]) . '</td>
                                <td><b>To:</b><br>Middleman Admin<br>Invoice ID: ' . htmlspecialchars(explode('.', $transaction_tracking_id)[1]) . '</td>
                            </tr>
                        </table>
                        <table>
                            <thead>
                                <tr><th>Item Name</th><th>Category</th><th>Price</th><th>Quantity</th><th>Total</th></tr>
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
                        <p class="total">Grand Total: ₹' . $highest_bid . '</p>
                        <p><strong>Platform Fees:</strong> 5% (₹' . ($highest_bid * 0.05) . ') - 3% (₹' . ($highest_bid * 0.03) . ') for maintenance, 2% (₹' . ($highest_bid * 0.02) . ') for security management.</p>
                        <p><strong>Next Steps:</strong> The amount is held by the middleman and will be transferred to the farmer upon confirmation of delivery/quality, or refunded if issues arise.</p>
                        <p><a href="http://localhost/eAuction/public/conform.php?id=' . $auction_id . '"><button style="background: #28a745; color: #fff; padding: 10px; border: none; border-radius: 5px;">Confirm Product Delivery/Quality</button></a></p>
                        <p><strong>Disclaimer:</strong> Please confirm receipt and quality within 7 days using the link above. Failure to update status or report issues (e.g., non-delivery, poor quality) may result in forfeiture of refund rights.</p>
                        <p class="footer"><h2>Thank you!</h2>We appreciate your business.</p>
                    </div>
                    <p>eAgri Auction</p>
                </body>
                </html>';
                $mail->addStringAttachment($pdf_content, $pdf_file, 'base64', 'application/pdf');
                $mail->send();
            } catch (Exception $e) {
                error_log("Buyer email failed: " . $e->getMessage());
            }

            ob_clean();
            echo json_encode([
                'success' => true,
                'transaction_id' => htmlspecialchars(explode('_', explode('.', $transaction_tracking_id)[0])[1]),
                'amount' => $highest_bid,
                'auction_id' => $auction_id
            ]);
            exit();
        } else {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Database or JSON insertion failed.']);
            exit();
        }
    } catch (PDOException $e) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
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
    <title>Payment - Auction #<?php echo $auction_id; ?></title>
    <?php include("../assets/link.html"); ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Arial', sans-serif; background-color: #f5f5f5; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; padding: 20px; background: #fff; border-radius: 10px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); }
        h1 { font-size: 1.8rem; color: #2c3e50; text-align: center; margin-bottom: 20px; }
        .card { border: none; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); }
        .card-header { background: #28a745; color: #fff; padding: 10px 15px; font-weight: bold; text-align: center; }
        .card-body { padding: 20px; }
        .form-group { margin-bottom: 20px; position: relative; }
        label { display: block; font-size: 0.9rem; color: #34495e; margin-bottom: 5px; }
        .form-control { width: 100%; padding: 10px; font-size: 1rem; border: 1px solid #dcdcdc; border-radius: 5px; transition: border-color 0.3s ease, box-shadow 0.3s ease; }
        .form-control:focus { border-color: #28a745; box-shadow: 0 0 5px rgba(40, 167, 69, 0.3); outline: none; }
        .input-group { display: flex; align-items: center; }
        .input-group .form-control { flex: 1; }
        .input-group-text { background: #f8f9fa; border: 1px solid #dcdcdc; border-left: none; padding: 10px; border-radius: 0 5px 5px 0; }
        .error-message { color: #e74c3c; font-size: 0.85rem; margin-top: 5px; display: none; transition: opacity 0.3s ease; }
        .input-error { border-color: #e74c3c; }
        .shake { animation: shake 0.4s ease-in-out; }
        @keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-5px); } 75% { transform: translateX(5px); } }
        .btn-success { background: #28a745; border: none; padding: 12px; font-size: 1rem; font-weight: bold; color: #fff; border-radius: 5px; width: 100%; transition: background 0.3s ease, transform 0.2s ease; }
        .btn-success:hover { background: #218838; transform: translateY(-2px); }
        .btn-success:disabled { background: #6c757d; cursor: not-allowed; transform: none; }
        .terms-group { display: flex; align-items: center; margin-bottom: 20px; }
        .terms-group input { margin-right: 10px; }
        .terms-link { color: #28a745; cursor: pointer; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Payment for Auction #<?php echo $auction_id; ?></h1>
        <div class="card">
            <div class="card-header">Credit Card Payment</div>
            <div class="card-body">
                <p>Amount: ₹<?php echo $highest_bid; ?></p>
                <form id="paymentForm" method="POST">
                    <div class="form-group">
                        <label for="username">Card Owner</label>
                        <input type="text" name="username" id="username" class="form-control" placeholder="Card Owner Name" required>
                        <div class="error-message" id="usernameError"></div>
                    </div>
                    <div class="form-group">
                        <label for="cardNumber">Card Number</label>
                        <div class="input-group">
                            <input type="text" name="cardNumber" id="cardNumber" class="form-control" placeholder="Valid Card Number" required>
                            <span class="input-group-text">
                                <i class="fab fa-cc-visa"></i>
                                <i class="fab fa-cc-mastercard mx-1"></i>
                                <i class="fab fa-cc-amex"></i>
                            </span>
                        </div>
                        <div class="error-message" id="cardNumberError"></div>
                    </div>
                    <div class="row">
                        <div class="col-sm-8">
                            <div class="form-group">
                                <label>Expiration Date (MM/YY)</label>
                                <div class="input-group">
                                    <input type="number" name="expiryMonth" id="expiryMonth" class="form-control" placeholder="MM" min="1" max="12" required>
                                    <input type="number" name="expiryYear" id="expiryYear" class="form-control" placeholder="YY" min="24" max="99" required>
                                </div>
                                <div class="error-message" id="expiryDateError"></div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label for="cvv">CVV <i class="fa fa-question-circle" data-toggle="tooltip" title="3-digit code on the back of your card"></i></label>
                                <input type="text" name="cvv" id="cvv" class="form-control" placeholder="CVV" required>
                                <div class="error-message" id="cvvError"></div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group terms-group">
                        <input type="checkbox" name="terms" id="terms" required>
                        <label for="terms">I agree to the <span class="terms-link" onclick="showTerms()">Terms and Conditions</span></label>
                    </div>
                    <button type="submit" id="cnfbtn" class="btn btn-success" disabled>Confirm Payment</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(document).ready(function() {
        $('[data-toggle="tooltip"]').tooltip();
        const form = $('#paymentForm');
        const inputs = {
            username: $('#username'),
            cardNumber: $('#cardNumber'),
            expiryMonth: $('#expiryMonth'),
            expiryYear: $('#expiryYear'),
            cvv: $('#cvv'),
            terms: $('#terms')
        };
        const errors = {
            username: $('#usernameError'),
            cardNumber: $('#cardNumberError'),
            expiryDate: $('#expiryDateError'),
            cvv: $('#cvvError')
        };
        const submitBtn = $('#cnfbtn');

        function showError(field, message) {
            errors[field].text(message).show();
            inputs[field].addClass('input-error shake');
            setTimeout(() => inputs[field].removeClass('shake'), 400);
        }

        function clearError(field) {
            errors[field].hide();
            inputs[field].removeClass('input-error');
        }

        function validateField(field) {
            const value = inputs[field].val().trim();
            let isValid = true;

            if (field === 'username') {
                if (value.length < 2) {
                    showError('username', 'Name must be at least 2 characters.');
                    isValid = false;
                } else {
                    clearError('username');
                }
            } else if (field === 'cardNumber') {
                if (!/^\d{13,19}$/.test(value)) {
                    showError('cardNumber', 'Card number must be 13-19 digits.');
                    isValid = false;
                } else {
                    clearError('cardNumber');
                }
            } else if (field === 'expiryMonth' || field === 'expiryYear') {
                const month = parseInt(inputs.expiryMonth.val()) || 0;
                const year = parseInt(inputs.expiryYear.val()) || 0;
                const currentDate = new Date();
                const currentMonth = currentDate.getMonth() + 1;
                const currentYear = currentDate.getFullYear() % 100;

                if (month < 1 || month > 12 || year < currentYear || (year === currentYear && month < currentMonth)) {
                    showError('expiryDate', 'Invalid or expired date.');
                    isValid = false;
                } else {
                    clearError('expiryDate');
                }
            } else if (field === 'cvv') {
                if (!/^\d{3}$/.test(value)) {
                    showError('cvv', 'CVV must be 3 digits.');
                    isValid = false;
                } else {
                    clearError('cvv');
                }
            }
            return isValid;
        }

        Object.keys(inputs).forEach(field => {
            if (field !== 'terms') {
                inputs[field].on('input', function() {
                    validateField(field);
                    toggleSubmitButton();
                });
            }
        });

        inputs.terms.on('change', toggleSubmitButton);

        function toggleSubmitButton() {
            const allFieldsFilled = Object.values(inputs).every(input => {
                if (input.attr('type') === 'checkbox') {
                    return input.is(':checked');
                }
                return input.val().trim() !== '';
            });
            const noErrors = Object.values(errors).every(error => error.is(':hidden'));
            submitBtn.prop('disabled', !(allFieldsFilled && noErrors));
        }

        toggleSubmitButton();

        form.on('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Processing Payment...',
                text: 'Please wait while we process your payment.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: form.serialize(),
                dataType: 'json',
                success: function(response) {
                    Swal.close();
                    if (response.success) {
                        Swal.fire({
                            title: 'Payment Successful!',
                            text: `Transaction ID: ${response.transaction_id}\nAmount Paid: ₹${response.amount}`,
                            icon: 'success',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#28a745',
                            allowOutsideClick: false
                        }).then(() => {
                            window.location.href = 'bid.php?id=' + response.auction_id;
                        });
                    } else {
                        Swal.fire({
                            title: 'Payment Failed',
                            text: response.error || 'An unknown error occurred.',
                            icon: 'error',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#e74c3c'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.close();
                    Swal.fire({
                        title: 'Error',
                        text: 'An error occurred while processing your payment: ' + (xhr.responseJSON?.error || error),
                        icon: 'error',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#e74c3c'
                    });
                }
            });
        });
    });

    function showTerms() {
        Swal.fire({
            title: 'Terms and Conditions',
            html: `
                <div style="text-align: left; line-height: 1.6;">
                    <p><strong>eAgri Auction Payment Terms</strong></p>
                    <ul>
                        <li>The payment of ₹${<?php echo $highest_bid; ?>} for Auction ID ${<?php echo $auction_id; ?>} will be held by the middleman admin until you confirm receipt and quality of the product.</li>
                        <li>Upon confirmation, 95% of the amount (₹${<?php echo $highest_bid * 0.95; ?>}) will be transferred to the farmer, with 5% (₹${<?php echo $highest_bid * 0.05; ?>}) retained as platform fees (3% maintenance, 2% security).</li>
                        <li>If you report an issue (e.g., non-delivery, poor quality) within 7 days, you may request a refund by uploading proof (1-3 images). Refunds are subject to admin review.</li>
                        <li>Failure to confirm or report issues within 7 days may result in the amount being transferred to the farmer, forfeiting your refund rights.</li>
                        <li>Any fraudulent activity or violation of platform policies may result in account suspension.</li>
                    </ul>
                    <p>By agreeing to these terms, you acknowledge and accept the conditions outlined above.</p>
                </div>
            `,
            icon: 'info',
            confirmButtonText: 'Close',
            width: '600px'
        });
    }
    </script>
</body>
</html>
<?php
include_once("./review-popup.php");
include_once("./footer.php");
ob_end_flush();
?>