<?php
/**
 * Created by PhpStorm.
 * User: kok
 * Date: 2017/7/15
 * Time: 08:16
 */

$post_string = trim(file_get_contents("php://input"));
include('functions.php');
require ("config.php");
$pdo = db::getInstance();
$wx_api = new wx_api(WX_APPID, WX_SECRET_KEY, WX_PAY_KEY, WX_MCH_ID);
if ($post_string) {
    $post_data = @simplexml_load_string($post_string, 'SimpleXMLElement', LIBXML_NOCDATA);
$post_data = (array) $post_data;
    file_put_contents('logs/wx.log',json_encode($post_data),FILE_APPEND);

    if ($post_data) {
        $post_data = (array)$post_data;
        //验证签名
        $sign = $wx_api -> get_pay_signature($post_data);
        if ($sign === $post_data['sign']) {
            //业务处理 日志,记录微信的会掉
            if ($post_data['result_code'] == 'SUCCESS') {
                $sql = 'select * from orders where out_trade_no=? limit 1';
                $sth = $pdo->prepare($sql);
                $sth->bindParam(1,$post_data['out_trade_no']);
                $sth->execute();
                $order = $sth->fetch(PDO::FETCH_ASSOC);

                if ($order) {

                    if (
                        $order['pay_status'] !== 'PAID' ||
                        ($order['pay_type'] == 'CASH' && $order['pay_status'] == 'PAID')) {

                        $settlement_fee = (string)$post_data['total_fee'];
                        $settlement_fee = bcdiv($settlement_fee, '100', 2);
                        $pdo->beginTransaction();
                        if($settlement_fee == $order['pay_fee']){

                            $sql="UPDATE orders SET order_status=1,pay_status='PAID',pay_type=?,pay_confirm_source=?,pay_time=NOW() WHERE out_trade_no=? limit 1";
                            $sth =  $pdo->prepare($sql);
                            $pay_type='weixin';
                            $sth->bindParam(1,$pay_type);
                            $pay_confirm_source = 'wx_notify';
                            $sth->bindParam(2,$pay_confirm_source);
                            $sth->bindParam(3,$order['out_trade_no']);
                            $sth->execute();
                            $pdo->commit();
                        }else{
                            $sql="UPDATE orders SET order_status=-1,pay_status='PAY ERROR ',pay_type=?,pay_time=NOW(),comm=? WHERE out_trade_no=? limit 1";
                            $sth =  $pdo->prepare($sql);
                            $pay_type='weixin';
                            $sth->bindParam(1,$pay_type);
                            $sth->bindValue(2,'支付金额错误');
                            $sth->bindParam(3,$order['out_trade_no']);
                            $sth->execute();
                            $pdo->commit();
                        }
                    }
                }

            }
            ob_clean();
            $wx = new wx();
            $wx-> reply_message(array(
                'return_code' => 'SUCCESS',
                'return_msg' => 'OK'
            ));
        }
    }
}