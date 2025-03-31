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
  $product_type = $_POST["product_type"];
  $product_quantity = $_POST["product_quantity"];
  $product_unit = $_POST["product_unit"];
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
               Error: ' . $result . '
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
  <style>
    body {
      background-color: #f4e1d2 !important; /* Sandy beige */
      color: #3e2723; /* Dark brown */
      font-family: 'Arial', sans-serif;
      margin: 0; /* Reset default margin */
      padding-bottom: 100px; /* Add padding to prevent footer overlap */
      min-height: 100vh; /* Ensure body takes full viewport height */
      display: flex;
      flex-direction: column;
    }
    .container {
      padding: 10px 20px;
      flex: 1; /* Allow container to grow and push footer down */
    }
    .card {
      background-color: #ffffff;
      border-radius: 15px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      overflow: hidden;
    }
    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    }
    .card-header {
      background: linear-gradient(45deg, #689f38, #8bc34a); /* Lime green gradient */
      color: #ffffff;
      font-size: 1.5rem;
      padding: 15px;
      border-bottom: none;
      border-radius: 15px 15px 0 0;
    }
    .card-body {
      padding: 20px;
    }
    .form-label {
      color: #3e2723; /* Dark brown */
      font-weight: 500;
    }
    .form-control, .form-select {
      border: 1px solid #3e2723; /* Dark brown border */
      border-radius: 5px;
      transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }
    .form-control:focus, .form-select:focus {
      border-color: #f59e0b; /* Mustard yellow */
      box-shadow: 0 0 5px rgba(245, 158, 11, 0.5);
      outline: none;
    }
    /* Category Dropdown Styling */
    .dropdown-toggle {
      background-color: #ffffff !important;
      border: 1px solid #3e2723 !important; /* Dark brown border */
      color: #3e2723 !important; /* Dark brown text */
      border-radius: 5px;
      transition: background-color 0.3s ease, border-color 0.3s ease, color 0.3s ease;
    }
    .dropdown-toggle:hover {
      background-color: #f3e8d6 !important; /* Light beige */
      border-color: #f59e0b !important; /* Mustard yellow */
      color: #f59e0b !important; /* Mustard yellow text */
    }
    .dropdown-toggle::after {
      border: none !important;
      content: '\f078'; /* Font Awesome chevron-down */
      font-family: "Font Awesome 6 Free";
      font-weight: 900;
      color: #3e2723; /* Dark brown */
      margin-left: 5px;
      transition: transform 0.3s ease, color 0.3s ease;
    }
    .dropdown-toggle:hover::after {
      color: #f59e0b; /* Mustard yellow */
      transform: rotate(180deg); /* Flip arrow up */
    }
    .dropdown-toggle.show::after {
      transform: rotate(180deg); /* Flip arrow up when open */
    }
    .dropdown-menu {
      background-color: #f3e8d6 !important; /* Light beige */
      border: 1px solid #3e2723; /* Dark brown border */
      border-radius: 5px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      width: 100%;
      z-index: 1060 !important; /* Ensure it appears above other elements */
      opacity: 0;
      transform: translateY(-10px);
      transition: opacity 0.3s ease-in-out, transform 0.3s ease-in-out;
    }
    .dropdown-menu.show {
      opacity: 1;
      transform: translateY(0);
    }
    .dropdown-item {
      color: #3e2723 !important; /* Dark brown */
      transition: background-color 0.3s ease, color 0.3s ease, padding-left 0.3s ease;
    }
    .dropdown-item:hover {
      background-color: #e2d9c8 !important; /* Slightly darker beige */
      color: #f59e0b !important; /* Mustard yellow */
      padding-left: 1.5rem; /* Slight indent on hover */
    }
    /* Cropper Modal Styling */
    #cropperModal .modal-dialog {
      max-width: 90vw; /* Adjust modal width */
    }
    #cropperModal .modal-body {
      height: 60vh; /* Reduced height to 60vh */
      display: flex;
      justify-content: center;
      align-items: center;
      overflow: hidden; /* Prevent overflow */
    }
    #cropperImage {
      max-width: 100%;
      max-height: 100%;
      object-fit: contain; /* Ensures the image is fully visible */
    }
    /* Image Preview Styling */
    #imagePreview {
      max-width: 300px; /* Increased width */
      max-height: 300px; /* Increased height */
      object-fit: cover; /* Ensure the image fits nicely */
      border: 2px solid #3e2723 !important; /* Dark brown border */
      border-radius: 5px;
    }
    /* Buttons */
    .btn-primary {
      background: linear-gradient(45deg, #689f38, #8bc34a); /* Green gradient */
      border: none;
      border-radius: 20px;
      padding: 8px 20px;
      font-weight: 600;
      color: #ffffff;
      transition: transform 0.3s ease, background 0.3s ease;
    }
    .btn-primary:hover {
      background: linear-gradient(45deg, #8bc34a, #a4d007); /* Lighter green */
      transform: scale(1.05);
    }
    .btn-secondary {
      background: linear-gradient(45deg, #c05621, #d97706); /* Terracotta gradient */
      border: none;
      border-radius: 20px;
      padding: 8px 20px;
      font-weight: 600;
      color: #ffffff;
      transition: transform 0.3s ease, background 0.3s ease;
    }
    .btn-secondary:hover {
      background: linear-gradient(45deg, #d97706, #e69500); /* Lighter terracotta */
      transform: scale(1.05);
    }
    /* Footer Adjustment */
    footer {
      margin-top: auto; /* Push footer to the bottom */
      width: 100%;
      z-index: 1000; /* Ensure footer is below other content */
    }
  </style>
</head>
<body>
  <div class="container py-5 mt-3"> <!-- Reduced margin-top from mt-5 to mt-3 -->
    <div class="card mb-4">
      <div class="card-header">
        <i class="fa fa-gavel"></i> Add Auction
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
                class="btn dropdown-toggle w-100 text-start"
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
              <?php else: ?>
              <p>No categories available.</p>
              <?php endif; ?>
              <input type="hidden" name="category" id="selectedCategory" required>
            </div>
          </div>
          <div class="mb-3">
            <label for="product_type" class="form-label">Product Type</label>
            <select id="product_type" name="product_type" class="form-control form-select" required>
              <option value="" disabled selected>Select</option>
              <option value="organic">ORGANIC</option>
              <option value="hybrid">HYBRID</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="product_quantity" class="form-label">Quantity</label>
            <input type="number" id="product_quantity" name="product_quantity" class="form-control" required>
          </div>
          <div class="mb-3">
            <label for="product_unit" class="form-label">Quantity Type</label>
            <select id="product_unit" name="product_unit" class="form-control form-select" required>
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
            <img id="imagePreview" class="img-fluid rounded-1 border border-2 border-dark" style="display: none;">
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
      const imagePreview = document.getElementById('imagePreview');

      let cropper;
      let modal;

      // Ensure Bootstrap dropdown is initialized
      const dropdownElement = new bootstrap.Dropdown(categoryDropdownButton);

      // Category selection with visual feedback
      categoryItems.forEach(item => {
        item.addEventListener('click', function (e) {
          e.preventDefault(); // Prevent default anchor behavior
          const selectedCategory = this.getAttribute('data-value');
          const categoryId = this.getAttribute('data-id');
          categoryDropdownButton.textContent = selectedCategory;
          categoryDropdownButton.classList.add('selected');
          selectedCategoryInput.value = categoryId;
          dropdownElement.hide(); // Close the dropdown after selection
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
      productImageInput.addEventListener('change', function () {
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
          modal = new bootstrap.Modal(cropperModal);
          modal.show();
        };
        reader.readAsDataURL(this.files[0]);
      });

      // Crop button action
      cropButton.addEventListener('click', function () {
        const canvas = cropper.getCroppedCanvas({
          width: 500,
          height: 500
        });
        croppedImageInput.value = canvas.toDataURL('image/webp');
        imagePreview.src = canvas.toDataURL('image/webp');
        imagePreview.style.display = 'block';
        modal.hide();
        cropper.destroy();
        cropper = null;
      });

      // GSAP animation for dropdown
      const dropdown = document.querySelector('.dropdown');
      const dropdownMenu = dropdown.querySelector('.dropdown-menu');
      dropdown.addEventListener('show.bs.dropdown', function () {
        gsap.fromTo(dropdownMenu, 
          { opacity: 0, y: -10, scale: 0.95 }, 
          { 
            duration: 0.4, 
            opacity: 1, 
            y: 0, 
            scale: 1, 
            ease: 'power2.out' 
          }
        );
        gsap.from(dropdownMenu.querySelectorAll('.dropdown-item'), {
          duration: 0.3,
          opacity: 0,
          y: 10,
          stagger: 0.1,
          ease: 'power2.out',
          delay: 0.1
        });
      });
      dropdown.addEventListener('hide.bs.dropdown', function () {
        gsap.to(dropdownMenu, { 
          duration: 0.3, 
          opacity: 0, 
          y: -10, 
          scale: 0.95,
          ease: 'power2.in' 
        });
      });
    });
  </script>
</body>
</html>

<?php
include_once("./auction-chatbot.php");
include_once("./footer.php");
ob_end_flush();
?>