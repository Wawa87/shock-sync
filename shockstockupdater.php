<?php
/*
Plugin Name:  Shock Stock Updater
Plugin URI:   https://www.runicdigital.com
Description:  Product inventory updater. The plugin removes the 'In-Stock' category from the current products and then re-adds that category to the products in the CSV, matching on Part Number. The stock is also updated.
Version:      1.0
Author:       Runic Digital
Author URI:   https://www.runicdigital.com
License:      Private
License URI:  https://www.runicdigital.com
Text Domain:  shockstockupdater
Domain Path:  /languages
*/

if (!class_exists('ShockStockUpdater')) {
    class ShockStockUpdater {
        public static $outputMessage = "Initial plugin message.";
    
        function __construct() {
            add_action('admin_menu', array($this, 'ss_admin_page'));
        }
    
        function shockstock_activate() {}
    
        function shockstock_deactivate() {}
    
        function ss_admin_page() {
            $hookname = add_menu_page(
                "Shock Stock Updater",
                "Shock Stock Updater",
                "manage_options",
                "shockstock",
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
            );
            $products = wc_get_products($args);
            ?>
            <div>
                <h1>Shock Stock Updater</h1>
                <p>There are currently <b style="font-size: 1.2em;"><?php echo sizeof($products); ?></b> products in stock.</p>
                
                <h4>Choose a CSV file to upload that contains the updated stock quantities.</h4>
                <form enctype="multipart/form-data" action="<?php menu_page_url('shockstockupdater') ?>" method="POST">
                    <input type="text" name="formSubmitted" value="processCSV" hidden/>
                    <input type="file" id="csvUpdatedStock" name="csvUpdatedStock" style="display:block;">
                    <?php submit_button("Upload"); ?>
                </form>
                <p>
                    <?php echo ShockStockUpdater::$outputMessage; ?>
                </p>
            </div>
            <?php
        }

        function handlePost() {
            ShockStockUpdater::$outputMessage = "Method 'handlePost' called...<br/>";
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                ShockStockUpdater::$outputMessage .= "Request Method is POST...<br/>";
                
                // Process the CSV.
                if (!empty($_POST['formSubmitted']) && $_POST['formSubmitted'] == 'processCSV') {
                    ShockStockUpdater::$outputMessage .= "Form submitted as " . $_POST['formSubmitted'] . "...<br/>";

                    if (!empty($_FILES['csvUpdatedStock']['tmp_name'])) {
                        ShockStockUpdater::$outputMessage .= "Filename " . $_FILES['csvUpdatedStock']['name'] . " was uploaded" . "...<br/>";
                        $tmpName = $_FILES['csvUpdatedStock']['tmp_name']; // Gets the temporary file path
                        $csvAsArray = array_map('str_getcsv', file($tmpName));
                        $uploadedProductArray = [];

                        // Iterate through the rows in the CSV.
                        foreach($csvAsArray as $key=>$val) {
                            // Check that first column is a number, which represents the quantity.
                            // If it is blank or text, that means it was a blank row or a row of label strings.
                            if (intval($val[0], 10) >= 1) { // Return values of 1 or greater mean it was successful and thus a valid row.
                                array_push($uploadedProductArray, [
                                    "quantity" => $val[0],
                                    "part_number" => $val[1],
                                    "description" => $val[2],
                                    "price" => $val[3]
                                ]);
                            }
                        }

                        // If no rows were pushed to the $uploadedProductArray, that means something was wrong with the format.
                        if (sizeof($uploadedProductArray) == 0) {
                            ShockStockUpdater::$outputMessage .= "The uploaded file produced an empty product array. Check the format and try again" . "...<br/>";
                            return;
                        } else {
                            ShockStockUpdater::$outputMessage .= "The uploaded file contains " . sizeof($uploadedProductArray) . " products to update...<br/>";
                        }

                        // Iterate through all products. Set the stock to 0, then if the part numbers match, update the quantity.
                        // NOTE: If any products contain the same part number, they will both have their quantities updated.
                        $products = wc_get_products(array('limit' => '-1'));
                        ShockStockUpdater::$outputMessage .= "Stock reset to 0 for " . sizeof($products) . " products...<br/>";
                        foreach ($products as &$prod) {
                            // Set the stock to 0.
                            $prod->set_manage_stock(true); // Must be true to modify the stock number.
                            $prod->set_stock_quantity(0);
                            
                            // Check if part numbers match.
                            $atts = $prod->get_attributes();
                            $partNumber = $atts['part-number']->get_data()['value'];
                            foreach($uploadedProductArray as $pn) {
                                if ($partNumber == $pn['part_number']) {
                                    ShockStockUpdater::$outputMessage .= " Part number: $partNumber - Stock set to:  " . $pn['quantity'] . "...<br/>";
                                    $prod->set_stock_quantity($pn['quantity']);
                                }
                            }
                            $prod->save();
                        }
                    } else {
                        ShockStockUpdater::$outputMessage .= "No File Uploaded...<br/>";
                    }
                }
            }
        }
    }
    $stockUpdater = new ShockStockUpdater();
}

register_activation_hook(__FILE__, 'shockstock_activate');
register_deactivation_hook(__FILE__, 'shockstock_deactivate');