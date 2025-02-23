<?php
error_reporting(E_ALL & ~E_DEPRECATED);
require_once __DIR__ . '/../config/cloudinary_config.php';  // Make sure this file sets up the Cloudinary API
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

use Dotenv\Dotenv;
use Cloudinary\Configuration\Configuration;
use Cloudinary\Cloudinary;
use Cloudinary\Tag\ImageTag; 
use Cloudinary\Api\Admin\AdminApi;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$config = new Configuration($_ENV['CLOUDINARY_URL']);
$cld = new Cloudinary($config);


// Initialize Configuration
$config = new Configuration($_ENV['CLOUDINARY_URL']);

$api = new AdminAPI($config);
if (isset($_GET['id'])) {
    $product_id = $_GET['id'];
    $product = getproduct($pdo, $product_id);
} else {
    echo "product not found.";
    exit;
}

// Default values: use current product image/video if no new files uploaded
$image_url = $product['product_image_url'];
$image_public_id = $product['image_public_id'];
$video_url = $product['product_video_url'];
$video_public_id = $product['video_public_id'];
$image_caption = $product['image_caption'];
$video_moderation_status = $product['video_moderation_status'];
$video_public_id_temp = $product['video_public_id_temp'];

// Get external ids for metadata fields.
$allFieldsResponse = $api->listMetadataFields();
$allFields = $allFieldsResponse['metadata_fields'] ?? [];    
$externalIds=[];
$newLabel = "Description";
checkAndAppendExternalId($allFields, $newLabel, $externalIds);
$newLabel = "SKU";
checkAndAppendExternalId($allFields, $newLabel, $externalIds);
$newLabel = "Price";
checkAndAppendExternalId($allFields, $newLabel, $externalIds);
$newLabel = "Category";
checkAndAppendExternalId($allFields, $newLabel, $externalIds);


// Get the metadata from Cloudianry to display.
$metadata_result = $api->asset($product['image_public_id']);
// Check if 'Description' exists in $externalIds and in metadata
$description = isset($externalIds['Description']) && isset($metadata_result['metadata'][$externalIds['Description']]) 
? $metadata_result['metadata'][$externalIds['Description']] 
: 'No description available';

// Check if 'Price' exists in $externalIds and in metadata
$price = isset($externalIds['Price']) && isset($metadata_result['metadata'][$externalIds['Price']]) 
? $metadata_result['metadata'][$externalIds['Price']] 
: 0; // Default price

// Check if 'SKU' exists in $externalIds and in metadata
$sku = isset($externalIds['SKU']) && isset($metadata_result['metadata'][$externalIds['SKU']]) 
? $metadata_result['metadata'][$externalIds['SKU']] 
: 'Unknown SKU';

// Check if 'Category' exists in $externalIds and in metadata
$category = isset($externalIds['Category']) && isset($metadata_result['metadata'][$externalIds['Category']][0]) 
? $metadata_result['metadata'][$externalIds['Category']][0] 
: 'clothes'; // Default category    

// Map list value external IDs to display values.
$category_labels = [
    'clothes' => 'Clothes',
    'accessories' => 'Accessories',
    'footwear' => 'Footwear',
    'home_and_living' => 'Home & Living',
    'electronics' => 'Electronics',
];

