<?php
session_start(); // Start the session at the very top
ob_start(); // Start output buffering

include("header.php");
include("navbar.php");
// isAuthenticated();
isAuthenticatedAsAdmin();
$bids = getAllBid();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete'])) {
        deleteUser($_POST['userId'],$_POST['userEmail']);
        header("Location: manage-user.php");
        exit(); // Ensure exit after header redirect
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Manage Bids</title>
    <?php include_once("../assets/link.html"); ?>
    <link href="../assets/styles.css" rel="stylesheet" />
    <style>
    /* Custom Scrollbar Styling */
::-webkit-scrollbar {
    width: 10px;
    height: 4px;
}

/* Track */
::-webkit-scrollbar-track {
    background: #f0f0f0; 
    border-radius: 10px;
}

/* Handle */
::-webkit-scrollbar-thumb {
    background: #ADFF2F;
    border-radius: 10px;
}

/* Handle on hover */
::-webkit-scrollbar-thumb:hover {
    background: #feff2f;
}
        td {
            height: 50px;
            line-height: 50px;
        }
        td, th {
            min-width: 100px;
            max-width: 140px;
            text-align: center;
            vertical-align: middle;
            white-space: nowrap;
            overflow: auto;
            padding: 10px;
        }
        @media (max-width: 768px) {
            td{
                height: 40px;
                line-height: 40px;
            }
            th, td {
                font-size: 12px;
                padding: 5px;
            }
            td img {
                width: 40px;
                height: 40px;
            }
        }
    </style>
</head>
<body>
  <div class="container">
    <h1 class="mt-4">Manage Bids</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Manage Bids</li>
    </ol>
    <div class="card mb-4">
      <div class="card-header">
        <i class="fas fa-line-chart me-1"></i>
        Bids Table
      </div>
      <div class="card-body">
        <table id="bidsTable">
          <thead>
            <tr>
              <th>S/No</th>
              <th>Auction Id</th>
              <th>User Name</th>
              <th>Bid Amount</th>
            </tr>
          </thead>
          <tfoot>
            <tr>
              <th>S/No</th>
              <th>Auction Id</th>
              <th>User Name</th>
              <th>Bid Amount</th>
            </tr>
          </tfoot>
          <tbody>
            <?php
              $counter=1;
            foreach ($bids as $bid) {
              echo "<tr>
                      <td>". $counter++ ."</td>
                      <td>{$bid['bidAuctionId']}</td>
                      <td><a class='text-secondary fw-bold' href='view-profile.php?id=".base64_encode($bid['bidUserId']) ."' target='_blank'>".htmlspecialchars(getUserName($bid['bidUserId'] ))."</a></td>
                      <td>".($bid['bidAmount'] ?? 'NULL')."</td>
                    </tr>";
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php include("./bids-chart.php"); ?>
  </div>
    <script>
        window.addEventListener('DOMContentLoaded', event => {
            const datatablesSimple = document.getElementById('bidsTable');
            if (datatablesSimple) {
                new simpleDatatables.DataTable(datatablesSimple);
            }
        });
    </script>
</body>
</html> 
<?
  include("./footer.php");
  ob_end_flush(); // End buffering and flush output
?>