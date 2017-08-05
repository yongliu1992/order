<?php
/**
 * Created by PhpStorm.
 * User: kok
 * Date: 2017/8/5
 * Time: 23:02
 */;
var_dump($_SERVER);exit;
$wx_api = new wx_api(WX_APPID, WX_SECRET_KEY, WX_PAY_KEY, WX_MCH_ID);
$openid=$wx_api->get_openid();
