<?php
function sendImageURL ($img_name) {
    $base_dir = __DIR__;
//    $doc_root = preg_replace("!${_SERVER['SCRIPT_NAME']}$!", '', $_SERVER['SCRIPT_FILENAME']);
//    $base_url = preg_replace("!^${doc_root}!", '', $base_dir);
    $protocol = empty($_SERVER['HTTPS']) ? 'http' : 'https';
    $port = $_SERVER['SERVER_PORT'];
    $disp_port = ($protocol == 'http' && $port == 80 || $protocol == 'https' && $port == 443) ? '' : ":$port";
    $domain = $_SERVER['SERVER_NAME'];
    $img_url = "${protocol}://${domain}${disp_port}${$base_dir}/assets/${img_name}";

    return $img_url;
}

class Product {
    public $collection;
    public $products = [];

    public function __construct($collection = null){
        $this->collection = $collection;

        if ($this->collection) {
            $this->start();
        }
    }

    public function start () {
        $this->getProducts();
    }

    public function sql_fetch_all ($sql_string, $fetch_type) {
        global $conn;

        $rows = $conn->query($sql_string);
        if (!$rows) {
            print json_encode($conn->error);
        }

        return $rows->fetch_all($fetch_type);
    }

    public function getVariants ($prod_id = null, $mod_id = null) {
        if (!$mod_id) {
            $sql_mods = "SELECT mod_title, modifications.mod_id, qty, modifications.price,
              opt1, opt2, opt3
              FROM prod_mods JOIN modifications
              ON prod_mods.mod_id = modifications.mod_id JOIN options
              ON options.mod_id = modifications.mod_id
              WHERE prod_id = $prod_id"; //get all variants of target product
        } else {
            $sql_mods = "SELECT mod_title, modifications.mod_id, qty, price, opt1, opt2, opt3
               FROM modifications JOIN options
               ON options.mod_id = modifications.mod_id
               WHERE modifications.mod_id = $mod_id"; //get single variant by
        }

      $mods = $this->sql_fetch_all($sql_mods, MYSQLI_ASSOC);

        if ($mods) {
            //set images for variants
            foreach($mods as $m_key => $m_value) {
                $mod_id = $m_value['mod_id'];

                $sql_mod_images = "SELECT img1, img2, img3, img4 FROM variant_images WHERE mod_id = $mod_id";

                $rows_mod_images = $this->sql_fetch_all($sql_mod_images, MYSQLI_NUM);

                for ($i = 0; $i < count($rows_mod_images[0]); $i++) {
                    $img = $rows_mod_images[0][$i];

                    if ($img) {
                        $img_url = sendImageURL($img);
                        $mods[$m_key]['mod_images'][] = $img_url;
                    }
                }
            }
        }

        return $mods;
    }

    public function productInfo ($prod_id) {
        $sql_prod_name = "SELECT name FROM Products WHERE id = $prod_id";
        $prod_row = $this->sql_fetch_all($sql_prod_name, MYSQLI_ASSOC);

        $sql_prod_params = "SELECT name FROM Params WHERE prod_id = $prod_id";
        $params_rows = $this->sql_fetch_all($sql_prod_params, MYSQLI_ASSOC);

        return array('prod_name' => $prod_row[0]['name'], 'params' => $params_rows);
    }

    public function getProducts ($product_id = null, $cart_item = null) {
        if ($product_id) {
            $sql_prod = "SELECT id, name, main_photo, products.price, collection
            FROM Products WHERE id = '$product_id'";
        } else {
            $sql_prod = "SELECT id, name, main_photo, products.price, collection
            FROM Products WHERE collection = '$this->collection'";
        }

        $this->products = $this->sql_fetch_all($sql_prod, MYSQLI_ASSOC);

        foreach ($this->products as $key => $value) {
            $opt1 = [];
            $opt2 = [];
            $opt3 = [];
            $this->products[$key]['main_photo'] = sendImageURL($this->products[$key]['main_photo']);
            $mods = $this->getVariants($value['id']);
            $this->products[$key]['modifications'] = $mods;

            foreach ($mods as $v) {
                if (!in_array($v['opt1'], $opt1)) {
                    $opt1[] = $v['opt1'];
                }

                if (!in_array($v['opt2'], $opt2)) {
                    $opt2[] = $v['opt2'];
                }

                if (!in_array($v['opt3'], $opt3)) {
                    $opt3[] = $v['opt3'];
                }
            }

            $this->products[$key]['options'][] = $opt1;
            $this->products[$key]['options'][] = $opt2;
            $this->products[$key]['options'][] = $opt3;


            $sql_params = "SELECT name FROM params WHERE prod_id = $value[id]";
            $prod_params = $this->sql_fetch_all($sql_params, MYSQLI_ASSOC);

            if ($prod_params) {
                foreach ($prod_params as $val) {
                    $this->products[$key]['params'][] = $val['name'];
                }
            }

            $sql_product_images = "SELECT img1, img2, img3, img4 FROM photos
            WHERE prod_id = $value[id]";

            $product_images = $this->sql_fetch_all($sql_product_images, MYSQLI_NUM);

            if ($product_images) {
                for ($i = 0; $i < count($product_images[0]); $i++) {
                    $img = $product_images[0][$i];

                    if ($img) {
                        $img_url = sendImageURL($img);
                        $this->products[$key]['images'][] = $img_url;
                    }
                }
            }
        }

        if ($cart_item) {
            return $this->products;
        } else {
            print json_encode($this->products); 
        }
    }
}

function collection_route ($method, $url_list, $request_data) {
    $product = new Product;
    if ($method == 'GET') {
        if ($request_data->parameters['collection']) {
            new Product($request_data->parameters['collection']);
        } else {
            $product->getProducts($request_data->parameters['product_id']);
        }
    } else {
        //error
    }
}
?>