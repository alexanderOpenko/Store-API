<?php
require 'upload_media.php';

class New_product {
    public $data;
    public $mods = [];
    public $last_prod_id;

    public function __construct($data) {
        $this->data = $data;

        if (!$data) {
            //error
            exit();
        }

        if ($data['product_name']) {
            $this->insert_product();
        }

        if ($data['modification']) {
            $this->insert_variants();
        }
    }

    public function set_multiple_images ($images, $table, $row, $id) {
            global $conn;

            for ($i = 0; $i < count($_FILES[$images]['name']); $i++) {
            $opt = 'img' . +($i+1);
            $opt_value = $_FILES[$images]['name'][$i];

            upload_image($opt_value, $_FILES[$images]["tmp_name"][$i]);

            $sql_fill_options = $conn->prepare("UPDATE $table SET $opt = ? WHERE $row = $id");

            $sql_fill_options->bind_param('s', $opt_value);

            if(!$sql_fill_options->execute()) {
                print json_encode('execute() failed:' . $conn->error);
            }
            }
    }

    public function insert_product () {
        global $conn;

        $sql_product = $conn->prepare("INSERT INTO Products (name, collection, price)
                VALUES (?, ?, ?)");

        $sql_product->bind_param('ssi', $this->data['product_name'], $this->data['collection'], $this->data['prod_price']);

        if(!$sql_product->execute()) {
            print json_encode("product error: $conn->error");
        }

        $this->last_prod_id = $conn->insert_id;

         $sql_product_main_photo = $conn->prepare("UPDATE Products SET main_photo = ? WHERE id = $this->last_prod_id");
            $sql_product_main_photo->bind_param('s', $_FILES['productImages']['name'][0]);

            if (!$sql_product_main_photo->execute()) {
                print json_encode("main image error: $conn->error");
            }

           if (count($_FILES['productImages']['name']) > 1) {
               $sql_create_photos_row = ("INSERT INTO photos (prod_id) VALUES ($this->last_prod_id)");
               $conn->query($sql_create_photos_row);

               $this->set_multiple_images('productImages', 'photos', 'prod_id', $this->last_prod_id);
            }

        foreach ($_POST['option_name'] as $value) {
            $sql_set_product_params = $conn->prepare("INSERT INTO Params (prod_id, name) VALUES(?, ?)");
            $sql_set_product_params->bind_param('is', $this->last_prod_id, $value);

            if (!$sql_set_product_params->execute()) {
                print json_encode($conn->error);
            }
        }
    }

    public function insert_variants () {
        for ($i = 0; $i < count($this->data['modification']); $i++) {
            $this->mods[$i] = array('variant_title' => $this->data['modification'][$i]);
            $this->mods[$i]['price'] = $this->data['price'][$i];
            $this->mods[$i]['qty'] = $this->data['qty'][$i];
            $this->mods[$i]['options'] = $this->data['options'][$i];
        }

        foreach($this->mods as $key => $value) {
            global $conn;

            $sql_variants = $conn->prepare("INSERT INTO Modifications (mod_title, qty, price)
            VALUES(?, ?, ?)");

            $sql_variants->bind_param('sii', $value['variant_title'],$value['qty'], $value['price']);

            if (!$sql_variants->execute()) {
                print json_encode($conn->error);
            }

            $last_mod_id = $conn->insert_id;

            //product mods manager
            $sql_fill_prod_mod_manager = "INSERT INTO prod_mods (prod_id, mod_id)
            VALUES($this->last_prod_id, $last_mod_id)";

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

            $variant_images_index = $key + 1;


            $this->set_multiple_images("variant-images-$variant_images_index", 'variant_images', 'mod_id', $last_mod_id);
        }
    }
}

function product_route ($method, $url_list, $request_data) {
    if ($method == 'POST') {
        new New_product($request_data);

    } else {
        //error
    }
}
?>


