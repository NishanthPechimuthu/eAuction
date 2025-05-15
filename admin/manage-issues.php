<?php
ob_start();
session_start();

include("header.php");
include("navbar.php");

// Call the authentication function
isAuthenticatedAsAdmin();

$issuesFilePath = realpath(__DIR__ . "/../public/InfoData/issues.json");
if (!$issuesFilePath) {
    die("Issues file not found.");
}

function getAllIssues($filePath) {
    $json = file_get_contents($filePath);
    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $data = [];
    }
    return $data;
}

$issues = getAllIssues($issuesFilePath);

$pending_issues = array_filter($issues, function($issue) {
    return strtolower($issue['issueStatus']) === 'pending';
});
$processed_issues = array_filter($issues, function($issue) {
    return strtolower($issue['issueStatus']) !== 'pending';
});

usort($pending_issues, function($a, $b) {
    return strtotime($a['createdAt'] ?? '1970-01-01') - strtotime($b['createdAt'] ?? '1970-01-01');
});
usort($processed_issues, function($a, $b) {
    return strtotime($b['createdAt'] ?? '1970-01-01') - strtotime($a['createdAt'] ?? '1970-01-01');
});

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve']) || isset($_POST['unapprove'])) {
        $issueId = $_POST['issue_id'];
        $newStatus = isset($_POST['approve']) ? 'approved' : 'pending';
        foreach ($issues as &$issue) {
            if ($issue['issueId'] == $issueId) {
                $issue['issueStatus'] = $newStatus;
                break;
            }
        }
        unset($issue);
        file_put_contents($issuesFilePath, json_encode($issues, JSON_PRETTY_PRINT));
        header("Location: manage-issues.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Issues</title>
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
        td img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            cursor: pointer;
        }
        .modal-img {
            width: 100%;
            height: auto;
        }
    </style>
</head>
<body>
<div class="container py-4">
      <h1 class="mt-4">Manage Issues</h1>
      <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item">
          <a href="dashboard.php">Dashboard</a>
        </li>
        <li class="breadcrumb-item active">Manage Issues</li>
      </ol>
    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <img src="" id="modalImage" class="modal-img" alt="Issue Image" />
            </div>
        </div>
    </div>

    <!-- Pending Issues -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-hourglass-start me-2"></i> Pending Issues (FIFO)
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="pendingIssuesTable" class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>S/No</th>
                            <th>User ID</th>
                            <th>Type</th>
                            <th>Message</th>
                            <th>Image</th>
                            <th>Timestamp</th>
                            <th>Approve</th>
                            <th>Unapprove</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $count = 1; foreach ($pending_issues as $issue): ?>
                            <tr>
                                <td><?= $count++ ?></td>
                                <td><?= htmlspecialchars($issue['userId']) ?></td>
                                <td><?= htmlspecialchars($issue['issueType']) ?></td>
                                <td><?= htmlspecialchars($issue['issueMessage']) ?></td>
                                <td>
                                    <?php if (!empty($issue['issueImage'])): ?>
                                        <img src="<?= htmlspecialchars($issue['issueImage']) ?>" alt="Issue" onclick="zoomImage(this.src)" />
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($issue['createdAt'] ?? 'N/A') ?></td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="issue_id" value="<?= $issue['issueId'] ?>">
                                        <button name="approve" class="btn btn-success btn-sm" onclick="return confirm('Approve this issue?')">Approve</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="issue_id" value="<?= $issue['issueId'] ?>">
                                        <button name="unapprove" class="btn btn-warning btn-sm" onclick="return confirm('Unapprove this issue?')">Unapprove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; if (empty($pending_issues)): ?>
                            <tr><td colspan="8">No pending issues.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Processed Issues -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-secondary text-white">
            <i class="fas fa-history me-2"></i> Processed Issues (LIFO)
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="processedIssuesTable" class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>S/No</th>
                            <th>User ID</th>
                            <th>Type</th>
                            <th>Message</th>
                            <th>Image</th>
                            <th>Timestamp</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $count = 1; foreach ($processed_issues as $issue): ?>
                            <tr>
                                <td><?= $count++ ?></td>
                                <td><?= htmlspecialchars($issue['userId']) ?></td>
                                <td><?= htmlspecialchars($issue['issueType']) ?></td>
                                <td><?= htmlspecialchars($issue['issueMessage']) ?></td>
                                <td>
                                    <?php if (!empty($issue['issueImage'])): ?>
                                        <img src="<?= htmlspecialchars($issue['issueImage']) ?>" alt="Issue" onclick="zoomImage(this.src)" />
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($issue['createdAt'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge <?= strtolower($issue['issueStatus']) === 'approved' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                        <?= htmlspecialchars($issue['issueStatus']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; if (empty($processed_issues)): ?>
                            <tr><td colspan="7">No processed issues.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    function zoomImage(src) {
        const modal = new bootstrap.Modal(document.getElementById('imageModal'));
        document.getElementById('modalImage').src = src;
        modal.show();
    }

    document.addEventListener('DOMContentLoaded', () => {
        new simpleDatatables.DataTable(document.getElementById('pendingIssuesTable'));
        new simpleDatatables.DataTable(document.getElementById('processedIssuesTable'));
    });
</script>
</body>
</html>

<?php include_once("footer.php"); ob_end_flush(); ?>