<?php
function product_validation($type, $fields) {
    list('prod_name' => $prod_name,
        'collection' => $collection,
        'price' => $price,
        'variant' => $variant,
        'qty' => $qty,
        'options' => $options
        ) = $fields;

    $errors = [];

    if ($prod_name && strlen(trim($prod_name)) < 4) {
        $errors['product'] = "Product name must be not less 4 characters";
    }

    if ($collection && strlen(trim($collection)) < 4) {
        $errors['collection'] = "Collection must be not less 4 characters";
    }

    if ($price) {
        if (strlen(trim($price)) > 10) {
            $errors['price'][] = "$type $variant price must be not longer 10 characters";
        }

        if (strpos(trim($price), ' ')) {
            $errors['price'][] = "$type $variant price must not contain spaces";
        }

        if (!is_numeric(str_replace(' ', '', $price))) {
            $errors['price'][] = "$type $variant price must be a number";
        }
    }

    if ($qty) {
        if (strpos(trim($qty), ' ')) {
            $errors['quantity'][] = "$type $variant quantity must not contain spaces";
        }

        if (!is_numeric(str_replace(' ', '', $qty))) {
            $errors['quantity'][] = "$type $variant quantity must be a number";
        }
    }

    if ($options) {
        foreach ($options as $value) {
            if (strpos($value, ' ')) {
                $errors['options'][] = "$type $variant option '$value' must not contain spaces";
            }
        }
    }

    return $errors ? $errors : [];
}

function sql_error_handling () {
    global $conn;

    switch (true) {
        case ($conn->errno == 1146 || $conn->errno == 1054):
            set_HTTP_status('410', "SQL error: $conn->error");
            break;
        default: set_HTTP_status('500', "SQL error: $conn->error");
    }
}
?>