<?php
ob_start(); // Start output buffering
// Include necessary files (replace with your actual file paths)
include "header.php";

// Check and create issues.json if it doesn't exist
$issuesFile = 'issues.json';
if (!file_exists($issuesFile)) {
    file_put_contents($issuesFile, json_encode([]));
}

// Function to save issue reports
function saveIssue($userId, $issueType, $proofPath, $message) {
    global $issuesFile;
    $issues = json_decode(file_get_contents($issuesFile), true);
    
    $issue = [
        'issueId' => uniqid(),
        'issueUserId' => $userId,
        'issueType' => $issueType,
        'issueUserProof' => $proofPath,
        'issueUserMessage' => $message,
        'createdAt' => date('Y-m-d H:i:s')
    ];
    
    $issues[] = $issue;
    file_put_contents($issuesFile, json_encode($issues, JSON_PRETTY_PRINT));
    return $issue['issueId'];
}

// Handle form submissions for interests, reviews, chatbot queries, and issues
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

    // Handle chatbot query (for search only)
    if (isset($_POST["query"]) && !isset($_POST["issueType"])) {
        $query = $_POST["query"] ?? "";
        if (empty($query)) {
            echo json_encode([
                "success" => false,
                "message" => "Please enter a search query.",
            ]);
            exit();
        }
        try {
            // Use search functionality for auction queries.
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

    // Handle issue report with file upload (when report is selected)
    if (isset($_POST['issueType']) && isset($_FILES['proof'])) {
        $userId = $_SESSION['userId'];
        $issueType = $_POST['issueType'];
        $message = $_POST['message'] ?? '';
        
        $uploadDir = '../images/report/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExtension = pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION);
        $fileName = uniqid() . '.' . $fileExtension;
        $filePath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['proof']['tmp_name'], $filePath)) {
            $issueId = saveIssue($userId, $issueType, $filePath, $message);
            echo json_encode([
                'success' => true,
                'message' => 'Issue reported successfully. Issue ID: ' . $issueId
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to upload proof file.'
            ]);
        }
        exit();
    }
}

