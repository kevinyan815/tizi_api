<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class ftest extends CI_Controller {

	/**
	 * Simpe Test for log4php
	 */
    public function __construct() {
        parent::__construct();
    }
	
    public function index()
    {
    	exit();
    }

	function check_session()
	{		
		echo "<pre>";
		print_r($_COOKIE);
		echo "</pre>";
        echo "<pre>";
		print_r($this->session->all_userdata());				
		echo "</pre>";
		echo get_remote_ip();
		echo "<br />";
		echo $this->input->server('HTTP_USER_AGENT');
	}
	function clear_session()
	{
		$this->session->sess_destroy();
		//$this->load->helper("cookie");
		delete_cookie(Constant::COOKIE_TZUSERNAME);
	}
}

/* End of file log4php.php */
/* Location: ./application/controllers/test/log4php.php */
