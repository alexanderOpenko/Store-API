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
        if ($product && !isset($variant)) {  
            $cookie = 'products';
            $id = $product;
            $id_key = 'prod_id';

            $prod_name = $this->productInfo($product)['prod_name'];
            $available_qty = 5;
    
            $exist_product = $this->check_in_cookies_and_update_item_qty(
                $cookie,
                $id,
                'increase',
                $qty,
                $id_key,
                $available_qty,
                $prod_name
            );
    
            if ($exist_product) {
                return;
            }
    
            $available = $this->check_availability($available_qty);
            $warning = $this->check_available_quantity($available_qty, $qty);
            $product_index = count($_COOKIE['products']);
    
            if (!$available) {
                set_HTTP_status(400, ['unavailable' => $this->warnings['unavailable']], 0);
                return;
            }
    
            $item = new stdClass();
            $item->available = $available;
            $item->prod_id = $product;
            $item->qty = $qty;
            $item->warning = $warning;
    
            $_COOKIE["$cookie"][$product_index] = $item;
    
            $this->set_cookie('products', $product_index, $item);
            $body = $this->getCartItems();
    
            set_HTTP_status(200, "Add new cart item $prod_name", 10, $body);
    
            return;
           } else {
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
                set_HTTP_status(400, ['unavailable' => $this->warnings['unavailable']], 0);
                return;
            }

            $item = new stdClass();
            $item->available = $available;
            $item->prod_id = $product;
            $item->var_id = $variant;
            $item->qty = $qty;
            $item->warning = $warning;

            // print json_encode($warning)

            $_COOKIE["$cookie"][$variant_index] = $item;

            $this->set_cookie('products_variants', $variant_index, $item);
            $body = $this->getCartItems();

              set_HTTP_status(200, "Add new cart item $prod_name $target_variant[mod_title]", 10, $body);

            return;
        }
    }

    public function check_in_cookies_and_update_item_qty ($cookie, $id, $action, $qty, $id_key, $available_qty, $prod_name, $target_variant = null) {
        foreach ($_COOKIE["$cookie"] as $key => $item) { //if item not found by id then create new cookie. Otherwise increase or decrease
            if ($id !== $item->$id_key) {
                continue;
            }

            $available = $this->check_availability($available_qty);
            $item->available = $available;

            if ($action === 'increase') {
                $warning = $this->check_available_quantity($available_qty, $item->qty)[0];
                if ($warning) {
                    print_r($warning[0]);
                    $item->warning = $warning[0];
                    $this->delete_cookie($cookie, $key);
                    $this->set_cookie($cookie, $key, $item);
                    set_HTTP_status(400, $warning, 5);
                    return true;
                }

                $item->qty += $qty;

                // $warning = $this->check_available_quantity($available_qty, $item->qty);
                // $item->warning = $warning;

                $this->delete_cookie($cookie, $key);
                $this->set_cookie($cookie, $key, $item);
                $body = $this->getCartItems();

                set_HTTP_status(200, "Increased quantity of $prod_name $target_variant[mod_title]", 10, $body);
            } else {
                $item->qty -= 1;
                $item->warning = '';

                if ($item->qty == 0) {
                    $this->delete_cookie($cookie, $key);
                    unset($_COOKIE["$cookie"][$key]);
                    $body = $this->getCartItems();

                    if(!count($_COOKIE['products_variants']) && !count($_COOKIE['products'])) {
                        set_HTTP_status(200, $this->warnings['empty'], 0, []);
                        break;
                    } else {
                        set_HTTP_status(200, "Deleted cart item $prod_name $target_variant[mod_title]", 10, $body);
                    }
                } else {
                    $body = $this->getCartItems();
                    $this->delete_cookie($cookie, $key);
                    $this->set_cookie($cookie, $key, $item);
                    set_HTTP_status(200, "Decreased quantity of $prod_name $target_variant[mod_title]", 10, $body);
                }
            }
            return true; //item is found
        }
        return false; //item is not found
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

    public function getCartItems() {
        $products_variants = [];
        $products = [];

        if (count($_COOKIE['products_variants'])) {
            ksort($_COOKIE['products_variants']); 
            $products_variants = $this->getItemsByCookieName('products_variants');
        }

        if (count($_COOKIE['products'])) {
            ksort($_COOKIE['products']); 
            $products = $this->getItemsByCookieName('products');
        }

        $cart_items = array_merge($products_variants, $products);

        return $cart_items;
    }

    public function getItemsByCookieName ($cookie) {
        if ($cookie === 'products_variants') { 
        $variants = [];

        foreach ($_COOKIE["$cookie"] as $key => $item) {
            $variant = $this->getVariants(null, $item->var_id)[0];
            $available_qty = $variant['qty'];
            $cart_item_price = $variant['price'];
            $cart_item_warning = $this->check_available_quantity($available_qty, $item->qty);
            
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
    } else {
        $products = [];
    
        foreach ($_COOKIE["$cookie"] as $key => $item) {
            $product = $this->getProducts($item->prod_id, true)[0];
            $available_qty = 5;

            $cart_item_warning = $this->check_available_quantity($available_qty, $item->qty);
            $available = $this->check_availability($available_qty);

            if (count($cart_item_warning)) {
                $item->warning = $cart_item_warning[0];
                $this->delete_cookie($cookie, $key);
                $this->set_cookie($cookie, $key, $item);
            }
            $product['available'] = $available;
            $product['warning'] = $cart_item_warning[0];
            $product['prod_id'] = $item->prod_id;
            $product['line_quantity'] = $item->qty;
            $product['price'] = $product['price'] * $item->qty;

            $i = count($products);
            $products[$i] = $product;
        }

        return $products;
    }
       
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

    public function delete_cart_item($cookie, $id, $id_key, $prod_name, $target_variant = null) {
        foreach ($_COOKIE[$cookie] as $key => $item) {
            if ($id !== $item->$id_key) {
                continue;
            }
            $this->delete_cookie($cookie, $key);
            unset($_COOKIE["$cookie"][$key]);
            $body = $this->getCartItems();
            set_HTTP_status(200, "Deleted cart item $prod_name $target_variant[mod_title]", 10, $body);
        }
    }
}

function cart_route ($method, $url_list, $request_data) {
    $cart = new Cart();

    if (count($_COOKIE['products'])) {
        $cart->decode_cookie('products');
    }
    
    if (count($_COOKIE['products_variants'])) {
        $cart->decode_cookie('products_variants');
    }

    if ($method == 'POST') {
        $cart->set_id_session($request_data['product_id'], $request_data['variant_id'], $request_data['quantity']);
    } else if ($method == 'GET') {
        if (!count($_COOKIE["products_variants"]) && !count($_COOKIE["products"])) {
            set_HTTP_status(200, $cart->warnings['empty'], 0);
            die();
        }

        $cart_items = $cart->getCartItems();
        set_HTTP_status(200, count($cart_items) . " items in cart", 10, $cart_items);
    } else if ($method == 'PUT') {
        //$cookie, $id, $action, $qty, $id_key, $available_qty, $prod_name
        $action = $request_data->action;
        $prod_name = $cart->productInfo($request_data->product_id)['prod_name'];

        if (isset($request_data->variant_id)) {
            $cookie = 'products_variants';
            $id = $request_data->variant_id;
            $id_key = 'var_id';
            $target_variant = $cart->getVariants(null, $request_data->variant_id)[0];
            $available_qty = $target_variant['qty'];
        } else {
            $cookie = 'products';
            $id = $request_data->product_id;
            $id_key = 'prod_id';
            $available_qty = 5;
        }

        $cart->check_in_cookies_and_update_item_qty(
            $cookie,
            $id,
            $action,
            1,
            $id_key,
            $available_qty,
            $prod_name,
            $target_variant
        );
    } else if ($method == 'DELETE') {
        $prod_name = $cart->productInfo($request_data->product_id)['prod_name'];

        if (isset($request_data->variant_id)) {
            $target_variant = $cart->getVariants(null, $request_data->variant_id)[0];
        }

        if (isset($request_data->product_id) && isset($request_data->variant_id)) {  
            $cookie = 'products_variants';
            $id = $request_data->variant_id;
            $id_key = 'var_id';

            $cart->delete_cart_item($cookie, $id, $id_key, $prod_name, $target_variant);
        } else {
            $cookie = 'products';
            $id = $request_data->product_id;
            $id_key = 'prod_id';

            $cart->delete_cart_item($cookie, $id, $id_key, $prod_name, $target_variant);
        }       
    }
}
?>