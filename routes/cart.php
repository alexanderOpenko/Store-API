<?php
require 'collection.php';

class Cart extends Product {
    public $warnings = array(
        'cart' => 'Last count of items',
        'qty' => 'Available items count is only: ',
        'unavailable' => 'out of stock'
    );

    public function set_id_session($product, $variant, $qty) {
        if ($variant) {
            if (!$_SESSION['products_variants']) {
                $_SESSION['products_variants'];
            }

            $session = 'products_variants';
            $id = $variant;
            $id_key = 'var_id';
            $available_qty = $this->getVariants(null, $id)[0]['qty']; //?

            $exist_variant = $this->check_in_session($session, $id, $qty, $id_key, $available_qty);

            if (!$exist_variant) {
                $available = $this->check_availability($available_qty);
                $warning = $this->check_available_quantity($available_qty, $qty);

                if (!$warning) {
                    $warning = [];
                }

                $_SESSION['products_variants'][] = array(
                    'available' => $available,
                    'prod_id' => $product,
                    'var_id' => $variant,
                    'qty' => $qty,
                    'warning' => $warning
                );
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
            $available_qty = $this->productInfo($id)[qty];

            $exist_product = $this->check_in_session($session, $id, $qty, $id_key, $available_qty);

            if (!$exist_product) {
                list($available, $warning) = $this->check_availability($available_qty, $qty);
                $_SESSION['products'][] = array(
                    'available' => $available,
                    'prod_id' => $product,
                    'qty' => $qty,
                    'warning' => $warning
                );
            }
        }
    }

    public function check_in_session($session, $id, $qty, $id_key, $available_qty) {
        foreach ($_SESSION["$session"] as $key => $item) {
            if ($id === $item[$id_key]) {
                $available = $this->check_availability($available_qty);
                $warning = $this->check_available_quantity($available_qty, $item['qty']);
                $_SESSION["$session"][$key]['available'] = $available;

                if (!$warning) {
                    $_SESSION["$session"][$key]['qty'] += $qty;
                    $warning = $this->check_available_quantity($available_qty, $_SESSION["$session"][$key]['qty']);

                    if($warning) {
                        $_SESSION["$session"][$key]['warning'] = $warning[0];
                    }
                } else {
                    $_SESSION["$session"][$key]['warning'] = $warning[0];
                }
                return true;
            }
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

    public function getCartItems() {
        $variants = [];

        foreach ($_SESSION['products_variants'] as $key => $item) {
            $variant = $this->getVariants(null, $item[var_id])[0];
            $variants[$key] = $variant;
            $variants[$key]['warning'] = $item['warning'];
            $variants[$key]['available'] = $item['available'];
            $variants[$key]['name'] = $this->productInfo($item[prod_id])[prod_name];
            $params = $this->productInfo($item[prod_id])[params];

            for ($i = 0; $i < count($params); $i++) {
                $opt_name = $params[$i]['name'];
                $opt = 'opt' . ($i + 1);
                $variants[$key]['options'][] = ["$opt_name" => $variants[$key][$opt]];
            }

            $i = 1;

            do {
                $opt = 'opt' . $i;
                unset($variants[$key][$opt]);
                $i++;
            } while ($i <= 3);

            $variants[$key]['line_quantity'] = $item[qty];
        }

        print json_encode($variants);
    }

    public function relevance_check($session) {
        foreach ($_SESSION["$session"] as $key => $item) {
            $available_qty = $this->getVariants(null, $item['var_id'])[0]['qty'];
            $warning = $this->check_available_quantity($available_qty, $item['qty']);
            $available = $this->check_availability($available_qty);

            if($warning) {
                $_SESSION["$session"][$key]['warning'] = $warning[0];
            }
            $_SESSION["$session"][$key]['available'] = $available;
        }
    }
}

function cart_route ($method, $url_list, $request_data) {
    session_start();

    $cart = new Cart();

    if ($method == 'POST') {
        $cart->set_id_session($request_data['product_id'], $request_data['variant_id'], $request_data['quantity']);
        $cart->getCartItems();
    } else if ($method == 'GET') {
        $cart->relevance_check('products_variants');
        $cart->getCartItems();
    } else if ($method == 'DELETE') {
        print json_encode($request_data->variant_id);
    }
}
?>