<?php
    // Read the CSV file into an array.
    echo $_FILES['myFile']['name'] . ' has been uploaded...';
    $tmpName = $_FILES['myFile']['tmp_name']; // Gets the temporary file path
    $csvAsArray = array_map('str_getcsv', file($tmpName));
    $cleanedData = [];

    // Iterate through the rows in the CSV.
    foreach($csvAsArray as $key=>$val) {
        // Check that first column is a number, which represents the quantity.
        // If it is blank or text, that means it was a blank row or a row of label strings.
        if (intval($val[0], 10) >= 1) { // Return values of 1 or greater mean it was successful and thus a valid row.
            array_push($cleanedData, [
                "quantity" => $val[0],
                "part_number" => $val[1],
                "description" => $val[2],
                "price" => $val[3]
            ]);
        }
    }

    foreach($cleanedData as $key=>$val) {
        echo $val["quantity"] . ", " . $val["part_number"] . ", " . $val["price"] . ", " . $val["description"] . "<br/>";
    }

    // Iterate through all of the products.
    // Set the quantity to 0.
    // Check if that product is in the new dataset by comparing the part number, then update the quantity with the new value.

?>