<?php
header('Access-Control-Allow-Origin: http://localhost:3000');

require 'db_connect.php';
require 'upload_media.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sql_product = "INSERT INTO Products (name, collection, price)
        VALUES ('$_POST[product_name]', '$_POST[collection]', '$_POST[prod_price]')";

    $sql_images = $conn->prepare("INSERT INTO Photos (img1, img2, img3, img4)");

    $conn->query($sql_product);
    $last_prod_id = $conn->insert_id;

    $sql_create_photos_row = ("INSERT INTO photos (prod_id)
             VALUES ($last_prod_id)");

    $conn->query($sql_create_photos_row);

    for ($i = 0; $i < count($_FILES['productImages']['name']); $i++) {
        if ($i == 0) {
            $sql_product_main_photo = $conn->prepare("UPDATE Products SET main_photo = ? WHERE id = $last_prod_id");
            $sql_product_main_photo->bind_param('s', $_FILES['productImages']['name'][$i]);

            if (!$sql_product_main_photo->execute()) {
                printf("Сообщение ошибки: %s\n", $conn->error);
            }
        }

        if ($i == 0) {
            continue;
        }

        $opt = 'img' . +$i;
        $opt_value = $_FILES['productImages']['name'][$i];

        upload_image($opt_value, $_FILES['productImages']["tmp_name"][$i]);

        $sql_fill_options = $conn->prepare("UPDATE photos SET $opt = ? WHERE prod_id = $last_prod_id");

        $sql_fill_options->bind_param('s', $opt_value);

        $sql_fill_options->execute();
    }

    $mods = [];

    if ($_POST['modification']) {
        for ($i = 0; $i < count($_POST['modification']); $i++) {
            $mods[$i] = array('variant_title' => $_POST['modification'][$i]);
            $mods[$i]['variant_image'] = $_FILES['variant_images']['name'][$i];

            $variant_images_index = $i+1;

            for($vi = 0; $vi < count($_FILES["variant-images-$variant_images_index"]['name']); $vi++) {
                $name =  $_FILES["variant-images-$variant_images_index"]['name'][$vi];
                $tmp_name = $_FILES["variant-images-$variant_images_index"]['tmp_name'][$vi];
                $mods[$i]['images'][] = $name;
                upload_image($name, $tmp_name);
            }

            $mods[$i]['price'] = $_POST['price'][$i];
            $mods[$i]['qty'] = $_POST['qty'][$i];
            $mods[$i]['options'] = $_POST['options'][$i];
        }
    }
    print json_encode($mods);

    if (count($mods)) {
        foreach($mods as $value) {
            $sql_variants = "INSERT INTO Modifications (mod_title, qty, price)
            VALUES('$value[variant_title]', '$value[qty]', '$value[price]')";

            if (!$conn->query($sql_variants)) {
                printf("Сообщение ошибки: %s\n", $conn->error);
            }

            $last_mod_id = $conn->insert_id;

            //product mods manager
            $sql_fill_prod_mod_manager = "INSERT INTO prod_mods (prod_id, mod_id)
            VALUES($last_prod_id, $last_mod_id)";

            $conn->query($sql_fill_prod_mod_manager);

            $conn->query("INSERT INTO Options (mod_id) VALUES ($last_mod_id)");
            $last_opt_id = $conn->insert_id;

            $options = explode(', ', $value['options']);

            for ($oi = 0; $oi < count($options); $oi++) {
                $opt = 'opt' . +($oi + 1);
                $opt_value = $options[$oi];

                $sql_fill_options = $conn->prepare("UPDATE Options SET $opt = ? WHERE id = $last_opt_id");

                $sql_fill_options->bind_param('s', $opt_value);

                $sql_fill_options->execute();
            }

            $conn->query("INSERT INTO variant_images (mod_id) VALUES ($last_mod_id)");
            $last_variant_images_id = $conn->insert_id;

            for($mi = 0; $mi < count($value['images']); $mi++) {
                $opt = 'img' . +($mi + 1);
                $opt_value = $value['images'][$mi];

                $sql_fill_images = $conn->prepare("UPDATE variant_images SET $opt = ? WHERE id = $last_variant_images_id");

                $sql_fill_images->bind_param('s', $opt_value);

                $sql_fill_images->execute();
            }
        }
    }
    // set params
    foreach ($_POST['option_name'] as $value) {

        $sql_set_product_params = $conn->prepare("INSERT INTO Params (prod_id, name) VALUES(?, ?)");
        $sql_set_product_params->bind_param('is', $last_prod_id, $value);

        if (!$sql_set_product_params->execute()) {
            printf("Сообщение ошибки: %s\n", $conn->error);
        }
    }
}
?>


