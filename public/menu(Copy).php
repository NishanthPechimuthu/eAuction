<?php
ob_start(); // Start output buffering
// Include necessary files (replace with your actual file paths)
include "header.php";

// Handle form submissions for interests, reviews, and chatbot queries
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  // Handle interest submission
  if (isset($_POST["categoryId"])) {
    $userId = $_SESSION["userId"];
    $categories = $_POST["categoryId"]; // Array of selected categories
    $type = $_POST["type"];
    $keywords = $_POST["keywords"];

    try {
      global $pdo;

      // Insert interests for each selected category
      foreach ($categories as $categoryId) {
        $stmt = $pdo->prepare("INSERT INTO interests (interestUserId, interestCategoryId, interestProductType, interestKeywords) 
                                       VALUES (:userId, :categoryId, :type, :keywords)");
        $stmt->execute([
          ":userId" => $userId,
          ":categoryId" => $categoryId,
          ":type" => $type,
          ":keywords" => $keywords,
        ]);
      }

      $successMessage = "Interest(s) added successfully.";
    } catch (PDOException $e) {
      $errorMessage = "Error: " . $e->getMessage();
    }
  }

  // Handle review submission
  if (isset($_POST["reviewMessage"])) {
    $userId = $_SESSION["userId"];
    $reviewMessage = $_POST["reviewMessage"] ?? "";

    if (empty($reviewMessage)) {
      echo json_encode([
        "success" => false,
        "message" => "Review message is required.",
      ]);
      exit();
    }

    $response = addReview($userId, $reviewMessage);
    echo json_encode($response);
    exit();
  }

  // Handle chatbot query
  if (isset($_POST["query"])) {
    $query = $_POST["query"] ?? "";

    if (empty($query)) {
      echo json_encode([
        "success" => false,
        "message" => "Please enter a search query.",
      ]);
      exit();
    }

    try {
      $response = getAuctionResults($query);
      echo json_encode(
        $response,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
      );
    } catch (Exception $e) {
      echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage(),
      ]);
    }
    exit();
  }
}

