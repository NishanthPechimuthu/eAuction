<?php
ob_start(); // Start output buffering

include 'db.php';

// Set a session variable for user ID
function setUserSession($userId) {
    $_SESSION['$userId'] = $userId;
}

// Function to handle adding a review
function addReview($userId, $reviewMessage) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("INSERT INTO reviews (reviewUserId, reviewMessage, reviewStatus) VALUES (:userId, :message, 'deactivate')");
        $stmt->execute([
            ':userId' => $userId,
            ':message' => htmlspecialchars(trim($reviewMessage)),
        ]);
        return ["success" => true, "message" => "Review submitted successfully."];
    } catch (PDOException $e) {
        return ["success" => false, "message" => "Database error: " . $e->getMessage()];
    }
}

// user ID from session
function getUserFromSession() {
    if (isset($_SESSION["userId"])) {
        return $_SESSION["userId"]; 
    } else {
        return null; 
    }
}

// Fetch all active auctions
function getActiveAuctions() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM auctions WHERE auctionEndDate > NOW() AND auctionStartDate < NOW() AND auctionStatus = 'activate'");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch all participated auctions
function getAuctionsParticipate() {
    global $pdo;
    $user_id = getUserFromSession();
    
    if (!$user_id) {
        header("Location: ../local/login.php"); // User not authenticated
    }

    $sql = "SELECT DISTINCT a.* FROM auctions a JOIN bids b ON a.auctionId = b.bidAuctionId WHERE NOW() > a.auctionEndDate AND b.bidUserId = :user_id AND a.auctionStatus = 'activate' ORDER BY a.auctionEndDate DESC;";
            
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch a single auction by its ID
function getAuctionById($auction_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM auctions WHERE auctionId = :auction_id");
    $stmt->execute(['auction_id' => $auction_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch a single auction by its ID
function getCategoryById($category_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE categoryId = :category_id");
    $stmt->execute(['category_id' => $category_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result["categoryName"];
}

// Fetch a all categories
function getCategories() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM categories");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC); // Fetch all categories as an array
}

//Get the user maximium number bid for a specific auction 
function getNumberBid($user_id, $auction_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(bidAuctionId) as count FROM bids WHERE bidAuctionId = :auction_id AND bidUserId = :user_id");
    $stmt->execute(['auction_id' => $auction_id, 'user_id' => $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result && isset($result['count'])) {
        return (int) $result['count']; 
    } else {
        return 0; 
    }
}

//Get all bids
function getAllBid() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM bids");
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $result; // Return the result to be used elsewhere
}

//Get reviews
function getReviews() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM reviews WHERE reviewStatus = :status");
    $status = "activate"; // Define the status value
    $stmt->bindParam(':status', $status, PDO::PARAM_STR); // Correct parameter binding
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $result; // Return the result to be used elsewhere
}

//Get all reviews
function getAllReviews() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM reviews");
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $result; // Return the result to be used elsewhere
}

//Get Activate Heroes
function getActiveHeroes() {
  global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM heroes WHERE heroStatus = 'activate'");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

//Get Heroes
function getAllHeroes() {
  global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM heroes WHERE heroStatus <> 'suspend'");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get Heroes by Id
function getHeroById($id) {
  global $pdo;
  $stmt = $pdo->prepare("SELECT * FROM heroes WHERE heroId = :id");
  $stmt->bindValue(":id", $id, PDO::PARAM_INT); // Bind the parameter correctly
  $stmt->execute();
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get the highest bid for a specific auction
function getHighestBid($auction_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT MAX(bidAmount) AS highest_bid FROM bids WHERE bidAuctionId = :auction_id");
    $stmt->execute(['auction_id' => $auction_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['highest_bid'] ?? 0;
}
// Get the highest bid for a specific auction
function getHighestBidderId($auction_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT MAX(bidAmount) AS highest_bid, bidUserId FROM bids WHERE bidAuctionId = :auction_id");
    $stmt->execute(['auction_id' => $auction_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['bidUserId'] ?? 0;
}

// Place a bid
function placeBid($auction_id, $user_id, $bid_amount) {
    global $pdo;
     // Get user_id from session
    if (!$user_id) {
        return false; // User not authenticated
    }

    $highest_bid = getHighestBid($auction_id);

    // Ensure the bid is higher than the highest bid
    if ($bid_amount > $highest_bid) {
        $stmt = $pdo->prepare("INSERT INTO bids (bidAuctionId, bidUserId, bidAmount) VALUES (:auction_id, :user_id, :bid_amount)");
        $stmt->execute([
            'auction_id' => $auction_id,
            'user_id' => $user_id,
            'bid_amount' => $bid_amount
        ]);
        return true;
    }
    return false;
}

// Check if a user is the highest bidder
function isHighestBidder($auction_id) {
    global $pdo;
    $user_id = getUserFromSession(); // Get user_id from session

    if (!$user_id) {
        return false; // User not authenticated
    }

    $stmt = $pdo->prepare("SELECT bidUserId FROM bids WHERE bidAuctionId = :auction_id ORDER BY bidAmount DESC LIMIT 1");
    $stmt->execute(['auction_id' => $auction_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result && $result['user_id'] == $user_id;
}

// Fetch user data by ID
function getUserById($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE userId = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch user Name by ID
function getUserName($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE userId = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result["userName"];
}

// Fetch user Image by ID
function getUserImage($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE userId = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result["userProfileImg"];
}

// Fetch all auctions
function getAllAuctions() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM auctions ORDER BY createdAt DESC");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch all auctions by the user
function getUsersAuctions() {
    global $pdo;
    $user_id = getUserFromSession();

    if (!$user_id) {
        return []; // User not authenticated
    }

    try {
        $sql = "SELECT * FROM auctions WHERE auctionCreatedBy = :user_id AND auctionStatus <> 'suspend' ORDER BY createdAt DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []; // Return empty array if no results
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return []; // Handle the error appropriately
    }
}

// Fetch all users
function getAllUsers() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE userStatus <> :sStatus AND userStatus = :aStatus");
    $stmt->execute(['sStatus' => 'suspend','aStatus' => 'activate']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch all inactivate users
function getInactivateUsers() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE userStatus <> :sStatus AND userStatus = :iaStatus");
    $stmt->execute(['sStatus' => 'suspend','iaStatus' => 'deactivate']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addHero($heroTitle, $heroMessage, $heroContent, $heroImg, $heroStatus) {
    global $pdo;

    try {
        // Prepare the SQL insert statement with correct column names
        $stmt = $pdo->prepare("INSERT INTO heroes 
            (heroTitle, heroMessage, heroContent, heroImg, heroStatus, createdAt) 
            VALUES (:heroTitle, :heroMessage, :heroContent, :heroImg, :heroStatus, NOW())");

        // Execute the query with the correct named parameters
        $success = $stmt->execute([
            'heroTitle' => $heroTitle,
            'heroMessage' => $heroMessage,
            'heroContent' => $heroContent,
            'heroImg' => $heroImg,
            'heroStatus' => $heroStatus,
        ]);

        if ($success) {
            return "Hero added successfully!";
        } else {
            return "Error: Failed to add hero.";
        }
    } catch (PDOException $e) {
        return "Database error: " . $e->getMessage();
    }
}

// Add a new auction
function addAuction($title, $start_price, $start_time, $end_date, $category_id, $address, $description, $uniqueName, $user_id, $product_type, $product_quantity, $product_unit) {
    global $pdo;

    try {
        // Prepare the SQL insert statement with correct column names
        $stmt = $pdo->prepare("INSERT INTO auctions 
            (auctionTitle, auctionStartPrice, auctionStartDate, auctionEndDate, auctionAddress, auctionDescription, auctionCategoryId, auctionProductImg, auctionCreatedBy,
              auctionProductType,
              auctionProductQuantity,
              auctionProductUnit) 
            VALUES (:title, :start_price, :start_time, :end_date, :address, :description, :category_id, :product_img, :user_id,
              :product_type,
              :product_quantity,
              :product_unit)");

        // Execute the query with the correct named parameters
        $success = $stmt->execute([
            'title' => $title,
            'start_price' => $start_price,
            'start_time' => $start_time,
            'end_date' => $end_date,
            'address' => $address,
            'description' => $description,
            'category_id' => $category_id, // Note: Changed from 'category_name' to 'category_id'
            'product_img' => $uniqueName,
            'user_id' => (int)$user_id,
            'product_type' => $product_type, 
            'product_quantity' => $product_quantity, 
            'product_unit' => $product_unit 
        ]);

        // Check if the query executed successfully
        if ($success) {
            return "Auction added successfully!";
        } else {
            return "Error: Failed to add auction.";
        }

    } catch (PDOException $e) {
        // Catch any database errors and return the error message
        return "Img" .$product_img."End Database error: " . $e->getMessage();
    }
}

// Add a new auction
function addCategory($category_name, $unique_name) {
    global $pdo;

    try {
        // Prepare the SQL insert statement
        $stmt = $pdo->prepare("INSERT INTO categories 
        (categoryName,categoryImg) 
        VALUES (:category_name,:unique_name)");

        // Execute the query
        $success = $stmt->execute([
            'category_name' => $category_name,
            'unique_name' => $unique_name
        ]);

        // Check if the query executed successfully
        if ($success) {
            return "Category added successfully!";
        } else {
            return "Error: Failed to add auction.";
        }

    } catch (PDOException $e) {
        // Catch any database errors and return the error message
        return "Database error: " . $e->getMessage();
    }
}

// Delete an auction
function deleteAuction($auction_id) {
    global $pdo;

    // Check if the auction exists
    $stmt = $pdo->prepare("SELECT * FROM auctions WHERE auctionId = :auctionId");
    $stmt->execute(['auctionId' => $auction_id]);
    $auction = $stmt->fetch();

    if ($auction) {
        // Now, delete the auction itself
        $deleteAuctionStmt = $pdo->prepare("UPDATE auctions SET auctionStatus = 'suspend' WHERE auctionId = :auctionId");
        $deleteAuctionStmt->execute(['auctionId' => $auction_id]);

        // Optionally, check if deletion was successful
        if ($deleteAuctionStmt->rowCount() > 0) {
                echo '
                <p class="alert alert-success alert-dismissible fade show d-flex align-items-center" 
                       role="alert"  data-bs-dismiss="alert" 
                              aria-label="Close" 
                       style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
              Auction deleted successfully.
            </p>
         ';
        } else {
                echo '
                <p class="alert alert-warning alert-dismissible fade show d-flex align-items-center" 
                       role="alert"  data-bs-dismiss="alert" 
                              aria-label="Close" 
                       style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
              Failed to delete auction.
            </p>
         ';
        }
    } else {
                echo '
                <p class="alert alert-danger alert-dismissible fade show d-flex align-items-center" 
                       role="alert"  data-bs-dismiss="alert" 
                              aria-label="Close" 
                       style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
              Auction not found.
            </p>
         ';
    }
}

// Delete a review (mark as suspended)
function deleteHero($hero_id) {
    global $pdo;

    // Check if the review exists
    $stmt = $pdo->prepare("SELECT * FROM heroes WHERE heroId = :heroId");
    $stmt->execute(['heroId' => $hero_id]);
    $hero = $stmt->fetch();

    if ($hero) {
        // Now, update the review status to 'suspend'
        $deleteHeroStmt = $pdo->prepare("UPDATE heroes SET heroStatus = 'suspend' WHERE heroId = :heroId");
        $deleteHeroStmt->execute(['heroId' => $hero_id]);

        // Optionally, check if update was successful
        if ($deleteHeroStmt->rowCount() > 0) {
            echo '
            <p class="alert alert-success alert-dismissible fade show d-flex align-items-center" 
                   role="alert"  data-bs-dismiss="alert" 
                          aria-label="Close" 
                   style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
              Hero suspended successfully.
            </p>
            ';
        } else {
            echo '
            <p class="alert alert-warning alert-dismissible fade show d-flex align-items-center" 
                   role="alert"  data-bs-dismiss="alert" 
                          aria-label="Close" 
                   style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
              Failed to suspend hero.
            </p>
            ';
        }
    } else {
        echo '
        <p class="alert alert-danger alert-dismissible fade show d-flex align-items-center" 
               role="alert"  data-bs-dismiss="alert" 
                      aria-label="Close" 
               style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
          hero not found.
        </p>
        ';
    }
}
// Delete a review (mark as suspended)
function deleteReview($review_id) {
    global $pdo;

    // Check if the review exists
    $stmt = $pdo->prepare("SELECT * FROM reviews WHERE reviewId = :reviewId");
    $stmt->execute(['reviewId' => $review_id]);
    $review = $stmt->fetch();

    if ($review) {
        // Now, update the review status to 'suspend'
        $deleteReviewStmt = $pdo->prepare("UPDATE reviews SET reviewStatus = 'suspend' WHERE reviewId = :reviewId");
        $deleteReviewStmt->execute(['reviewId' => $review_id]);

        // Optionally, check if update was successful
        if ($deleteReviewStmt->rowCount() > 0) {
            echo '
            <p class="alert alert-success alert-dismissible fade show d-flex align-items-center" 
                   role="alert"  data-bs-dismiss="alert" 
                          aria-label="Close" 
                   style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
              Review suspended successfully.
            </p>
            ';
        } else {
            echo '
            <p class="alert alert-warning alert-dismissible fade show d-flex align-items-center" 
                   role="alert"  data-bs-dismiss="alert" 
                          aria-label="Close" 
                   style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
              Failed to suspend review.
            </p>
            ';
        }
    } else {
        echo '
        <p class="alert alert-danger alert-dismissible fade show d-flex align-items-center" 
               role="alert"  data-bs-dismiss="alert" 
                      aria-label="Close" 
               style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
          Review not found.
        </p>
        ';
    }
}
// Delete a review (mark as suspended)
function deactivateReview($review_id) {
    global $pdo;

    // Check if the review exists
    $stmt = $pdo->prepare("SELECT * FROM reviews WHERE reviewId = :reviewId");
    $stmt->execute(['reviewId' => $review_id]);
    $review = $stmt->fetch();

    if ($review) {
        // Now, update the review status to 'suspend'
        $deleteReviewStmt = $pdo->prepare("UPDATE reviews SET reviewStatus = 'deactivate' WHERE reviewId = :reviewId");
        $deleteReviewStmt->execute(['reviewId' => $review_id]);

        // Optionally, check if update was successful
        if ($deleteReviewStmt->rowCount() > 0) {
            echo '
            <p class="alert alert-success alert-dismissible fade show d-flex align-items-center" 
                   role="alert"  data-bs-dismiss="alert" 
                          aria-label="Close" 
                   style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
              Review suspended successfully.
            </p>
            ';
        } else {
            echo '
            <p class="alert alert-warning alert-dismissible fade show d-flex align-items-center" 
                   role="alert"  data-bs-dismiss="alert" 
                          aria-label="Close" 
                   style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
              Failed to suspend review.
            </p>
            ';
        }
    } else {
        echo '
        <p class="alert alert-danger alert-dismissible fade show d-flex align-items-center" 
               role="alert"  data-bs-dismiss="alert" 
                      aria-label="Close" 
               style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
          Review not found.
        </p>
        ';
    }
}

// Approve a review (mark as activated)
function approveReview($review_id) {
    global $pdo;

    // Check if the review exists
    $stmt = $pdo->prepare("SELECT * FROM reviews WHERE reviewId = :reviewId");
    $stmt->execute(['reviewId' => $review_id]);
    $review = $stmt->fetch();

    if ($review) {
        // Now, update the review status to 'activate'
        $approveReviewStmt = $pdo->prepare("UPDATE reviews SET reviewStatus = 'activate' WHERE reviewId = :reviewId");
        $approveReviewStmt->execute(['reviewId' => $review_id]);

        // Optionally, check if update was successful
        if ($approveReviewStmt->rowCount() > 0) {
            echo '
            <p class="alert alert-success alert-dismissible fade show d-flex align-items-center" 
                   role="alert"  data-bs-dismiss="alert" 
                          aria-label="Close" 
                   style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
              Review approved successfully.
            </p>
            ';
        } else {
            echo '
            <p class="alert alert-warning alert-dismissible fade show d-flex align-items-center" 
                   role="alert"  data-bs-dismiss="alert" 
                          aria-label="Close" 
                   style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
              Failed to approve review.
            </p>
            ';
        }
    } else {
        echo '
        <p class="alert alert-danger alert-dismissible fade show d-flex align-items-center" 
               role="alert"  data-bs-dismiss="alert" 
                      aria-label="Close" 
               style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
          Review not found.
        </p>
        ';
    }
}

// Fetch bids for a specific auction
function getBidsForAuction($auction_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM bids WHERE bidAuctionId = :auction_id ORDER BY bidAmount DESC");
    $stmt->execute(['auction_id' => $auction_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch top bidders for a specific auction
function getTopBidders($auction_id, $limit = 10) {
    global $pdo;
    $stmt = $pdo->prepare(
        "SELECT u.userName AS userId, MAX(b.bidAmount) AS highestBid
          FROM bids b
          JOIN users u ON b.bidUserId = u.userId
          WHERE b.bidAuctionId = :auction_id
          GROUP BY u.userId, u.userName
          ORDER BY highestBid DESC
          LIMIT :limit;
        "
    );
    $stmt->bindParam(':auction_id', $auction_id);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

//Update user profile
function updateUserProfile($user_id, $fname, $lname, $account_no, $image, $phone,$address) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users 
                           SET userFirstName = :fname, 
                               userLastName = :lname, 
                               userAccountNo = :account_no, 
                       userProfileImg = :image,
                       userPhone = :phone, 
                       userAddress = :address 
                           WHERE userId = :user_id");

    $result = $stmt->execute([
        'fname' => $fname,
        'lname' => $lname,
        'account_no' => $account_no,
        'image' => $image,
        'phone' => $phone,
        'address' => $address,
        'user_id' => $user_id
    ]);

    return $result;
}

// Update heros details
function updateHero($heroId, $title, $message, $content, $status, $oldImage, $newImage = null) {
    global $pdo;

    // Validate that the status is one of the valid ENUM values
    $validStatuses = ['activate', 'deactivate', 'suspend'];
    if (!in_array($status, $validStatuses)) {
        return " Invalid status value. Allowed values are 'activate', 'deactivate', or 'suspend'.";
    }

    // If a new image is uploaded, use it; otherwise, retain the old image
    $image = $newImage ? $newImage : $oldImage;

    // Prepare the SQL query to update the hero details
    $sql = "UPDATE heroes 
            SET heroTitle = :title, 
                heroMessage = :message, 
                heroContent = :content, 
                heroStatus = :status, 
                heroImg = :image
            WHERE heroId = :heroId";

    // Prepare and execute the statement
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':title' => $title,
            ':message' => $message,
            ':content' => $content,
            ':status' => $status,
            ':image' => $image,
            ':heroId' => $heroId
        ]);

        return "Hero updated successfully";
    } catch (PDOException $e) {
        return "Error: " . $e->getMessage();
    }
}

// Update auction details
function updateAuction($auctionId, $title, $start_price, $start_time, $end_date, $category_id, $address, $description, $image, $status, $product_type, $product_quantity, $product_unit) {
    global $pdo;

    $validStatuses = ['activate', 'deactivate', 'suspend'];
    if (!in_array($status, $validStatuses)) {
        return false; // Return false for invalid status
    }

    try {
        $sql = "UPDATE auctions 
                SET auctionTitle = :title, 
                    auctionStartPrice = :start_price, 
                    auctionStartDate = :start_time, 
                    auctionEndDate = :end_date, 
                    auctionCategoryId = :category_id, 
                    auctionAddress = :address, 
                    auctionDescription = :description, 
                    auctionProductImg = :image, 
                    auctionStatus = :status,
                    auctionProductType = :product_type,
                    auctionProductQuantity = :product_quantity,
                    auctionProductUnit = :product_unit
                WHERE auctionId = :auctionId";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':title' => $title,
            ':start_price' => $start_price,
            ':start_time' => $start_time,
            ':end_date' => $end_date,
            ':category_id' => $category_id,
            ':address' => $address,
            ':description' => $description,
            ':image' => $image,
            ':status' => $status,
            ':product_type' => $product_type,
            ':product_quantity' => $product_quantity,
            ':product_unit' => $product_unit,
            ':auctionId' => $auctionId
        ]);

        return true; // Return true for success
    } catch (PDOException $e) {
        error_log("Error updating auction: " . $e->getMessage());
        return false; // Return false for failure
    }
}

// Fetch only the UPI ID of a user by their ID
function getUserAccountNo($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT userAccountNo FROM users WHERE userId = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['userAccountNo'] ?? null;
}

// Fetch only the Image of a user by their ID
function getUserImg($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT userProfileImg FROM users WHERE userId = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['userProfileImg'] ?? null;
}

// Function to create UPI payment link
function createUpiRequest($upi_id, $amount, $payee_name) {
    return "upi://pay?pa={$upi_id}&pn=" . urlencode($payee_name) . "&am={$amount}&cu=INR";
}

function getHighestBidder($auction_id) {
    global $pdo;

    try {
        // Prepare the SQL statement to find the highest bidder
        $stmt = $pdo->prepare("
            SELECT bidUserId 
            FROM bids 
            WHERE bidAuctionId = :auction_id 
            ORDER BY bidAmount DESC 
            LIMIT 1
        ");
        $stmt->bindValue(':auction_id', $auction_id, PDO::PARAM_INT);
        $stmt->execute();

        // Fetch the highest bidder or return an empty array
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ["bidUserId"=>0];
    } catch (PDOException $e) {
        // Handle any errors gracefully
        error_log("Database error in getHighestBidder: " . $e->getMessage());
        return [];
    }
}

// Get user old password
function getUserPassword($user_id) {
    global $pdo;
    $result=getUserById($user_id);
    return $result["userPassword"];
}

function getUserByEmail($email) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE userEmail = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user;
}

function getUserEmail($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE userId = :id");
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user["userEmail"];
}

function getUserFullName($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE userId = :id");
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user["userFirstName"]." ".$user["userLastName"];
}

function validateResetToken($user_id, $token) {
    global $pdo;

    // Query to validate the reset token
    $sql = "SELECT 1 FROM passResets 
            WHERE passResetUserId = :user_id 
              AND passResetToken = :token 
              AND DATE_ADD(createdAt, INTERVAL 10 MINUTE) > NOW()";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'user_id' => $user_id,
        'token' => $token
    ]);

    // Return true if the token is valid, false otherwise
    return $stmt->rowCount() === 1;
}

function validateUserActivate($user_id, $token) {
    global $pdo;

    // Query to validate the reset token
    $sql = "SELECT 1 FROM userActivate 
            WHERE userActivateUserId = :user_id 
              AND userActivateToken = :token 
              AND DATE_ADD(createdAt, INTERVAL 10 MINUTE) > NOW()";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'user_id' => $user_id,
        'token' => $token
    ]);

    // Return true if the token is valid, false otherwise
    return $stmt->rowCount() === 1;
}

function updatePassResetToken($user_id, $token) {
    global $pdo;
    $sql = "UPDATE passResets SET passResetToken = 'EXPIRED' WHERE passResetUserId = :user_id and passResetToken = :token";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
      'user_id' => $user_id,
      'token' => $token
      ]);
}

function updateUserActivateToken($user_id, $token) {
    global $pdo;
    $sql = "UPDATE userActivate SET userActivateToken = 'EXPIRED' WHERE userActivateUserId = :user_id and userActivateToken = :token";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
      'user_id' => $user_id,
      'token' => $token
      ]);
}

function updateUserPassword($user_id, $hashed_password) {
    global $pdo;
    $sql = "UPDATE users SET userPassword = :password WHERE userId = :user_id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute(['password' => $hashed_password, 'user_id' => $user_id]);
}

function createPassResetToken($user_id, $token) {
    global $pdo;

    try {
        // Prepare the SQL statement without including the auto-increment passRestId
        $stmt = $pdo->prepare("INSERT INTO passResets (passResetUserId, passResetToken, createdAt) VALUES (:id, :token, NOW())");

        // Execute the statement with the provided user_id and token
        $stmt->execute([
            ':id' => $user_id,
            ':token' => $token
        ]);

        // Check if the insert was successful by checking the row count
        if ($stmt->rowCount() > 0) {
            return true; // Insert was successful
        } else {
            // Log if no rows were affected
                echo '
                <p class="alert alert-warning alert-dismissible fade show d-flex align-items-center" 
                       role="alert"  data-bs-dismiss="alert" 
                              aria-label="Close" 
                       style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
              Error: Failed to insert token into the database. No rows affected.
            </p>
         ';
            return false; // Insert failed
        }

    } catch (PDOException $e) {
        // Log error to file or server log for debugging
                        echo '
                <p class="alert alert-warning alert-dismissible fade show d-flex align-items-center" 
                       role="alert"  data-bs-dismiss="alert" 
                              aria-label="Close" 
                       style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
              Error inserting password reset token: ' . $e->getMessage() . '
            </p>
         ';
        
        // Return false if there's an error
        return false;
    }
}

function createUserActivateToken($user_id, $token) {
    global $pdo;

    try {
        // Prepare the SQL statement without including the auto-increment passRestId
        $stmt = $pdo->prepare("INSERT INTO userActivate (userActivateUserId, userActivateToken, createdAt) VALUES (:id, :token, NOW())");

        // Execute the statement with the provided user_id and token
        $stmt->execute([
            ':id' => $user_id,
            ':token' => $token
        ]);

        // Check if the insert was successful by checking the row count
        if ($stmt->rowCount() > 0) {
            return true; // Insert was successful
        } else {
            // Log if no rows were affected
                echo '
                <p class="alert alert-warning alert-dismissible fade show d-flex align-items-center" 
                       role="alert"  data-bs-dismiss="alert" 
                              aria-label="Close" 
                       style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
              Error: Failed to insert token into the database. No rows affected.
            </p>
         ';
            return false; // Insert failed
        }

    } catch (PDOException $e) {
        // Log error to file or server log for debugging
                        echo '
                <p class="alert alert-warning alert-dismissible fade show d-flex align-items-center" 
                       role="alert"  data-bs-dismiss="alert" 
                              aria-label="Close" 
                       style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
              Error inserting user activation token: ' . $e->getMessage() . '
            </p>
         ';
        
        // Return false if there's an error
        return false;
    }
}

// Function to fetch bid data
function getBidData() {
    global $pdo;

    $query = "
        SELECT 
            DATE(createdAt) as bidDate,
            MAX(bidAmount) as maxBid,
            SUM(bidAmount) as totalBid
        FROM bids
        GROUP BY DATE(createdAt)
        ORDER BY bidDate ASC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to fetch paginated bid data and calculate total pages
function getPaginatedBidData($itemsPerPage, $offset) {
    global $pdo;

    // Get the total number of unique dates
    $countQuery = "SELECT COUNT(DISTINCT DATE(createdAt)) as totalDays FROM bids";
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute();
    $totalDays = (int)$stmt->fetchColumn();

    // Calculate total pages
    $totalPages = ceil($totalDays / $itemsPerPage);

    // Fetch the paginated bid data
    $dataQuery = "
        SELECT 
            DATE(createdAt) as bidDate,
            MAX(bidAmount) as maxBid,
            SUM(bidAmount) as totalBid
        FROM bids
        GROUP BY DATE(createdAt)
        ORDER BY bidDate DESC
        LIMIT :offset, :itemsPerPage
    ";

    $stmt = $pdo->prepare($dataQuery);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':itemsPerPage', $itemsPerPage, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [$data, $totalPages];
}

// Function to fetch users registration
function getUserRegistrationData() {
    global $pdo;

    $query = "
        SELECT 
            DATE_FORMAT(createdAt, '%Y-%m') AS registrationMonth, 
            COUNT(*) AS userCount
        FROM users
        WHERE createdAt >= CURDATE() - INTERVAL 6 MONTH
        GROUP BY registrationMonth
        ORDER BY registrationMonth ASC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAuctionData($offset = 0, $limit = 10) {
    global $pdo;

    $query = "
        SELECT 
            a.auctionTitle,
            a.auctionStartPrice,
            IFNULL(MAX(b.bidAmount), 0) AS highestBid,
            DATE(a.createdAt) AS createdDate
        FROM auctions a
        LEFT JOIN bids b ON a.auctionId = b.bidAuctionId
        GROUP BY a.auctionId
        ORDER BY a.createdAt DESC
        LIMIT :offset, :limit
    ";

    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAuctionCount() {
    global $pdo;

    $query = "SELECT COUNT(*) AS total FROM auctions";
    $stmt = $pdo->query($query);
    return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}

// Get the current date and time
function getLastUpdateLabel() {
    $currentDate = new DateTime();
    return $currentDate->format('F j, Y');
}

function getUserStatusData() {
    global $pdo; // Assuming $pdo is your database connection
    $sql = "SELECT userStatus, COUNT(*) as statusCount FROM users GROUP BY userStatus";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Prepare data for charts
function prepareChartData($data, $labelsKey, $dataKeys, $truncateLabels = false, $maxLength = 8) {
    $labels = [];
    $datasets = [];
    foreach ($dataKeys as $key) {
        $datasets[$key] = [];
    }

    foreach ($data as $row) {
        // Truncate labels if required
        $label = $row[$labelsKey];
        if ($truncateLabels && strlen($label) > $maxLength) {
            $label = substr($label, 0, $maxLength) . "...";
        }
        $labels[] = $label;

        foreach ($dataKeys as $key) {
            $datasets[$key][] = $row[$key];
        }
    }

    return [$labels, $datasets];
}

// check the user wheather paginated
function hasUserPaid($user_id, $auction_id, $highest_bid) {
    global $pdo; // Assuming you are using PDO

    $query = "SELECT * FROM trans WHERE transUserId = :user_id AND transAuctionId = :auction_id AND transAmount = :highest_bid";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':auction_id', $auction_id, PDO::PARAM_INT);
    $stmt->bindParam(':highest_bid', $highest_bid, PDO::PARAM_STR);
    $stmt->execute();

    return $stmt->rowCount() > 0; // Returns true if a matching record exists
}

// check the user wheather paginated
function getInvoiceDetails($user_id, $auction_id, $highest_bid) {
    global $pdo; // Assuming you are using PDO

    $query = "SELECT * FROM trans WHERE transUserId = :user_id AND transAuctionId = :auction_id AND transAmount = :highest_bid";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':auction_id', $auction_id, PDO::PARAM_INT);
    $stmt->bindParam(':highest_bid', $highest_bid, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC); // Corrected here to fetch() with PDO::FETCH_ASSOC
    return $result; // Returns the matching record or false if not found
}

// get all new moment
function getAllMoment() {
    global $pdo;

    try {
        $stmt = $pdo->prepare("SELECT * FROM moments ORDER BY 1 DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC); // Fetch all results as an associative array
    } catch (PDOException $e) {
        // Handle error and return empty array or log the error
        error_log($e->getMessage()); // Log the error message
        return []; // Return an empty array in case of error
    }
}

// Add a new moment
function addMoment($userId, $momentImg) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("INSERT INTO moments (momentUserId, momentImg, momentStatus) VALUES (:userId, :momentImg, 'deactivate')");
        $stmt->execute([
            ':userId' => $userId,
            ':momentImg' => htmlspecialchars(trim($momentImg)),
        ]);
        return ["success" => true, "message" => "Moment added successfully."];
    } catch (PDOException $e) {
        return ["success" => false, "message" => "Database error: " . $e->getMessage()];
    }
}

// Approve a moment
function approveMoment($momentId) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("UPDATE moments SET momentStatus = 'activate' WHERE momentId = :momentId");
        $stmt->execute([':momentId' => $momentId]);

        if ($stmt->rowCount() > 0) {
            return ["success" => true, "message" => "Moment deactivated successfully."];
        } else {
            return ["success" => false, "message" => "Failed to deactivate the moment or it is already deactivated."];
        }
    } catch (PDOException $e) {
        return ["success" => false, "message" => "Database error: " . $e->getMessage()];
    }
}

// Deactivate a moment
function deactivateMoment($momentId) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("UPDATE moments SET momentStatus = 'deactivate' WHERE momentId = :momentId");
        $stmt->execute([':momentId' => $momentId]);

        if ($stmt->rowCount() > 0) {
            return ["success" => true, "message" => "Moment deactivated successfully."];
        } else {
            return ["success" => false, "message" => "Failed to deactivate the moment or it is already deactivated."];
        }
    } catch (PDOException $e) {
        return ["success" => false, "message" => "Database error: " . $e->getMessage()];
    }
}
// Delete a moment
function deleteMoment($momentId) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("UPDATE moments SET momentStatus = 'suspend' WHERE momentId = :momentId");
        $stmt->execute([':momentId' => $momentId]);

        if ($stmt->rowCount() > 0) {
            return ["success" => true, "message" => "Moment deactivated successfully."];
        } else {
            return ["success" => false, "message" => "Failed to deactivate the moment or it is already deactivated."];
        }
    } catch (PDOException $e) {
        return ["success" => false, "message" => "Database error: " . $e->getMessage()];
    }
}

?>