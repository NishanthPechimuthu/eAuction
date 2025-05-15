<?php
ob_start(); // Start output buffering

include("header.php");
include("navbar.php");

// Call the authentication function
isAuthenticatedAsAdmin();

// Retrieve reviews from the database
$reviews = getAllReviews(); // Assume `getAllReviews()` retrieves all reviews from the database

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve'])) {
        approveReview($_POST['review_id']);
        header("Location: manage-review.php");
        exit();
    }
    if (isset($_POST['deactivate'])) {
        deactivateReview($_POST['review_id']);
        header("Location: manage-review.php");
        exit();
    }
    if (isset($_POST['suspend'])) {
        deleteReview($_POST['review_id']);
        header("Location: manage-review.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Reviews</title>
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
    <div class="container py-5">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-comments me-1"></i> Manage Reviews
            </div>
            <div class="card-body">
                <table id="reviewsTable">
                    <thead>
                        <tr>
                            <th>S/No</th>
                            <th>User Name</th>
                            <th>Message</th>
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
                            <th>Message</th>
                            <th>Status</th>
                            <th>Approve</th>
                            <th>Deactivate</th>
                            <th>Suspend</th>
                        </tr>
                    </tfoot>
                    <tbody>
                        <?php $counter = 1; ?>
                        <?php foreach ($reviews as $review): ?>
                            <tr>
                                <td><?= $counter++ ?></td>
                                <td><?= htmlspecialchars(getUserName($review['reviewUserId'])) ?></td>
                                <td><?= htmlspecialchars($review['reviewMessage']) ?></td>
                                <td>
                    <p class="badge rounded-pill 
                        <?= $review['reviewStatus'] === 'activate' ? 'bg-success text-white' : 
                            ($review['reviewStatus'] === 'deactivate' ? 'bg-warning text-dark' : 
                            ($review['reviewStatus'] === 'suspend' ? 'bg-danger text-white' : 
                            ($review['reviewStatus'] === 'leader' ? 'bg-primary text-white' : 'bg-secondary text-white'))) ?> m-0">
                        <?= htmlspecialchars($review['reviewStatus']) ?>
                    </p>
                                </td>
<td>
    <form method="POST" class="d-inline">
        <input type="hidden" name="review_id" value="<?= htmlspecialchars($review['reviewId']) ?>">
        <button type="submit" 
                <?php if ($review['reviewStatus'] == "activate") { echo "disabled"; } ?> 
                name="approve" 
                class="btn btn-success btn-sm fw-bold text-white" 
                onclick="return confirm('Are you sure you want to approve this review?')">Approve</button>
    </form>
</td>
<td>
    <form method="POST" class="d-inline">
        <input type="hidden" name="review_id" value="<?= htmlspecialchars($review['reviewId']) ?>">
        <button type="submit" 
                <?php if ($review['reviewStatus'] == "deactivate") { echo "disabled"; } ?> 
                name="deactivate" 
                class="btn btn-warning btn-sm fw-bold text-dark" 
                onclick="return confirm('Are you sure you want to deactivate this review?')">Deactivate</button>
    </form>
</td>
<td>
    <form method="POST" class="d-inline">
        <input type="hidden" name="review_id" value="<?= htmlspecialchars($review['reviewId']) ?>">
        <button type="submit" 
                <?php if ($review['reviewStatus'] == "suspend") { echo "disabled"; } ?> 
                name="suspend" 
                class="btn btn-danger btn-sm fw-bold text-white" 
                onclick="return confirm('Are you sure you want to suspend this review?')">Suspend</button>
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
        const datatablesSimple = document.getElementById('reviewsTable');
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