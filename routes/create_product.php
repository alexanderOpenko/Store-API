<?php
require 'upload_media.php';
require 'validators.php';

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

            for ($i = 1; $i < count($_FILES[$images]['name']); $i++) {
            $opt = 'img' . $i;
            $opt_value = $_FILES[$images]['name'][$i];

            upload_image($opt_value, $_FILES[$images]["tmp_name"][$i]);

            $sql_fill_options = $conn->prepare("UPDATE $table SET $opt = ? WHERE $row = $id");

            if(!$sql_fill_options) {
                sql_error_handling();
                return;
            }

            $sql_fill_options->bind_param('s', $opt_value);

            if(!$sql_fill_options->execute()) {
                sql_error_handling();
            }
            }
    }

    public function insert_product () {
        global $conn;

        $errors = product_validation('product', [
            'prod_name' => $this->data['product_name'],
            'collection' => $this->data['collection'],
            'price' => $this->data['prod_price']
        ]);

        if (!strlen($this->data['prod_price'])) {
            $errors['price'][] = "Product {$this->data['product_name']} has no price";
        }

        if ($errors) {
            set_HTTP_status('400', $errors);
            die();
        }

        $sql_product = $conn->prepare("INSERT INTO Products (name, collection, price)
                VALUES (?, ?, ?)");

        if (!$sql_product) {
            sql_error_handling();
            return;
        }

        $prod_name = trim($this->data['product_name']);
        $collection = trim($this->data['collection']);
        $prod_price = trim($this->data['prod_price']);

        $sql_product->bind_param('ssi',$prod_name, $collection, $prod_price);

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

           if ($this->data['option_name']) {
               foreach ($this->data['option_name'] as $value) {

                   $sql_set_product_params = $conn->prepare("INSERT INTO Params (prod_id, name) VALUES(?, ?)");

                   $sql_set_product_params->bind_param('is', $this->last_prod_id, $value);

                   if (!$sql_set_product_params->execute()) {
                       print json_encode($conn->error);
                   }
               }
           }
    }

    public function insert_variants () {
        $variants_errors = [];

        for ($i = 0; $i < count($this->data['modification']); $i++) {
            $this->mods[$i] = array('variant_title' => $this->data['modification'][$i]);
            $this->mods[$i]['price'] = $this->data['price'][$i];
            $this->mods[$i]['qty'] = $this->data['qty'][$i];
            $this->mods[$i]['options'] = $this->data['options'][$i];
        }

        foreach($this->mods as $key => $value) {
            $options = $value['options'] ? explode(', ', $value['options']) : [];

            global $conn;

            if (!strlen(trim($value['price']))) {
                $variants_errors['price'][] = "Variant $value[variant_title] has no price";
            }

            $errors = product_validation('variant', [
                'variant' => $value['variant_title'],
                'price' => $value['price'],
                'qty' => $value['qty'],
                'options' => $options
            ]);

            if ($errors) {
                foreach ($errors as $er => $er_value) {
                    $variants_errors[$er][] = $er_value;
                }
                continue;
            }

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

            if ($options) {
                $conn->query("INSERT INTO Options (mod_id) VALUES ($last_mod_id)");
                $last_opt_id = $conn->insert_id;

                for ($oi = 0; $oi < count($options); $oi++) {
                    $opt = 'opt' . +($oi + 1);
                    $opt_value = $options[$oi];

                    $sql_fill_options = $conn->prepare("UPDATE Options SET $opt = ? WHERE id = $last_opt_id");
                    $sql_fill_options->bind_param('s', $opt_value);
                    $sql_fill_options->execute();
                }
            }

            $variant_images_index = $key + 1;

            if ($_FILES["variant-images-$variant_images_index"]) {
                $conn->query("INSERT INTO variant_images (mod_id) VALUES ($last_mod_id)");

                $this->set_multiple_images("variant-images-$variant_images_index", 'variant_images', 'mod_id', $last_mod_id);
            }
        }

        if ($variants_errors) {
            set_HTTP_status('400', $variants_errors);
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


