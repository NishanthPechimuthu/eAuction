<?php
session_start();
ob_start();
include("header.php");
require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';
require '../tcpdf/tcpdf.php'; // Include TCPDF for PDF generation
isAuthenticated();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure user is logged in
$user_id = $_SESSION['userId'];
$user = getUserById($user_id); // Assumes this function exists
$user_type = $user['userType'] ?? 'unknown';
$subscription_fee = 1000;

// Check subscription status
$json_dir = '../public/InfoData';
$json_file = $json_dir . '/subscription_transactions.json';
$currentMonthYear = date('Y-m');

if (!file_exists($json_dir)) {
    mkdir($json_dir, 0775, true);
}
if (!file_exists($json_file)) {
    file_put_contents($json_file, json_encode(['subscriptions' => []], JSON_PRETTY_PRINT));
}

$data = json_decode(file_get_contents($json_file), true);
$subscriptions = $data['subscriptions'] ?? [];
$subscriptionPaid = false;

foreach ($subscriptions as $subscription) {
    if ($subscription['user_id'] === $user_id && 
        strpos($subscription['timestamp'], $currentMonthYear) === 0 && 
        $subscription['status'] === 'completed') {
        $subscriptionPaid = true;
        break;
    }
}

// If subscription is paid, show notice and exit
if ($subscriptionPaid) {
    ob_end_clean();
    header('Content-Type: text/html; charset=UTF-8');
    echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Subscription Paid</title></head><body>";
    echo "<div style='text-align: center; margin-top: 50px;'><h2>Your subscription for this month is already paid.</h2>";
    echo "<p><a href='index.php'>Go to Dashboard</a></p></div></body></html>";
    exit();
}

