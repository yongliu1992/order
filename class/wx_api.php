<?php
/**
 * Created by PhpStorm.
 * User: kok
 * Date: 2017/7/15
 * Time: 08:57
 */
class wx_api {

    var $token_file = '.token/token'; //保存token的文件
    var $ticket_file = '.token/ticket'; //保存api_ticket的文件
    var $retry_count = 0; //api请求失败的重试计数
    var $is_weixin = false;
    var $error_logfile = null;
    var $ignore_error = false;
    var $component = null; //微信第三方平台对象 wx_component_api

    var $db = null;

    var $wx_appid;
    var $wx_key;
    var $wx_pay_key;
    var $wx_mch_id;

    function __construct($wx_appid, $wx_key, $wx_pay_key = null, $wx_mch_id = null) {
        $this -> wx_appid = $wx_appid;
        $this -> wx_key = $wx_key;
        $this -> wx_pay_key = $wx_pay_key;
        $this -> wx_mch_id = $wx_mch_id;


        if(defined('ROOT_PATH')){
            @$token_dir = ROOT_PATH.'.token/';
        }
      @  $this -> token_dir = $token_dir;

        $ua = $_SERVER['HTTP_USER_AGENT'];
        if (stristr($ua, 'MicroMessenger')) {
            $this -> is_weixin = true;
        }
    }

    function get_rand($length, $babel = false) {
        $temp = $babel ? 'abcdefghijklmnopqrstuvwxyz123456789-_ABCDEFGHIJKLMNOPQRSTUVWXYZ' : 'abcdefghijklmnopqrstuvwxyz123456789';
        $strlen = strlen($temp);
        $temp_str = '';
        for ($i = 0;$i < $length; $i++) {
            $pos = mt_rand(0, $strlen - 1);
            $temp_str .= $temp{$pos};
        }
        return $temp_str;
    }

    function read_token($type) {
        if ($this -> db) {
            $token_data = $this -> db -> get_row("SELECT token,create_time FROM app_token WHERE appid='".$this -> wx_appid."' and type='".$type."'");
            if ($token_data) {
                if (time() - strtotime($token_data['create_time']) < 7000) {
                    return $token_data['token'];
                }
            }
            return null;
        } else {
            $token_file = $this -> token_dir.'.'.$type.'_'.$this -> wx_appid;
            if (!file_exists($token_file) || (time() - filemtime($token_file)) > 7000) {
                return null;
            } else {
                return file_get_contents($token_file);
            }
        }
    }

