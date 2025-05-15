<?php
session_start(); // Start the session at the very top
ob_start(); // Start output buffering

include "header.php";
include "navbar.php";
isAuthenticatedAsAdmin();
$users = getInactivateUsers();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["activate"])) {
        activateUser($_POST["userId"]);
        header("Location: manage-inactivate.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Inactivate Users</title>
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
            td {
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
    <h1 class="mt-4">Manage Inactivate Users</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Manage Inactivate Users</li>
    </ol>
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-user-times me-1"></i>
            Users Table
        </div>
        <div class="card-body">
            <table id="usersTable">
                <thead>
                    <tr>
                        <th>S/No</th>
                        <th>Name</th>
                        <th>Profile</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Address</th>
                        <th>UPI ID</th>
                        <th>Activate</th>
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <th>S/No</th>
                        <th>Name</th>
                        <th>Profile</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Address</th>
                        <th>UPI ID</th>
                        <th>Activate</th>
                    </tr>
                </tfoot>
                <tbody>
                    <?php
                    $counter = 1;
                    foreach ($users as $user) {
                        echo "<tr>
                                <td>{$counter}</td>
                                <td>{$user['userName']}</td>
                                <td>
                                    <img src='../images/profiles/" . htmlspecialchars($user['userProfileImg']) . "' 
                                         alt='User Profile' class='rounded-1 border border-dark' 
                                         width='50' height='50'>
                                </td>
                                <td>" . ($user['userFirstName'] ?? 'NULL') . "</td>
                                <td>" . ($user['userLastName'] ?? 'NULL') . "</td>
                                <td>" . ($user['userPhone'] ?? 'NULL') . "</td>
                                <td>{$user['userEmail']}</td>
                                <td>" . ($user['userAddress'] ?? 'NULL') . "</td>
                                <td>" . ($user['userUpiId'] ?? 'NULL') . "</td>
                                <td>
                                    <form method='POST'>
                                        <input type='hidden' value='{$user['userId']}' name='userId'/>
                                        <input class='btn btn-success fw-bold' type='submit' value='Activate' name='activate'/>
                               </form>
                                </td>
                              </tr>";
                        $counter++;
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php include("./user-status-chart.php"); ?>
</div>

<script>
    window.addEventListener('DOMContentLoaded', event => {
        const datatablesSimple = document.getElementById('usersTable');
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