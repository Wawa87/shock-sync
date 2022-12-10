<?php
/*
Plugin Name:  Shock Sync
Plugin URI:   https://www.runicdigital.com
Description:  Product inventory and price updater from CSV files. The shock prices and current stock are provided by separate entities in slighlty different formats. The plugin speeds up the process for sync'ing this information.
Version:      1.0
Author:       Runic Digital
Author URI:   https://www.runicdigital.com
License:      Private
License URI:  https://www.runicdigital.com
Text Domain:  shocksync
Domain Path:  /languages
*/

if (!class_exists('ShockSync')) {
    class ShockSync {
        public static $outputMessage = "Initial plugin message.";
    
        function __construct() {
            add_action('admin_menu', array($this, 'ss_admin_page'));
        }
    
        function shockstock_activate() {}
    
        function shockstock_deactivate() {}
    
        function ss_admin_page() {
            $hookname = add_menu_page(
                "Shock Sync",
                "Shock Sync",
                "manage_options",
                "shocksync",
                array($this, 'shockstock_html'),
                "dashicons-upload",
                99
            );
            add_action('load-' . $hookname, array($this, 'handlePost'));
        }
        
        function shockstock_html() {
            // Get in-stock products.
            $args = array(
                'stock_status' => 'instock',
                'limit' => '-1'
            );
            $products = wc_get_products($args);
            ?>
            <div>
                <h1>Shock Sync</h1>
                <p>There are currently <b style="font-size: 1.2em;"><?php echo sizeof($products); ?></b> products in stock.</p>
                
                <h4>Upload a CSV file to update the price or the stock for the products by part number.</h4>
                <h4>The CSV must match the exact header names in the same order:</h4>
                <h4>PartNumber, Description, Quantity, Price</h4>
                <form enctype="multipart/form-data" class="wp-upload-form" action="<?php menu_page_url('ShockSync') ?>" method="POST">
                    <input type="text" name="formSubmitted" value="processCSV" hidden/>
                    <label for="syncAction">Select Sync Action:</label><br/>
                    <select name="syncAction" id="syncAction" required>
                        <option disabled selected value="">Select an option</option>
                        <option value="syncStock">Sync Stock</option>
                        <option value="syncPrice">Sync Price</option>
                    </select>
                    <input type="file" id="csvSource" name="csvSource" style="display:block;">
                    <?php submit_button("Upload"); ?>
                </form>
                <p>
                    <?php echo ShockSync::$outputMessage; ?>
                </p>
            </div>
            <?php
        }

        function handleCSV($syncAction) {
            if (!empty($_FILES['csvSource']['tmp_name'])) {
                ShockSync::$outputMessage .= "Filename " . $_FILES['csvSource']['name'] . " was uploaded" . "...<br/>";
                $tmpName = $_FILES['csvSource']['tmp_name']; // Gets the temporary file path
                $csvAsArray = array_map('str_getcsv', file($tmpName));
                $uploadedProductArray = [];

                for ($x = 0; $x < sizeof($csvAsArray); $x++) {
                    // Check for headers matching the required format: PartNumber, Description, Quantity, Price
                    if ($x == 0) {
                        $headerRow = $csvAsArray[0];
                        if (!($headerRow[0] == "PartNumber" && $headerRow[1] == "Description" && $headerRow[2] == "Quantity" && $headerRow[3] == "Price")) {
                            ShockSync::$outputMessage .= "Error - Header row doesn't match the column format: PartNumber, Description, Quantity, Price...<br/>";
                            return;
                        } else {
                            ShockSync::$outputMessage .= "Header row check success...<br/>";
                            continue;
                        }
                    }
                    // Check for valid data row.
                    // Must have a part number.
                    // Must have a description.
                    // Must have either a Quantity or Price depending on $syncAction
                    $partNumber = trim($csvAsArray[$x][0]);
                    $description = trim($csvAsArray[$x][1]);
                    if (strlen($partNumber) == 0 || strlen($description) == 0) {
                        continue;
                    }
                    // Must have a Quantity if $syncAction=syncStock
                    $quantity = trim($csvAsArray[$x][2]);
                    if ($syncAction == "syncStock") {
                        $quantity = intval($quantity, 10);
                        if ($quantity == 0) {
                            continue;
                        }
                    }
                    // Must have a Quantity if $syncAction=syncPrice
                    $price = str_replace("$", "", trim($csvAsArray[$x][3]));
                    if ($syncAction == "syncPrice") {
                        if (strlen($price) == 0) {
                            continue;
                        }
                    }
                    // Add the checked row to the return array.
                    array_push($uploadedProductArray, [
                        "partNumber" => $partNumber,
                        "description" => $description,
                        "quantity" => $quantity,
                        "price" => $price
                    ]);
                }
                return $uploadedProductArray;
            } else {
                ShockSync::$outputMessage .= "No File Uploaded...<br/>";
            }
        }

        function handlePost() {
            ShockSync::$outputMessage = "Method 'handlePost' called...<br/>";
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                ShockSync::$outputMessage .= "Request Method is POST...<br/>";

                $uploadedProductArray = $this->handleCSV($_POST['syncAction']);
                
                // Synchronize Stock
                if (!empty($_POST['syncAction']) && $_POST['syncAction'] == 'syncStock') {
                    ShockSync::$outputMessage .= "Sync Action: syncStock...<br/>";

                    // If no rows were pushed to the $uploadedProductArray, don't do anything
                    if (sizeof($uploadedProductArray) == 0) {
                        ShockSync::$outputMessage .= "The uploaded file produced an empty product array. Check the format and try again" . "...<br/>";
                        return;
                    } else {
                        ShockSync::$outputMessage .= "The uploaded file contains " . sizeof($uploadedProductArray) . " products to update...<br/>";
                    }

                    // Iterate through all products. Set the stock to 0, then if the part numbers match, update the quantity.
                    // NOTE: If any products contain the same part number, they will both have their quantities updated.
                    $products = wc_get_products(array('limit' => '-1'));
                    ShockSync::$outputMessage .= "Stock reset to 0 for " . sizeof($products) . " products...<br/>";
                    foreach ($products as &$prod) {
                        // Set the stock to 0.
                        $prod->set_manage_stock(true); // Must be true to modify the stock number.
                        $prod->set_stock_quantity(0);
                        
                        // Check if part numbers match.
                        $atts = $prod->get_attributes();
                        foreach ($atts as $att) {
                            $dat = $att->get_data();
                            $partNumber = $dat['value'];
                            foreach($uploadedProductArray as $pn) {
                                if ($partNumber == $pn['partNumber']) {
                                    ShockSync::$outputMessage .= " Part number: $partNumber - Stock set to:  " . $pn['quantity'] . "...<br/>";
                                    $prod->set_stock_quantity($pn['quantity']);
                                }
                            }
                        }
                        $prod->save();
                    }
                }

                // Synchronize Price
                if (!empty($_POST['syncAction']) && $_POST['syncAction'] == 'syncPrice') {
                    ShockSync::$outputMessage .= "Sync Action: syncPrice...<br/>";

                    // If no rows were pushed to the $uploadedProductArray, don't do anything
                    if (sizeof($uploadedProductArray) == 0) {
                        ShockSync::$outputMessage .= "The uploaded file produced an empty product array. Check the format and try again" . "...<br/>";
                        return;
                    } else {
                        ShockSync::$outputMessage .= "The uploaded file contains " . sizeof($uploadedProductArray) . " products to update...<br/>";
                    }

                    // Iterate through all products. Set the stock to 0, then if the part numbers match, update the quantity.
                    // NOTE: If any products contain the same part number, they will both have their quantities updated.
                    $products = wc_get_products(array('limit' => '-1'));
                    foreach ($products as &$prod) {
                        // Check if part numbers match.
                        $atts = $prod->get_attributes();
                        foreach ($atts as $att) {
                            $dat = $att->get_data();
                            $partNumber = $dat['value'];
                            foreach($uploadedProductArray as $pn) {
                                if ($partNumber == $pn['partNumber']) {
                                    ShockSync::$outputMessage .= " Part number: $partNumber - Price set to:  " . $pn['price'] . "...<br/>";
                                    $prod->set_regular_price($pn['price']);
                                }
                            }
                        }
                        $prod->save();
                    }
                }
            }
        }
    }
    $stockUpdater = new ShockSync();
}

register_activation_hook(__FILE__, 'shockstock_activate');
register_deactivation_hook(__FILE__, 'shockstock_deactivate');