<?php
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');

global $conn;
require 'headers.php';

$conn = new mysqli('localhost:8889', 'root', 'root', 'products');

if ($conn->connect_error) {
    set_HTTP_status('500', 'DB connection error: ', $conn->connect_error);
}

function getData($method) {
    $data = new stdClass();
    $data->parameters = [];

    if ($method == 'POST') {
        return $_POST;
    } else if ($method == 'GET') {
        foreach ($_GET as $key => $value) {
            if ($key != 'q') {
                $data->parameters[$key] = $value;
            }
        }
    } else {
        return json_decode(file_get_contents('php://input'));
    }

    return $data;
}

function method() {
    return $_SERVER['REQUEST_METHOD'];
}

$url = rtrim(isset($_GET['q']) ? $_GET['q'] : '', "/");
$url_list = explode('/', $url);
$route = $url_list[0];
$method = method();
$request_data = getData(method());

if (dirname(__FILE__) . '/routes/' . $route . '.php') {
    include_once 'routes/' . $route . '.php';

    switch ($route) {
        case 'cart':
            cart_route($method, $url_list, $request_data);
            break;
        case 'collection':
            collection_route($method, $url_list, $request_data);
            break;
        case 'create_product':
            product_route($method, $url_list, $request_data);
            break;
    }
}
?>