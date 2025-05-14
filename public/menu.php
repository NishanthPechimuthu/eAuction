<?php
ob_start();
session_start();

// Ensure CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
    exit();
}

// Build the path based on __DIR__ for InfoData folder
$baseDir = '../public/InfoData';
$issuesFile = $baseDir . '/issues.json';
$fallbackFile = $baseDir . '/problem.json';

// Ensure the InfoData directory exists and is writable
if (!is_dir($baseDir)) {
    if (!mkdir($baseDir, 0755, true)) {
        error_log("Failed to create directory: $baseDir");
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Cannot create InfoData directory.']);
        exit();
    }
}
if (!is_writable($baseDir)) {
    error_log("Directory not writable: $baseDir");
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'InfoData directory is not writable.']);
    exit();
}

// Create primary file if it doesn’t exist
if (!file_exists($issuesFile)) {
    if (!file_put_contents($issuesFile, json_encode([]))) {
        error_log("Failed to create issues.json at: $issuesFile");
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Cannot create issues.json.']);
        exit();
    }
    chmod($issuesFile, 0666); // Set permissions for web server access
}

// Check if primary file is writable
if (!is_writable($issuesFile)) {
    error_log("File not writable: $issuesFile");
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Cannot write to issues.json. Please check server permissions.']);
    exit();
}

/**
 * Attempts to write the $issues array to $file.
 * Returns true on success, false otherwise.
 */
function safeWriteToFile($file, $issues) {
    $maxRetries = 3;
    $retryCount = 0;
    while ($retryCount < $maxRetries) {
        // Removed LOCK_EX flag to support streams that don't allow it.
        if (file_put_contents($file, json_encode($issues, JSON_PRETTY_PRINT)) !== false) {
            return true;
        }
        $retryCount++;
        usleep(100000); // Wait 100ms before retry
    }
    error_log("Failed to write to $file after $maxRetries attempts");
    return false;
}

// Function to save issue reports with fallback logic
function saveIssue($userId, $issueType, $proofPath, $message) {
    global $issuesFile, $fallbackFile;

    // Read existing issues from primary file
    $issuesJson = @file_get_contents($issuesFile);
    if ($issuesJson === false) {
        error_log("Failed to read $issuesFile");
        $issues = [];
    } else {
        $issues = json_decode($issuesJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error in $issuesFile: " . json_last_error_msg());
            $issues = [];
        }
    }

    $issue = [
        'issueId'      => uniqid(),
        'userId'       => $userId,
        'issueType'    => $issueType,
        'issueMessage' => $message,
        'issueImage'   => $proofPath,
        'issueStatus'  => 'pending',
        'createdAt'    => date('c')
    ];

    $issues[] = $issue;

    if (safeWriteToFile($issuesFile, $issues)) {
        // Verify primary write
        $writtenJson = @file_get_contents($issuesFile);
        if ($writtenJson === false) {
            error_log("Failed to read $issuesFile after write");
            throw new Exception('Failed to verify write to issues.json.');
        }
        $writtenIssues = json_decode($writtenJson, true);
        if (json_last_error() !== JSON_ERROR_NONE || !in_array($issue, $writtenIssues, true)) {
            error_log("Issue not found in $issuesFile after write");
            throw new Exception('Issue not found in primary file after write.');
        }
        return $issue['issueId'];
    }

    // Attempt fallback file
    if (!file_exists($fallbackFile)) {
        if (!file_put_contents($fallbackFile, json_encode([]))) {
            error_log("Failed to create $fallbackFile");
            throw new Exception('Cannot create fallback file problem.json.');
        }
        chmod($fallbackFile, 0666);
    }
    $fallbackIssuesJson = @file_get_contents($fallbackFile);
    if ($fallbackIssuesJson === false) {
        error_log("Failed to read $fallbackFile");
        $fallbackIssues = [];
    } else {
        $fallbackIssues = json_decode($fallbackIssuesJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error in $fallbackFile: " . json_last_error_msg());
            $fallbackIssues = [];
        }
    }
    $fallbackIssues[] = $issue;

    if (!safeWriteToFile($fallbackFile, $fallbackIssues)) {
        error_log("Failed to write to $fallbackFile");
        throw new Exception('Failed to write issue to both primary and fallback files.');
    }

    // Verify fallback write
    $writtenJson = @file_get_contents($fallbackFile);
    if ($writtenJson === false) {
        error_log("Failed to read $fallbackFile after write");
        throw new Exception('Failed to verify write to problem.json.');
    }
    $writtenIssues = json_decode($writtenJson, true);
    if (json_last_error() !== JSON_ERROR_NONE || !in_array($issue, $writtenIssues, true)) {
        error_log("Issue not found in $fallbackFile after write");
        throw new Exception('Issue not found in fallback file after write.');
    }

    return $issue['issueId'] . " (saved to fallback file problem.json)";
}

