<?php
ob_start(); // Start output buffering

include("header.php");
include("navbar.php");

// Call the authentication function
isAuthenticatedAsAdmin();

// Retrieve moments from the database
$moments = getAllMoment(); // Assume `getAllMoment()` retrieves all moments from the database

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve'])) {
        approveMoment($_POST['moment_id']);
        header("Location: manage-moment.php");
        exit();
    }
    if (isset($_POST['deactivate'])) {
        deactivateMoment($_POST['moment_id']);
        header("Location: manage-moment.php");
        exit();
    }
    if (isset($_POST['suspend'])) {
        deleteMoment($_POST['moment_id']);
        header("Location: manage-moment.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Moments</title>
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
        td img {
            width: 100px;
            height: 100px;
            object-fit: cover;
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
                width: 50px;
                height: 50px;
            }
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-images me-1"></i> Manage Moments
            </div>
            <div class="card-body">
                <table id="momentsTable">
                    <thead>
                        <tr>
                            <th>S/No</th>
                            <th>User Name</th>
                            <th>Image</th>
                            <th>Status</th>
                            <th>Approve</th>
                            <th>Deactivate</th>
                            <th>Suspend</th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th>S/No</th>
                            <th>User Name</th>
                            <th>Image</th>
                            <th>Status</th>
                            <th>Approve</th>
                            <th>Deactivate</th>
                            <th>Suspend</th>
                        </tr>
                    </tfoot>
                    <tbody>
                        <?php $counter = 1; ?>
                        <?php foreach ($moments as $moment): ?>
                            <tr>
                                <td><?= $counter++ ?></td>
                                <td><?= htmlspecialchars(getUserName($moment['momentUserId'])) ?></td>
                                <td><img src="../images/moments/<?= htmlspecialchars($moment['momentImg']) ?>" alt="Moment Image"></td>
                                <td>
                                    <p class="badge rounded-pill 
                                        <?= $moment['momentStatus'] === 'activate' ? 'bg-success text-white' : 
                                            ($moment['momentStatus'] === 'deactivate' ? 'bg-warning text-dark' : 
                                            'bg-danger text-white') ?> m-0">
                                        <?= htmlspecialchars($moment['momentStatus']) ?>
                                    </p>
                                </td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="moment_id" value="<?= htmlspecialchars($moment['momentId']) ?>">
                                        <button type="submit" 
                                                <?php if ($moment['momentStatus'] == "activate") { echo "disabled"; } ?> 
                                                name="approve" 
                                                class="btn btn-success btn-sm fw-bold text-white" 
                                                onclick="return confirm('Are you sure you want to approve this moment?')">Approve</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="moment_id" value="<?= htmlspecialchars($moment['momentId']) ?>">
                                        <button type="submit" 
                                                <?php if ($moment['momentStatus'] == "deactivate") { echo "disabled"; } ?> 
                                                name="deactivate" 
                                                class="btn btn-warning btn-sm fw-bold text-dark" 
                                                onclick="return confirm('Are you sure you want to deactivate this moment?')">Deactivate</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="moment_id" value="<?= htmlspecialchars($moment['momentId']) ?>">
                                        <button type="submit" 
                                                <?php if ($moment['momentStatus'] == "suspend") { echo "disabled"; } ?> 
                                                name="suspend" 
                                                class="btn btn-danger btn-sm fw-bold text-white" 
                                                onclick="return confirm('Are you sure you want to suspend this moment?')">Suspend</button>
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
        const datatablesSimple = document.getElementById('momentsTable');
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