<?php
header('Access-Control-Allow-Origin: http://localhost:3000');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require 'db_connect.php';

    $collection = $_GET['collection'];

    $products = [];

    $sql_prod = "SELECT id, name, main_photo, products.price, collection
            FROM PRODUCTS WHERE collection = 'jeans'";

    $result_prod = $conn->query($sql_prod);

    if (!$result_prod) {
        printf("Errormessage: %s\n", $conn->error);
    }

    $rows = $result_prod->fetch_all(MYSQLI_ASSOC);

    foreach ($rows as $row) {
        $products[] = $row;
    }

    foreach ($products as $key => $value) {
            $sql_mods = "SELECT mod_title, modifications.mod_id, qty, mod_photo, modifications.price,
                opt1, opt2, opt3
                FROM prod_mods JOIN modifications
                ON prod_mods.mod_id = modifications.mod_id JOIN options 
                ON options.mod_id = modifications.mod_id
                WHERE prod_id = $value[id]";

            $sql_params = "SELECT name 
            FROM prod_params JOIN params 
            ON prod_params.param_id = params.param_id
            WHERE prod_id = $value[id]";

            $result_mods = $conn->query($sql_mods);
            $result_params = $conn->query($sql_params);

            if ($result_mods) {
                $rows_mod = $result_mods->fetch_all(MYSQLI_ASSOC);
                $products[$key]['modifications'] = $rows_mod;
            }

            if($result_params) {
                $rows_params = $result_params->fetch_all(MYSQLI_ASSOC);

                foreach($rows_params as $val) {
                    $products[$key]['params'][] = $val[name];
                }
            }
        }

    print json_encode($products);
}
?>