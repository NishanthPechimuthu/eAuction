<?php
ob_start();
include 'db.php';

if (!function_exists('isAuthenticated')) {
    function isAuthenticated() {
        // Check if user is logged in
        if (!isset($_SESSION['userId'])) {
            header("Location: ../public/login.php");
            exit();
        }

        // Fetch user details and update session
        $user = getUserById($_SESSION['userId']);
        $_SESSION['userId'] = $user['userId'];
        $_SESSION['userName'] = $user['userName'];
        $_SESSION['userRole'] = $user['userRole'];
        $_SESSION['userEmail'] = $user['userEmail'];
        $_SESSION['userProfileImg'] = $user['userProfileImg'];

        // Get the current page filename
        $currentPage = basename($_SERVER['PHP_SELF']);

        // Skip subscription check if on pay.php
        if ($currentPage === 'pay.php') {
            return true; // Allow pay.php to load even if subscription is unpaid
        }

        // Check subscription status for other pages
        $user_id = $_SESSION['userId'];
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

        // Check if user has a completed subscription for this month
        $subscriptionPaid = false;
        foreach ($subscriptions as $subscription) {
            if ($subscription['user_id'] === $user_id && 
                strpos($subscription['timestamp'], $currentMonthYear) === 0 && 
                $subscription['status'] === 'completed') {
                $subscriptionPaid = true;
                break;
            }
        }

        // If no subscription paid for this month, redirect to pay.php
        if (!$subscriptionPaid) {
            header("Location: pay.php");
            exit();
        }

        return true; // User is authenticated and has paid subscription
    }
}

    function isAuthenticatedAsAdmin() {
        if (isset($_SESSION['userId'])) {
          $user=getUserById($_SESSION['userId']);
          $_SESSION['userId'] = $user['userId'];
          $_SESSION['userName'] = $user['userName'];
          $_SESSION['userRole'] = $user['userRole'];
          $_SESSION['userProfileImg'] = $user['userProfileImg'];
          if($user['userRole']=="admin"){
          return true;
          }else{
            header("Location: ../public/auctions.php");
            exit();
          }
        } else {
            header("Location: ../public/login.php");
            exit();
        }
    }

// Login function
function login($username, $password) {
    global $pdo;
    $status = "suspend";
    $stmt = $pdo->prepare("SELECT * FROM users WHERE userName = :username AND userStatus <> :status");
    $stmt->execute(['username' => $username,'status' => $status]);
    $user = $stmt->fetch();

    // Log user data for debugging
    error_log("User Data: " . print_r($user, true));
      if ($user && password_verify($password, $user['userPassword'])) {
          // Set session and cookies for persistence
    if ($user["userStatus"] == 'activate') {
          $_SESSION['userId'] = $user['userId'];
          $_SESSION['userName'] = $user['userName'];
          $_SESSION['userRole'] = $user['userRole'];
          $_SESSION['userEmail'] = $user['userEmail'];
          $_SESSION['userProfileImg'] = $user['userProfileImg'];
          return true;
    } else {
      echo '
        <p class="alert alert-warning alert-dismissible fade show d-flex align-items-center" 
           role="alert" data-bs-dismiss="alert" 
           aria-label="Close" 
           style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
           your account not activate</p>
      ';
    }
      }else {
        echo '
        <p class="alert alert-warning alert-dismissible fade show d-flex align-items-center"
           role="alert"  data-bs-dismiss="alert"
                  aria-label="Close"
           style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
          Invalid credentials.
        </p>
    ';
      }
    return false;
}

