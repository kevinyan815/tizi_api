<?php
require_once('db_mysql.class.php');
/**
 * 数据库连接配置文件,继承和实例化
 */

class Tiku extends DB_Sql 
{
    var $Host ="rdsnuyizmnuyizm.mysql.rds.aliyuncs.com";
    var $Database = "tiku";
    var $User = "mobile_tiku";
    var $Password = "JgEC23SpyQcYc";
    var $LinkName = "conn_tiku";
}
$db_tiku = new Tiku;

class XueTang extends DB_Sql 
{
    var $Host ="rdsnuyizmnuyizm.mysql.rds.aliyuncs.com";
    var $Database = "tizi";
    var $User = "tizi";
    var $Password = "Kx38_Fn2k";
    var $LinkName = "conn_xuetang";
}
$db_xuetang = new XueTang;

class TikuCms extends DB_Sql
{
    var $Host ="168.63.214.100";
    var $Database = "tiku_admin";
    var $User = "mobile_tiku";
    var $Password = "ti_tiku_zi";
    var $LinkName = "conn_tikucms";
}
$db_tikuCms = new TikuCms;