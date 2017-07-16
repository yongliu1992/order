<?php
/**
 * Created by PhpStorm.
 * User: kok
 * Date: 2017/7/16
 * Time: 16:42
 */

class alipay_api {
    var $error_logfile = null;
    var $partner_id;
    var $key;

    function alipay_api($partner_id, $key) {
        $this -> partner_id = $partner_id;
        $this -> key = $key;

    }
    //获取微信支付签名字段值
    function get_pay_data($data) {
        ksort($data);
        $signature_array = array();
        foreach ($data as $k => $v) {
            if ($v !== '' && $k !== 'sign' && $k !== 'sign_type') {
                $signature_array[] = $k.'='.$v;
            }
        }

        $signature_string = join($signature_array, '&');
        $data['sign'] = md5($signature_string.$this -> key);
        $data['sign_type'] = 'MD5';

        return $data;
    }

    function pay_url($data = null, $method = 'get') {

        $data = array_merge(array(
            'partner' => $this -> partner_id,
            '_input_charset' => 'utf-8'
        ), $data);

        $data = $this -> get_pay_data($data);
        $request_url = 'https://mapi.alipay.com/gateway.do?'.query_string_encode($data);

        return $request_url;
    }

    function verify_return() {
        if (!empty($_GET)) {
            $data = $this -> get_pay_data($_GET);
            if ($data['sign'] === $_GET['sign']) {
                $notify_id = $_GET['notify_id'];
                if (true === $this -> check_notify_id($notify_id)) {
                    return true;
                }
            }
        }
        return false;
    }

    function verify_notify() {
        if (!empty($_POST)) {
            $data = $this -> get_pay_data($_POST);
            if ($data['sign'] === $_POST['sign']) {
                $notify_id = $_POST['notify_id'];
                if (true === $this -> check_notify_id($notify_id)) {
                    return true;
                }
            }
        }
        return false;
    }

    function check_notify_id($notify_id) {
        $transport = 'http';
        $partner = trim($this->alipay_config['partner']);

        if ($transport == 'https') {
            $veryfy_url = 'https://mapi.alipay.com/gateway.do?service=notify_verify&';
        } else {
            $veryfy_url = 'http://notify.alipay.com/trade/notify_query.do?';
        }

        $veryfy_url = $veryfy_url.'partner='.$this -> partner_id.'&notify_id='.$notify_id;

        $curl = curl_init($veryfy_url);
        curl_setopt($curl, CURLOPT_HEADER, 0 ); // 过滤HTTP头
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, 1);// 显示输出结果
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);//SSL证书认证
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);//严格认证
        //curl_setopt($curl, CURLOPT_CAINFO, $cacert_url);//证书地址
        $response = curl_exec($curl);
        curl_close($curl);

        if (preg_match("/true$/i",$response)) {
            return true;
        }
        return false;
    }

    function raise_error($error) {
        $message = '[Weixin API ERROR]: '.$error;

        if ($this -> error_logfile) {
            @$error_log = '['.get_time().'] [client: '.get_ip().'] '.$_SERVER['REQUEST_URI']."\r\n".$error."\r\n";
            @$fp = fopen($this -> error_logfile, 'a');
            @flock($fp, 2);
            @fwrite($fp, $error_log);
            @fclose($fp);

            $message = 'ERROR:(';
        }

        if ($this -> page) {
            $this -> page -> error_page($message);
        } else {
            die($message);
        }

    }

}