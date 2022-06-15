<?php
header('Access-Control-Allow-Origin: http://localhost:3000');
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //set product
    $sql_product = "INSERT INTO Products (name)
        VALUES ('$_POST[product_name]')";

    $conn->query($sql_product);
    $last_prod_id = $conn->insert_id;

    $mods = [];

    //set variants
    for ($i = 0; $i < count($_POST['modification']); $i++) {
        $mods[$i] = array('variant_title' => $_POST['modification'][$i]);
        if (count($_POST['price']) >=2) {
            $mods[$i]['price'] = $_POST['price'][$i];
            $mods[$i]['qty'] = $_POST['qty'][$i];
        } else {
            $mods[$i]['price'] = $_POST['price'];
            $mods[$i]['qty'] = $_POST['qty'];
        }
         foreach($_POST['options-' .+ ($i + 1)] as $value) {
             $mods[$i]['options'][] = $value;
        }
    }


    foreach ($mods as $value) {
        $sql_variants = "INSERT INTO Modifications (mod_title, qty, price)
        VALUES('$value[variant_title]', '$value[qty]', '$value[price]')";

        if ($conn->query($sql_variants) === TRUE) {
            echo "New record created successfully";
        } else {
            echo "Error: " . $sql_variants . "<br>" . $conn->error;
        }
        $last_mod_id = $conn->insert_id;

        $sql_fill_prod_mod_manager = "INSERT INTO prod_mods (prod_id, mod_id)
            VALUES($last_prod_id, $last_mod_id)";

        $conn->query($sql_fill_prod_mod_manager);
    }

    print_r($mods);

    //get id of new product
}
?>