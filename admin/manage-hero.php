<?php
ob_start(); // Start output buffering

include("header.php");
include("navbar.php");

// Call the authentication function
isAuthenticatedAsAdmin();

// Retrieve heroes from the database
$heroes = getAllHeroes(); // Assume `getAllHeroes()` retrieves all heroes from the database

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['delete'])) {
    deleteHero($_POST['hero_id']); // Assume `deleteHero()` deletes a hero by ID
    header("Location: manage-hero.php");
    exit();
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Heroes</title>
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
    <div class="container mt-5">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-cogs me-1"></i> Manage Heroes
            </div>
            <div class="card-body">
                <table id="heroesTable">
                    <thead>
                        <tr>
                            <th>S/No</th>
                            <th>Title</th>
                            <th>Message</th>
                            <th>Hero Image</th>
                            <th>Status</th>
                            <th>View</th>
                            <th>Edit</th>
                            <th>Delete</th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th>S/No</th>
                            <th>Title</th>
                            <th>Message</th>
                            <th>Hero Image</th>
                            <th>Status</th>
                            <th>View</th>
                            <th>Edit</th>
                            <th>Delete</th>
                        </tr>
                    </tfoot>
                    <tbody>
                        <?php $counter = 1; ?>
                        <?php foreach ($heroes as $hero): ?>
                            <tr>
                                <td><?= $counter++ ?></td>
                                <td><?= htmlspecialchars($hero['heroTitle']) ?></td>
                                <td><?= htmlspecialchars($hero['heroMessage']) ?></td>
                                <td>
                                  <img src="../images/heroes/<?= htmlspecialchars($hero['heroImg']) ?>" alt="Hero Image" class="rounded-1 border border-dark" width="50" height="50">
                                </td>
                                <td>
                                    <p class="badge rounded-pill <?= $hero['heroStatus'] === 'activate' ? 'bg-success text-white' : ($hero['heroStatus'] === 'deactivate' ? 'bg-warning text-dark' : 'bg-danger text-white') ?> m-0">
                                        <?= htmlspecialchars($hero['heroStatus']) ?>
                                    </p>
                                </td>
                                <td>
                                    <a href="hero-content.php?id=<?= base64_encode($hero['heroId'] )?>" class="btn btn-info btn-sm fw-bold text-white align-items-center">View</a>
                                </td>
                                <td>
                                    <a href="edit-hero.php?heroId=<?= htmlspecialchars($hero['heroId']) ?>" class="btn btn-warning btn-sm fw-bold text-dark">Edit</a>
                                </td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="hero_id" value="<?= htmlspecialchars($hero['heroId']) ?>">
                                        <button type="submit" name="delete" class="btn btn-danger btn-sm fw-bold text-white" onclick="return confirm('Are you sure?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<script>
    window.addEventListener('DOMContentLoaded', event => {
        const datatablesSimple = document.getElementById('heroesTable');
        if (datatablesSimple) {
            new simpleDatatables.DataTable(datatablesSimple);
        }
    });
</script>
</body>
</html>

<?php
include_once("./footer.php");
ob_end_flush();
?>