<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Register extends MY_Controller {

    function __construct()
    {
        parent::__construct();

//		$this->load->model("login/login_model");
//		$this->load->model("login/session_model");
		$this->load->model("login/register_model");
    }

	/**
	 * user register
	 */
	public function user_register() {
		$username = $this->input->post('username', true);
		$password = $this->input->post('password', true);
		$name = $this->input->post('name', true);
		$user_type = $this->input->post('user_type', true);
		$send_email = isset($_POST['send_email']) ? $_POST['send_email'] : true;
		$register_type = $this->input->post('register_type', true);

		$app_name = $this->input->post('app_name', true);
		$phone_os = $this->input->post('phone_os', true);

		$error_code = array('errorcode' => false, 'error' => '');

		if (empty($username) || empty($password) || empty($user_type) || empty($register_type)) {
			$error_code['error'] = '参数不正确！';
			echo json_token($error_code);
			exit;
		}

		$check_email = $this->register_model->check_email($username);

		if ($check_email['errorcode']){
			//如果存在
			$error_code['error'] = '您注册的邮箱已经存在！';
			echo json_token($error_code);
			exit;
		}

		$user_data = array();
		if ($app_name == Constant::APP_TIKU_NAME) {
			$user_data['register_origin'] = ($phone_os == 'ios') ? Constant::REG_ORIGIN_IOS_TIKU : Constant::REG_ORIGIN_ANDROID_TIKU;
		} else {
			$user_data['register_origin'] = ($phone_os == 'ios') ? Constant::REG_ORIGIN_APP_IOS : Constant::REG_ORIGIN_APP_ANDROID;
		}


		$user = $this->register_model->insert_register($username, $password, $name, $register_type, $user_type, $user_data, $send_email);

		!$user['errorcode'] ? $error_code['error'] = '用户注册失败！' : $error_code['errorcode'] = true;
		echo json_token($error_code);
		exit;
	}

	/**http://api.tizi.cn/register/phone_register?phone=13301250583&password=e0b0616098d16fec3de7b2f12b7ce519&user_type=2&register_type=2&app_name=daf
	 * 手机注册
	 */
	public function phone_register() {//echo md5('ti123123zi');exit;
		$username = $this->input->post('phone', true);
		$password = $this->input->post('password', true);
		$name = $this->input->post('name', true);
		$user_type = $this->input->post('user_type', true);
		$send_email = isset($_POST['send_email']) ? $_POST['send_email'] : true;
		$register_type = $this->input->post('register_type', true);

		// 返回信息
		$error_code = array('errorcode' => false, 'error' => '', 'response_status' => 'error', 'response_error_message' => '');

		// 验证码验证逻辑
		$authcode=$this->input->post('code',true);
		$code_type=$this->input->post('code_type',true);
		$this->load->model("login/verify_model");
		$auth=$this->verify_model->verify_authcode_phone($authcode,$username);
		if( ! $auth['errorcode'] || (!empty($code_type) && $code_type != $auth['code_type']) )
		{
			$error_code['response_error_message'] = '验证码输入有误';
			$error_code['errorcode']              = -5;
			echo json_token($error_code);
			exit;
		}

		$app_name = $this->input->post('app_name', true);
		$phone_os = $this->input->post('phone_os', true);

		if (empty($username) || empty($password) || empty($user_type) || empty($register_type)) {
			$error_code['error'] = '参数不正确！';
			echo json_token($error_code);
			exit;
		}

		$check_phone = $this->register_model->check_phone($username);

		if ($check_phone['errorcode']){
			//如果存在
			$error_code['error'] = '您注册的手机号已经存在！';
			echo json_token($error_code);
			exit;
		}

		$user_data = array();
		if ($app_name == Constant::APP_DAFEN_NAME) {
			$user_data['register_origin'] = ($phone_os == 'ios') ? Constant::REG_ORIGIN_IOS_DAFEN : Constant::REG_ORIGIN_ANDROID_DAFEN;
		} else {
			$user_data['register_origin'] = ($phone_os == 'ios') ? Constant::REG_ORIGIN_APP_IOS : Constant::REG_ORIGIN_APP_ANDROID;
		}


		$user = $this->register_model->insert_register($username, $password, $name, $register_type, $user_type, $user_data, $send_email);

		if (!$user['errorcode']) {
			$error_code['error'] = '用户注册失败！';
			$error_code['response_error_message'] = '用户注册失败！';
		} else {
			$error_code['errorcode'] = true;
			$error_code['response_status'] = 'ok';
			$error_code['response_data'] = array('done'=> 1);
		}
		echo json_token($error_code);
		exit;
	}


}
/* End of file login.php */
/* Location: ./application/controllers/login/login.php */
