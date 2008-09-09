<?php

if (!function_exists('add_action'))
{
  require_once("../../../wp-config.php");
}


$post_view_id = $_POST['post_view_id'];
$request_url = $_POST['request_url'];

if (isset($access_expiration)) {
    $access_expiration->request_post_access($post_view_id, $request_url);
}

?>
