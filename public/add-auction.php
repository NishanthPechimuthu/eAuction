<?php
ob_start(); // Start output buffering
session_start(); // Start the session
include("header.php");
include("navbar.php");

$categories = getCategories();
// Call the authentication function
isAuthenticated();
$AccountNo = getUserAccountNo($_SESSION["userId"]);
if ($AccountNo === NULL) {
  header("Location: update-profile.php");
  exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Sanitize user inputs
  $title = htmlspecialchars(trim($_POST['title']));
  $product_type=$_POST["product_type"];
  $product_quantity=$_POST["product_quantity"];
  $product_unit=$_POST["product_unit"];
  $start_price = filter_var($_POST['start_price'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
  $start_time = $_POST['start_time'];
  $end_date = $_POST['end_date'];
  $address = $_POST['address'];
  $category_id = $_POST['category']; // Use category ID, not category name
  $description = $_POST['description'];

  // Handle the cropped image data
  if (!empty($_POST['cropped_image'])) {
    $croppedImageData = $_POST['cropped_image'];
    $uploadDir = '../images/products/';

    // Ensure the directory exists
    if (!is_dir($uploadDir)) {
      mkdir($uploadDir, 0777, true);
    }

    // Generate a unique name for the image
    $uniqueName = 'prod_' . uniqid() . '.webp';
    $targetFile = $uploadDir . $uniqueName;

    // Decode base64 and save the image
    list(, $croppedImageData) = explode(',', $croppedImageData);
    $croppedImageData = base64_decode($croppedImageData);

    if (file_put_contents($targetFile, $croppedImageData)) {
      // If save successful, call function to add auction
      $user_id = $_SESSION["userId"];
      $result = addAuction($title, $start_price, $start_time, $end_date, $category_id, $address, $description, $uniqueName, $user_id, $product_type, $product_quantity, $product_unit);

      if (strpos($result, "Auction added successfully") !== false) {
        header("Location: manage-auction.php");
        exit();
      } else {
        echo '
            <p class="alert alert-danger alert-dismissible fade show d-flex align-items-center"
               role="alert" data-bs-dismiss="alert"
               aria-label="Close"
               style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
               Error: '.$result.'
              </p>
        ';
      }
    } else {
      echo '
            <p class="alert alert-danger alert-dismissible fade show d-flex align-items-center"
               role="alert" data-bs-dismiss="alert"
               aria-label="Close"
               style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
               Error: Failed to save the cropped image.
              </p>
        ';
    }
  } else {
    echo '
            <p class="alert alert-danger alert-dismissible fade show d-flex align-items-center"
               role="alert" data-bs-dismiss="alert"
               aria-label="Close"
               style="white-space:nowrap; max-width: 100%; overflow-y: auto;">
                               Error: No cropped image data received.
              </p>
        ';
  }
}

ini_set('display_errors', 1);
error_reporting(E_ALL);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Auction</title>
  <?php include_once("../assets/link.html"); ?>
</head>
<body>
  <div class="container py-5">
    <div class="card mb-4">
      <div class="card-header">
        <i class="fa fa-gavel"></i>&nbsp;
        Add Auction
      </div>
      <div class="card-body">
        <form id="auctionForm" action="add-auction.php" method="POST" enctype="multipart/form-data">
          <div class="mb-3">
            <label for="title" class="form-label">Title</label>
            <input type="text" id="title" name="title" required class="form-control">
          </div>
          <div class="mb-3">
            <label for="category" class="form-label">Category</label>
            <div class="dropdown">
              <button
                class="btn btn-outline-secondary dropdown-toggle w-100 text-start"
                type="button"
                id="categoryDropdown"
                data-bs-toggle="dropdown"
                aria-expanded="false">
                Select a Category
              </button>
              <?php if (isset($categories) && is_array($categories) && count($categories) > 0): ?>
              <ul class="dropdown-menu w-100" aria-labelledby="categoryDropdown">
                <?php foreach ($categories as $category): ?>
                <li>
                  <a class="dropdown-item d-flex align-items-center" href="#"
                    data-value="<?= htmlspecialchars($category['categoryName']) ?>"
                    data-id="<?= htmlspecialchars($category['categoryId']) ?>">
                    <?= htmlspecialchars($category['categoryName']) ?>
                  </a>
                </li>
                <?php endforeach; ?>
              </ul>
              <?php else : ?>
              <p>
                No categories available.
              </p>
              <?php endif; ?>
              <input type="hidden" name="category" id="selectedCategory" required>
            </div>
          </div>
          <div class="mb-3">
            <label for="product_type" class="form-label">Product Type</label>
          <select id="product_type" name="product_type" class="form-control" required>
            <option value="" disabled selected>Select</option>
            <option value="organic">ORGANIC</option>
            <option value="hybrid">HYBRID</option>
          </select>
          </div>
          <div class="mb-3">
            <label for="product_quantity" class="form-label">Quantity</label>
            <input type="number" id="product_quantity" name="product_quantity"  class="form-control">
          </div>
          <div class="mb-3">
            <label for="product_unit" class="form-label">Quantity Type</label>
            <select id="product_unit" name="product_unit" class="form-control" required>
            <option value="" disabled selected>Select</option>
              <option value="kg">Kg</option>
              <option value="ton">Ton</option>
              <option value="nos">Nos</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="start_price" class="form-label">Starting Price</label>
            <input type="number" id="start_price" name="start_price" required class="form-control">
          </div>
          <div class="mb-3">
            <label for="start_time" class="form-label">Start Time</label>
            <input type="datetime-local" id="start_time" name="start_time" required class="form-control">
          </div>
          <div class="mb-3">
            <label for="end_date" class="form-label">End Date</label>
            <input type="datetime-local" id="end_date" name="end_date" required class="form-control">
          </div>
          <div class="mb-3">
            <label for="address" class="form-label">Address</label>
            <input type="text" id="address" name="address" required class="form-control">
          </div>
          <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea id="description" name="description" required class="form-control"></textarea>
          </div>
          <div class="mb-3">
            <label for="productImage" class="form-label">Product Image</label>
            <input type="file" id="productImage" name="productImage" accept="image/jpeg, image/png, image/webp" required class="form-control">
            <input type="hidden" name="cropped_image" id="croppedImage">
          </div>

          <!-- Preview of cropped image -->
          <div class="mb-3">
            <label for="imagePreview" class="form-label">Image Preview</label>
            <img id="imagePreview" class="img-fluid rounded-1 border border-2 border-dark" style="max-width: 100%; height: auto; display: none;">
          </div>

          <!-- Cropper Modal -->
          <div id="cropperModal" class="modal fade" tabindex="-1" aria-labelledby="cropperModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
              <div class="modal-content">
                <div class="modal-body">
                  <img id="cropperImage" class="img-fluid rounded-1 border border-dark">
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                  <button type="button" id="cropButton" class="btn btn-primary">Crop</button>
                </div>
              </div>
            </div>
          </div>
          <div class="d-flex justify-content-between">
            <input type="submit" class="btn btn-primary" value="Add Product">
            <input type="reset" class="btn btn-secondary" value="Clear">
          </div>
        </form>
      </div>
    </div>
  </div>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const categoryDropdownButton = document.getElementById('categoryDropdown');
      const categoryItems = document.querySelectorAll('.dropdown-item');
      const selectedCategoryInput = document.getElementById('selectedCategory');
      const auctionForm = document.getElementById('auctionForm');
      const productImageInput = document.getElementById('productImage');
      const cropperModal = document.getElementById('cropperModal');
      const cropperImage = document.getElementById('cropperImage');
      const cropButton = document.getElementById('cropButton');
      const croppedImageInput = document.getElementById('croppedImage');
      const imagePreview = document.getElementById('imagePreview'); // Image preview element

      let cropper;
      let modal;

      // Category selection
      categoryItems.forEach(item => {
        item.addEventListener('click', function() {
          const selectedCategory = this.getAttribute('data-value');
          categoryDropdownButton.textContent = selectedCategory;
          selectedCategoryInput.value = this.getAttribute('data-id');
        });
      });

      // Form submission validation
      auctionForm.addEventListener('submit', function (event) {
        if (!selectedCategoryInput.value) {
          alert('Please select a category.');
          event.preventDefault();
        }
      });

      // Product image input change
      productImageInput.addEventListener('change',
        function () {
          const reader = new FileReader();
          reader.onload = function (e) {
            cropperImage.src = e.target.result;
            if (cropper) {
              cropper.destroy();
            }
            cropper = new Cropper(cropperImage, {
              aspectRatio: 1,
              viewMode: 2,
              responsive: true,
              scalable: true,
              rotatable: true,
            });
            modal = new bootstrap.Modal(cropperModal); // Initialize modal
            modal.show(); // Show modal
          };
          reader.readAsDataURL(this.files[0]);
        });

      // Crop button action
      cropButton.addEventListener('click',
        function () {
          const canvas = cropper.getCroppedCanvas({
            width: 500,
            height: 500
          });
          croppedImageInput.value = canvas.toDataURL('image/webp'); // Save the cropped image in hidden input

          // Show the preview in the form
          imagePreview.src = canvas.toDataURL('image/webp');
          imagePreview.style.display = 'block'; // Make the preview visible

          modal.hide(); // Close the modal
          cropper.destroy();
          cropper = null;
        });
    });
  </script>
</body>
</html>

<?
  include_once("./auction-chatbot.php");
  include_once("./footer.php");
  ob_end_flush();
?>