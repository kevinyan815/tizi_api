<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Qrcode_Login extends MY_Controller{

	public function __construct(){
		parent::__construct();
		$this->load->model('redis/redis_model');
		$this->load->model('login/session_model');
	}
	//http://api.tizi.cn/sso/qrcode?uid=1000006&qrtoken=123123;
	public function login() {
		$qrtoken=str_replace(' ', '+', $this->input->post('qrtoken',true,true,''));
		$api_type = $this->input->post("app",true);
		$session_id = $this->input->post("session_id",true);

		$user_info = $this->session_model->get_api_session($session_id, $api_type, "user_id");

		$error_code = array('errorcode'=>false,'response_status'=>'error','response_error_code' => 99,'response_error_message'=>'该账号已在其它手机登陆，请退出重新登录','error'=>'该账号已在其它手机登陆，请退出重新登录');

		if (!$api_type || !$session_id || !$qrtoken) {
			$error_code['response_error_code'] = -1;
			$error_code['response_error_message'] = '参数传递有误！';
			$error_code['error'] = '参数传递有误！';
			echo json_token($error_code);
			exit;
		}

		$user_id = !empty($user_info['user_id']) ? $user_info['user_id'] : 0;
		if(!$user_id) {
			echo json_token($error_code);
			exit;
		}

		if(!$this->redis_model->connect('qrcode_login')){
			$error_code['response_error_code'] = -2;
			$error_code['response_error_message'] = 'redis连接失败！';
			$error_code['error'] = 'redis连接失败！';
			echo json_token($error_code);
			exit;
		}

		if (!$this->cache->exists($qrtoken)) {
			$error_code['response_error_code'] = -3;
			$error_code['response_error_message'] = '二维码已失效！';
			$error_code['error'] = '二维码已失效！';
			echo json_token($error_code);
			exit;
		}

		//获取二维码
		$login_value = $this->cache->get($qrtoken);
		$login_value = json_decode($login_value, true);

		if(empty($login_value['session_id'])){
			$error_code['response_error_code'] = -4;
			$error_code['response_error_message'] = 'redis已过期！';
			$error_code['error'] = 'redis已过期！';
			echo json_token($error_code);
			exit;
		}
		//指定用户登录
		$session = $this->session_model->generate_session($user_id, $login_value['session_id']);
		$this->session->set_userdata('login_app', $api_type);
		if(!isset($session['errorcode'])) {
			$error_code['response_error_code'] = -5;
			$error_code['response_error_message'] = '用户登录失败！';
			$error_code['error'] = '用户登录失败！';
			echo json_token($error_code);
			exit;
		}
		//登陆成功
		$this->cache->delete($qrtoken);

		$error_code['response_error_code'] = 1;
		$error_code['response_error_message'] = '';
		$error_code['error'] = '';
		$error_code['errorcode'] = '';
		$error_code['response_status'] = 'ok';
		$error_code['response_data'] = array('done' => 1);

		echo json_token($error_code);
		exit;
	}
}
