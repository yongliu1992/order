<?php
/**
 * Created by PhpStorm.
 * User: kok
 * Date: 2017/7/14
 * Time: 21:11
 */

 abstract class abs_order {
     abstract function create($order);
     abstract function detail();
     abstract function delete();
     abstract function find();
     abstract function get();
     abstract function change();
 }

 class orders extends  abs_order {
        private $pdo;
     public function __construct()
     {
        $this->pdo = db::getInstance();
     }

     function create($order){
         $pdo = $this->pdo;
        if($order['user_id']>1000000){
            $sub_uid = substr($order['user_id'],0,6);
        }
        $sub_uid = @$sub_uid?:$order['user_id'];
        $order['create_time'] = date('Y-m-d H:i:s');
        $rand = mt_rand(111111,999999);
        $order['order_no']    = date('YmdHis').$sub_uid.$rand;
        $order['out_trade_no'] = $order['order_no'];
        $sql = "insert into orders(order_no,out_trade_no,user_id,item_id,item_num,item_type,fee,pay_fee,source,comm,create_time) values(
:order_no,:out_trade_no,:user_id,:item_id,:item_num,:item_type,:fee,:pay_fee,:source,:comm,:create_time
)";

        $sth = $pdo->prepare($sql);
        $pdo->beginTransaction();
        $bool = $sth->execute($order);
        $pdo->commit();
        return $bool ? $order : false;
    }
    function detail()
    {
        // TODO: Implement detail() method.
    }

    function delete()
    {
        // TODO: Implement delete() method.
    }

    function find()
    {
        // TODO: Implement find() method.
    }

    function get()
    {
        // TODO: Implement get() method.
    }
    function change()
    {
        // TODO: Implement change() method.
    }

    protected function hasOrder($oid){

    }

    protected function pay()
    {

    }
 }
