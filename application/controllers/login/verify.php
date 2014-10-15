<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Verify extends MY_Controller {
	
    function __construct()
    {
        parent::__construct();
		$this->load->model("login/session_model");
		$this->load->model("login/register_model");
		$this->load->model("login/verify_model");
    }

	/**
	 * 发送手机验证码
	 */
	function send_phone_code()
	{
		$phone=$this->input->post("phone",true);
		$code_type=$this->input->post("code_type",true);
		$session_id=$this->input->post("session_id",true);
		$api_type=$this->input->post("app",true);

		$user_id=$this->session_model->get_api_session($session_id,$api_type,"user_id");

		if (!isset($user_id['user_id'])) $user_id['user_id'] = 0;
		if($phone)
		{
			$checkphone=$this->register_model->check_phone($phone);//print_r($checkphone);exit;
			if(!$checkphone['errorcode']&&$checkphone['user_id']==-127)
			{//发送手机验证码的服务挂了
				$errorcode['errorcode']=false;
				$errorcode['response_status']='error';
				$errorcode['response_error_code']=0;
				$errorcode['error']=$errorcode['response_error_message']=$this->lang->line('default_error');
			}
			else if($checkphone['errorcode']&&$code_type==Constant::CODE_TYPE_REGISTER)
			{//注册已经存在此手机
				$errorcode['errorcode']=false;
				$errorcode['response_status']='error';
				$errorcode['response_error_code']=0;
				$errorcode['error']=$errorcode['response_error_message']=$this->lang->line('error_reg_exist_phone');
			}
			else if($checkphone['errorcode']&&$checkphone['user_id']!=$user_id['user_id']&&$code_type==Constant::CODE_TYPE_CHANGE_PHONE)
			{//更改手机号的时候  sessionid的urserid！= 手机号注册的用户
				$errorcode['errorcode']=false;
				$errorcode['response_status']='error';
				$errorcode['response_error_code']=0;
				$errorcode['error']=$errorcode['response_error_message']=$this->lang->line('error_exist_phone');
			}
			else if(!$checkphone['errorcode']&&$code_type==Constant::CODE_TYPE_CHANGE_PASSWORD)
			{//修改密码不存在手机号
				$errorcode['errorcode']=false;
				$errorcode['response_status']='error';
				$errorcode['response_error_code']=0;
				$errorcode['error']=$errorcode['response_error_message']=$this->lang->line('error_reset_password_not_verify_phone');
			}
			else
			{

				$this->load->model('login/verify_model');
				$authcode=$this->verify_model->generate_authcode_phone($phone,$code_type,$user_id['user_id']);
				if($authcode['errorcode']&&$authcode['authcode'])
				{
					$errorcode=$this->verify_model->send_authcode_phone($authcode['authcode'],$phone,$code_type);
					if($errorcode['errorcode']) {
						$errorcode['response_status']='ok';
						$errorcode['error']=$this->lang->line('success_send_auth_phone');
						$errorcode['response_data'] = array('done' => 1);
					} else {
						$errorcode['response_status']='error';
						$errorcode['response_error_code']=0;
						$errorcode['error']=$errorcode['response_error_message']=$this->lang->line('error_send_auth_phone');
					}
				}
				else
				{
					$error=$this->lang->line('error_authcode_phone_limit');
					$errorcode=array('errorcode'=>false,'response_status'=>'error','response_error_code'=>0,'error'=>$error,'response_error_message'=>$error);
				}
			}
		}
		else
		{
			$errorcode=array('errorcode'=>false,'response_status'=>'error',
				'error'=>$this->lang->line("error_invalid_phone"),'response_error_code'=>0,
				'response_error_message'=>$this->lang->line("error_invalid_phone")
			);
		}
		echo json_token($errorcode);
		exit();
	}

	function check_code(){
		$phone=$this->input->post('phone',true);
		$authcode=$this->input->post('code',true);
		$code_type=$this->input->post('code_type',true);
		$verify=$this->input->post('verify',true);

		$errorcode_arr = array();

		$auth=$this->verify_model->verify_authcode_phone($authcode,$phone,$verify);

		if($auth['errorcode'])
		{
			$errorcode_arr['errorcode'] = true;
			$errorcode_arr['response_status'] = 'ok';

			if (!empty($code_type) && $code_type != $auth['code_type']) {
				$errorcode_arr['errorcode'] = false;
				$errorcode_arr['response_status'] = 'error';
				$errorcode_arr['error'] = $errorcode_arr['response_error_message'] = $this->lang->line('error_code_type');
			}
		}
		else
		{
			$errorcode_arr['errorcode']=false;
			$errorcode_arr['response_status'] = 'error';
			$errorcode_arr['error'] = $errorcode_arr['response_error_message'] = $this->lang->line('error_sms_code');
		}

		if ($errorcode_arr['errorcode']) $errorcode_arr['response_data'] = array('done' => 1);
		echo json_token($errorcode_arr);
		exit();
	}

	//检测手机号是否存在  http://api.tizi.cn/login/check_phone?phone=13301250583
	public function check_phone() {
		$username=$this->input->post("phone",true);

		$error_code = array('errorcode' => false,'response_status' => 'error', 'error' => '', 'response_error_message' => '');

		if (!$username) {
			$error_code['error'] = '传参有误！';
			$error_code['response_error_message'] = '传参有误！';
			echo json_token($error_code);
			exit;
		}

		$check_phone = $this->register_model->check_phone($username);
		if ($check_phone['errorcode']){
			//如果存在
			$error_code['errorcode'] = true;
			$error_code['response_status'] = 'ok';
			$error_code['error'] = '您注册的手机号已经存在！';
			$error_code['response_data'] = array('done' => 1);
			echo json_token($error_code);
			exit;
		}
		//如果不存在
		$error_code['errorcode'] = false;
		$error_code['error'] = '您的手机号还未注册！';
		$error_code['response_error_message'] = '您的手机号还未注册！';
		echo json_token($error_code);
		exit;
	}
	
}	
/* End of file login.php */
/* Location: ./application/controllers/login/login.php */