// Function to get auction results based on the query
function getAuctionResults($query)
{
  global $pdo;

  // Check if $pdo is initialized
  if (!isset($pdo)) {
    throw new Exception("Database connection is not established.");
  }

  // Sanitize input
  $query = trim($query);

  // First, search for auctions with a direct match or partial match
  $stmt = $pdo->prepare("
        SELECT * 
        FROM auctions 
        WHERE (auctionTitle LIKE :query OR auctionDescription LIKE :query)
          AND auctionEndDate >= NOW() 
          AND auctionStartDate <= NOW()
        ORDER BY auctionTitle ASC 
        LIMIT 5
    ");
  $stmt->execute(["query" => "%$query%"]);
  $auctions = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (count($auctions) > 0) {
    return ["success" => true, "auctions" => $auctions];
  }

  // Fallback: Search for auctions using SOUNDEX (approximate match)
  $stmt = $pdo->prepare("
        SELECT * 
        FROM auctions 
        WHERE SOUNDEX(auctionTitle) = SOUNDEX(:query)
          AND auctionEndDate >= NOW() 
        LIMIT 5
    ");
  $stmt->execute(["query" => $query]);
  $similarAuctions = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (count($similarAuctions) > 0) {
    return [
      "success" => true,
      "message" => "No exact matches found, but here are some similar results.",
      "auctions" => $similarAuctions,
    ];
  }

  return ["success" => false, "message" => "No auctions found for your query."];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unified Popup Interface</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        /* Floating button styles */
        .floating-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: background 0.3s;
        }

        .floating-btn:hover {
            background-color: #0056b3;
        }

        /* Chatbot styles */
        .chatbot-container {
            position: fixed;
            bottom: 60px;
            right: 20px;
            width: 350px;
            height: 600px;
            border: 1px solid #ccc;
            background-color: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            display: none;
            flex-direction: column;
            z-index: 9999;
        }

        .chatbot-header {
            background-color: #007bff;
            color: white;
            padding: 10px;
            font-size: 16px;
            text-align: center;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .chatbot-messages {
            padding: 10px;
            flex-grow: 1;
            overflow-y: auto;
            font-size: 14px;
            max-height: 400px;
        }

        .chatbot-input {
            display: flex;
            padding: 10px;
            border-top: 1px solid #ccc;
        }

        .chatbot-input input {
            flex-grow: 1;
            border-radius: 4px;
        }

        .chat-message {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .chat-message img {
            height: 32px;
            width: 32px;
            border-radius: 50%;
            margin-right: 10px;
            border: 1px solid #000000;
        }

        .chat-message.bot img {
            margin-left: 5px;
        }

        .chat-message.bot {
            justify-content: flex-start;
            text-align: left;
        }

        .chat-message.user {
            justify-content: flex-end;
            text-align: right;
        }

        .chat-message.bot div {
            background-color: #d4f8d4;
            color: black;
            border-radius: 15px;
            padding: 10px;
            max-width: 70%;
            display: inline-block;
        }

        .chat-message.user div {
            background-color: #d0e9ff;
            color: black;
            border-radius: 15px;
            padding: 10px;
            max-width: 70%;
            display: inline-block;
        }

        /* Adjusted card styles for the chatbot */
        .card-custom {
            max-width: 100%;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 10px;
        }

        .card-custom .card-body {
            padding: 10px;
            font-size: 12px;
        }

        .card-custom .card-title {
            font-size: 14px;
            color: #007bff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .card-custom img {
            width: 100%;
            height: auto;
            border-radius: 5px;
        }

        .card-custom .badge {
            font-size: 12px;
        }
    </style>
</head>
<body>
    <!-- Floating Button -->
    <div class="floating-btn" id="three-dot-menu">
        <i class="fas fa-ellipsis-v"></i>
    </div>

    <!-- Chatbot Container -->
    <div id="chatbot-container" class="chatbot-container">
        <div class="chatbot-header d-flex justify-content-between align-items-center bg-white border-bottom">
    <span class="text-dark">Chatbot Assitance</span>
    <button id="close-chatbot" class="btn btn-sm btn-close"></button>
</div>
        <div id="chatbot-messages" class="chatbot-messages">
            <div class="chat-message bot">
                <img src="../images/profiles/bot.webp" alt="blk">
                <div><strong>blk:</strong> How can I help you today?</div>
            </div>
        </div>
        <div class="chatbot-input">
            <input type="text" id="user-query" class="form-control" placeholder="Ask about an auction..." />
            <button id="send-query" class="btn btn-primary ms-2">Send</button>
        </div>
    </div>

    <!-- Interest Form Modal -->
    <div class="modal fade" id="interestModal" tabindex="-1" aria-labelledby="interestModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="interestModalLabel">Interest</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (isset($successMessage)): ?>
                        <div class="alert alert-success"><?= $successMessage ?></div>
                    <?php elseif (isset($errorMessage)): ?>
                        <div class="alert alert-danger"><?= $errorMessage ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" class="form-control" name="userId" value="<?= $_SESSION[
                          "userId"
                        ] ?>" required>

<div class="mb-3">
    <label for="categoryId" class="form-label">Select Categories</label>
    <select class="form-select select2" id="categoryId" name="categoryId[]" multiple="multiple" required>
        <?php
        $categories = getCategories(); // Fetch categories from database
        if (!empty($categories)) {
          foreach ($categories as $category): ?>
            <option value="<?= htmlspecialchars($category["categoryId"]) ?>">
                <?= htmlspecialchars($category["categoryName"]) ?>
            </option>
        <?php endforeach;
        } else {
          echo "<option disabled>No categories available</option>";
        }
        ?>
    </select>
</div>

                        <div class="mb-3">
                            <label for="type" class="form-label">Product Type</label>
                            <select class="form-select" id="type" name="type" required>
                                <option value="organic">Organic</option>
                                <option value="hybrid">Hybrid</option>
                                <option value="both">Both</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="keywords" class="form-label">Keywords</label>
                            <input type="text" class="form-control" id="keywords" name="keywords" placeholder="Enter keywords (optional)">
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Submit</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Review Modal -->
    <div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reviewModalLabel">Submit Your Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="reviewForm">
                        <div class="mb-3">
                            <textarea class="form-control" name="reviewMessage" rows="5" placeholder="Write your review..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Submit Review</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
<!-- The existing PHP and HTML code remains the same until the JavaScript section -->
<script>
    $(document).ready(function () {
        // Initialize Select2
        $('#categoryId').select2({
            dropdownParent: $('#interestModal'),
            placeholder: "Select categories",
            allowClear: true
        });

        // Floating menu toggle
        let isMenuVisible = false;
        $('#three-dot-menu').on('click', function (e) {
            e.stopPropagation();
            isMenuVisible = !isMenuVisible;
            $('#floating-menu').toggle(isMenuVisible);
        });

        // Close floating menu when clicking outside
        $(document).on('click', function () {
            $('#floating-menu').hide();
            isMenuVisible = false;
        });

        // Handle review form submission
        $('#reviewForm').on('submit', function (e) {
    e.preventDefault();
    var formData = $(this).serialize();
    $.ajax({
        url: 'menu.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Review Added',
                    text: 'Your review was added successfully.',
                    confirmButtonText: 'OK'
                }).then(() => {
                    location.reload();  // Reload the page after success
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Review could not be added: ' + response.message,
                    confirmButtonText: 'OK'
                });
            }
        },
        error: function (xhr, status, error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'An unexpected error occurred. Please try again later.',
                confirmButtonText: 'OK'
            });
        }
    });
});

        // Handle chatbot query submission
        $('#send-query').on('click', function () {
            const query = $('#user-query').val().trim();
            if (!query) return;

            // Append user message
            $('#chatbot-messages').append(`
                <div class="chat-message user">
                    <img src="../images/profiles/<?php echo $_SESSION[
                      "userProfileImg"
                    ] ?? "profile.webp"; ?>" alt="User">
                    <div><strong>You:</strong> ${query}</div>
                </div>
            `);
            $('#user-query').val('');

            // Send AJAX request
            $.ajax({
                url: 'menu.php',
                type: 'POST',
                data: { query: query },
                dataType: 'json',
                success: function (response) {
                    let botMessage = '';
                    if (response.success) {
                        if (response.auctions.length > 0) {
                            response.auctions.forEach(auction => {
                                $('#chatbot-messages').append(`
                                    <div class="card card-custom">
                                        <div class="card-body">
                                            <h5 class="card-title">${auction.auctionTitle}</h5>
                                            <img src="../images/products/${auction.auctionProductImg}" alt="Product Image">
                                            <p>Price: ₹${auction.auctionStartPrice}</p>
                                            <a href="bid.php?id=${auction.auctionId}" class="btn btn-primary">View Auction</a>
                                        </div>
                                    </div>
                                `);
                            });
                        } else {
                            botMessage = response.message || "No results found.";
                        }
                    } else {
                        botMessage = response.message || "An error occurred.";
                    }

                    if (botMessage) {
                        $('#chatbot-messages').append(`
                            <div class="chat-message bot">
                                <img src="../images/profiles/bot.webp" alt="blk">
                                <div><strong>blk:</strong> ${botMessage}</div>
                            </div>
                        `);
                    }
                    $('#chatbot-messages').scrollTop($('#chatbot-messages')[0].scrollHeight);
                }
            });
        });
        // Reinitialize Select2 when the modal is opened
        $('#interestModal').on('shown.bs.modal', function () {
            initializeSelect2();
        });
        // Enter key handler for chatbot
        $('#user-query').on('keypress', function (e) {
            if (e.which === 13) {
                $('#send-query').click();
            }
        });
    });
    $(document).ready(function () {
    // Close chatbot when clicking the close button
    $('#close-chatbot').on('click', function () {
        $('#chatbot-container').hide();
    });
});
$(document).ready(function () {
    function initializeSelect2() {
        $('#categoryId').select2({
            dropdownParent: $('#interestModal'),
            placeholder: "Select Categories",
            allowClear: true,
            width: '100%' // Ensures the dropdown is properly styled
        });
    }

    // Initialize Select2 on document ready
    initializeSelect2();

    // Reinitialize Select2 when the modal is opened
    $('#interestModal').on('shown.bs.modal', function () {
        initializeSelect2();
    });
});
</script>

<!-- Add floating menu HTML -->
<div id="floating-menu" class="shadow" style="display: none; position: fixed; bottom: 90px; right: 20px; background: white; border-radius: 8px; padding: 10px; width: 200px; z-index: 1001;">
    <button class="btn btn-light w-100 mb-2 shadow" onclick="$('#chatbot-container').toggle();">
        <i class="fas fa-robot me-2"></i>Chatbot
    </button>
    <button class="btn btn-light w-100 mb-2 shadow" data-bs-toggle="modal" data-bs-target="#interestModal">
        <i class="fas fa-bell me-2"></i>Notify Interest
    </button>
    <button class="btn btn-light w-100 mb-2 shadow" data-bs-toggle="modal" data-bs-target="#reviewModal">
        <i class="fas fa-comment me-2"></i>Submit Review
    </button>
</div>
</body>
</html>