// Handle AJAX payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $username = $_POST['username'] ?? '';
    $cardNumber = $_POST['cardNumber'] ?? '';
    $expiryMonth = $_POST['expiryMonth'] ?? '';
    $expiryYear = $_POST['expiryYear'] ?? '';
    $cvv = $_POST['cvv'] ?? '';

    // Validate inputs
    if (empty($username) || empty($cardNumber) || empty($expiryMonth) || empty($expiryYear) || empty($cvv)) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'All fields are required.']);
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

    // Process payment
    $transaction_tracking_id = uniqid('sub_', true);
    $data = json_decode(file_get_contents($json_file), true);
    $new_entry = [
        'transaction_id' => $transaction_tracking_id,
        'user_id' => $user_id,
        'user_type' => $user_type,
        'card_number' => $cardNumber,
        'amount' => $subscription_fee,
        'timestamp' => date('Y-m-d H:i:s'),
        'status' => 'completed'
    ];
    $data['subscriptions'][] = $new_entry;
    $success = file_put_contents($json_file, json_encode($data, JSON_PRETTY_PRINT));

    if ($success !== false) {
        // Generate PDF Invoice
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->AddPage();

        // PDF HTML content
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
                <td>Invoice to<br><strong>' . htmlspecialchars($user["userFirstName"] . " " . $user["userLastName"]) . '</strong></td>
                <td align="right">
                    <strong>Total Due: ₹' . $subscription_fee . '</strong><br>
                    Invoice Date: ' . date("d-m-Y") . '
                </td>
            </tr>
            <tr>
                <td><strong>Transaction:</strong><br>
                    Name: ' . htmlspecialchars($user["userName"]) . '<br>
                    Card No: ' . htmlspecialchars($cardNumber) . '<br>
                    Transaction ID: ' . htmlspecialchars(explode('_', explode('.', $transaction_tracking_id)[0])[1]) . '
                </td>
            </tr>
        </table>
        <table>
            <thead>
                <tr style="font-weight: bold;">
                    <th>Description</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="border-bottom: 1px solid #222">Monthly Subscription Fee (' . htmlspecialchars($user_type) . ')</td>
                    <td style="border-bottom: 1px solid #222">₹' . $subscription_fee . '</td>
                </tr>
            </tbody>
        </table>
        <p class="total">Grand Total: ₹' . $subscription_fee . '</p>
        <p style="text-align: center;"><h2>Thank you for your subscription!</h2></p>
        <p><strong>Note:</strong> 90% (₹900) of this amount will be refunded after you complete one transaction (either selling or buying a product).</p>
        <hr><span>This is a digital invoice and does not require a physical signature.</span><hr>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf_file = 'invoice_subscription_' . explode('.', $transaction_tracking_id)[1] . '.pdf';
        $pdf_content = $pdf->Output($pdf_file, 'S'); // Get PDF as string

        // Send email with PDF attachment
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
            $mail->addAddress($user['userEmail']);
            $mail->isHTML(true);
            $mail->Subject = 'Subscription Payment Confirmation';
            $mail->Body = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Arial, sans-serif; background-color: #f4f4f4; color: #333; }
                    .container { max-width: 600px; margin: 20px auto; background: #fff; padding: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
                    h3 { color: #2c3e50; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { padding: 10px; border-bottom: 1px solid #ddd; }
                    .total { text-align: right; font-weight: bold; }
                </style>
            </head>
            <body>
                <div class="container">
                    <h3>Subscription Payment Confirmation</h3>
                    <p>Dear <b>' . htmlspecialchars($user["userFirstName"] . " " . $user["userLastName"]) . '</b>,</p>
                    <p>Your monthly subscription fee has been processed successfully. Please find the invoice attached.</p>
                    <h3>Transaction Details</h3>
                    <table>
                        <tr>
                            <td>Name: ' . htmlspecialchars($user["userName"]) . '<br>
                                Card No: ' . htmlspecialchars($cardNumber) . '<br>
                                Transaction ID: ' . htmlspecialchars(explode('_', explode('.', $transaction_tracking_id)[0])[1]) . '
                            </td>
                        </tr>
                    </table>
                    <table>
                        <tr><th>Description</th><th>Amount</th></tr>
                        <tr>
                            <td>Monthly Subscription Fee (' . htmlspecialchars($user_type) . ')</td>
                            <td>₹' . $subscription_fee . '</td>
                        </tr>
                    </table>
                    <p class="total">Total: ₹' . $subscription_fee . '</p>
                    <p><strong>Note:</strong> 90% (₹900) of this amount will be refunded after you complete one transaction (either selling or buying a product).</p>
                    <p style="text-align: center;">Thank you for your subscription!</p>
                </div>
            </body>
            </html>';
            $mail->addStringAttachment($pdf_content, $pdf_file, 'base64', 'application/pdf');
            $mail->send();
        } catch (Exception $e) {
            error_log("Email failed: " . $e->getMessage());
        }

        ob_clean();
        echo json_encode([
            'success' => true,
            'transaction_id' => htmlspecialchars(explode('_', explode('.', $transaction_tracking_id)[0])[1]),
            'amount' => $subscription_fee
        ]);
        exit();
    } else {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Failed to process payment.']);
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
    <title>Monthly Subscription Fee Payment</title>
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
        .form-control { width: 100%; padding: 10px; border: 1px solid #dcdcdc; border-radius: 5px; }
        .form-control:focus { border-color: #28a745; outline: none; }
        .input-group { display: flex; }
        .input-group-text { background: #f8f9fa; border: 1px solid #dcdcdc; padding: 10px; }
        .error-message { color: #e74c3c; font-size: 0.85rem; margin-top: 5px; display: none; }
        .input-error { border-color: #e74c3c; }
        .btn-success { background: #28a745; border: none; padding: 12px; color: #fff; width: 100%; }
        .btn-success:disabled { background: #6c757d; cursor: not-allowed; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Monthly Subscription Fee Payment (<?= htmlspecialchars($_SESSION["userName"]); ?>)</h1>
        <div class="card">
            <div class="card-header">Credit Card Payment</div>
            <div class="card-body">
                <p>Amount: ₹<?php echo $subscription_fee; ?></p>
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
                                <i class="fab fa-cc-visa"></i> <i class="fab fa-cc-mastercard mx-1"></i> <i class="fab fa-cc-amex"></i>
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
                                <label for="cvv">CVV</label>
                                <input type="text" name="cvv" id="cvv" class="form-control" placeholder="CVV" required>
                                <div class="error-message" id="cvvError"></div>
                            </div>
                        </div>
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
        const form = $('#paymentForm');
        const inputs = {
            username: $('#username'),
            cardNumber: $('#cardNumber'),
            expiryMonth: $('#expiryMonth'),
            expiryYear: $('#expiryYear'),
            cvv: $('#cvv')
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
            inputs[field].addClass('input-error');
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
            inputs[field].on('input', function() {
                validateField(field);
                toggleSubmitButton();
            });
        });

        toggleSubmitButton();

        function toggleSubmitButton() {
            const allFieldsFilled = Object.values(inputs).every(input => input.val().trim() !== '');
            const noErrors = Object.values(errors).every(error => error.is(':hidden'));
            submitBtn.prop('disabled', !(allFieldsFilled && noErrors));
        }

        form.on('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Processing Payment...',
                text: 'Please wait.',
                didOpen: () => Swal.showLoading()
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
                            confirmButtonText: 'OK'
                        }).then(() => {
                            window.location.href = 'index.php';
                        });
                    } else {
                        Swal.fire({
                            title: 'Payment Failed',
                            text: response.error || 'An unknown error occurred.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.close();
                    Swal.fire({
                        title: 'Error',
                        text: 'An error occurred: ' + (xhr.responseText || error),
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
        });
    });
    </script>
</body>
</html>
<?php
ob_end_flush();
?>