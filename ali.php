<?php
/**
 * Created by PhpStorm.
 * User: kok
 * Date: 2017/7/14
 * Time: 17:29
 */



$alipay_api = new alipay_api(ALIPAY_PARTNER_ID, ALIPAY_KEY);
$item['post_title']=$_POST['item_name'];
$order['content'] = $_POST['item_name'] ?:'测试';
$return_url='http://iphp.cc/alipay/a.php';
$pay_url = $alipay_api -> pay_url(array(
    'service' => 'create_direct_pay_by_user',
    'seller_email' => ALIPAY_EMAIL,
    'payment_type'	=> '1',
    'notify_url'	=> ALIPAY_PAY_NOTIFY_URL,
    'return_url' => $return_url,
    'out_trade_no'	=> $order['out_trade_no'],
    'subject'	=> $item['post_title'],
    'body'	=> '订单: '.$order['content'],
    'total_fee'	=> $order['pay_fee'],
    'show_url'	=> 'dassdad',
    'exter_invoke_ip'	=> get_ip(),
    'qr_pay_mode'=>1
));
echo '<a href=\''.$pay_url.'\'>支付</a>';