    function write_token($type, $token) {
        if ($this -> db) {
            $this -> db -> query("
				REPLACE INTO app_token SET 
					appid='".$this -> wx_appid."',
					type='".$type."',
					token='".$token."',
					create_time=CURRENT_TIME
			");
        } else {
            if (!file_exists($this -> token_dir)) {
                mkdir($this -> token_dir);
            }
            $token_file = $this -> token_dir.'.'.$type.'_'.$this -> wx_appid;
            file_put_contents($token_file, $token);
        }
    }

    function remove_token($type) {
        if ($this -> db) {
            $this -> db -> query("DELETE FROM app_token WHERE appid='".$this -> wx_appid."' and type='".$type."'");
        } else {
            $token_file = $this -> token_dir.'.'.$type.'_'.$this -> wx_appid;
            unlink($token_file);
        }
    }

    // 获取access_token
    function get_token() {
        $token = $this -> read_token('token');
        if (!$token) {

            if ($this -> component) {
                //使用公众号第三方平台刷新token
                $token_result = $this -> component -> api('https://api.weixin.qq.com/cgi-bin/component/api_authorizer_token', array(
                    'component_appid' => $this -> component -> appid,
                    'authorizer_appid' => $this -> wx_appid,
                    'authorizer_refresh_token' => $this -> refresh_token
                ));
                $token = $token_result['authorizer_access_token'];
            } else {
                $token_result = $this -> api('https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$this -> wx_appid.
                    '&secret='.$this -> wx_key,
                    null,
                    false,
                    'json',
                    'get');

                if ($token_result) {
                    $token = $token_result['access_token'];
                }
            }
            if ($token) {
                $this -> write_token('token', $token);
                return $token;
            }
        } else {
            return $token;
        }
        return null;
    }

    //使用session获取unionid和openid
    function get_userid_with_session($type, $redirect_url = null, $clear_code_after_auth = true) {

        if (!$redirect_url) {
            $redirect_url = $this -> get_current_url();
        }

        $cookie_name = '_open_session_'.$this -> wx_appid;

        $session_key = $_COOKIE[$cookie_name];

        if ($session_key) {
            $openid = $this -> db -> get_value("
				SELECT ".$type." FROM wx_user_session
				WHERE 
					session_key='".mysql_escape_string($session_key)."'
				LIMIT 1
			");
            if ($openid) {
                return $openid;
            }
        }

        //使用一个随机的key来验证获取用户code后的回调是否合法
        $return_key_cookie_name = '_openid_state_'.$this -> wx_appid;

        //从微信接口获取code后返回到当前页
        if ($_GET['state'] && ($_GET['state'] === $_COOKIE[$return_key_cookie_name])) {

            //验证key用后删除
            setcookie($return_key_cookie_name, false, -1, null, DOMAIN, false, true);

            if ($_GET['code']) {

                //使用公众号第三方平台获取换取用户access_token
                if ($this -> component) {
                    $url = 'https://api.weixin.qq.com/sns/oauth2/component/access_token?appid='.$this -> wx_appid.
                        '&component_appid='.$this -> component -> appid.
                        '&component_access_token='.$this -> component -> get_token().
                        '&code='.$_GET['code'].
                        '&grant_type=authorization_code';
                } else {
                    //使用公众号自身参数获取换取用户access_token
                    $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid='.$this -> wx_appid.
                        '&secret='.$this -> wx_key.
                        '&code='.$_GET['code'].
                        '&grant_type=authorization_code';
                }

                $result = $this -> api($url, null, false);

                $openid = $result['openid'];
                if ($openid) {

                    do {
                        $session_key = get_rand(50, true);
                        $check_session_key = $this -> db -> get_row("
							SELECT session_key
							FROM wx_user_session
							WHERE 
								session_key='".$session_key."' 
							LIMIT 1");
                    } while ($check_session_key);

                    $this -> db -> query("
						REPLACE INTO wx_user_session SET
							openid='".mysql_escape_string($openid)."',
							unionid='".mysql_escape_string($result['unionid'])."',
							appid='".mysql_escape_string($this -> wx_appid)."',
							session_key='".mysql_escape_string($session_key)."',
							session_create_time=NOW()
					");

                    setcookie($cookie_name, $session_key, time() + 60*60*24*30*12, '/', DOMAIN, false, true);

                    //在获取openid后是否再重定向一次来去掉code和state参数
                    if ($clear_code_after_auth) {
                        header("Location: $redirect_url");
                        die;
                    }

                    if ($type == 'unionid') {
                        $openid = $result['unionid'];
                    }

                    return $openid;
                } else if ($result['errcode'] == '40029') {
                    //code过期，再重新请求
                    $_GET['state'] = '';
                    $this -> get_userid_with_session($type, $redirect_url, $clear_code_after_auth);
                }
            }
        } else {
            //生成一个随机的一次性验证key，有效时间30秒（在app的webview中，后台退出app后session才能过期）
            $return_key = get_rand(50, true);
            setcookie($return_key_cookie_name, $return_key, time() + 30, null, DOMAIN, false, true);

            $redirect_url = preg_replace('/#.+$/', '', $redirect_url);

            $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='.
                $this -> wx_appid.'&redirect_uri='.urlencode($redirect_url).
                '&response_type=code&scope=snsapi_base&state='.$return_key.
                ($this -> component ? '&component_appid='.$this -> component -> appid : '').
                '#wechat_redirect';

            header("Location: $url");
            die;
        }
        return false;
    }

    //使用session获取unionid
    function get_unionid_with_session($redirect_url = null, $clear_code_after_auth = true) {
        //浏览器测试用
        if (!$this -> is_weixin && ENV_TYPE === 'DEVELOPMENT') {
            //return 'om9ecjn7LvXFesi2zbUGLURSXr54';
            return 'oVR3Vsl4RGDJX9l_pXzCLp2olrzg';
        }

        return $this -> get_userid_with_session('unionid', $redirect_url, $clear_code_after_auth);
    }

    //使用session获取openid，此方法不会对外暴露openid，用于对openid的真实性要求比较高的场景，比如抽奖
    function get_openid_with_session($redirect_url = null, $clear_code_after_auth = true) {
        //浏览器测试用
        if (!$this -> is_weixin && ENV_TYPE === 'DEVELOPMENT') {
            return 'om9ecjn7LvXFesi2zbUGLURSXr54';
        }

        return $this -> get_userid_with_session('openid', $redirect_url, $clear_code_after_auth);
    }

    //获取用户openid
    function get_openid($redirect_url = null, $clear_code_after_auth = true) {
        //浏览器测试用
        if (!$this -> is_weixin && ENV_TYPE === 'DEVELOPMENT') {
            return 'om9ecjn7LvXFesi2zbUGLURSXr54';
        }


        if (!$redirect_url) {
            $redirect_url = $this -> get_current_url();
        }

        $cookie_name = '_openid_'.$this -> wx_appid;

        $openid = $_COOKIE[$cookie_name];
        if ($openid) {
            return $openid;
        }

        //使用一个随机的key来验证获取用户code后的回调是否合法
        $return_key_cookie_name = '_openid_state_'.$this -> wx_appid;

        //从微信接口获取code后返回到当前页
        if ($_GET['state'] && ($_GET['state'] === $_COOKIE[$return_key_cookie_name])) {

            //验证key用后删除
            setcookie($return_key_cookie_name, false, -1, null, DOMAIN, false, true);

            if ($_GET['code']) {
                $result = $this -> api('https://api.weixin.qq.com/sns/oauth2/access_token?appid='.$this -> wx_appid.
                    '&secret='.$this -> wx_key.
                    '&code='.$_GET['code'].
                    '&grant_type=authorization_code',
                    null, false);

                $openid = $result['openid'];
                if ($openid) {
                    setcookie($cookie_name, $openid, time() + 60*60*24*30, '/', DOMAIN, false, true);

                    //在获取openid后是否再重定向一次来去掉code和state参数
                    if ($clear_code_after_auth) {
                        header("Location: $redirect_url");
                        die;
                    }

                    return $openid;
                } else if ($result['errcode'] == '40029') {
                    //code过期，再重新请求
                    $_GET['state'] = '';
                    $this -> get_openid($redirect_url, $clear_code_after_auth);
                }
            }
        } else {
            //生成一个随机的一次性验证key，有效时间30秒（在app的webview中，后台退出app后session才能过期）
            $return_key = get_rand(50, true);
            setcookie($return_key_cookie_name, $return_key, time() + 30, null, DOMAIN, false, true);

            $redirect_url = preg_replace('/#.+$/', '', $redirect_url);

            $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='.
                $this -> wx_appid.'&redirect_uri='.urlencode($redirect_url).
                '&response_type=code&scope=snsapi_base&state='.$return_key.
                ($this -> component ? '&component_appid='.$this -> component -> appid : '').
                '#wechat_redirect';

            header("Location: $url");
            die;
        }
        return false;
    }

    //使用openid获取用户资料(包括unionid，将公众号绑定到微信开放平台帐号后才能获取unionid)
    function get_userinfo($openid) {
        if ($openid) {
            $result = $this -> api('https://api.weixin.qq.com/cgi-bin/user/info?openid='.$openid.'&lang=zh_CN');
            return $result;
        } else {
            return array();
        }
    }

    //调用微信api
    function api($url, $data = null, $need_token = true, $data_type = 'json', $method = 'post', $use_cert = false) {

        $request_url = $url;
        //如果api需要access_token，则把access_token参数放入$url中
        if ($need_token === true) {
            $token = $this -> get_token();
            $pos = strrpos($request_url, '?');
            $request_url .= ($pos !== false ? '&' : '?').'access_token='.$token;
        }

        //初始化curl
        $ch = curl_init();

        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, 40);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($ch, CURLOPT_URL, $request_url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

        //使用SSL证书
        if (true === $use_cert) {
            $cert_file = ROOT_PATH.'resource/'.$this -> wx_mch_id.'/cert';
            if (!file_exists($cert_file)) {
                $this -> raise_error("请先上传微信支付证书");
            }

            curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLCERT, $cert_file);
            curl_setopt($ch, CURLOPT_SSLKEY, ROOT_PATH.'resource/'.$this -> wx_mch_id.'/key');
        }

        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);

        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        $data_string = '';
        if ($data) {
            if ($data_type == 'xml') {
                $temp_array = array('<xml>');
                foreach ($data as $key => $value) {
                    if (is_numeric($value)) {
                        $temp_array[] = "<".$key.">".$value."</".$key.">";
                    } else {
                        $temp_array[] = "<".$key."><![CDATA[".$value."]]></".$key.">";
                    }
                }
                $temp_array[] = '</xml>';
                $data_string = join('', $temp_array);
            } else if ($data_type == 'json' || $data_type == 'raw') {
                $data_string = json_encode($data, JSON_UNESCAPED_UNICODE);
            } else if ($data_type == 'file') {
                foreach ($data as $k => $v) {
                    if ($v{0} === '@') {
                        $v = substr($v, 1, strlen($v));
                    }
                    if (function_exists('curl_file_create')) {
                        $data[$k] = curl_file_create($v);
                    } else {
                        $data[$k] = '@'.$v;
                    }
                }
                $data_string = $data;
            } else {
                $data_string = (string) $data;
            }
        }

        //post提交方式
        if ($method == 'post') {
            if ($data_string !== '') {
                curl_setopt($ch, CURLOPT_POST, TRUE);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            }
        }

        //运行curl
        $result = curl_exec($ch);

        //返回结果
        if ($result) {
            curl_close($ch);

            if ($data_type == 'xml') {
                $xml_data = @simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA);
                if ($xml_data) {
                    $result = json_decode(json_encode($xml_data), true);
                }
            } else if ($data_type == 'json' || $data_type == 'file') {
                $result = json_decode($result, true);
            } else if ($data_type == 'raw') {
                if (strpos($result, 'errcode') !== false) {
                    $result = json_decode($result, true);
                }
            }

            //token失效的话刷新token再请求一次
            if (is_array($result)) {
                $errcode = $result['errcode'];
                if ($errcode == '40001') {
                    if ($this -> retry_count < 1) {
                        $this -> retry_count++;
                        $this -> remove_token('token');
                        return $this -> api($url, $data, $need_token, $data_type, $method);
                    }
                } elseif ($errcode && !in_array((string) $errcode, array('0', '40029'))) {
                    if (method_exists($this -> page, 'wx_api_error')) {
                        $this -> page -> wx_api_error($errcode);
                    }
                    $this -> raise_error('('.$errcode.') '.$result['errmsg'].' (API: '.$url.($data_string !== '' ? ' Data: '.$data_string : '').')');
                }
            }
            $this -> retry_count = 0;
            return $result;
        } else {
            $errorno= curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);
            $this -> raise_error("curl出错(".$errorno.' - '.$error.")");
            return false;
        }
    }

