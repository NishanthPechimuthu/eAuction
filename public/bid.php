<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Start the session only if not already started
}
ob_start(); // Start output buffering

include("header.php");
include("navbar.php");

// Call the authentication function
isAuthenticated();

$auction_id = $_GET['id'] ?? null;
if (!$auction_id) {
  echo '
        <p class="alert alert-danger alert-dismissible fade show d-flex align-items-center"
           role="alert"  data-bs-dismiss="alert"
                  aria-label="Close"
           style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
            Invalid auction ID.
        </p>
    ';
  exit();
}

// Fetch auction details
$user_id = $_SESSION["userId"];
$auction = getAuctionById($auction_id);
$starting_price = $auction['auctionStartPrice'];
$highest_bid = (getHighestBid($auction_id) ?? 0) > 0 ? getHighestBid($auction_id) : $starting_price;
$top_bidders = getTopBidders($auction_id, 10);
$minBid = $highest_bid + 1;
$userBids = getNumberBid($user_id, $auction_id);
$form_disable = FALSE;
if ($auction["auctionCreatedBy"] == $_SESSION["userId"]) {
  $form_disable = TRUE;
} else {
  $form_disable = FALSE;
}

// Set timezone to India/Chennai
date_default_timezone_set('Asia/Kolkata');
$current_time = date("Y-m-d G:i:s");
$has_ended = (strtotime($current_time) > strtotime($auction['auctionEndDate']));

// Initialize error message variable
$error_message = "";

// Handle bid submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$has_ended) {
  $bid_amount = $_POST['bid_amount'];

  // Server-side validation
  if ($bid_amount < $minBid) {
    $error_message = "Bid must be higher than the current highest bid of ₹ ".$minBid.".";
  } else {
    $user_id = getUserFromSession();
    if (placeBid($auction_id, $user_id, $bid_amount)) {
      // Redirect after placing the bid
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
                  <script>
                      $(document).ready(function() {
                          Swal.fire({
                              title: 'Your Bid has been successfully.',
                              icon: 'success',
                              confirmButtonText: 'OK',
                              confirmButtonColor: '#28a745',
                              allowOutsideClick: false
                          }).then((result) => {
                              if (result.isConfirmed) {
                                  window.location.href = 'bid.php?id=".$auction_id."'; // Redirect to another page
                              }
                          });
                      });
                  </script>";
    } else {
      $error_message = "Failed to place bid.";
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['delete'])) {
    deleteAuction($_POST['auction_id']);
    header("Location: manage-auction.php");
    exit();
  }
}

$user_id = $_SESSION['userId'];
$is_highest_bidder = false;
$highest_bidder = getHighestBidder($auction_id);
if ($highest_bidder['bidUserId'] == $user_id) {
  $is_highest_bidder = true;
}

