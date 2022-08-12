<?php
require 'collection.php';

class Cart extends Product {
    public $warnings = array(
        'cart' => 'Last count of items',
        'qty' => 'Available items count is only: ',
        'unavailable' => 'Out of stock'
    );

    public function set_id_session($product, $variant, $qty) {
        if ($variant) {
            $cookie = 'products_variants';
            $id = $variant;
            $id_key = 'var_id';
            $prod_name = $this->productInfo($product)['prod_name'];
            $target_variant = $this->getVariants(null, $id)[0];
            $available_qty = $target_variant['qty']; //?

            $exist_variant = $this->check_in_cookies_and_update_item_qty(
                $cookie,
                $id,
                'increase',
                $qty,
                $id_key,
                $available_qty,
                $prod_name,
                $target_variant
            );

            if (!$exist_variant) {
                $available = $this->check_availability($available_qty);
                $warning = $this->check_available_quantity($available_qty, $qty);
                $variant_index = count($_COOKIE['products_variants']);

                if (!$available) {
                    set_HTTP_status(400, $this->warnings['unavailable']);
                }

                $item = array(
                    'available' => $available,
                    'prod_id' => $product,
                    'var_id' => $variant,
                    'qty' => $qty,
                    'warning' => $warning
                );

                setcookie("products_variants[$variant_index]", json_encode($item));
                print json_encode(["Add new cart item $prod_name $target_variant[mod_title]"]);
            }
            return;
        }
//        if ($product && !$variant) {
//            if (!$_SESSION['products']) {
//                $_SESSION['products'][] = array('prod_id' => $product, 'qty' => $qty);
//                return;
//            }
//
//            $session = 'products';
//            $id = $product;
//            $id_key = 'prod_id';
//            $available_qty = $this->productInfo($id)[qty];
//
//            $exist_product = $this->check_in_cookies_and_update_item_qty($session, $id, $qty, $id_key, $available_qty);
//
//            if (!$exist_product) {
//                list($available, $warning) = $this->check_availability($available_qty, $qty);
//                $_SESSION['products'][] = array(
//                    'available' => $available,
//                    'prod_id' => $product,
//                    'qty' => $qty,
//                    'warning' => $warning
//                );
//            }
//        }
    }

    public function check_in_cookies_and_update_item_qty($cookie, $id, $action, $qty, $id_key, $available_qty, $prod_name, $target_variant) {
        for ($i = 0; $i < count($_COOKIE["$cookie"]); $i++) {
            $_COOKIE["$cookie"][$i] = json_decode($_COOKIE["$cookie"][$i]);
        }

        foreach ($_COOKIE["$cookie"] as $key => $item) {
            if ($id !== $item->$id_key) {
               continue;
            }

            $available = $this->check_availability($available_qty);
            $warning = $this->check_available_quantity($available_qty, $item->qty);
            $item->available = $available;
            if ($warning) {
                $item->warning = $warning[0];
                $this->delete_cookie($cookie, $key);
                $this->set_cookie($cookie, $key, $item);
                set_HTTP_status(400, $warning[0]);
                return true;
            }

            if ($action === 'increase') {
                $item->qty += $qty;
                set_HTTP_status(200, "Update quantity of $prod_name $target_variant[mod_title]");
            } else {
                $item->qty -= 1;
                //?`
            }

            $this->delete_cookie($cookie, $key);
            $this->set_cookie($cookie, $key, $item);
            $warning = $this->check_available_quantity($available_qty, $item->qty);
            if ($warning) {
                $item->warning = $warning[0];
                $this->delete_cookie($cookie, $key);
                $this->set_cookie($cookie, $key, $item);
            }
            return true;
        }
        return false;
    }

    public function check_availability($qty) {
        $available = true;

        if ($qty == 0) {
            $available = false;
        }

        return $available;
    }

    public function check_available_quantity($qty, $line_qty) {
        $warning = [];

        if ($qty == 0) {
            $warning['unavailable'] = $this->warnings['unavailable'];
            return array($warning);
        }

        if ($qty == $line_qty) {
            $warning['cart'] = $this->warnings['cart'];
            return array($warning);
        }

        if ($line_qty > $qty) {
            $warning['qty'] = $this->warnings['qty'] . $qty;
            return array($warning);
        }
    }

    public function getCartItems($cookie) {
//        print_r($_COOKIE["$cookie"]);
        for ($i = 0; $i < count($_COOKIE["$cookie"]); $i++) {
            $_COOKIE["$cookie"][$i] = json_decode($_COOKIE["$cookie"][$i]);
        }

        $variants = [];

//        foreach ($_COOKIE["$cookie"] as $key => $item) {
        for ($i = 0; $i < count($_COOKIE["$cookie"]); $i++) {
            $item = $_COOKIE["$cookie"][$i];
            $variant = $this->getVariants(null, $item->var_id)[0];
            $available_qty = $variant['qty'];
            $warning = $this->check_available_quantity($available_qty, $item->qty);
            $available = $this->check_availability($available_qty);

            $variant['warning'] = $warning[0];
            $variant['available'] = $available;
            $variant['name'] = $this->productInfo($item->prod_id)['prod_name'];
            $variant['prod_id'] = $item->prod_id;
            $params = $this->productInfo($item->prod_id)['params'];

            for ($pi = 0; $pi < count($params); $pi++) {
                $opt_name = $params[$pi]['name'];
                $opt = 'opt' . ($pi + 1);
                $variant['options'][] = ["$opt_name" => $variant[$opt]];
            }

            $oi = 1;

            do {
                $opt = 'opt' . $oi;
                unset($variant[$opt]);
                $oi++;
            } while ($oi <= 3);

            $variant['line_quantity'] = $item->qty;
            $variants[$i] = $variant;
        }

        print json_encode($variants);
    }

    public function delete_cookie ($cookie, $index) {
        setcookie("$cookie" . "[$index]", '', time() - 1);
    }

    public function set_cookie ($cookie, $index, $item) {
        setcookie("$cookie" . "[$index]", json_encode($item));
    }
}

function cart_route ($method, $url_list, $request_data) {
    $cart = new Cart();

    if ($method == 'POST') {
        $cart->set_id_session($request_data['product_id'], $request_data['variant_id'], $request_data['quantity']);
    } else if ($method == 'GET') {
        $cart->getCartItems('products_variants');
    } else if ($method == 'DELETE') {
        print json_encode($request_data->variant_id);
    }
}
?>