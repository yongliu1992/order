<?php
/**
 * 微信公众号API调用
 * 官方文档：
 * http://mp.weixin.qq.com/wiki/17/2d4265491f12608cd170a95559800f2d.html
 */
error_reporting(0);

$wx_api = new wx_api(WX_APPID, WX_SECRET_KEY, WX_PAY_KEY, WX_MCH_ID);
function get_rand($len=8){
    $str = 'abcdefghijklmnopqrstuvw123456789';
    return substr($str,mt_rand(1,10),$len);
}

$appid = $wx_api -> wx_appid;
$ip =  $ip?$_SERVER['REMOTE_ADDR']:'117.73.146.11';
$trade_type='NATIVE';
$order['content'] = $_POST['item_name'];
$pay_data = array(
    'body' => $order['content'] ? $order['content'] : date('Y-m-d H:i:s').'订单支付', //商品描述
    'out_trade_no' => $order['out_trade_no'], //商户订单号，32 个字符内
    'total_fee' => bcmul($order['pay_fee'], 100, 0), //订单总金额,单位为分
    'spbill_create_ip' => $ip,
    'notify_url' => WX_PAY_NOTIFY_URL,
    'trade_type' => $trade_type,
);
$data = $wx_api -> get_pay_data($pay_data);
$result = $wx_api -> api('https://api.mch.weixin.qq.com/pay/unifiedorder', $data, false, 'xml','post');

include ('qrcode/qrcode.php');
header("Content-type: image/png");
if (!$padding) {
    $padding = 0;
}
if (!$size) {
    $size = 300;
}
$size -= $padding * 2;
$qrCode = new QrCode();
$qrCode
    -> setText($result['code_url'])
    -> setSize($size)
    -> setErrorCorrection('medium')
    -> setPadding($padding)
    -> render();



