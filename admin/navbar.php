<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <?php include_once("../assets/link.html"); ?>
  <link rel="icon" type="image/x-icon" href="../images/logo/favicon.ico"> 
</head>
<body class="bg-light">
  <!-- Top Navbar -->
  <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm px-3 py-1">
    <a class="navbar-brand" href="../public/auctions.php">
      <img width="54px" height="54px" src="../images/logo/android-chrome-192x192.png" alt="Logo">
    </a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav mr-auto">
        <li class="nav-item active">
          <a class="nav-link" href="./dashboard.php">Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="./add-category.php">Add Category</a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            Manage
          </a>
          <div class="dropdown-menu" aria-labelledby="navbarDropdown">
            <a class="dropdown-item" href="./manage-user.php">Manage Users</a>
            <a class="dropdown-item" href="./manage-inactivate.php">Manage Inactive Users</a>
            <a class="dropdown-item" href="./manage-auction.php">Manage Auctions</a>
            <a class="dropdown-item" href="./manage-bid.php">Manage Bids</a>
            <a class="dropdown-item" href="./manage-refund.php">Manage Refunds</a>
            <a class="dropdown-item" href="./manage-issues.php">Manage issues</a>
          </div>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            CMS
          </a>
          <div class="dropdown-menu" aria-labelledby="navbarDropdown">
            <a class="dropdown-item" href="./add-hero.php">Add Hero</a>
            <a class="dropdown-item" href="./manage-hero.php">Manage Heroes</a>
            <a class="dropdown-item" href="./manage-moment.php">Manage Moments</a>
            <a class="dropdown-item" href="./manage-review.php">Manage Reviews</a>
          </div>
        </li>
      </ul>
      <ul class="navbar-nav ml-auto">
        <li class="nav-item dropdown">
          <?php $userImg = getUserImg($_SESSION["userId"] ?? ""); ?>
          <a class="nav-link dropdown-toggle" href="#" id="profileDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <img src="../images/profiles/<?= $userImg ?>" alt="Profile" class="rounded-circle border border-dark" width="30" height="30">
            <span class="text-secondary px-1"><?= $_SESSION["userName"] ?? "Guest" ?></span>
          </a>
          <div class="dropdown-menu dropdown-menu-right" aria-labelledby="profileDropdown">
            <div class="d-flex align-items-center">
              <div class="d-flex flex-column ml-1">
                <p class="dropdown-item text-black mb-0 fw-bold">
                  <?= $_SESSION["userName"] ?? "Guest" ?>
                </p>
                <p class="dropdown-item text-secondary mb-0">
                  <?= $_SESSION["userEmail"] ?? "guest@gmail.com" ?>
                </p>
              </div>
            </div>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="profile.php">Profile</a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="settings.php">Settings</a>
            <div class="dropdown-divider"></div>
            <form action="logout.php" method="post">
              <button class="dropdown-item" name="logout">Logout</button>
            </form>
          </div>
        </li>
      </ul>
    </div>
  </nav>
  <!-- Include Bootstrap JS and dependencies -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>
</body>
</html>