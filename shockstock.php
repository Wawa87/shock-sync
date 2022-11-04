<?php
/*
Plugin Name:  Shock Stock
Plugin URI:   https://www.runicdigital.com
Description:  Product inventory updater. The plugin removes the 'In-Stock' category from the current products and then re-adds that category to the products in the CSV, matching on Part Number. The stock is also updated.
Version:      1.0
Author:       Runic Digital
Author URI:   https://www.runicdigital.com
License:      Private
License URI:  https://www.runicdigital.com
Text Domain:  shockstock
Domain Path:  /languages
*/

register_activation_hook(__FILE__, 'shockstock_activate');

function shockstock_activate() {

}

register_deactivation_hook(__FILE__, 'shockstock_deactivate');

function shockstock_deactivate() {

}

add_action('admin_menu', 'ss_add_menu_page');

function ss_add_menu_page() {
    add_menu_page(
        "Shock Stock",
        "Shock Stock Updater",
        "manage_options",
        "shockstock",
        "shockstock_html",
        "dashicons-upload",
        99
    );
}

function shockstock_html() {
    ?>
    <div>
        <h2>Shock Stock Updater</h2>
        <button onclick>Reset In-Stock Products</button>
        <p>This will remove the 'In Stock' category from all products and set all products Stock to 0.</p>
        <form enctype="multipart/form-data" action="uploader.php" target="uploader.php" method="POST">
            <input type="file" id="myFile" name="myFile" style="display:block;">
            <input type="submit" value="Upload">
        </form>
    </div>
<?php
}