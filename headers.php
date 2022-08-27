<?php
function set_HTTP_status ($status, $message, $response_code, $body = null) {
    switch ($status) {
        case '200':
            $status='HTTP/1.0 200 OK';
            break;
        case '400':
            $status='HTTP/1.0 400 Bad request';
            break;
        case '404':
            $status='HTTP/1.0 404 Not found';
            break;
        case '410':
            $status='HTTP/1.0 410 Gone';
            break;
        case '500':
            $status='HTTP/1.0 500 Internal Server Error';
            break;
    }
    header($status);
    if (!is_null($message)) {
       print json_encode(['message' => $message, 'code' => $response_code, 'body' => $body]);
    }
}
?>


