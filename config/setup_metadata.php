
<?php
// Include Cloudinary configuration
require_once __DIR__ . '/cloudinary_config.php';

// Import necessary classes
use Dotenv\Dotenv;
use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Admin\AdminApi;
use Cloudinary\Api\Exception\ApiError;
use Cloudinary\Api\Metadata\SetMetadataField;
use Cloudinary\Api\Metadata\StringMetadataField;
use Cloudinary\Api\Metadata\IntMetadataField;

// Load environment variables from .env file
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

// Initialize Cloudinary configuration using the CLOUDINARY_URL from environment variables
$config = new Configuration($_ENV['CLOUDINARY_URL']);
$api = new AdminApi($config);

try {
    // Prepare and add a "Set" metadata field with predefined categories
    $datasourceValues = [
        ['value' => 'Footwear', 'external_id' => 'footwear'],
        ['value' => 'Clothes', 'external_id' => 'clothes'],
        ['value' => 'Accessories', 'external_id' => 'accessories'],
        ['value' => 'Home & Living', 'external_id' => 'home_and_living'],
        ['value' => 'Electronics', 'external_id' => 'electronics'],
    ];
    $setMetadataField = new SetMetadataField('category', $datasourceValues);
    $setMetadataField->setLabel('Category');
    $setMetadataField->setExternalId('category');
    $setMetadataField->setMandatory(true); // Makes this field required
    $setMetadataField->setDefaultValue(['footwear']); // Sets a default value
    $api->addMetadataField($setMetadataField);
    echo "Set metadata field added successfully.\n";
} catch (ApiError $e) {
    echo 'API Error (Set field): ' . $e->getMessage();
} catch (Exception $e) {
    echo 'Error (Set field): ' . $e->getMessage();
}

try {
    // Prepare and add a "String" metadata field (e.g., SKU)
    $stringMetadataField = new StringMetadataField('sku');
    $stringMetadataField->setLabel('SKU');
    $stringMetadataField->setExternalId('sku');
    $stringMetadataField->setMandatory(true); // Makes this field required
    $stringMetadataField->setDefaultValue(['1234']); // Sets a default value
    $api->addMetadataField($stringMetadataField);
    echo "String metadata field added successfully.\n";
} catch (ApiError $e) {
    echo 'API Error (String field): ' . $e->getMessage();
} catch (Exception $e) {
    echo 'Error (String field): ' . $e->getMessage();
}

try {
    // Prepare and add an "Integer" metadata field (e.g., Price)
    $intMetadataField = new IntMetadataField('price');
    $intMetadataField->setLabel('Price');
    $intMetadataField->setExternalId('price');
    $intMetadataField->setMandatory(true); // Makes this field required
    $intMetadataField->setDefaultValue([10]); // Sets a default value
    $api->addMetadataField($intMetadataField);
    echo "Int metadata field added successfully.\n";
} catch (ApiError $e) {
    echo 'API Error (Integer field): ' . $e->getMessage();
} catch (Exception $e) {
    echo 'Error (Integer field): ' . $e->getMessage();
}

try {
    // List all metadata fields at the end
    echo "Listing all metadata fields:\n";
    $fields = $api->listMetadataFields();
    echo '<pre><code>';
    print_r($fields);
    echo '</code></pre>';
} catch (ApiError $e) {
    echo 'API Error (List fields): ' . $e->getMessage();
} catch (Exception $e) {
    echo 'Error (List fields): ' . $e->getMessage();
}
?>