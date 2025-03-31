<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php include_once("../assets/link.html"); ?>
  <link rel="icon" type="image/x-icon" href="../images/logo/favicon.ico"> 
</head>
<body class="bg-light">

  <!-- Top Navbar -->
  <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm px-2 py-1 mb-0">
    <a class="navbar-brand" href="../public/auctions.php">
      <img width="54px" height="54px" src="../images/logo/android-chrome-192x192.png" alt="Logo">
    </a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav mr-auto">
        <li class="nav-item active">
          <a class="nav-link" href="../public/auctions.php">Home</a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            Auction
          </a>
          <div class="dropdown-menu" aria-labelledby="navbarDropdown">
            <a class="dropdown-item" href="../public/add-auction.php">Add Auctions</a>
            <a class="dropdown-item" href="../public/revisited-auctions.php">Revisited Auction</a>
            <a class="dropdown-item" href="../public/manage-auction.php">Manage Auction</a>
          </div>
        </li>
        <li class="nav-item active">
          <a class="nav-link" href="../public/add-moments.php">Add Moment</a>
        </li>
      </ul>
      <?php if (isset($_SESSION["userId"]) && $_SESSION["userId"] != null): ?>
        <ul class="navbar-nav ml-auto"> <!-- Ensure this pushes items to the right -->
          <li class="nav-item dropdown">
            <?php $userImg = isset($_SESSION["userId"]) ? getUserImg($_SESSION["userId"]) : ''; ?>
            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="profileDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              <img src="../images/profiles/<?= $userImg ?>" alt="Profile" class="rounded-circle border border-dark" width="30" height="30">
              <span class="text-secondary px-1"><?= $_SESSION["userName"] ?? 'Guest' ?></span>
            </a>
            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="profileDropdown">
              <div class="d-flex align-items-center px-3 py-2">
                <div>
                  <p class="mb-0 font-weight-bold text-dark">
                    <?= $_SESSION["userName"] ?? "Guest" ?>
                  </p>
                  <p class="mb-0 text-muted">
                    <?= $_SESSION["userEmail"] ?? "guest@gmail.com" ?>
                  </p>
                </div>
              </div>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item" href="profile.php">Profile</a>
              <a class="dropdown-item" href="settings.php">Settings</a>
              <div class="dropdown-divider"></div>
              <form action="logout.php" method="post">
                <button class="dropdown-item" name="logout">Logout</button>
              </form>
            </div>
          </li>
        </ul>
      <?php endif; ?>
    </div>
  </nav>

  <!-- Include Bootstrap JS and dependencies -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>
</body>
</html>