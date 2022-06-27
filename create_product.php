<?php
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim(file_get_contents("php://input"));
    $decoded = json_decode($content, true);

    $sql_product = "INSERT INTO Products (name, price, collection)
        VALUES ('$decoded[product_name]', '$decoded[colletion]', '$decoded[prod_price]')";

    $conn->query($sql_product);
    $last_prod_id = $conn->insert_id;

    $mods = [];

    //set variants
    for ($i = 0; $i < count($decoded['modification[]']); $i++) {
        if (count($decoded['modification[]']) >= 2) {
            $mods[$i] = array('variant_title' => $decoded['modification[]'][$i]);
            $mods[$i]['price'] = $decoded['price'][$i];
            $mods[$i]['qty'] = $decoded['qty'][$i];
        } else {
            $mods[$i] = array('variant_title' => $decoded['modification[]']);
            $mods[$i]['price'] = $decoded['price'];
            $mods[$i]['qty'] = $decoded['qty'];
        }
        $mods[$i]['options'] = $decoded['options'];
    }

    //print json_encode($mods);

    foreach ($mods as $value) {
        $sql_variants = "INSERT INTO Modifications (mod_title, qty, price)
        VALUES('$value[variant_title]', '$value[qty]', '$value[price]')";

        $conn->query($sql_variants);

        $last_mod_id = $conn->insert_id;

        $sql_fill_prod_mod_manager = "INSERT INTO prod_mods (prod_id, mod_id)
            VALUES($last_prod_id, $last_mod_id)";

        $conn->query($sql_fill_prod_mod_manager);

        $sql_create_opt_row = ("INSERT INTO Options (mod_id)
             VALUES ($last_mod_id)");

        $conn->query($sql_create_opt_row);

        $options = explode(', ', $value['options']);
        print json_encode($options);
        for ($i = 0; $i < count($options); $i++) {
            $opt = 'opt' . +($i + 1);
            $opt_value = $options[$i];

            $sql_fill_options = $conn->prepare("UPDATE Options SET $opt = ?");

            $sql_fill_options->bind_param('s', $opt_value);

            $sql_fill_options->execute();
        }
    }
}
?>


