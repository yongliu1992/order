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
    return '106.37.114.244';
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