// Admin Login function
function AdminLogin($username, $password) {
    global $pdo;

    if (empty($username) || empty($password)) {
        return false; // Or handle as needed
    }

    $role = "admin";
    try {
        $stmt = $pdo->prepare("SELECT userId, userName, userRole, userPassword FROM users WHERE userName = :username AND userRole = :role");
        $stmt->execute(['username' => $username, 'role' => $role]);
        $user = $stmt->fetch();

        // Verify password
        if ($user && password_verify($password, $user['userPassword'])) {
            $_SESSION['userId'] = $user['userId'];
            $_SESSION['userName'] = $user['userName'];
            $_SESSION['userRole'] = $user['userRole'];
            return true;
        }
} catch (PDOException $e) {
    // Log error or handle accordingly
    echo '
      <p class="alert alert-warning alert-dismissible fade show d-flex align-items-center" 
         role="alert" data-bs-dismiss="alert" 
         aria-label="Close" 
         style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
        ' . 'Database error: ' . $e->getMessage() . '
      </p>
    ';
}
    
    return false;
}

// Register function
function register($username, $email, $password) {
    global $pdo;

    try {
        // Check if the username already exists
        $status="suspend";
        $stmt = $pdo->prepare("SELECT * FROM users WHERE userName = :username");
        $stmt->execute(['username' => $username]);

        if ($stmt->rowCount() > 0) {
            echo '
                <p class="alert alert-warning alert-dismissible fade show d-flex align-items-center" 
                   role="alert" data-bs-dismiss="alert" 
                   aria-label="Close" 
                   style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
                   User with that username already exists.
                </p>
            ';
            return false; // Exit function early if username already exists
        }

        // Check if the email already exists
        $status="suspend";
        $stmt = $pdo->prepare("SELECT * FROM users WHERE userEmail = :email AND userStatus <> :status");
        $stmt->execute(['email' => $email,'status' => $status]);

        if ($stmt->rowCount() > 0) {
            echo '
                <p class="alert alert-warning alert-dismissible fade show d-flex align-items-center" 
                   role="alert" data-bs-dismiss="alert" 
                   aria-label="Close" 
                   style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
                   User with that email already exists.
                </p>
            ';
            return false; // Exit function early if email already exists
        }

        // Hash the password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // Insert the new user
        $stmt = $pdo->prepare("INSERT INTO users (userName, userEmail, userPassword) VALUES (:username, :email, :password)");

        // Execute the insert query
        if ($stmt->execute(['username' => $username, 'email' => $email, 'password' => $hashedPassword])) {
            return true; // Registration successful
        } else {
            throw new Exception("An error occurred while inserting the data.");
        }

    } catch (PDOException $e) {
        // Handle PDOException (database issues)
        echo '
            <p class="alert alert-danger alert-dismissible fade show d-flex align-items-center" 
               role="alert" data-bs-dismiss="alert" 
               aria-label="Close" 
               style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
               Database error: ' . $e->getMessage() . '
            </p>
        ';
    } catch (Exception $e) {
        // Handle other exceptions (e.g., errors during the execution)
        echo '
            <p class="alert alert-danger alert-dismissible fade show d-flex align-items-center" 
               role="alert" data-bs-dismiss="alert" 
               aria-label="Close" 
               style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
               Error: ' . $e->getMessage() . '
            </p>
        ';
    }
}

// Logout function
    function logout() {
        // Clear session and cookies
        session_unset();
        session_destroy();
        
        header("Location: index.php");
        exit();
    }

function deleteUser($user, $email){
    global $pdo;
    $status = "suspend";
    $expemail = "EXP:" . $email;

    try {
        $stmt = $pdo->prepare("UPDATE users SET userStatus = :status, userEmail = :expemail WHERE userId = :user");
        $stmt->execute(['user' => $user, 'status' => $status, 'expemail' => $expemail]);
    } catch (PDOException $e) {
        // Handle the error (could log it or show a message depending on your needs)
        echo "Error: " . $e->getMessage();
    }
}

function activateUser($user){
    global $pdo;
    $status = "activate";

    try {
        $stmt = $pdo->prepare("UPDATE users SET userStatus = :status WHERE userId = :user");
        $stmt->execute(['user' => $user, 'status' => $status]);
        return true;
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}

function suspendUser($user){
    global $pdo;
    $status = "deactivate";

    try {
        $stmt = $pdo->prepare("UPDATE users SET userStatus = :status WHERE userId = :user");
        $stmt->execute(['user' => $user, 'status' => $status]);
        return true;
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}
    
    
?>