// Include necessary files
include "./header.php";

// Fallback functions
if (!function_exists('addReview')) {
    function addReview($userId, $reviewMessage) {
        return ['success' => false, 'message' => 'Review functionality is not available.'];
    }
}
if (!function_exists('getCategories')) {
    function getCategories() {
        return [];
    }
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Interest submission block
    if (isset($_POST["categoryId"])) {
        if (!isset($_SESSION['userId'])) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Please log in to continue.']);
            exit();
        }
        $userId = $_SESSION["userId"];
        $categories = $_POST["categoryId"];
        $type = $_POST["type"];
        $keywords = $_POST["keywords"];
        try {
            global $pdo;
            if (!isset($pdo)) {
                throw new Exception("Database connection is not established.");
            }
            foreach ($categories as $categoryId) {
                $stmt = $pdo->prepare("
                    INSERT INTO interests (interestUserId, interestCategoryId, interestProductType, interestKeywords)
                    VALUES (:userId, :categoryId, :type, :keywords)
                ");
                $stmt->execute([
                    ":userId" => $userId,
                    ":categoryId" => $categoryId,
                    ":type" => $type,
                    ":keywords" => $keywords,
                ]);
            }
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Interest(s) added successfully.']);
        } catch (Exception $e) {
            error_log("Interest submission error: " . $e->getMessage());
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }

    // Review submission block
    if (isset($_POST["reviewMessage"])) {
        if (!isset($_SESSION['userId'])) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Please log in to continue.']);
            exit();
        }
        $userId = $_SESSION["userId"];
        $reviewMessage = $_POST["reviewMessage"] ?? "";
        if (empty($reviewMessage)) {
            ob_clean();
            echo json_encode(["success" => false, "message" => "Review message is required."]);
            exit();
        }
        try {
            $response = addReview($userId, $reviewMessage);
            ob_clean();
            echo json_encode($response);
        } catch (Exception $e) {
            error_log("Review submission error: " . $e->getMessage());
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }

    // Chatbot query block
    if (isset($_POST["query"])) {
        $query = $_POST["query"] ?? "";
        if (empty($query)) {
            ob_clean();
            echo json_encode(["success" => false, "message" => "Please enter a search query."]);
            exit();
        }
        try {
            $response = getAuctionResults($query);
            ob_clean();
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Exception $e) {
            error_log("Search query error: " . $e->getMessage());
            ob_clean();
            echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
        }
        exit();
    }

    // Issue report block with file upload
    if (isset($_POST['issueType']) && isset($_FILES['proof'])) {
        if (!isset($_SESSION['userId'])) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Please log in to continue.']);
            exit();
        }
        $userId = $_SESSION['userId'];
        $issueType = $_POST['issueType'];
        $message = $_POST['message'] ?? '';

        // Set upload directory
        $uploadDir = '../images/report/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                error_log("Failed to create upload directory: $uploadDir");
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Cannot create upload directory.']);
                exit();
            }
        }
        if (!is_writable($uploadDir)) {
            error_log("Upload directory not writable: $uploadDir");
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Cannot write to upload directory.']);
            exit();
        }

        if ($_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'File upload error: ' . $_FILES['proof']['error']]);
            exit();
        }

        $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB
        $fileExtension = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));
        $fileSize = $_FILES['proof']['size'];
        if (!in_array($fileExtension, $allowedTypes)) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowedTypes)]);
            exit();
        }
        if ($fileSize > $maxFileSize) {
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit']);
            exit();
        }

        $fileName = uniqid() . '.' . $fileExtension;
        $filePath = $uploadDir . $fileName;

        try {
            if (move_uploaded_file($_FILES['proof']['tmp_name'], $filePath)) {
                $issueId = saveIssue($userId, $issueType, $filePath, $message);
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'message' => 'Issue reported successfully. Issue ID: ' . $issueId
                ]);
            } else {
                error_log("Failed to move uploaded file to: $filePath");
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Failed to upload proof file.']);
            }
        } catch (Exception $e) {
            error_log("Issue submission error: " . $e->getMessage());
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }
}

