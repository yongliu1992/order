<?php
/**
 * Created by PhpStorm.
 * User kok
 * Date 2017/7/14
 * Time 2205
 */
if(!$_POST){
    include('index.html');
    exit;
}
include "functions.php";
include('config.php');
$pdo = db::getInstance();

$o = new orders();

$order['user_id']=888;//以后自己改 session里面读 或者对属性
$order['item_id']=18888;
$order['item_num']=intval($_POST['item_num']);
$order['item_type']=$_POST['item_type'];
$price=$_POST['item_price']>0 ? $_POST['item_price']: 0.01;
$order['fee']     = $price * $order['item_num'];
$discount=0;
$order['pay_fee']     = $price * $order['item_num'] - $discount;
$order['source'] ='html';
$order['comm']='';
$order = $o->create($order,$pdo);
if($order){
    //include "wx.php";
    include "ali.php";
}

