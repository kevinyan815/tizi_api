<?php 
class Api_Controller extends LI_Controller 
{
    protected $_check_login=false;
    protected $_check_token=false;
    protected $_check_captcha=false;
    protected $_check_post=false;

	public  function __construct()
	{
		parent::__construct('api');
	}



}