    //获取调用微信 JSAPI 的临时票据
    function get_api_ticket() {
        $js_ticket = $this -> read_token('js_ticket');
        if (!$js_ticket) {
            $tick_result = $this -> api('https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi');
            $tick = $tick_result['ticket'];
            if ($tick) {
                $this -> write_token('js_ticket', $tick);
                return $tick;
            } else {
                $this -> raise_error('appid: '.$this -> wx_appid.' result: '.json_encode($tick_result, JSON_UNESCAPED_UNICODE));
            }
        } else {
            return $js_ticket;
        }
        return null;
    }

    //获取调用微信 JSAPI 的config对象和签名 
    function get_js_config($url = null) {

        if (!$url) {
            $url = $this -> get_current_url(false);
        }

        $wx_ticket = $this -> get_api_ticket();

        $wx_js_config = array(
            'noncestr' => $this -> get_rand(8),
            'jsapi_ticket' => $wx_ticket,
            'timestamp' => (string) time(),
            'url' => $url,
        );

        ksort($wx_js_config);
        $signature_array = array();
        foreach ($wx_js_config as $k => $v) {
            $signature_array[] = $k.'='.$v;
        }
        $signature_string = join($signature_array, '&');
        $wx_js_config['signature'] = sha1($signature_string);
        $wx_js_config['appid'] = $this -> wx_appid;
        return $wx_js_config;
    }

