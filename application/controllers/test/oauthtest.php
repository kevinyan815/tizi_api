<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class oauthtest extends CI_Controller {

    public function __construct() {
        parent::__construct();
    }
	
    public function index()
    {
    	echo "<a href='/test/oauthtest/login' target='_blank'>Oauth登录测试</a>";
    }

    public function login()
    {
		require("TiziOauth.php");
    	$config = require('config.php');
		$tizi = new TiziOauth($config);
		$tizi->tizi_login();
    }

    public function callback()
    {
		require("TiziOauth.php");
    	$config = require('config.php');
		$tizi = new TiziOauth($config);

		$result = $tizi->get_accesstoken();
		if (!isset($result['error'])) 
		{
			$openid = $tizi->get_openid();
			if($openid)
			{
				$user_info = $tizi->get_user_info();
				print_r($user_info);
			}
		}
		else
		{
			var_dump($result['error']);
		}
    }

}

/* End of file log4php.php */
/* Location: ./application/controllers/test/log4php.php */