// Ensure end date is in a format that JavaScript can understand
$auction_end_date = date("Y-m-d H:i:s", strtotime($auction['auctionEndDate']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>Place Bid</title>
  <?php include_once("../assets/link.html"); ?>
  <style>
    /* Ensures the table container allows horizontal scrolling */
    .table-container {
      overflow-x: auto;
    }
    table {
      border-collapse: collapse;
      width: 100%;
      min-width: 330px;
    }
    th, td {
      text-align: center;
      vertical-align: middle;
    }
    /* Specific styles for title and description scrolling */
    .title-cell {
      max-height: 100px;
      overflow-y: auto;
      max-width: 240px;
      white-space: nowrap;
    }
    .description-cell {
      max-width: 400;
      max-height: 120px;
      /* Set max height */
      overflow: auto;
      /* Enable scrolling */
      white-space: normal;
      /* Wrap text properly */
      text-align: justify;
      /* Justify the text */
    }

    @media (min-width: 768px) {
      .title-cell {
        max-width: auto;
      }
      .description-cell {
        max-width: auto;
      }
    }
  </style>
  <script>
    window.onload = function() {
      const endTime = "<?= htmlspecialchars($auction_end_date) ?>"; // Pass the PHP date to JavaScript
      startCountdown(endTime);
    };

    function startCountdown(endTime) {
      const endDate = new Date(endTime).getTime(); // Convert to timestamp

      const countdownInterval = setInterval(function() {
        const now = new Date().getTime();
        const distance = endDate - now;

        if (distance <= 0) {
          clearInterval(countdownInterval);
          document.getElementById("countdown").innerHTML = "Auction has ended";
        } else {
          const days = Math.floor(distance / (1000 * 60 * 60 * 24));
          const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
          const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
          const seconds = Math.floor((distance % (1000 * 60)) / 1000);

          document.getElementById("countdown").innerHTML =
          days + "d " + hours + "h " + minutes + "m " + seconds + "s ";
        }
      },
        1000); // Update every second
    }
  </script>
</head>
<body>
  <div class="container py-5">
    <div class="card mb-4">
      <div class="card-header">
        <i class="fa fa-balance-scale"></i>&nbsp;
        Bid
      </div>
      <div class="card-body">
        <div class="col-12 col-sm-12">
          <div class="row">
            <table class="table table-bordered mx-auto">
              <tr>
                <th colspan="4" class="text-center" style="position: relative;">
                  <!-- Auction Image -->
                  <img src="../images/products/<?= htmlspecialchars($auction['auctionProductImg']) ?>"
                  class="img-fluid rounded-top-2" alt="Auction Image">

                  <!-- Author Info Positioned at the Bottom-Left -->
                  <div class="author"
                    style="bottom: 10px; left: 10px;  background: rgba(0, 0, 0, 0.5); color: white; padding: 5px 10px; border-radius: 0 0 5px 5px;">
                    <a style="text-decoration: none;display: flex; align-items: center;" href="view-profile.php?id=<?=base64_encode($auction['auctionCreatedBy']) ?>">
                      <img src="../images/profiles/<?= $img = getUserImg($auction['auctionCreatedBy']); ?>"
                      alt="" class="rounded-circle border border-2 border-dark" width="30" height="30" style="margin-right: 8px;">
                      <p style="margin: 0; color:white;">
                        <?= htmlspecialchars(getUserName($auction['auctionCreatedBy'])) ?>
                      </p>
                    </a>
                  </div>
                </th>
              </tr>
              <tr>
                <th colspan="1" width="100px"> Title:</th>
                <td colspan="3">
                  <div class="title-cell">
                    <?= htmlspecialchars($auction['auctionTitle']) ?>
                  </div>
                </td>
              </tr>
              <tr>
                <th colspan="4" style="text-align: center;">Price (&#8377;)</th>
              </tr>
              <tr>
                <th colspan="2" width="200px" style="text-align: center;"><i class="fa fa-coins"></i>&nbsp; Base</th>
                <th colspan="2" width="200px" style="text-align: center;"><i class="fa fa-line-chart"></i>&nbsp; High</th>
              </tr>
              <tr>
                <td colspan="2" style="text-align: center;">&#8377;<?= htmlspecialchars($starting_price) ?></td>
                <td colspan="2" style="text-align: center;">&#8377;<?= htmlspecialchars(getHighestBid($auction['auctionId'])) ?></td>
              </tr>
              <tr>
                <th colspan="2" width="200px" style="text-align: center;"><i class="fa fa-balance-scale"></i>&nbsp; Quantity</th>
                <th colspan="2" width="200px" style="text-align: center;"><i class="fa fa-vial"></i>&nbsp; Product Type</th>
              </tr>
              <tr>
                <td colspan="2" style="text-align: center;"><?= htmlspecialchars(number_format($auction["auctionProductQuantity"], 2)." ".$auction["auctionProductUnit"]) ?></td>
                <td colspan="2" style="text-align: center;"><?= htmlspecialchars($auction["auctionProductType"]) ?></td>
              </tr>
              <tr>
                <th colspan="1" width="100px"><i class="fa fa-hourglass"></i>&nbsp;Time</th>
                <td colspan="3"><span id="countdown"></span></td>
              </tr>
              <tr>
                <th colspan="2"><i class="fa fa-map-marker-alt"></i>&nbsp; Address</th>
                <th colspan="2"><i class="fa fa-palette"></i>&nbsp; Category</th>
              </tr>
              <tr>
                <td colspan="2">
                  <div style="max-width: 180px; white-space: nowrap; overflow-y: auto; text-align: center;">
                    <?= htmlspecialchars($auction["auctionAddress"]) ?>
                  </div>
                </td>
                <td colspan="2">
                  <div style="max-width: 160px; white-space: nowrap; overflow-y: auto; text-align: center;">
                    <?= $categ = getCategoryById($auction["auctionCategoryId"]); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <th colspan="4"> Description</th>
              </tr>
              <tr>
                <td colspan="4">
                  <div class="description-cell">
                    &nbsp;&nbsp;&nbsp;&nbsp;
                    <?= htmlspecialchars($auction['auctionDescription']) ?>
                  </div>
                </td>
              </tr>
            </table>
          </div>
        </div>
      </div>
    </div>
    <!-- Display Error Message -->
    <?php if ($error_message): ?>
    <p id='dangerMessage' class='alert alert-danger alert-dismissible fade show' role='alert'>
      <?= htmlspecialchars($error_message) ?>
      <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
    </p>
    <?php endif; ?>

    <?php if (!$form_disable): ?>
    <?php if (!$has_ended): ?>
    <?php if ($userBids < 10): ?>
    <form action="bid.php?id=<?= htmlspecialchars($auction_id) ?>" method="POST">
      <div class="mb-3">
        <label class="form-label">Your Bid Amount (must be higher than &#8377;&nbsp;<?= $minBid ?>)</label>
        <input type="number" name="bid_amount" required class="form-control" placeholder="Enter your bid" value="<?= isset($bid_amount) ? htmlspecialchars($bid_amount) : '' ?>">
      </div>
      <button type="submit" class="btn btn-primary font-weight-bold">Place Bid</button>
      <p class="btn btn-warning font-weight-800 fw-bold mb-0">
        <?= abs(10 - $userBids) . " bids left"; ?>
      </p>
    </form>
    <?php else : ?>
    <p class="alert alert-warning">
      Your bids limit reached for this auction.
    </p>
    <?php endif; ?>
    <?php else : ?>
    <p class="text-danger">
      The auction has ended.
    </p>
    <?php endif; ?>
    <?php else : ?>
    <br />
  <div class="d-inline-flex align-items-center">
    <a href="edit-auction.php?auctionId=<?= htmlspecialchars($auction['auctionId']) ?>" class="btn btn-warning fw-bold text-dark me-2">
      <i class="bi bi-wrench"></i> Edit
    </a>
    <form method="POST">
      <input type="hidden" name="auction_id" value="<?= htmlspecialchars($auction['auctionId']) ?>">
      <button type="submit" name="delete" class="btn btn-danger fw-bold text-white" onclick="return confirm('Are you sure?<?= htmlspecialchars($auction['auctionId']) ?>')">
        <i class="bi bi-trash3-fill"></i> Delete
      </button>
    </form>
  </div>
  <?php endif; ?>
  <h3 class="mt-4">Top 10 Bidders</h3>
  <table class="table table-bordered mt-3">
    <thead>
      <tr>
        <th>Bidder Place</th>
        <th>Bidder Name</th>
        <th>Bid Amount</th>
      </tr>
    </thead>
    <tbody>
      <?php $position = 1; ?>
      <?php foreach ($top_bidders as $bid): ?>
      <tr>
        <td>
          <?php if ($position == 1): ?>
          &#x1F947;
          <?php elseif ($position == 2): ?>
          &#x1F948;
          <?php elseif ($position == 3): ?>
          &#x1F949;
          <?php endif; ?>
          <?= $position++ ?>
        </td>
        <td><?= htmlspecialchars($bid['userId']) ?></td>
        <td>&#8377;&nbsp;<?= htmlspecialchars($bid['highestBid']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

<?php if ($is_highest_bidder && $has_ended): ?>
    <h3 id="paymentSession" class="mt-4">Payment Instructions</h3>
    <p>
        Please pay <strong>&#8377;&nbsp;<?= htmlspecialchars($highest_bid) ?></strong>
    </p>
    <p>
        Once you are ready to make the payment, click the button below:
    </p>

    <?php if (hasUserPaid($user_id, $auction_id, $highest_bid)): ?>
        <!-- Show the option to download the invoice if the user has already paid -->
        <a class="btn btn-success" href="genrate-invoice.php?auction_id=<?= htmlspecialchars($auction_id) ?>">Download Invoice</a>
    <?php else: ?>
        <!-- Otherwise, show the option to go to the payment page -->
        <a class="btn btn-primary" href="payment.php?auction_id=<?= htmlspecialchars($auction_id) ?>">Make Payment</a>
    <?php endif; ?>
<?php endif; ?>
</div>
</body>
</html>
<?
  include_once("./menu.php");
  include_once("./footer.php");
  ob_end_flush();
?>