    //获取卡券的api_ticket
    function get_card_api_ticket() {
        $card_ticket = $this -> read_token('card_ticket');
        if (!$card_ticket) {
            $tick_result = $this -> api('https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=wx_card');
            $tick = $tick_result['ticket'];
            if ($tick) {
                $this -> write_token('card_ticket', $tick);
                return $tick;
            } else {
                $this -> raise_error('appid: '.$this -> wx_appid.' result: '.json_encode($tick_result, JSON_UNESCAPED_UNICODE));
            }
        } else {
            return $card_ticket;
        }
        return null;
    }

    //获取领取卡券的JS Data参数对象
    function get_js_card_data($data) {
        $api_ticket = $this -> get_card_api_ticket();
        $data = array_merge(array(
            'timestamp' => (string) time(),
            'api_ticket' => $api_ticket,
        ), $data);

        asort($data);
        $card_signature_string = '';
        foreach ($data as $k => $v) {
            $data[$k] = (string) $v;
            if ($k == 'outer_id') {
                continue;
            }
            $card_signature_string .= $v;
        }
        $data['signature'] = sha1($card_signature_string);

        unset($data['api_ticket']);
        unset($data['card_id']);

        return $data;
    }

    //获取调用微信支付接口的参数对象
    function get_pay_data($data) {
        $data = array_merge(array(
            'appid' => $this -> wx_appid,
            'mch_id' => $this -> wx_mch_id,
            'nonce_str' => get_rand(8),
        ), $data);

        $data['sign'] = $this -> get_pay_signature($data);

        return $data;
    }

