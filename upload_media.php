<?php
function upload_image ($name, $tmp_name) {
    $uploads_dir = './assets';

    $name = basename($name);
    $name = str_replace(' ', '_', $name);
    move_uploaded_file($tmp_name, "$uploads_dir/$name");
}
?>