<?php
session_start(); // Start the session at the very top
ob_start(); // Start output buffering

include "header.php";
include "navbar.php";
isAuthenticatedAsAdmin();
$users = getAllUsers();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if (isset($_POST["delete"])) {
    deleteUser($_POST["userId"], $_POST["userEmail"]);
    header("Location: manage-user.php");
    exit(); // Ensure exit after header redirect
  }

  if (isset($_POST["suspend"])) {
    if (suspendUser($_POST["userId"])) {
      header("Location: manage-user.php");
      exit(); // Ensure exit after header redirect
    } else {
      echo '<p class="alert alert-danger alert-dismissible fade show d-flex align-items-center" 
                   role="alert" data-bs-dismiss="alert" aria-label="Close" 
                   style="white-space: nowrap; max-width: 100%; overflow-y: auto;">
                   Error: User not suspended
                  </p>';
    }
  }
}

ob_end_flush();

// End buffering and flush output
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Users</title>
    <?php include_once "../assets/link.html"; ?>
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
    <h1 class="mt-4">Manage Users</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Manage Users</li>
    </ol>
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-user  me-1"></i>
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
                        <th>Account No</th>
                        <th>View</th>
                        <th>Suspend</th>
                        <th>Delete</th>
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
                        <th>Account No</th>
                        <th>View</th>
                        <th>Suspend</th>
                        <th>Delete</th>
                    </tr>
                </tfoot>
                <tbody>
                    <?php
                    $counter = 1;
                    foreach ($users as $user) {
                      echo "<tr>
                                <td>{$counter}</td>
                                <td><a href='view-profile.php?id=" .
                        base64_encode($user["userId"]) .
                        "' target='_blank' class='text-dark'>{$user["userName"]}</a></td>
                                <td>
<a href='view-profile.php?id=" .
                        base64_encode($user["userId"]) .
                        "' target='_blank' class='text-dark'> <img src='../images/profiles/" .
                        htmlspecialchars($user["userProfileImg"]) .
                        "' 
                                           alt='User Profile' class='rounded-1 border border-dark' 
                                           width='50' height='50'>
                      </a>
                                </td>
                                <td>" .
                        ($user["userFirstName"] ?? "NULL") .
                        "</td>
                                <td>" .
                        ($user["userLastName"] ?? "NULL") .
                        "</td>
                                <td>" .
                        ("<a href='tel:" .
                          $user["userPhone"] .
                          "' class='text-dark text-decoration-none' >" .
                          $user["userPhone"] ??
                          "NULL" . "</a>") .
                        "</td>
                                <td><a href='mailto:" .
                        $user["userEmail"] .
                        "' class='text-dark text-decoration-none' >" .
                        $user["userEmail"] .
                        "</a></td>
                                <td>" .
                        ($user["userAddress"] ?? "NULL") .
                        "</td>
                                <td>" .
                        ($user["userAccountNo"] ?? "NULL") .
                        "</td>
                                <td>
                                    <a class='btn btn-primary fw-bold' href='./view-profile.php?id=" .
                        base64_encode($user["userId"]) .
                        "'>View</a>
                                </td>
                                <td>
                                    <form method='POST'>
                                        <input type='hidden' value='{$user["userId"]}' name='userId'/>
                                        <input class='btn btn-warning fw-bold' type='submit' value='Suspend' name='suspend'/>
                                    </form>
                                </td>
                                <td>
                                    <form method='POST'>
                                        <input type='hidden' value='{$user["userId"]}' name='userId'/>
                                        <input type='hidden' value='{$user["userEmail"]}' name='userEmail'/>
                                        <input class='btn btn-danger fw-bold' type='submit' value='Delete' name='delete'/>
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
    <div class="row">
      <?php include "./registration-chart.php"; ?>
      <?php include "./user-status-chart.php"; ?>
    </div>
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
?> ?> ?>