    //获取调用微信支付的JS Data参数对象
    function get_js_pay_data($prepay_id) {
        $data = array(
            'appId' => $this -> wx_appid,
            'timeStamp' => time(),
            'nonceStr' => get_rand(8),
            'package' => 'prepay_id='.$prepay_id,
            'signType' => 'MD5',
        );

        $data['paySign'] = $this -> get_pay_signature($data);
        $data['timestamp'] = $data['timeStamp'];
        unset($data['timeStamp']);
        unset($data['appId']);
        return $data;
    }

    //获取微信支付签名字段值
    function get_pay_signature($data) {
        ksort($data);
        $signature_array = array();
        foreach ($data as $k => $v) {
            if ($v !== '' && $k !== 'sign') {
                $signature_array[] = $k.'='.$v;
            }
        }

        $signature_array[] = 'key='.$this -> wx_pay_key;
        $signature_string = join($signature_array, '&');

        return strtoupper(md5($signature_string));
    }

    function get_current_url($wx_auth = true) {
        $request_scheme = get_request_scheme();
        $host = get_host();

        //去掉授权后的跳转页带的回调参数code和state，防止参数循环累加
        $uri = $_SERVER['REQUEST_URI'];
        $question_pos = strpos($uri, '?');
        if ($question_pos !== false) {
            $base_url = substr($uri, 0, $question_pos);
            $query_string = substr($uri, $question_pos + 1);
            $query_data = query_string_decode($query_string);
            unset($query_data['code']);
            unset($query_data['state']);
            $query_string = query_string_encode($query_data);
            if ($query_string !== '') {
                $uri = $base_url.'?'.$query_string;
            } else {
                $uri = $base_url;
            }
        }

        $url = $request_scheme.'://'.$host.$uri;

        if (true === $wx_auth) {
            if (strtolower($host) !== strtolower(WX_AUTH_DOMAIN)) {
                $url = $request_scheme.'://'.WX_AUTH_DOMAIN.'/wx_auth?wx_redirect='.urlencode($request_scheme.'://'.$host.$uri);
            }
        }

        return $url;
    }

    function raise_error($error) {
        $message = '[Weixin API ERROR]: '.$error;

        if ($this -> error_logfile) {
            @$error_log = '['.get_time().'] [client: '.get_ip().'] '.$_SERVER['REQUEST_URI']."\r\n".$error."\r\n";
            @$fp = fopen($this -> error_logfile, 'a');
            @flock($fp, 2);
            @fwrite($fp, $error_log);
            @fclose($fp);

            $message = '出错了:(';
        }

        if ($this -> ignore_error) {
            return;
        }

        if ($this -> page) {
            $this -> page -> error_page($message);
        } else {
            die($message);
        }

    }

}