// Function to get auction results based on the query
function getAuctionResults($query) {
    global $pdo;
    if (!isset($pdo)) {
        throw new Exception("Database connection is not established.");
    }
    $query = trim($query);
    try {
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
    } catch (Exception $e) {
        error_log("Auction search error: " . $e->getMessage());
        throw $e;
    }
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
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            transition: transform 0.2s, background 0.3s;
        }
        .floating-btn:hover {
            background-color: #0056b3;
            transform: scale(1.1);
        }
        /* Chatbot container */
        .chatbot-container {
            position: fixed;
            bottom: 90px;
            right: 20px;
            width: 360px;
            height: 600px;
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
            display: none;
            flex-direction: column;
            z-index: 9999;
            overflow: hidden;
        }
        @media (max-width: 576px) {
            .chatbot-container {
                width: 100%;
                height: 100%;
                bottom: 0;
                right: 0;
                border-radius: 0;
            }
        }
        .chatbot-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 12px 16px;
            font-size: 18px;
            font-weight: 600;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
        }
        .chatbot-messages {
            flex-grow: 1;
            padding: 16px;
            overflow-y: auto;
            background: #f8f9fa;
            max-height: 400px;
        }
        .chatbot-input {
            display: flex;
            padding: 12px;
            border-top: 1px solid #e0e0e0;
            background: #fff;
        }
        .chatbot-input input {
            border-radius: 20px;
            border: 1px solid #ced4da;
            padding: 8px 16px;
        }
        .chatbot-input button {
            border-radius: 20px;
            padding: 8px 16px;
            background-color: #007bff;
        }
        .chatbot-input button:hover {
            background-color: #0056b3;
        }
        /* Chat messages */
        .chat-message {
            display: flex;
            margin-bottom: 12px;
            align-items: flex-start;
        }
        .chat-message img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            margin: 0 8px;
            border: 1px solid #ddd;
        }
        .chat-message.bot {
            justify-content: flex-start;
        }
        .chat-message.user {
            justify-content: flex-end;
        }
        .chat-message.bot div {
            background: #e6f4ea;
            color: #333;
            border-radius: 15px 15px 15px 5px;
            padding: 10px 14px;
            max-width: 70%;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .chat-message.user div {
            background: #d0e9ff;
            color: #333;
            border-radius: 15px 15px 5px 15px;
            padding: 10px 14px;
            max-width: 70%;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        /* Option buttons */
        .option-buttons {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 10px;
        }
        @media (max-width: 576px) {
            .option-buttons {
                grid-template-columns: 1fr;
            }
        }
        .option-btn {
            background: #007bff;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 10px;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .option-btn:hover {
            background: #0056b3;
        }
        /* Main menu button */
        .main-menu-btn {
            background: #007bff;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 10px;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 10px;
            display: inline-block;
        }
        .main-menu-btn:hover {
            background: #0056b3;
        }
        /* Typing indicator */
        .typing-indicator {
            display: flex;
            padding: 10px;
        }
        .typing-indicator span {
            width: 8px;
            height: 8px;
            background: #007bff;
            border-radius: 50%;
            margin: 0 4px;
            animation: typing 1s infinite;
        }
        .typing-indicator span:nth-child(2) {
            animation-delay: 0.2s;
        }
        .typing-indicator span:nth-child(3) {
            animation-delay: 0.4s;
        }
        @keyframes typing {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        /* Card styles */
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
        /* SweetAlert2 z-index fix */
        .swal2-container {
            z-index: 10000 !important;
        }
    </style>
</head>
<body>
    <!-- Floating Button -->
    <div class="floating-btn" id="three-dot-menu" aria-label="Open menu">
        <i class="fas fa-ellipsis-v"></i>
    </div>
    <!-- Chatbot Container -->
    <div id="chatbot-container" class="chatbot-container">
        <div class="chatbot-header d-flex justify-content-between align-items-center">
            <span>AgriBot Assistance</span>
            <button id="close-chatbot" class="btn btn-sm btn-close" aria-label="Close chatbot"></button>
        </div>
        <div id="chatbot-messages" class="chatbot-messages" aria-label="Chatbot messages">
            <div class="chat-message bot">
                <img src="../images/profiles/bot.webp" alt="AgriBot">
                <div><strong>AgriBot:</strong> Welcome! How can I assist you today?</div>
            </div>
        </div>
        <div class="chatbot-input">
            <input type="text" id="user-query" class="form-control" placeholder="Type your message..." aria-label="Chatbot input" />
            <button id="send-query" class="btn btn-primary ms-2" aria-label="Send message"><i class="fas fa-paper-plane"></i></button>
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
                        <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
                    <?php elseif (isset($errorMessage)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" class="form-control" name="userId" value="<?= htmlspecialchars($_SESSION["userId"] ?? '') ?>" required>
                        <div class="mb-3">
                            <label for="categoryId" class="form-label">Select Categories</label>
                            <select class="form-select select2" id="categoryId" name="categoryId[]" multiple="multiple" required>
                                <?php
                                $categories = getCategories();
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
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <div class="mb-3">
                            <textarea class="form-control" name="reviewMessage" rows="5" placeholder="Write your review..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Submit Review</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Floating Menu -->
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
        // Initialize Select2
        initializeSelect2();
        // Chatbot state management
        let chatbotState = {
            currentMenu: 'main',
            selectedCategory: null,
            selectedIssue: null
        };
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
        // Show initial options after welcome message
        setTimeout(() => {
            showMainMenu();
        }, 1000);
        // Handle chatbot query submission
        $('#send-query').on('click', function () {
            const query = $('#user-query').val().trim();
            if (!query) return;
            // Append user message
            $('#chatbot-messages').append(`
                <div class="chat-message user">
                    <img src="../images/profiles/<?php echo htmlspecialchars($_SESSION['userProfileImg'] ?? 'profile.webp'); ?>" alt="User">
                    <div><strong>You:</strong> ${query}</div>
                </div>
            `);
            $('#user-query').val('');
            showTypingIndicator();
            setTimeout(() => {
                $('.typing-indicator').remove();
                handleUserInput(query);
                scrollToBottom();
            }, 800);
        });
        // Enter key handler
        $('#user-query').on('keypress', function (e) {
            if (e.which === 13) {
                $('#send-query').click();
            }
        });
        // Close chatbot
        $('#close-chatbot').on('click', function () {
            $('#chatbot-container').hide();
        });
        function initializeSelect2() {
            $('#categoryId').select2({
                dropdownParent: $('#interestModal'),
                placeholder: "Select Categories",
                allowClear: true,
                width: '100%'
            });
        }
        $('#interestModal').on('shown.bs.modal', initializeSelect2);
        function showTypingIndicator() {
            $('#chatbot-messages').append(`
                <div class="typing-indicator">
                    <span></span><span></span><span></span>
                </div>
            `);
            scrollToBottom();
        }
        function scrollToBottom() {
            const messages = $('#chatbot-messages')[0];
            if (messages.scrollHeight - messages.scrollTop <= messages.clientHeight + 50) {
                messages.scrollTop = messages.scrollHeight;
            }
        }
        function showMainMenu() {
            $('#chatbot-messages').append(`
                <div class="chat-message bot">
                    <img src="../images/profiles/bot.webp" alt="AgriBot">
                    <div>
                        <strong>AgriBot:</strong> Please choose an option:
                        <div class="option-buttons">
                            <button class="option-btn" data-action="search">Search Auctions</button>
                            <button class="option-btn" data-action="report">Report Issues</button>
                            <button class="option-btn" data-action="about">About Us</button>
                            <button class="option-btn" data-action="contact">Contact Us</button>
                        </div>
                    </div>
                </div>
            `);
            chatbotState.currentMenu = 'main';
            chatbotState.selectedCategory = null;
            chatbotState.selectedIssue = null;
            bindOptionButtons();
            scrollToBottom();
        }
        function bindOptionButtons() {
            $('.option-btn').off('click').on('click', function () {
                const action = $(this).data('action');
                showTypingIndicator();
                setTimeout(() => {
                    $('.typing-indicator').remove();
                    handleUserInput(action);
                    scrollToBottom();
                }, 800);
            });
        }
        function handleUserInput(query) {
            const lowerQuery = query.toLowerCase().trim();
            if (lowerQuery === 'y') {
                showMainMenu();
                return;
            }
            if (chatbotState.currentMenu === 'main') {
                if (lowerQuery === 'search' || lowerQuery.includes('search')) {
                    chatbotState.currentMenu = 'search';
                    $('#chatbot-messages').append(`
                        <div class="chat-message bot">
                            <img src="../images/profiles/bot.webp" alt="AgriBot">
                            <div><strong>AgriBot:</strong> Please enter your auction search query.</div>
                        </div>
                    `);
                    scrollToBottom();
                } else if (lowerQuery === 'report' || lowerQuery.includes('report')) {
                    chatbotState.currentMenu = 'report_category';
                    showIssueCategories();
                } else if (lowerQuery === 'about' || lowerQuery.includes('about')) {
                    showAboutUs();
                } else if (lowerQuery === 'contact' || lowerQuery.includes('contact')) {
                    showContactUs();
                } else {
                    $('#chatbot-messages').append(`
                        <div class="chat-message bot">
                            <img src="../images/profiles/bot.webp" alt="AgriBot">
                            <div><strong>AgriBot:</strong> Please select a valid option.</div>
                        </div>
                    `);
                    scrollToBottom();
                }
            } else if (chatbotState.currentMenu === 'search') {
                searchAuctions(lowerQuery);
            } else if (chatbotState.currentMenu === 'report_category') {
                $('#chatbot-messages').append(`
                    <div class="chat-message bot">
                        <img src="../images/profiles/bot.webp" alt="AgriBot">
                        <div><strong>AgriBot:</strong> Please select a category using the buttons provided.</div>
                    </div>
                `);
                scrollToBottom();
            } else if (chatbotState.currentMenu === 'report_issue') {
                $('#chatbot-messages').append(`
                    <div class="chat-message bot">
                        <img src="../images/profiles/bot.webp" alt="AgriBot">
                        <div><strong>AgriBot:</strong> Please select an issue using the buttons provided.</div>
                    </div>
                `);
                scrollToBottom();
            }
        }
        function showIssueCategories() {
            $('#chatbot-messages').append(`
                <div class="chat-message bot">
                    <img src="../images/profiles/bot.webp" alt="AgriBot">
                    <div>
                        <strong>AgriBot:</strong> Please select an issue category:
                        <div class="option-buttons">
                            <button class="option-btn issue-category" data-category="transaction">Transaction</button>
                            <button class="option-btn issue-category" data-category="auction">Auction</button>
                        </div>
                    </div>
                </div>
            `);
            $('.issue-category').on('click', function () {
                const category = $(this).data('category');
                chatbotState.selectedCategory = category;
                chatbotState.currentMenu = 'report_issue';
                showIssueOptions(category);
                scrollToBottom();
            });
            scrollToBottom();
        }
        function showIssueOptions(category) {
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
            const buttonsHtml = issues.map(issue => `
                <button class="option-btn issue-option" data-issue="${issue.type}">${issue.name}</button>
            `).join('');
            $('#chatbot-messages').append(`
                <div class="chat-message bot">
                    <img src="../images/profiles/bot.webp" alt="AgriBot">
                    <div>
                        <strong>AgriBot:</strong> Please select a specific issue:
                        <div class="option-buttons">${buttonsHtml}</div>
                    </div>
                </div>
            `);
            $('.issue-option').on('click', function () {
                const issueType = $(this).data('issue');
                chatbotState.selectedIssue = issueType;
                showIssueForm(issueType);
                scrollToBottom();
            });
            scrollToBottom();
        }
        function showIssueForm(issueType) {
            const issueTitles = {
                'transaction_status': 'Amount paid but status not changed',
                'transaction_refund': 'Amount not yet refunded',
                'subscription_refund': '90% subscription amount not refunded',
                'auction_infinite': 'Auction created with infinite time',
                'auction_payment': 'Auction ended but payment options not enabled'
            };
            const issueTitle = issueTitles[issueType] || 'General Issue';
            Swal.fire({
                title: `Report Issue: ${issueTitle}`,
                html: `
                    <form id="issue-form" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="proof" class="form-label">Upload Proof</label>
                            <input type="file" class="form-control" id="proof" name="proof" accept="image/*,.pdf" required>
                        </div>
                        <div class="mb-3">
                            <label for="message" class="form-label">Comments</label>
                            <textarea class="form-control" id="message" name="message" rows="4" placeholder="Describe your issue and add auction link for reference" required></textarea>
                        </div>
                        <input type="hidden" name="issueType" value="${issueType}">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    </form>
                `,
                showCancelButton: true,
                confirmButtonText: 'Submit Issue',
                cancelButtonText: 'Cancel',
                focusConfirm: false,
                preConfirm: () => {
                    const form = document.getElementById('issue-form');
                    const formData = new FormData(form);
                    return new Promise((resolve) => {
                        $.ajax({
                            url: 'menu.php',
                            type: 'POST',
                            data: formData,
                            contentType: false,
                            processData: false,
                            success: function (response) {
                                try {
                                    const data = typeof response === 'string' ? JSON.parse(response) : response;
                                    resolve(data);
                                } catch (e) {
                                    Swal.showValidationMessage('Invalid server response. Please try again.');
                                }
                            },
                            error: function () {
                                Swal.showValidationMessage('An error occurred. Please try again.');
                            }
                        });
                    });
                }
            }).then((result) => {
                if (result.isConfirmed && result.value.success) {
                    $('#chatbot-messages').append(`
                        <div class="chat-message bot">
                            <img src="../images/profiles/bot.webp" alt="AgriBot">
                            <div><strong>AgriBot:</strong> ${result.value.message}</div>
                        </div>
                    `);
                    chatbotState.currentMenu = 'main';
                    setTimeout(() => {
                        showMainMenu();
                    }, 1000);
                } else if (result.isConfirmed) {
                    $('#chatbot-messages').append(`
                        <div class="chat-message bot">
                            <img src="../images/profiles/bot.webp" alt="AgriBot">
                            <div><strong>AgriBot:</strong> Error: ${result.value.message}</div>
                        </div>
                    `);
                }
                scrollToBottom();
            });
        }
        function showAboutUs() {
            $('#chatbot-messages').append(`
                <div class="chat-message bot">
                    <img src="../images/profiles/bot.webp" alt="AgriBot">
                    <div>
                        <strong>AgriBot:</strong> About Us:<br>
                        This eAuction site specializes in agricultural products, enabling broker-free sales between farmers and vendors.<br>
                        Platform charges:<br>
                        - ₹1000/month subscription (90% refundable with at least one transaction without issues)<br>
                        - 5% commission from both farmer and vendor<br>
                        <button class="main-menu-btn" onclick="showMainMenu()">Back to Main Menu</button>
                    </div>
                </div>
            `);
            chatbotState.currentMenu = 'about_us';
            scrollToBottom();
        }
        function showContactUs() {
            $('#chatbot-messages').append(`
                <div class="chat-message bot">
                    <img src="../images/profiles/bot.webp" alt="AgriBot">
                    <div>
                        <strong>AgriBot:</strong> Contact Us:<br>
                        Phone: +91 9876543210<br>
                        Email: eagri.ct.ws@gmail.com<br>
                        Address: 1/283, S Ammapatti Road, Somavarapatti, Pethappampatti, Udumalpet, Tiruppur, Tamil Nadu -642205<br>
                        <button class="main-menu-btn" onclick="showMainMenu()">Back to Main Menu</button>
                    </div>
                </div>
            `);
            chatbotState.currentMenu = 'contact_us';
            scrollToBottom();
        }
        function searchAuctions(query) {
            $.ajax({
                url: 'menu.php',
                type: 'POST',
                data: { query: query, csrf_token: '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>' },
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
                                <img src="../images/profiles/bot.webp" alt="AgriBot">
                                <div><strong>AgriBot:</strong> ${botMessage}</div>
                            </div>
                        `);
                    }
                    $('#chatbot-messages').append(`
                        <div class="chat-message bot">
                            <img src="../images/profiles/bot.webp" alt="AgriBot">
                            <div>
                                <strong>AgriBot:</strong> Type 'y' to return to the main menu or click below:
                                <button class="main-menu-btn" onclick="showMainMenu()">Back to Main Menu</button>
                            </div>
                        </div>
                    `);
                    chatbotState.currentMenu = 'search_results';
                    scrollToBottom();
                },
                error: function () {
                    $('#chatbot-messages').append(`
                        <div class="chat-message bot">
                            <img src="../images/profiles/bot.webp" alt="AgriBot">
                            <div><strong>AgriBot:</strong> Error: Unable to fetch auction results.</div>
                        </div>
                    `);
                    scrollToBottom();
                }
            });
        }
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
        window.showMainMenu = showMainMenu;
    });
    </script>
</body>
</html>