// Add error handling for metadata
$description = !empty($_POST['description']) ? $_POST['description'] : $description;
$sku = !empty($_POST['sku']) ? $_POST['sku'] : $sku;
$price = !empty($_POST['price']) ? $_POST['price'] : $price;
$category = !empty($_POST['category']) ? $_POST['category'] : $category;
// Set up metadata entries for submission to cloudinary
$metadata = 
$externalIds['SKU'] . '=' . $sku . '|' .
$externalIds['Category'] . '=["' . $category . '"]|' .
$externalIds['Price'] . '=' . $price . '|' .
$externalIds['Description'] . '=' . $description;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];

    // Update product image
    if (!empty($_POST['image_url'])) {
        $product_image_url = $_POST['image_url']; // Retrieve the secure URL from the form submission
        $image_public_id = $_POST['image_id']; // Retrieve the public ID from the form submission
        // Update metadata
        $cloudinary_result = $cld->uploadApi()->explicit($image_public_id, ["type"=>"upload","metadata" => $metadata]);
        $image_url = $cloudinary_result['secure_url'];  // Save the new image URL
        $image_public_id = $cloudinary_result['public_id'];  // Save the new public ID of the image
        $image_caption = $cloudinary_result['info']['detection']['captioning']['data']['caption'];
    } else {
        $result = $api->update($image_public_id, ["metadata" => $metadata]);
    } 
    
    // Handle Video Moderation and Metadata
    if (!empty($_POST['video_url'])) {
        $product_video_url = $_POST['video_url'];
        // Hold the video public ID temporarily until moderation status is confirmed.
        $video_public_id_temp = $_POST['video_id'];
        // Set metadata and mark the video for moderation.
        $cloudinary_result = $cld->uploadApi()->explicit($video_public_id_temp, ['type' => 'upload', 'resource_type' => 'video', 'moderation' => 'aws_rek_video', "metadata" => $metadata]);
        $video_public_id = 'pending';  // Initialize public ID, to be updated after moderation
        $video_moderation_status = 'pending'; // Initialize moderation status
    } 
    updateproduct($pdo, $name, $image_url, $video_url, $image_public_id, $video_public_id, $video_moderation_status, $image_caption, $video_public_id_temp, $product['id']);
    header("Location: products.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update This Product</title>
    <link rel="stylesheet" href="../static/styles.css">
</head>
<body>
<nav>
    <ul>
        <li><a style="font-size:1.3rem;font-weight:75px;color:white;" href="../index.php">Catalog Creation App</a></li>
        <li><a href="products.php">View Products</a></li>
    </ul>
</nav>

<div class="container" style="margin-top:-95px;">
    <div style="align-self: flex-start; text-align: left;">
    
    <p style="font-size:12px;">Update an existing product in your catalog:</p>
    <ul style="font-size:10px;">
        <li style="margin-top:-3px;">Anything not edited will retain the existing data.</li>
        <li style="margin-top:3px;">The user-input name of the product is updated in the database and displayed wherever the product is rendered.</li>
        <li style="margin-top:3px;">A new image and video are uploaded from the client-side to your product environment using the <a href="https://cloudinary.com/documentation/upload_widget">Upload Widget</a>.</li>
        <li style="margin-top:3px;">The Upload Widget specifies an <a href="https://cloudinary.com/documentation/admin_api#upload_presets">upload preset</a> which calls <a href="https://cloudinary.com/documentation/cloudinary_ai_content_analysis_addon">Cloudinary's AI Content Analysis</a> add-on to generate image alt text automatically.</li>
        <li style="margin-top:3px;">When the product is submitted, the image and video, if new ones are specified, are updated using the  <a href ="https://cloudinary.com/documentation/image_upload_api_reference#explicit">explicit</a> endpoint of the Upload API:</li> 
            <ul>
                <li><b>Image & Video</b></li>
                    <ul>
                        <li style="margin-top:3px;">User-provided <a href="https://cloudinary.com/documentation/structured_metadata">structured metadata</a> is added.
                    </ul>
            </ul>
            <ul>
                <li><b>Video</b></li>
                    <ul>
                        <li style="margin-top:3px;">The video is marked for <a href="https://cloudinary.com/documentation/aws_rekognition_video_moderation_addon#banner">Amazon Rekognition Video Moderation</a>.</li>
                        <li style="margin-top:3px;">Its public ID is temporarily recorded in the database.</li>
                        <li style="margin-top:3px;">The video won't be displayed and its information stored until a webhook notification is received that the video has been approved.</li>
                        <li style="margin-top:3px;">If the new video is rejected, a message will be displayed explaining why.</li>
                    </ul>
            </ul>
        </li>
    </ul>
    </div>
</div>

<div class="products-page">
<div class="product-container" style="padding-left:80px;padding-right:80px;">
    <h2>Update This product</h2>
    <!--Initialize the form with current values-->
    <form action="edit_product.php?id=<?php echo $product['id']; ?>" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="name">Product Name:</label>
            <input style="width:370px;" type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" placeholder="Name">   
        </div>

        <div class="form-group">
            <label for="description">Product Description:</label>
            <input style="width:338px;" type="text" id="description" name="description" value="<?php echo htmlspecialchars($description); ?>" placeholder="Enter product description">
        </div>
        
        <div class="form-group">
            <label for="sku">Product SKU:</label>
            <input style="width:378px;" type="text" id="sku" name="sku" value="<?php echo htmlspecialchars($sku); ?>" placeholder="Enter product SKU">
        </div>

        <div class="form-group" style="margin-left:-205px;margin-bottom:10px;">
            <label for="price">Product Price ($):</label>
            <input type="number" id="price" name="price" value="<?php echo htmlspecialchars($price); ?>" placeholder="Enter product price" step="0.01" min="0.01">
        </div>

        <div class="form-group" style="margin-left:-295px;margin-bottom:15px;">
            <label for="category">Category:</label>
            <select id="category" name="category" >
                <option value="clothes" <?php echo ($category == 'clothes') ? 'selected' : ''; ?>>Clothes</option>
                <option value="accessories" <?php echo ($category == 'accessories') ? 'selected' : ''; ?>>Accessories</option>
                <option value="footwear" <?php echo ($category == 'footwear') ? 'selected' : ''; ?>>Footwear</option>
                <option value="home_and_living" <?php echo ($category == 'home_and_living') ? 'selected' : ''; ?>>Home & Living</option>
                <option value="electronics" <?php echo ($category == 'electronics') ? 'selected' : ''; ?>>Electronics</option>
            </select>
        </div>          
       <!-- Display image and video, or a message if they don't exist -->
        <div style="display:flex;">
            <div style="padding-right:10px;">
                <label>Current Image:</label>
                <div>
                    <?php if (!empty($product['product_image_url'])): ?>
                        <img src="<?php echo htmlspecialchars($product['product_image_url']); ?>" alt="Product Image" style="max-width: 200px; height: auto; margin-bottom: 15px;">
                    <?php else: ?>
                        <p>No product image available.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div id="new_image_section" style="padding-left:10px;">
                <!-- New image preview will be inserted here -->
            </div>
        </div>
        
        <div style="display:flex;margin-top:-20px;margin-bottom:20px;">
            <button type="button" id="upload_image_button">Upload New Image</button>
            <input type="hidden" name="image_url" id="image_url">
            <input type="hidden" name="image_id" id="image_id">
        </div>
        
        <div style="display:flex;padding-right:10px;">
            <div>
                <label>Current Video:</label>
                <div>
                    <?php if (!empty($product['product_video_url'])): ?>
                        <video controls style="max-width: 200px; height: auto; margin-bottom: 15px;">
                            <source src="<?php echo htmlspecialchars($product['product_video_url']); ?>" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    <?php else: ?>
                        <p>No product video available.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div id="new_video_section" style="padding-left:10px;">
                <!-- New video preview will be inserted here -->
            </div>
        </div>

        <div style="display:flex;">
            <button type="button" id="upload_video_button" style="margin-top:-10px;margin-bottom:20px;">Upload New Video</button>
            <input type="hidden" name="video_url" id="video_url">
            <input type="hidden" name="video_id" id="video_id">
        </div>

        <!-- Submit the updates -->
<button id="update" style="margin-bottom:70px;"type="submit">Update product</button>
        </form>
        </div>

<div id="spinner" style="display:none;margin-top:-50px;justify-content:center; align-items:center;">
    <div class="loader"></div>
</div>

<!-- Confirmation toast message -->

    
<div id="toast" style="right:-30px;margin-top:-50px;" class="toast">We're updating your product. Please wait.</div>

<!-- Include the Cloudinary Upload Widget library -->
<script src="https://upload-widget.cloudinary.com/global/all.js"></script>

<script>
    // Configure the upload widget for images
    const imageWidget = cloudinary.createUploadWidget({
        cloudName: '<?php echo $_ENV['CLOUDINARY_CLOUD_NAME']; ?>', // Replace with your Cloudinary cloud name
        uploadPreset: 'php_demo_preset', // Replace with your upload preset
        sources: ['local', 'url'], // Allow uploads from local files and URLs
        resourceType: 'image', // Specify resource type as image
        maxFileSize: 5000000, // Set a max file size (optional)
        folder: 'products/images', // Optional folder path
    }, (error, result) => {
        if (!error && result && result.event === "success") {
            console.log('Image uploaded successfully:', result.info.secure_url);
            document.getElementById('image_url').value = result.info.secure_url;
            document.getElementById('image_id').value = result.info.public_id;
            // Display the new image alongside the current one
            const newImageSection = document.getElementById("new_image_section");
            newImageSection.innerHTML = `
                <label>New Image:</label>
                <div>
                    <img src="${result.info.secure_url}" alt="New Product Image" style="max-width: 200px; height: auto; margin-bottom: 15px;">
                </div>
            `;
        }
    });

    // Configure the upload widget for videos
    const videoWidget = cloudinary.createUploadWidget({
        cloudName: '<?php echo $_ENV['CLOUDINARY_CLOUD_NAME']; ?>', // Replace with your Cloudinary cloud name
        uploadPreset: 'php_demo_preset', // Replace with your upload preset
        sources: ['local', 'url'], // Allow uploads from local files and URLs
        resourceType: 'video', // Specify resource type as video
        maxFileSize: 50000000, // Set a max file size (optional)
        folder: 'products/videos', // Optional folder path
    }, (error, result) => {
        if (!error && result && result.event === "success") {
            console.log('Video uploaded successfully:', result.info.secure_url);
            document.getElementById('video_url').value = result.info.secure_url;
            document.getElementById('video_id').value = result.info.public_id;
            // Display the new video alongside the current one
            const newVideoSection = document.getElementById("new_video_section");
            newVideoSection.innerHTML = `
                <label>New Video:</label>
                <div>
                    <video controls style="max-width: 200px; height: auto; margin-bottom: 15px;">
                        <source src="${result.info.secure_url}" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                </div>
            `;
        }
    });

    // Open the image upload widget
    document.getElementById('upload_image_button').addEventListener('click', () => {
        imageWidget.open();
    });

    // Open the video upload widget
    document.getElementById('upload_video_button').addEventListener('click', () => {
        videoWidget.open();
    });

    document.querySelector("form").addEventListener("submit", function (e) {
        const toast = document.getElementById("toast");
        const spinner = document.getElementById("spinner");

        // Show the toast
        toast.className = "toast show";

        // Show the spinner
        spinner.style.display = "flex";

        // Disable the submit button to prevent multiple submissions
        document.getElementById("update").disabled = true;

        // Hide the toast after 3 seconds
        setTimeout(() => {
            toast.className = toast.className.replace("show", "");
        }, 3000); // Toast disappears after 3 seconds

        // Optionally, handle redirection after the spinner shows
        setTimeout(() => {
            window.location.href = './products.php'; // Redirect after the spinner is shown
        }, 1000); // Adjust delay to make sure the spinner has time to appear before redirecting
    });
</script>

</body>
</html>
