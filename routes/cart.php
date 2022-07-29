<?php
session_start();

require 'collection.php';

class Cart extends Product {
    public function set_id_session($product, $variant, $qty) {
        if ($variant) {
            if (!$_SESSION['products_variants']) {
                $_SESSION['products_variants'];
                $_SESSION['products_variants'][] = array('prod_id'=> $product, 'var_id' => $variant, 'qty' => $qty);
                return;
            }

            $session = 'products_variants';
            $id = $variant;
            $id_key = 'var_id';

            $exist_variant = $this->check_in_session($session, $id, $qty, $id_key);

            if (!$exist_variant) {
                $_SESSION['products_variants'][] = array('prod_id'=> $product, 'var_id' => $variant, 'qty' => $qty);
            }

            return;
        }

        if ($product && !$variant) {
            if (!$_SESSION['products']) {
                $_SESSION['products'][] = array('prod_id'=> $product, 'qty' => $qty);
                return;
            }

            $session = 'products';
            $id = $product;
            $id_key = 'prod_id';

            $exist_product = $this->check_in_session($session, $id, $qty, $id_key);

            if (!$exist_product) {
                $_SESSION['products'][] = array('prod_id'=> $product, 'qty' => $qty);
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

    public function getCartItems () {
        $variants = [];

        foreach ($_SESSION['products_variants'] as $key => $item) {
           $variants[$key] = $this->getVariants(null, $item[var_id])[0];
           $variants[$key]['name'] = $this->productName($item[prod_id]);
           $variants[$key]['line_quantity'] = $item['qty'];
        }

        print json_encode($variants);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   $cart = new Cart();
   $cart->set_id_session($_POST['product_id'], $_POST['variant_id'], $_POST['quantity']);
   $cart->getCartItems();
}

?>