// Function to get auction results based on the query
function getAuctionResults($query)
{
    global $pdo;
    if (!isset($pdo)) {
        throw new Exception("Database connection is not established.");
    }
    $query = trim($query);
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
<!-- Responsive Menu HTML and CSS -->
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
            padding: 10px;
            border-top: 1px solid #ccc;
            display: flex;
            align-items: center;
        }
        /* Dropdown for selecting operation type */
        .chatbot-input select {
            width: 120px;
            margin-right: 8px;
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
            border: 1px solid #000;
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
        /* Card styles for search results */
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
        /* Issue report form styles inside chat */
        .issue-form-container {
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
        }
        .issue-form-container h5 {
            font-size: 16px;
            color: #007bff;
        }
        .issue-form-container input[type="file"],
        .issue-form-container textarea {
            width: 100%;
            margin-bottom: 10px;
        }
        .issue-form-container button {
            width: 100%;
        }
        /* Responsive styles */
        @media (max-width: 576px) {
            .chatbot-container {
                width: 100%;
                height: 100%;
                bottom: 0;
                right: 0;
                border-radius: 0;
            }
            .floating-btn {
                right: 10px;
                bottom: 10px;
                width: 50px;
                height: 50px;
                font-size: 20px;
            }
            .chatbot-input select {
                width: 100px;
                font-size: 12px;
            }
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
            <span class="text-dark">Chatbot Assistance</span>
            <button id="close-chatbot" class="btn btn-sm btn-close"></button>
        </div>
        <div id="chatbot-messages" class="chatbot-messages">
            <div class="chat-message bot">
                <img src="../images/profiles/bot.webp" alt="blk">
                <div><strong>blk:</strong> How can I help you today?</div>
            </div>
        </div>
        <!-- Chat input now has a dropdown to choose between Search and Report operations -->
        <div class="chatbot-input">
            <select id="chatbot-option" class="form-select">
                <option value="search" selected>Search</option>
                <option value="report">Report</option>
            </select>
            <input type="text" id="user-query" class="form-control" placeholder="Enter your query..." />
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
                        <input type="hidden" class="form-control" name="userId" value="<?= $_SESSION["userId"] ?>" required>
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
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    $(document).ready(function () {
        // Initialize Select2 for interest modal
        $('#categoryId').select2({
            dropdownParent: $('#interestModal'),
            placeholder: "Select Categories",
            allowClear: true,
            width: '100%'
        });
        // Floating menu toggle
        let isMenuVisible = false;
        $('#three-dot-menu').on('click', function (e) {
            e.stopPropagation();
            isMenuVisible = !isMenuVisible;
            $('#floating-menu').toggle(isMenuVisible);
        });
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
                            location.reload();
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
                error: function () {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An unexpected error occurred. Please try again later.',
                        confirmButtonText: 'OK'
                    });
                }
            });
        });
        // Chatbot functionality
        $('#send-query').on('click', function () {
            let option = $('#chatbot-option').val();
            let query = $('#user-query').val().trim();
            if (!query) return;
            // Append user message
            $('#chatbot-messages').append(`
                <div class="chat-message user">
                    <img src="../images/profiles/<?php echo $_SESSION["userProfileImg"] ?? "profile.webp"; ?>" alt="User">
                    <div><strong>You:</strong> ${query}</div>
                </div>
            `);
            $('#user-query').val('');
            // Call appropriate handler based on selected option
            if (option === "search") {
                handleSearch(query);
            } else if (option === "report") {
                handleReport(query);
            }
            $('#chatbot-messages').scrollTop($('#chatbot-messages')[0].scrollHeight);
        });
        // Enter key handler
        $('#user-query').on('keypress', function (e) {
            if (e.which === 13) {
                $('#send-query').click();
            }
        });
        $('#close-chatbot').on('click', function () {
            $('#chatbot-container').hide();
        });
        // Search handler function
        function handleSearch(query) {
            $('#chatbot-messages').append(`
                <div class="chat-message bot">
                    <img src="../images/profiles/bot.webp" alt="blk">
                    <div><strong>blk:</strong> Searching auctions for: "${query}"</div>
                </div>
            `);
            $.ajax({
                url: 'menu.php',
                type: 'POST',
                data: { query: query },
                dataType: 'json',
                success: function (response) {
                    let botMessage = '';
                    if (response.success) {
                        if (response.auctions && response.auctions.length > 0) {
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
        }
        // Report handler function: show request to select a report category.
        function handleReport(query) {
            $('#chatbot-messages').append(`
                <div class="chat-message bot">
                    <img src="../images/profiles/bot.webp" alt="blk">
                    <div>
                        <strong>blk:</strong> To report an issue, please select an issue category:
                        <div class="mt-2">
                            <button class="btn btn-sm btn-outline-primary me-2 issue-category" data-category="transaction">Transaction</button>
                            <button class="btn btn-sm btn-outline-primary issue-category" data-category="auction">Auction</button>
                        </div>
                    </div>
                </div>
            `);
            $('#chatbot-messages').scrollTop($('#chatbot-messages')[0].scrollHeight);
        }
        // Delegate event for issue category selection
        $(document).on('click', '.issue-category', function () {
            let category = $(this).data('category');
            let issues = [];
            if (category === 'transaction') {
                issues = [
                    { name: 'Amount paid but status not changed', type: 'transaction_status' },
                    { name: 'Amount not yet refunded', type: 'transaction_refund' },
                    { name: '90% subscription amount not refunded', type: 'subscription_refund' }
                ];
            } else if (category === 'auction') {
                issues = [
                    { name: 'Auction created with infinite time', type: 'auction_infinite' },
                    { name: 'Auction ended but payment options not enabled', type: 'auction_payment' }
                ];
            }
            let buttonsHtml = issues.map(issue => `<button class="btn btn-sm btn-outline-secondary me-2 issue-option" data-issue="${issue.type}">${issue.name}</button>`).join('');
            $('#chatbot-messages').append(`
                <div class="chat-message bot">
                    <img src="../images/profiles/bot.webp" alt="blk">
                    <div>
                        <strong>blk:</strong> Please select a specific issue:
                        <div class="mt-2">${buttonsHtml}</div>
                    </div>
                </div>
            `);
            $('#chatbot-messages').scrollTop($('#chatbot-messages')[0].scrollHeight);
        });
        // Delegate event for issue option selection to show report form
        $(document).on('click', '.issue-option', function () {
            let issueType = $(this).data('issue');
            let issueTitles = {
                'transaction_status': 'Amount paid but status not changed',
                'transaction_refund': 'Amount not yet refunded',
                'subscription_refund': '90% subscription amount not refunded',
                'auction_infinite': 'Auction created with infinite time',
                'auction_payment': 'Auction ended but payment options not enabled'
            };
            let issueTitle = issueTitles[issueType] || 'General Issue';
            $('#chatbot-messages').append(`
                <div class="chat-message bot">
                    <img src="../images/profiles/bot.webp" alt="blk">
                    <div>
                        <strong>blk:</strong> Reporting: ${issueTitle}
                        <div class="issue-form-container">
                            <h5>${issueTitle}</h5>
                            <form id="issue-form-${Date.now()}" enctype="multipart/form-data">
                                <div class="mb-2">
                                    <label class="form-label">Comments</label>
                                    <textarea class="form-control" name="message" rows="2" required></textarea>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Upload Proof</label>
                                    <input type="file" class="form-control" name="proof" accept="image/*,.pdf" required>
                                </div>
                                <input type="hidden" name="issueType" value="${issueType}">
                                <button type="submit" class="btn btn-primary btn-sm">Submit Issue</button>
                            </form>
                        </div>
                    </div>
                </div>
            `);
            $('#chatbot-messages').scrollTop($('#chatbot-messages')[0].scrollHeight);
        });
        // Delegate form submission for report issues inside chat
        $(document).on('submit', 'form[id^="issue-form-"]', function (e) {
            e.preventDefault();
            let form = this;
            let $submitButton = $(this).find('button[type="submit"]');
            $submitButton.prop('disabled', true).html('Submitting...');
            let formData = new FormData(form);
            $.ajax({
                url: 'menu.php',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function (response) {
                    let res = JSON.parse(response);
                    if (res.success) {
                        $('#chatbot-messages').append(`
                            <div class="chat-message bot">
                                <img src="../images/profiles/bot.webp" alt="blk">
                                <div><strong>blk:</strong> ${res.message}</div>
                            </div>
                        `);
                    } else {
                        $('#chatbot-messages').append(`
                            <div class="chat-message bot">
                                <img src="../images/profiles/bot.webp" alt="blk">
                                <div><strong>blk:</strong> Error: ${res.message}</div>
                            </div>
                        `);
                    }
                    $submitButton.prop('disabled', false).html('Submit Issue');
                    $('#chatbot-messages').scrollTop($('#chatbot-messages')[0].scrollHeight);
                },
                error: function () {
                    $('#chatbot-messages').append(`
                        <div class="chat-message bot">
                            <img src="../images/profiles/bot.webp" alt="blk">
                            <div><strong>blk:</strong> An error occurred. Please try again.</div>
                        </div>
                    `);
                    $submitButton.prop('disabled', false).html('Submit Issue');
                    $('#chatbot-messages').scrollTop($('#chatbot-messages')[0].scrollHeight);
                }
            });
        });
    });
    // Reinitialize Select2 when the modal is opened
    $(document).ready(function () {
        function initializeSelect2() {
            $('#categoryId').select2({
                dropdownParent: $('#interestModal'),
                placeholder: "Select Categories",
                allowClear: true,
                width: '100%'
            });
        }
        initializeSelect2();
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