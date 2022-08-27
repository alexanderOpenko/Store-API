<?php
require 'collection.php';

class Cart extends Product {
    public $warnings = array(
        'cart' => 'Last count of items',
        'qty' => 'Available count is only: ',
        'unavailable' => 'Out of stock',
        'empty' => 'Cart is empty'
    );

    public function set_id_session($product, $variant, $qty) {
        if ($variant) {
            $cookie = 'products_variants';
            $id = $variant;
            $id_key = 'var_id';
            $prod_name = $this->productInfo($product)['prod_name'];
            $target_variant = $this->getVariants(null, $id)[0];
            $available_qty = $target_variant['qty'];

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

            if($exist_variant) {
                return;
            }

            $available = $this->check_availability($available_qty);
            $warning = $this->check_available_quantity($available_qty, $qty);
            $variant_index = count($_COOKIE['products_variants']);

            if (!$available) {
                set_HTTP_status(400, $this->warnings['unavailable'], 0);
                return;
            }

            $item = new stdClass();
            $item->available = $available;
            $item->prod_id = $product;
            $item->var_id = $variant;
            $item->qty = $qty;
            $item->warning = $warning;

            $_COOKIE["$cookie"][$variant_index] = $item;
            $this->set_cookie('products_variants', $variant_index, $item);
            $body = $this->getCartItems('products_variants');

              set_HTTP_status(200,
                  "Add new cart item $prod_name $target_variant[mod_title]",
                  10,
              $body
              );

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
//        ksort($_COOKIE["$cookie"]);
        foreach ($_COOKIE["$cookie"] as $key => $item) {
            if ($id !== $item->$id_key) {
                continue;
            }
            $available = $this->check_availability($available_qty);
            $item->available = $available;

            if ($action === 'increase') {
                $warning = $this->check_available_quantity($available_qty, $item->qty);
                if ($warning) {
                    $item->warning = $warning[0];
                    $this->delete_cookie($cookie, $key);
                    $this->set_cookie($cookie, $key, $item);
                    set_HTTP_status(400, $warning[0], 5);
                    return true;
                }

                $item->qty += $qty;

                $this->delete_cookie($cookie, $key);
                $this->set_cookie($cookie, $key, $item);
                $body = $this->getCartItems('products_variants');

                set_HTTP_status(200,
                    "Increased quantity of $prod_name $target_variant[mod_title]",
                    10,
                    $body
                );
            } else {
                $item->qty -= 1;
                $item->warning = '';

                if ($item->qty == 0) {
                    $this->delete_cookie($cookie, $key);
                    unset($_COOKIE["$cookie"][$key]);
                    $body = $this->getCartItems('products_variants');

                    if(!count($_COOKIE["$cookie"])) {
                        set_HTTP_status(200, $this->warnings['empty'], 0, []);
                        break;
                    } else {
                        set_HTTP_status(200, "Deleted cart item $prod_name $target_variant[mod_title]", 10, $body);
                    }
                } else {
                    $body = $this->getCartItems('products_variants');
                    $this->delete_cookie($cookie, $key);
                    $this->set_cookie($cookie, $key, $item);
                    set_HTTP_status(200, "Decreased quantity of $prod_name $target_variant[mod_title]", 10, $body);
                }
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
        ksort($_COOKIE["$cookie"]);
        $variants = [];
        global $cart_item_price;
        global $cart_item_warning;
        global $available_qty;

        foreach ($_COOKIE["$cookie"] as $key => $item) {
            if($item->var_id) {
                $variant = $this->getVariants(null, $item->var_id)[0];
                $available_qty = $variant['qty'];
                $cart_item_price = $variant['price'];
                $cart_item_warning = $this->check_available_quantity($available_qty, $item->qty);
            }
            $available = $this->check_availability($available_qty);

            if (count($cart_item_warning)) {
                $item->warning = $cart_item_warning[0];
                $this->delete_cookie($cookie, $key);
                $this->set_cookie($cookie, $key, $item);
            }
            $variant['warning'] = $cart_item_warning[0];
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
            $variant['price'] = $cart_item_price * $item->qty;
            $i = count($variants); //$key is not indexed
            $variants[$i] = $variant;
        }
        return $variants;
    }

    public function delete_cookie ($cookie, $index) {
        setcookie("$cookie" . "[$index]", '', time() - 1);
    }

    public function set_cookie ($cookie, $index, $item) {
        setcookie($cookie . "[$index]", json_encode($item));
    }

    public function decode_cookie ($cookies) {
//        print_r($_COOKIE["$cookies"]);
        foreach ($_COOKIE["$cookies"] as $key => $item) {
            $_COOKIE["$cookies"][$key] = json_decode($_COOKIE["$cookies"][$key]);
        }
    }
}

function cart_route ($method, $url_list, $request_data) {
    $cart = new Cart();

    if ($method == 'POST') {
        $cart->decode_cookie('products_variants');
        $cart->set_id_session($request_data['product_id'], $request_data['variant_id'], $request_data['quantity']);
    } else if ($method == 'GET') {
        if (!count($_COOKIE["products_variants"])) {
            set_HTTP_status(200, $cart->warnings['empty'], 0);
            die();
        }
        $cart->decode_cookie('products_variants');
        $variants = $cart->getCartItems('products_variants');
        set_HTTP_status(200, count($variants) . " items in cart", 10, $variants);
    } else if ($method == 'PUT') {
        $cart->decode_cookie('products_variants');
        $action = $request_data->action;
        $prod_name = $cart->productInfo($request_data->product_id)['prod_name'];
        $target_variant = $cart->getVariants(null, $request_data->variant_id)[0];
        $available_qty = $target_variant['qty'];

        $cart->check_in_cookies_and_update_item_qty(
            'products_variants',
            $request_data->variant_id,
            $action,
            1,
            'var_id',
            $available_qty,
            $prod_name,
            $target_variant
        );
    } else if
    ($method == 'DELETE') {
        $cart->decode_cookie('products_variants');
        foreach ($_COOKIE['products_variants'] as $key => $item) {
            if ($request_data->variant_id !== $item->var_id) {
                continue;
            }
                $prod_name = $cart->productInfo($request_data->product_id)['prod_name'];
                $target_variant = $cart->getVariants(null, $request_data->variant_id)[0];
                $available_qty = $target_variant['qty'];

                if ($request_data->delete_item) {
                    $cart->delete_cookie($request_data->variant_id, $key);
                    set_HTTP_status(200, "Deleted cart item $prod_name $target_variant[mod_title]", 10);
                    return;
                }

                $cart->check_in_cookies_and_update_item_qty(
                    'products_variants',
                    $request_data->variant_id,
                    'decrease',
                    1,
                    'var_id',
                    $available_qty,
                    $prod_name,
                    $target_variant
                );
        }
        $cart->getCartItems('products_variants');
    }
}
?>