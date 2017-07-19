<?php
/**
 * Created by PhpStorm.
 * User: kok
 * Date: 2017/7/15
 * Time: 08:16
 */

class wx {
    //转发请求
    function redirect_request($url, $post_string) {
        //初始化curl
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 40);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);

        //运行curl
        $result = curl_exec($ch);
        curl_close($ch);

        if (!$result || $result === 'success') {
            die((string)$result);
        }
        $this -> reply_message($result);
    }

    function get_xml($data) {
        return  '<xml>'.$this -> _get_xml($data).'</xml>';
    }

    function _get_xml($data) {
        $result = array();
        foreach ((array)$data as $key => $value) {
            if (is_array($value)) {
                if (!is_numeric($key)) {
                    $result[] = '<'.$key.'>';
                }
                $result[] = $this -> _get_xml($value);
                if (!is_numeric($key)) {
                    $result[] = '</'.$key.'>';
                }
            } else {
                if (is_string($value)) {
                    $result[] = '<'.$key.'><![CDATA['.$value.']]></'.$key.'>';
                } else {
                    $result[] = '<'.$key.'>'.$value.'</'.$key.'>';
                }
            }
        }

        return join('', $result);
    }

    //向微信接口被动回复消息
    function reply_message($data, $log = false) {
        if (is_array($data)) {
            $result = $this -> get_xml($data);
        } else {
            $result = $data;
        }
        if($log){
           file_put_contents($log,json_encode($data),FILE_APPEND);
        }
        echo($result);
    }
}

$post_string = trim(file_get_contents("php://input"));

// $post_string = '<xml><appid><![CDATA[wx04156fbd016537b7]]></appid>
// <bank_type><![CDATA[CFT]]></bank_type>
// <cash_fee><![CDATA[1]]></cash_fee>
// <fee_type><![CDATA[CNY]]></fee_type>
// <is_subscribe><![CDATA[Y]]></is_subscribe>
// <mch_id><![CDATA[1278451001]]></mch_id>
// <nonce_str><![CDATA[zsbbz8p3]]></nonce_str>
// <openid><![CDATA[oxONcwVR8xzSc5xF2E-cKKS6nwi4]]></openid>
// <out_trade_no><![CDATA[20160326073595487]]></out_trade_no>
// <result_code><![CDATA[SUCCESS]]></result_code>
// <return_code><![CDATA[SUCCESS]]></return_code>
// <sign><![CDATA[EC90E528866397A1CF61E4DBE54252F1]]></sign>
// <time_end><![CDATA[20160326073530]]></time_end>
// <total_fee>1</total_fee>
// <trade_type><![CDATA[JSAPI]]></trade_type>
// <transaction_id><![CDATA[4004592001201603264282730088]]></transaction_id>
// </xml>';

include ("pdo.php");
require ("config.php");

$wx_api = new wx_api(WX_APPID, WX_SECRET_KEY, WX_PAY_KEY, WX_MCH_ID);
if ($post_string) {
    $post_data = @simplexml_load_string($post_string, 'SimpleXMLElement', LIBXML_NOCDATA);
$post_data = (array) $post_data;
    file_put_contents('wx.log',json_encode($post_data),FILE_APPEND);

    if ($post_data) {
        $post_data = (array)$post_data;
        //验证签名
        $sign = $wx_api -> get_pay_signature($post_data);
        if ($sign === $post_data['sign']) {
            //业务处理 日志,记录微信的会掉

            if ($post_data['result_code'] == 'SUCCESS') {

                $sql = 'select * from orders where out_trade_no=? limit 1';
                $sth = $pdo->prepare($sql);
               // $p = '20170714235353888769774';
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
                            $sql="UPDATE orders SET order_status=1,pay_status='PAID',pay_type=?,pay_time=NOW() WHERE out_trade_no=? limit 1";
                            $sth =  $pdo->prepare($sql);
                            $pay_type='weixin';
                            $sth->bindParam(1,$pay_type);
                            $sth->bindParam(2,$order['out_trade_no']);
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