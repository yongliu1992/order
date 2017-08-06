<?php
/**
 * Created by PhpStorm.
 * User: kok
 * Date: 2017/6/14
 * Time: 11:19
 */

class db {
        static private $_link;
   private function __construct()
    {

        $u    = DB_USER;
        $p    = DB_PASSWORD;
        $dbms = 'mysql';
        $dsn  = $dbms.":host=".DB_HOST.";dbname=".DB;
        $options = array(
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
            PDO::ATTR_PERSISTENT => true
        );
        try{
            self::$_link= new PDO($dsn,$u,$p,$options);
        }catch (Exception $e){
            echo  $e->getMessage();
        }
        return self::$_link;
    }

   final private function __clone(){
    }

    static public function getInstance(){
       if(is_NULL(self::$_link)){
            new self();
       }
       return self::$_link;
    }

}
