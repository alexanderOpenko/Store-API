<?php
session_start();
require 'collection.php';

class Cart extends Product {
    public function set_id_session($product, $variant, $qty) {
        if ($variant) {
            if (!$_SESSION['products_variants']) {
                $_SESSION['products_variants'];
                $_SESSION['products_variants'][] = array('prod_id' => $product, 'var_id' => $variant, 'qty' => $qty);
                return;
            }

            $session = 'products_variants';
            $id = $variant;
            $id_key = 'var_id';

            $exist_variant = $this->check_in_session($session, $id, $qty, $id_key);

            if (!$exist_variant) {
                $_SESSION['products_variants'][] = array('prod_id' => $product, 'var_id' => $variant, 'qty' => $qty);
            }

            return;
        }

        if ($product && !$variant) {
            if (!$_SESSION['products']) {
                $_SESSION['products'][] = array('prod_id' => $product, 'qty' => $qty);
                return;
            }

            $session = 'products';
            $id = $product;
            $id_key = 'prod_id';

            $exist_product = $this->check_in_session($session, $id, $qty, $id_key);

            if (!$exist_product) {
                $_SESSION['products'][] = array('prod_id' => $product, 'qty' => $qty);
            }
        }
    }

    public function check_in_session($session, $id, $qty, $id_key) {
        foreach ($_SESSION["$session"] as $key => $item) {
            if ($id === $item[$id_key]) {
                $_SESSION["$session"][$key][qty] += $qty;
                return true;
            }
        }

        return false;
    }

    public function check_availability($item, $line_qty) {
        $item['available'] = true;

        if ($line_qty == $item[qty]) {
            $item['warnings']['cart'] = "last count of items";
        }

        if ($line_qty > $item[qty]) {
            $item['warnings']['qty'] = "only $item[qty] items available";
            $item['available'] = false;
        }

        return $item;
    }

    public function getCartItems() {
        $variants = [];

        foreach ($_SESSION['products_variants'] as $key => $item) {
            $variant = $this->getVariants(null, $item[var_id])[0];
            $variant = $this->check_availability($variant, $item[qty]);
            $variants[$key] = $variant;
            $variants[$key]['name'] = $this->productName($item[prod_id]);
            $variants[$key]['line_quantity'] = $item[qty];
        }

        print json_encode($variants);
    }
}

function cart_route ($method, $url_list, $request_data) {
    if ($method == 'POST') {
        $cart = new Cart();
        $cart->set_id_session($request_data['product_id'], $request_data['variant_id'], $request_data['quantity']);
        $cart->getCartItems();
        print_r($_COOKIE['products_variants']);
    } else {
        //error
    }
}
?>