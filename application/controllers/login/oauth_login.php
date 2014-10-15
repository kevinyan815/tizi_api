<?php
/**
 * Created by JetBrains PhpStorm.
 * User: 91waijiao
 * Date: 14-4-24
 * Time: 下午3:23
 * To change this template use File | Settings | File Templates.
 */
class Oauth_Login extends MY_Controller{

	public function __construct(){
		parent::__construct();
	}
//http://api.tizi.cn/oauth/callback?open_id=1653158472&platform=2&access_token=2.001CUsnBzyxEbE225981e3fbanpLpB
	public function callback(){
		$db_data=array(
			'open_id'=>$this->input->post('open_id', true),
			'platform'=>$this->input->post('platform', true),
			'access_token'=>$this->input->post('access_token', true),
		);

		if (empty($db_data['open_id']) || empty($db_data['platform']) || empty($db_data['access_token'])) {
			echo json_token(array('errorcode' => 1, 'error' => '参数不正确！'));
			exit;
		}

		$this->load->model('oauth/oauth_model');
		$user_auth_data = $this->oauth_model->save($db_data);

		if(empty($user_auth_data['user_id'])){//未绑定用户
			echo json_token(array('errorcode' => 2, 'error' => '账户不存在，去完善信息！', 'oauth_data' => $user_auth_data));
			exit;
		}

		echo json_token(array('errorcode' => 3, 'error' => '账户已经存在，去登陆！', 'oauth_data' => $user_auth_data));
		exit;
	}
//http://api.tizi.cn/oauth/register?username=1653158472@126.com&password=&name=ttmm&user_type=2&register_type=1&oauth_id=4
	public function register(){
		$username = $this->input->post('username', true);
		$password = $this->input->post('password', true);
		$name = $this->input->post('name', true);
		$user_type = $this->input->post('user_type', true);
		$register_type = $this->input->post('register_type', true);

		$send_email = isset($_POST['send_email']) ? $_POST['send_email'] : true;

		$app_name = $this->input->post('app_name', true);
		$phone_os = $this->input->post('phone_os', true);
		$platform = $this->input->post('platform', true);

		$oauth_id = $this->input->post('oauth_id', true);

		$error_code = array('errorcode' => false, 'error' => '');

		if (empty($username) || empty($user_type) || empty($register_type) || empty($oauth_id)) {
			$error_code['error'] = '参数不正确！';
			echo json_token($error_code);
			exit;
		}

		$this->load->model('login/register_model');
		$this->load->model('oauth/oauth_model');

		$check_email = $this->register_model->check_email($username);

		if ($check_email['errorcode']){
			//如果存在
			$error_code['error'] = '您注册的邮箱已经存在！';
			echo json_token($error_code);
			exit;
		}

		$user_data = array('register_origin' => Constant::REG_ORIGIN_APP_ANDROID_QQ);
		if ($app_name == Constant::APP_TIKU_NAME) {
			if ($platform == 1) {
				//qq
				$user_data['register_origin'] = ($phone_os == 'ios') ? Constant::REG_ORIGIN_IOS_TIKU_QQ : Constant::REG_ORIGIN_ANDROID_TIKU_QQ;
			} else if ($platform == 2){
				//weibo
				$user_data['register_origin'] = ($phone_os == 'ios') ? Constant::REG_ORIGIN_IOS_TIKU_WEIBO : Constant::REG_ORIGIN_ANDROID_TIKU_WEIBO;
			}
		} else {
			if ($platform == 1) {
				//qq
				$user_data['register_origin'] = ($phone_os == 'ios') ? Constant::REG_ORIGIN_APP_IOS_QQ : Constant::REG_ORIGIN_APP_ANDROID_QQ;
			} else if ($platform == 2){
				//weibo
				$user_data['register_origin'] = ($phone_os == 'ios') ? Constant::REG_ORIGIN_APP_IOS_WEIBO : Constant::REG_ORIGIN_APP_ANDROID_WEIBO;
			}
		}

		$user = $this->register_model->insert_register($username,$password,$name,$register_type,$user_type,$user_data, $send_email);
		if ($user['errorcode']) {
			$user_auth_data = $this->oauth_model->save(array('user_id' => $user['user_id']), $oauth_id);
			$error_code['errorcode'] = true;
			$error_code['user_info'] = $user;
		} else {
			$error_code['error'] = '用户注册失败！';
		}

		echo json_token($error_code);
		exit;
	}
//http://api.tizi.cn/oauth/login?user_id=812805577&app_type=4&name=ttmm123
	public function login(){
		$user_id = $this->input->post('user_id', true);
		$api_type=$this->input->post("app_type",true);
		$api_name=$this->input->post("app_name",true);
		$name = $this->input->post('name', true);

		if (empty($user_id) || empty($api_type) || empty($api_name)) {
			echo json_token(array('errorcode'=>false, 'response_status' => 'error', 'error'=>'参数不正确！'));
			exit;
		}

		$this->load->model('login/register_model');
		$this->load->model('login/session_model');
		$user_info = $this->register_model->get_user_info($user_id);

		$submit = array('errorcode'=>false, 'response_status' => 'error', 'error'=>'');

		if (!$user_info['errorcode']) {
			$submit['error'] = $submit['response_error_message'] = '用户不存在！';
			echo json_token($submit);
			exit;
		}

//		if($user_info['user']->user_type != Constant::USER_TYPE_STUDENT) {
//			$submit['error'] = $submit['response_error_message'] = '用户类型不正确！';
//			echo json_token($submit);
//			exit;
//		}

		if ($this->register_model->update_name($user_id, $name)) $user_info['user']->name = $name;

		$session_id=$this->session_model->generate_api_session($user_id,$api_type);

		$this->load->model('user_data/user_data_model');
		$user_data_info = $this->user_data_model->init_user_data(array('user_id' => $user_id));
		//更新用户使用过的应用
		$this->user_data_model->update_user_apps($user_id, $api_name);

		$submit['errorcode'] = true;
		$submit['response_status'] = 'ok';
		$submit['response_data'] = array('done' => 1,'session_id' => $session_id);
		$submit['response_data']['user_info'] = $user_info['user'];
		$submit['response_data']['user_data_info'] = $user_data_info;


		echo json_token($submit);
		exit;
	}
}