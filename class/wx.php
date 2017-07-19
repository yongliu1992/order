<?php
/**
 * Created by PhpStorm.
 * User: kok
 * Date: 2017/7/19
 * Time: 20:35
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