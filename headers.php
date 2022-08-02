<?php
function set_HTTP_status ($status = '200', $message = null) {
    switch ($status) {
        default:
        case '200':
            $status='HTTP/1.0 200 OK';
            break;
        case '400':
            $status='HTTP/1.0 400 Bad request';
            break;
        case '404':
            $status='HTTP/1.0 404 Not found';
            break;
        case '500':
            $status='HTTP/1.0 500 Internal Server Error';
            break;
    }
    header($status);
    if (!is_null($message)) {
       print json_encode(['message' => $message]);
    }
}
?>