<?php
/**
 * 微信公众号API调用
 * 官方文档：
 * http://mp.weixin.qq.com/wiki/17/2d4265491f12608cd170a95559800f2d.html
 */
error_reporting(7);

$wx_api = new wx_api(WX_APPID, WX_SECRET_KEY, WX_PAY_KEY, WX_MCH_ID);
function get_rand($len=8){
    $str = 'abcdefghijklmnopqrstuvw123456789';
    return substr($str,mt_rand(1,10),$len);
}

$appid = $wx_api -> wx_appid;
$ip =  get_ip();
$ip =  $ip=='127.0.0.1'?'117.73.146.11':$ip;

$trade_type='JSAPI';
switch ($trade_type) {
    case 'NATIVE':

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
        break;
    case 'JSAPI':
        //先拿到code  然后拿到openid 然后统一下单 得到处理id 输出js
       $openid = $wx_api->get_openid();

        $order['content'] = $_POST['item_name']?:'书籍购买';
        $order['pay_fee']=100;

        $pay_data = array(
            'body' => $order['content'] ? $order['content'] : date('Y-m-d H:i:s').'订单支付', //商品描述
            'out_trade_no' => $order['out_trade_no']?:$oid, //商户订单号，32 个字符内
            'total_fee' => bcmul($order['pay_fee'], 100, 0), //订单总金额,单位为分
            'spbill_create_ip' => $ip,
            'notify_url' => WX_PAY_NOTIFY_URL,
            'trade_type' => $trade_type,
            'openid'=>$openid
        );
        $data = $wx_api -> get_pay_data($pay_data);
//	var_dump($data);exit;
        $result = $wx_api -> api('https://api.mch.weixin.qq.com/pay/unifiedorder', $data, false, 'xml','post');
$jsApiParameters = json_encode($wx_api->get_js_pay_data($result['prepay_id']));
//echo $jsApiParameters;
//require("jsWePay.php");

        break;

}


?>

<script type="text/javascript">

    //调用微信JS api 支付
    function jsApiCall()
    {
        WeixinJSBridge.invoke(
            'getBrandWCPayRequest',
            <?php echo $jsApiParameters; ?>,
            function(res){
                WeixinJSBridge.log(res.err_msg);
                alert(res.err_code+res.err_desc+res.err_msg);
            }
        );
    }

    function callpay()
    {
        if (typeof WeixinJSBridge == "undefined"){
            if( document.addEventListener ){
                document.addEventListener('WeixinJSBridgeReady', jsApiCall, false);
            }else if (document.attachEvent){
                document.attachEvent('WeixinJSBridgeReady', jsApiCall);
                document.attachEvent('onWeixinJSBridgeReady', jsApiCall);
            }
        }else{
            jsApiCall();
        }
    }

    callpay();
</script>

