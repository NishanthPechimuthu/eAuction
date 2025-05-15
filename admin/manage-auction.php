<?php
session_start();
include("header.php");
include("navbar.php");
isAuthenticatedAsAdmin();
$auctions = getAllAuctions();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete'])) {
        deleteAuction($_POST['auction_id']);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Auctions</title>
    <?php include_once("../assets/link.html"); ?>
    <link href="../assets/css/table-styles.css" rel="stylesheet" />
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
        <h1 class="mt-4">Manage Auctions</h1>
        <ol class="breadcrumb mb-4">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Manage Auctions</li>
        </ol>
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-gavel me-1"></i> Manage Auctions
            </div>
            <div class="card-body">
                <table id="auctionsTable">
                    <thead>
                        <tr>
                            <th>S/No</th>
                            <th>Title</th>
                            <th>Profile</th>
                            <th>Base Price (₹)</th>
                            <th>High Price (₹)</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th>View</th>
                            <th>Delete</th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th>S/No</th>
                            <th>Title</th>
                            <th>Profile</th>
                            <th>Base Price (₹)</th>
                            <th>High Price (₹)</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th>View</th>
                            <th>Delete</th>
                        </tr>
                    </tfoot>
                    <tbody>
                        <?php $counter = 1; ?>
                        <?php foreach ($auctions as $auction): ?>
                            <tr>
                                <td><?= $counter++ ?></td>
                                <td><?= htmlspecialchars($auction['auctionTitle']) ?></td>
                                <td>
                                    <img src="../images/products/<?= htmlspecialchars($auction['auctionProductImg']) ?>" alt="Product Image" class="rounded-1 border border-dark" width="50" height="50">
                                </td>
                                <td><?= htmlspecialchars($auction['auctionStartPrice']) ?></td>
                                <td><?= htmlspecialchars(getHighestBid($auction['auctionId'])) ?></td>
                                <td><?= (new DateTime($auction['auctionStartDate']))->format('d/m/Y') ?></td>
                                <td><?= (new DateTime($auction['auctionEndDate']))->format('d/m/Y') ?></td>
                                <td>
                                    <p class="badge rounded-pill <?= $auction['auctionStatus'] === 'activate' ? 'bg-success text-white' : ($auction['auctionStatus'] === 'deactivate' ? 'bg-warning text-dark' : 'bg-danger text-white') ?> m-0">
                                        <?= htmlspecialchars($auction['auctionStatus']) ?>
                                    </p>
                                </td>
                                <td>
                                    <a href="bid.php?id=<?= $auction['auctionId'] ?>" class="btn btn-info btn-sm fw-bold text-white align-items-center">View</a>
                                </td>
                                <td>
                  <form method="POST" class="d-inline">
                                        <input type="hidden" name="auction_id" value="<?= htmlspecialchars($auction['auctionId']) ?>">
                                        <button type="submit" name="delete" class="btn btn-danger btn-sm fw-bold text-white" onclick="return confirm('Are you sure?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="row">
          <?php include("./auction-chart.php"); ?>
        </div>
    </div>

    <script>
        window.addEventListener('DOMContentLoaded', event => {
            const datatablesSimple = document.getElementById('auctionsTable');
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