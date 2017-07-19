<?php
/**
 * Created by PhpStorm.
 * User: kok
 * Date: 2017/7/18
 * Time: 23:09
 */
require('functions.php');
require ('config.php');
file_put_contents('logs/alipay.log',json_encode($_POST),FILE_APPEND);
$alipay_api = new alipay_api(ALIPAY_PARTNER_ID, ALIPAY_KEY);
$pdo = db::getInstance();
if (true === $alipay_api -> verify_notify()) {

    $data = $_POST;
    //商户订单号
    $out_trade_no = $data['out_trade_no'];
    //支付宝交易号
    $trade_no = $data['trade_no'];

    //交易状态
    $trade_status = $data['trade_status'];

    if (in_array($trade_status, array('TRADE_FINISHED', 'TRADE_SUCCESS'))) {
       $sql = "SELECT * FROM `orders`
				WHERE
				out_trade_no=?
				LIMIT 1
				";
       $sth = $pdo->prepare($sql);
        $sth->bindParam(1,$_POST['out_trade_no']);
        $sth->execute();
        $order =  $sth->fetch(PDO::FETCH_ASSOC);

        if ($order) {

            if($order['pay_fee']!=$data['total_fee']){
                //todo 写入权限检测
                file_put_contents('logs/notify_error',json_encode($data),FILE_APPEND);
                exit;
            }

            if (
               @ $order['pay_status'] !== 'PAID' ||
                ($order['pay_type'] == 'CASH' && $order['pay_status'] == 'PAID')
            ) {

                $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT,0);
                $pdo->beginTransaction();
                $pay_type='ALIPAY';
                $pay_confirm_source='ali_notify';
                $pdo->query('SET SQL_SAFE_UPDATES = 0');
                $logData=json_encode($_POST);
                $sql =
                "UPDATE orders SET order_status=1,
							pay_status='PAID',
							pay_type=?,
							pay_time=NOW(),pay_confirm_source=? WHERE 	out_trade_no=? LIMIT 1";

                $sth = $pdo->prepare($sql);
                $sth->bindParam(1,$pay_type);
                $sth->bindParam(2,$pay_confirm_source);
                $sth->bindParam(3,$data['out_trade_no']);

                $sth->execute();
                //更新订单表
                $affRow = $sth->rowCount();
                $sql = "INSERT INTO orders_notify_log (content,pay_type,out_no,source,create_time) values(?,?,?,?,now())";
                $sth = $pdo->prepare($sql);
                $sth->bindParam(1,$logData);
                $sth->bindParam(2,$pay_type);
                $sth->bindParam(3,$_POST['out_trade_no']);
                $sth->bindParam(4,$pay_confirm_source);
                $sth->execute();

                //这里可以完成一些除订单之外的相关逻辑
                // $this -> pay_success($order);
                if($affRow==1){
                    $pdo->commit();
                    echo 'success';
                }else{
                    $pdo->rollBack();
                    echo 'fail';
                }
                $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
            }else {
                echo 'success';
            }
        }

    }

}
