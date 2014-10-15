<?php
define('UC_CONNECT', 'mysql');

require_once $_SERVER['DOCUMENT_ROOT'].'/discuz/environment.php';
//оъио

//require_once $_SERVER['DOCUMENT_ROOT'].'/discuz/online_environment.php';


if(ENVIRONMENT=='development'){
	define('UC_DBHOST', '192.168.11.12');
	define('UC_DBUSER', 'tizi');
	define('UC_DBPW', 'tizi');
	define('UC_DBNAME', 'tizibbs');
	define('UC_DBCHARSET', 'utf8');
	define('UC_DBTABLEPRE', '`tizibbs`.tizi_ucenter_');
	define('DISCUZ_DBTABLEPRE', '`tizibbs`.tizi_');
	define('DISCUZ_MEMBER', '`tizibbs`.tizi_common_member');
	define('UC_DBCONNECT', '0');
	define('UC_KEY', '22222');
	define('UC_API', 'http://192.168.11.12:8687/uc_server');
	define('UC_CHARSET', 'utf-8');
	define('UC_IP', '');
	define('UC_APPID', '3');
	define('UC_PPP', '20');
	
}else{
	define('UC_DBHOST', 'rdsnuyizmnuyizm.mysql.rds.aliyuncs.com');
	define('UC_DBUSER', 'tizibbs');
	define('UC_DBPW', '6dp8SM0d2UnZc');
	define('UC_DBNAME', 'tizibbs');
	define('UC_DBCHARSET', 'utf8');
	define('UC_DBTABLEPRE', '`tizibbs`.tizi_ucenter_');
	define('DISCUZ_DBTABLEPRE', '`tizibbs`.tizi_');
	define('DISCUZ_MEMBER', '`tizibbs`.tizi_common_member');
	define('UC_DBCONNECT', '0');
	define('UC_KEY', '22222');
	define('UC_API', 'http://bbs.tizi.com/uc_server');
	define('UC_CHARSET', 'utf-8');
	define('UC_IP', '');
	define('UC_APPID', '3');
	define('UC_PPP', '20');
}

