<?php
header('Access-Control-Allow-Origin: http://localhost:3000');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require 'db_connect.php';
    $collection = $_GET['collection'];
    $products = [];

    function sendImageURL ($img_name) {
        $base_dir = __DIR__;
        $doc_root = preg_replace("!${_SERVER['SCRIPT_NAME']}$!", '', $_SERVER['SCRIPT_FILENAME']);
        $base_url = preg_replace("!^${doc_root}!", '', $base_dir);
        $protocol = empty($_SERVER['HTTPS']) ? 'http' : 'https';
        $port = $_SERVER['SERVER_PORT'];
        $disp_port = ($protocol == 'http' && $port == 80 || $protocol == 'https' && $port == 443) ? '' : ":$port";
        $domain = $_SERVER['SERVER_NAME'];
        $img_url = "${protocol}://${domain}${disp_port}${base_url}/assets/${img_name}";
        return $img_url;
    }

    $sql_prod = "SELECT id, name, main_photo, products.price, collection
            FROM Products WHERE collection = '$collection'";

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

            $sql_params = "SELECT name FROM params WHERE prod_id = $value[id]";

            $sql_product_photos = "SELECT img1, img2, img3, img4 FROM photos
            WHERE prod_id = $value[id]";

            $result_mods = $conn->query($sql_mods);
            $result_params = $conn->query($sql_params);
            $result_photos = $conn->query($sql_product_photos);

            if ($result_mods) {
                $rows_mod = $result_mods->fetch_all(MYSQLI_ASSOC);

                foreach($rows_mod as $m_key => $m_value) {
                    $mod_id = $m_value[mod_id];

                    $sql_mod_images = "SELECT img1, img2, img3, img4 FROM variant_images WHERE mod_id = $mod_id";
                    $result_mod_images = $conn->query($sql_mod_images);
                    $rows_mod_images = $result_mod_images->fetch_all(MYSQLI_ASSOC);

                    $rows_mod[$m_key]['mod_images'] = $rows_mod_images;
                }

                for ($i = 0; $i < count($rows_mod); $i++) {
                    $rows_mod[$i]['mod_photo'] = sendImageURL($rows_mod[0]['mod_photo']);
                }

                $products[$key]['modifications'] = $rows_mod;
            }

            if($result_params) {
                $rows_params = $result_params->fetch_all(MYSQLI_ASSOC);

                foreach($rows_params as $val) {
                    $products[$key]['params'][] = $val[name];
                }
            }

            if($result_photos) {
                $photo_params = $result_photos->fetch_all(MYSQLI_NUM);

                for ($i = 0; $i < count($photo_params[0]); $i++) {
                    $img = $photo_params[0][$i];

                    if ($img) {
                        $img_url = sendImageURL($img);
                        $GLOBALS["products"][$key]['images'][] = $img_url;
                    }
                }
            }
        }

 print json_encode($products);
}
?>