<?php
/**
 * Created by PhpStorm.
 * User: kok
 * Date: 2017/7/16
 * Time: 15:25
 */

spl_autoload_register(function($name){
    include('class/'.$name.'.php');
});

function query_string_encode($data) {
    $rows = array();
    $data = (array) $data;
    foreach ($data as $k => $v) {
        $rows[] = urlencode($k).'='.urlencode($v);
    }
    return join('&', $rows);
}


function get_ip() {
    if($_SERVER['HTTP_X_FORWARDED_FOR']) {
        if(preg_match_all("#[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}#s", $_SERVER['HTTP_X_FORWARDED_FOR'], $addresses)) {
            while (list($key, $val) = each($addresses[0])) {
                if (!preg_match("#^(10|172\.16|192\.168)\.#", $val)) {
                    $ip = $val;
                    break;
                }
            }
        }
    }
    if(!$ip) {
        if($_SERVER['HTTP_CLIENT_IP']) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
    }
    return $ip;
}

function get_request_scheme() {
    $request_scheme = $_SERVER['REQUEST_SCHEME'];
    if (!$request_scheme) {
        $request_scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'];
    }
    if (!$request_scheme) {
        $request_scheme = 'http';
    }

    return $request_scheme;
}
function get_host() {
    $host = $_SERVER["HTTP_X_FORWARDED_HOST"] ? $_SERVER["HTTP_X_FORWARDED_HOST"] : $_SERVER['HTTP_HOST'];
    return $host;
}