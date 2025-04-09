<?php
session_start();
ob_start();
include("header.php");
require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

isAuthenticated();

// Ensure user is logged in
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
$auction = getAuctionById($auction_id); // Assumes this function exists
$highest_bidder_id = getHighestBidderId($auction_id); // Buyer
$farmer_id = $auction["auctionCreatedBy"]; // Farmer/Vendor
$highest_bid = getHighestBid($auction_id);
$buyer = getUserById($highest_bidder_id);
$farmer = getUserById($farmer_id);

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
    if ($t['auction_id'] === $auction_id && $t['status'] === 'pending') {
        $transaction = $t;
        break;
    }
}

if (!$transaction) {
    echo "No pending transaction found for this auction.";
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $transaction_id = $transaction['transaction_id'];

    if ($user_id === $farmer_id) { // Farmer/Vendor confirmation
        if ($action === 'confirm') {
            // Update existing transaction status to 'confirmed'
            foreach ($data['transactions'] as &$t) {
                if ($t['transaction_id'] === $transaction_id) {
                    $t['status'] = 'confirmed';
                    break;
                }
            }

            // Add new transaction: Middleman to Farmer
            $new_transaction = [
                'transaction_id' => uniqid('txn_', true),
                'buyer_id' => $highest_bidder_id,
                'vendor_id' => $farmer_id,
                'auction_id' => $auction_id,
                'amount' => $highest_bid * 0.95, // 95% after 5% platform fee
                'from' => 'middleman_admin',
                'to' => $farmer_id,
                'timestamp' => date('Y-m-d H:i:s'),
                'status' => 'completed'
            ];
            $data['transactions'][] = $new_transaction;

            file_put_contents($json_file, json_encode($data, JSON_PRETTY_PRINT));

            // Send email to farmer
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
                $mail->Subject = 'Payment Transferred for Auction ID: ' . $auction_id;
                $mail->Body = '
                <p>Dear ' . htmlspecialchars($farmer["userFirstName"] . " " . $farmer["userLastName"]) . ',</p>
                <p>The payment of ₹' . ($highest_bid * 0.95) . ' for Auction ID: ' . $auction_id . ' has been transferred to your account from the middleman after deducting 5% platform fees (3% maintenance, 2% security).</p>
                <p>Transaction ID: ' . htmlspecialchars(explode('_', explode('.', $new_transaction['transaction_id'])[0])[1]) . '</p>
                <p>Thank you for your business!</p>';
                $mail->send();
            } catch (Exception $e) {
                error_log("Farmer email failed: " . $e->getMessage());
            }

            echo "<p>Payment confirmed and transferred to farmer. Redirecting...</p>";
            header("Refresh: 2; URL=index.php");
            exit();
        } elseif ($action === 'not_satisfied') {
            if (!isset($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
                echo "<p>Please upload a proof image.</p>";
            } else {
                $upload_dir = '../public/proofs/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0775, true);
                }
                $proof_file = $upload_dir . $transaction_id . '_' . basename($_FILES['proof']['name']);
                move_uploaded_file($_FILES['proof']['tmp_name'], $proof_file);

                // Update transaction status to 'disputed'
                foreach ($data['transactions'] as &$t) {
                    if ($t['transaction_id'] === $transaction_id) {
                        $t['status'] = 'disputed';
                        $t['proof'] = $proof_file;
                        break;
                    }
                }

                // Add refund transaction: Middleman to Buyer
                $new_transaction = [
                    'transaction_id' => uniqid('txn_', true),
                    'buyer_id' => $highest_bidder_id,
                    'vendor_id' => $farmer_id,
                    'auction_id' => $auction_id,
                    'amount' => $highest_bid * 0.95, // Refund 95% after 5% fee
                    'from' => 'middleman_admin',
                    'to' => $highest_bidder_id,
                    'timestamp' => date('Y-m-d H:i:s'),
                    'status' => 'refunded'
                ];
                $data['transactions'][] = $new_transaction;

                file_put_contents($json_file, json_encode($data, JSON_PRETTY_PRINT));

                // Send email to buyer
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
                    $mail->addAddress($buyer['userEmail']);
                    $mail->isHTML(true);
                    $mail->Subject = 'Refund Processed for Auction ID: ' . $auction_id;
                    $mail->Body = '
                    <p>Dear ' . htmlspecialchars($buyer["userFirstName"] . " " . $buyer["userLastName"]) . ',</p>
                    <p>The farmer reported dissatisfaction with the product for Auction ID: ' . $auction_id . '. Your payment of ₹' . ($highest_bid * 0.95) . ' has been refunded after deducting 5% platform fees.</p>
                    <p>Transaction ID: ' . htmlspecialchars(explode('_', explode('.', $new_transaction['transaction_id'])[0])[1]) . '</p>
                    <p>Thank you for using eAgri Auction!</p>';
                    $mail->send();
                } catch (Exception $e) {
                    error_log("Buyer email failed: " . $e->getMessage());
                }

                echo "<p>Dispute recorded and refund processed. Redirecting...</p>";
                header("Refresh: 2; URL=index.php");
                exit();
            }
        }
    } else {
        echo "<p>You are not authorized to confirm this transaction.</p>";
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
    <style>
        body { font-family: Arial, sans-serif; background-color: #f5f5f5; }
        .container { max-width: 600px; margin: 40px auto; padding: 20px; background: #fff; border-radius: 10px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); }
        h1 { font-size: 1.8rem; color: #2c3e50; text-align: center; }
        .card { border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); }
        .card-header { background: #28a745; color: #fff; padding: 10px; text-align: center; }
        .card-body { padding: 20px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; color: #34495e; margin-bottom: 5px; }
        .btn { padding: 10px; border: none; border-radius: 5px; color: #fff; width: 100%; cursor: pointer; }
        .btn-confirm { background: #28a745; }
        .btn-dispute { background: #e74c3c; }
        .btn:hover { opacity: 0.9; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Confirm Transaction - Auction #<?php echo $auction_id; ?></h1>
        <div class="card">
            <div class="card-header">Transaction Details</div>
            <div class="card-body">
                <p><strong>Auction Title:</strong> <?php echo htmlspecialchars($auction["auctionTitle"]); ?></p>
                <p><strong>Highest Bid:</strong> ₹<?php echo $highest_bid; ?></p>
                <p><strong>Buyer:</strong> <?php echo htmlspecialchars($buyer["userName"]); ?></p>
                <p><strong>Farmer:</strong> <?php echo htmlspecialchars($farmer["userName"]); ?></p>

                <?php if ($user_id === $farmer_id): ?>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <button type="submit" name="action" value="confirm" class="btn btn-confirm">Confirm Product Satisfaction</button>
                        </div>
                        <div class="form-group">
                            <label for="proof">Not Satisfied? Upload Proof (Image):</label>
                            <input type="file" name="proof" id="proof" accept="image/*">
                            <button type="submit" name="action" value="not_satisfied" class="btn btn-dispute">Report Dissatisfaction</button>
                        </div>
                    </form>
                <?php else: ?>
                    <p>Please wait for the farmer to confirm the transaction.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
<?php
ob_end_flush();
?>