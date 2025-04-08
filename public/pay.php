<?php
session_start();
ob_start(); // Start output buffering
include("header.php");
require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';
isAuthenticated();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure user is logged in
if (!isset($_SESSION['userId'])) {
    echo "You need to be logged in to make a payment.";
    exit();
}

$user_id = $_SESSION['userId'];
$user = getUserById($user_id); // Assuming this function exists to fetch user details
$user_type = $user['userType'] ?? 'unknown'; // 'farmer' or 'vendor', fallback to 'unknown'

// Fixed subscription fee for both farmer and vendor
$subscription_fee = 1000; // ₹1000

// Check if subscription for this month is already paid
$json_dir = '../public/InfoData';
$json_file = $json_dir . '/subscription_transactions.json';
$currentMonthYear = date('Y-m'); // e.g., "2025-04"

// Create directory and file if they don’t exist
if (!file_exists($json_dir)) {
    mkdir($json_dir, 0775, true);
}
if (!file_exists($json_file)) {
    file_put_contents($json_file, json_encode(['subscriptions' => []], JSON_PRETTY_PRINT));
}

// Read JSON data
$data = json_decode(file_get_contents($json_file), true);
$subscriptions = $data['subscriptions'] ?? [];

foreach ($subscriptions as $subscription) {
    if ($subscription['user_id'] === $user_id && 
        strpos($subscription['timestamp'], $currentMonthYear) === 0 && 
        $subscription['status'] === 'completed') {
        // Subscription for this month already paid, redirect to index.php
        header('Location: index.php');
        exit();
    }
}

// Handle payment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? null;
    $cardNumber = $_POST['cardNumber'] ?? null;
    $expiryMonth = $_POST['expiryMonth'] ?? null;
    $expiryYear = $_POST['expiryYear'] ?? null;
    $cvv = $_POST['cvv'] ?? null;

    // Server-side validation
    if (!$username || !$cardNumber || !$expiryMonth || !$expiryYear || !$cvv) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'All fields are required.']);
        exit();
    }

    if (strlen($username) < 2) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Card owner name must be at least 2 characters.']);
        exit();
    }

    if (!preg_match('/^\d{13,19}$/', $cardNumber)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Card number must be 13-19 digits.']);
        exit();
    }

    $month = (int)$expiryMonth;
    $year = (int)$expiryYear;
    $currentDate = new DateTime();
    $currentMonth = (int)$currentDate->format('m');
    $currentYear = (int)$currentDate->format('y');

    if ($month < 1 || $month > 12 || $year < $currentYear || ($year == $currentYear && $month < $currentMonth)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid or expired date.']);
        exit();
    }

    if (!preg_match('/^\d{3}$/', $cvv)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'CVV must be 3 digits.']);
        exit();
    }

    $transaction_tracking_id = uniqid('sub_', true);

    // Log to InfoData/subscription_transactions.json
    $data = file_exists($json_file) ? json_decode(file_get_contents($json_file), true) : ['subscriptions' => []];
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
        // Send email to user
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
            $mail->Subject = 'Subscription Fee Payment Confirmation';
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
                    <h3>Subscription Fee Payment Confirmation</h3>
                    <p>Dear <b>' . htmlspecialchars($user["userFirstName"]) . ' ' . htmlspecialchars($user["userLastName"]) . '</b>,</p>
                    <p>Your monthly subscription fee has been processed successfully. Details below:</p>
                    <h3>Transaction Details</h3>
                    <table>
                        <tr>
                            <td><b>User:</b><br>Name: ' . htmlspecialchars($user["userName"]) . '<br>Card No: ' . htmlspecialchars($cardNumber) . '<br>Transaction ID: ' . htmlspecialchars(explode('_', explode('.', $transaction_tracking_id)[0])[1]) . '</td>
                        </tr>
                    </table>
                    <table>
                        <thead>
                            <tr><th>Description</th><th>Amount</th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Monthly Subscription Fee (' . htmlspecialchars($user_type) . ')</td>
                                <td>₹' . htmlspecialchars($subscription_fee) . '</td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="total">Total: ₹' . htmlspecialchars($subscription_fee) . '</p>
                    <p class="footer"><h2>Thank you!</h2>We appreciate your subscription.</p>
                </div>
                <p>eAgri Auction</p>
            </body>
            </html>';
            $mail->send();
        } catch (Exception $e) {
            error_log("Email failed: " . $e->getMessage());
        }

        header('Content-Type: application/json');
        ob_clean();
        echo json_encode([
            'success' => true,
            'transaction_id' => htmlspecialchars(explode('_', explode('.', $transaction_tracking_id)[0])[1]),
            'amount' => $subscription_fee
        ]);
        exit();
    } else {
        header('Content-Type: application/json');
        ob_clean();
        echo json_encode(['error' => 'Failed to write to JSON file.']);
        exit();
    }
}

// Include header and navbar for GET requests
include("header.php");
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
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            padding: 20px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        h1 {
            font-size: 1.8rem;
            color: #2c3e50;
            text-align: center;
            margin-bottom: 20px;
        }
        .card {
            border: none;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background: #28a745;
            color: #fff;
            padding: 10px 15px;
            font-weight: bold;
            text-align: center;
        }
        .card-body {
            padding: 20px;
        }
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        label {
            display: block;
            font-size: 0.9rem;
            color: #34495e;
            margin-bottom: 5px;
        }
        .form-control {
            width: 100%;
            padding: 10px;
            font-size: 1rem;
            border: 1px solid #dcdcdc;
            border-radius: 5px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .form-control:focus {
            border-color: #28a745;
            box-shadow: 0 0 5px rgba(40, 167, 69, 0.3);
            outline: none;
        }
        .input-group {
            display: flex;
            align-items: center;
        }
        .input-group .form-control {
            flex: 1;
        }
        .input-group-text {
            background: #f8f9fa;
            border: 1px solid #dcdcdc;
            border-left: none;
            padding: 10px;
            border-radius: 0 5px 5px 0;
        }
        .error-message {
            color: #e74c3c;
            font-size: 0.85rem;
            margin-top: 5px;
            display: none;
            transition: opacity 0.3s ease;
        }
        .input-error {
            border-color: #e74c3c;
        }
        .shake {
            animation: shake 0.4s ease-in-out;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        .btn-success {
            background: #28a745;
            border: none;
            padding: 12px;
            font-size: 1rem;
            font-weight: bold;
            color: #fff;
            border-radius: 5px;
            width: 100%;
            transition: background 0.3s ease, transform 0.2s ease;
        }
        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        .btn-success:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }
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
                    <button type="submit" id="cnfbtn" class="btn btn-success" disabled>Confirm Payment</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        $('[data-toggle="tooltip"]').tooltip();
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
            errors[field].text(message).fadeIn(200);
            inputs[field].addClass('input-error shake');
            setTimeout(() => inputs[field].removeClass('shake'), 400);
        }

        function clearError(field) {
            errors[field].fadeOut(200);
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

        // Validate all fields on input and toggle button
        Object.keys(inputs).forEach(field => {
            inputs[field].on('input', function() {
                validateField(field);
                toggleSubmitButton();
            });
        });

        // Initial validation on page load
        Object.keys(inputs).forEach(field => {
            validateField(field);
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
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = 'index.php'; // Redirect to index.php
                            }
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
                        text: 'An error occurred while processing your payment: ' + (xhr.responseText || error),
                        icon: 'error',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#e74c3c'
                    });
                }
            });
        });
    });
    </script>
</body>
</html>
<?php
include_once("./footer.php");
ob_end_flush();
?>