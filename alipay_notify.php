<?php
/**
 * Created by PhpStorm.
 * User: kok
 * Date: 2017/7/18
 * Time: 23:09
 */
require('functions.php');
require ('config.php');
file_put_contents('alipay.log',json_encode($_POST),FILE_APPEND);
$alipay_api = new alipay_api(ALIPAY_PARTNER_ID, ALIPAY_KEY);
$pdo = db::getInstance();
   // $json ='{"discount":"0.00","payment_type":"1","trade_no":"2017071821001004020285357059","subject":"test","buyer_email":"a3831524@126.com","gmt_create":"2017-07-18 23:38:30","notify_type":"trade_status_sync","quantity":"1","out_trade_no":"20170718233821888886423","seller_id":"2088021970397651","notify_time":"2017-07-18 23:38:38","body":"\u8ba2\u5355: test","trade_status":"TRADE_SUCCESS","is_total_fee_adjust":"N","total_fee":"0.01","gmt_payment":"2017-07-18 23:38:38","seller_email":"marcsong@zggonglue.com","price":"0.01","buyer_id":"2088102328382023","notify_id":"ba867a0d3a3b4ddad6e84afe517f9b4g5m","use_coupon":"N","sign_type":"MD5","sign":"efb9d0ecde679fc52a99fe7f696c86fa"}';
//$_POST = json_decode($json,true);

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
                $sth->bindParam(3,$_POST['out_trade_no']);
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
