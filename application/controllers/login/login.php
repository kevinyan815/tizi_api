<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Login extends MY_Controller {

    function __construct()
    {
        parent::__construct();

		$this->load->model("login/login_model");
		$this->load->model("login/session_model");
		$this->load->model("login/register_model");
    }

    public function tizi_submit()
    {
		$username=$this->input->post("username",true);
		$password=$this->input->post("password",true);
//		$api_type=$this->input->post("app_type",true);
//		$user_id = $this->input->post('user_id', true);
//		$name = $this->input->post('name', true);


		//debug
		if(strlen($password) != 32) $password=md5('ti'.$password.'zi');

		$submit=$this->submit($username,$password,Constant::API_TYPE_TIZI);
		echo json_token($submit);
    }

    public function jxt_submit()
    {

    }

    private function submit($username,$password,$api_type) {
		$submit=array('errorcode'=>false, 'response_status' => 'error', 'error'=>'');

		$user_id=$this->login_model->login($username,$password);

		if($user_id['errorcode']==Constant::LOGIN_SUCCESS)
		{
			//create app session
			$session_id=$this->session_model->generate_api_session($user_id['user_id'],$api_type);

			if($user_id['error']) {
				$submit['error']=$this->lang->line('error_'.strtolower($user_id['error']));
			}

			$submit['session_id']=$session_id;
			$submit['errorcode']=true;
		}
		else if($user_id['errorcode'] != Constant::LOGIN_INVALID_TYPE)
		{
			$submit['error']=$submit['response_error_message']=$this->lang->line('error_'.strtolower($user_id['error']));
		}
		else
		{
			$submit['error']=$submit['response_error_message']=$this->lang->line('error_'.strtolower($user_id['error']));
		}

		if ($submit['errorcode']) {
			$submit['response_status'] = 'ok';
			$submit['response_data'] = array('done' => 1,'session_id' => $session_id);
		}

		return $submit;
	}

	/**http://api.tizi.cn/login/user_login?username=1054770532@qq.com&password=e0b0616098d16fec3de7b2f12b7ce519&app_type=4&app_name=dafen&login_type=3
	 * 用户登录（刷题）
	 */
	public function user_login() {
		$username=$this->input->post("username",true);
		$password=$this->input->post("password",true);
		$api_type=$this->input->post("app_type",true);//API_TYPE_DAFEN
		$api_name = $this->input->post('app_name', true);//APP_DAFEN_NAME
		$login_type = $this->input->post('login_type', true);//USER_TYPE_TEACHER


		if (empty($api_type) || empty($username) || empty($password) || empty($api_name)) {
			echo json_token(array('errorcode'=>false, 'response_status' => 'error', 'error'=>'参数不正确！', 'response_error_message'=>'参数不正确！'));
			exit;
		}

		$this->load->model('user_data/user_data_model');
		$submit=array('errorcode'=>false, 'response_status' => 'error', 'error'=>'');


		$user_id=$this->login_model->login($username,$password);

		if ($login_type && !empty($user_id['user_type']) && $user_id['user_type'] != $login_type) {
			$submit['error'] = $submit['response_error_message'] = '用户类型不正确！';
			echo json_token($submit);
			exit;
		}

		if($user_id['errorcode']==Constant::LOGIN_SUCCESS) {
			//create app session
			$session_id=$this->session_model->generate_api_session($user_id['user_id'],$api_type);

			if($user_id['error']) {
				$submit['error']=$this->lang->line('error_'.strtolower($user_id['error']));
			}

			$submit['session_id']=$session_id;
			$submit['errorcode']=true;
		} else if($user_id['errorcode'] != Constant::LOGIN_INVALID_TYPE) {
			$submit['error']=$submit['response_error_message']=$this->lang->line('error_'.strtolower($user_id['error']));
		} else {
			$submit['error']=$submit['response_error_message']=$this->lang->line('error_'.strtolower($user_id['error']));
		}

		if ($submit['errorcode']) {
			$submit['response_status'] = 'ok';
			$submit['response_data'] = array('done' => 1,'session_id' => $session_id);
			$user_info = $this->register_model->get_user_info($user_id['user_id']);
			$user_phone = $this->register_model->get_phone($user_id['user_id']);
			$this->load->helper('img_helper');
			$submit['response_data']['phone'] = $user_phone['errorcode'] ? $user_phone['phone'] : '';
			$submit['response_data']['avatar_url'] = !empty($user_info['user']->avatar) ? path2avatar($user_id['user_id']) : tizi_url() . 'application/views/static/debug/image/common/default_avatar.gif';
//			$submit['response_data']['avatar_url'] = !empty($user_info['user']->avatar) ? path2avatar($user_id['user_id']) : static_url('tizi') . 'debug/image/common/default_avatar.gif';
			$submit['response_data']['user_info'] = $user_info['user'];

			$user_data_info = $this->user_data_model->init_user_data(array('user_id' => $user_id['user_id']));

			//更新用户使用过的应用
			$this->user_data_model->update_user_apps($user_id['user_id'], $api_name);

			$submit['response_data']['user_data_info'] = $user_data_info;
		}

		echo json_token($submit);
		exit;
	}

	/**
	 * 重置密码
	 */
	public function reset_password () {
		$password = $this->input->post('password');
		$phone = $this->input->post('phone');
		$authcode=$this->input->post('code',true);
		$code_type=$this->input->post('code_type',true);
		$errorcode=array('errorcode' => false, 'error' => '', 'response_status' => 'error', 'response_error_message' => '');

		if (!$password || !$phone || !$authcode || !$code_type) {
			$errorcode['error'] = '传参有误！';
			$errorcode['response_error_message'] = '传参有误！';
			echo json_token($errorcode);
			exit;
		}

		// 验证码验证逻辑
		$this->load->model("login/verify_model");
		$auth=$this->verify_model->verify_authcode_phone($authcode,$phone);
		if( ! $auth['errorcode'] || (!empty($code_type) && $code_type != $auth['code_type']) )
		{
			$errorcode['response_error_message'] = '验证码输入有误';
			$errorcode['errorcode']              = -5;
			echo json_token($errorcode);
			exit;
		}


		$user_p = $this->register_model->check_phone($phone);

		if (!$user_p['errorcode']) {
			$errorcode['error'] = '手机号不存在！';
			$errorcode['response_error_message'] = '手机号不存在！';
			echo json_token($errorcode);
			exit;
		}

		$reset_pwd = $this->register_model->update_password($user_p['user_id'], $password);

		if (!$reset_pwd['errorcode']) {
			$errorcode['error'] = '更改密码失败！';
			$errorcode['response_error_message'] = '更改密码失败！';
			echo json_token($errorcode);
			exit;
		}

//		$errorcode['error'] = '';
		$errorcode['response_status'] = 'ok';
		$errorcode['errorcode'] = true;
		$errorcode['response_data'] = array('done' => 1);
		echo json_token($errorcode);
		exit;
	}

	function logout()
	{
		//clear app session;
	}

}
/* End of file login.php */
/* Location: ./application/controllers/login/login.php */
