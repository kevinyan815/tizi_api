<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class User_Info extends MY_Controller {

	protected $user_id=0;
	protected $session_id='';
	protected $api_type=0;

	function __construct()
	{
		parent::__construct();
		$this->load->model("login/session_model");
		$this->load->model("login/register_model");
		$this->load->model("login/verify_model");

		$this->api_type=$this->input->post("app",true);
		$this->session_id=$this->input->post("session_id",true);
		$user_info=$this->session_model->get_api_session($this->session_id,$this->api_type,"user_id");
		if(isset($user_info['user_id'])) $this->user_id=$user_info['user_id'];

		if(!$this->user_id)
		{
			echo json_token(array('errorcode'=>false,'response_status'=>'error','response_error_code'=>99,'response_error_message'=>'该账号已在其它手机登陆，请退出重新登录','error'=>'该账号已在其它手机登陆，请退出重新登录'));
			exit();
		}
	}

	public function change_name()
	{
		$name=trim($this->input->post("urname",true));
		$errorcode=array('errorcode'=>false,'error'=>$this->lang->line('error_change_default'));

		$errorcode=$this->register_model->update_name($this->user_id,$name);
		if(!$errorcode['errorcode']) {
			$errorcode['error']=$this->lang->line('error_change_default');
			$errorcode['response_error_code']=0;
		}
		else {
			$errorcode['error']=$this->lang->line('success_change_default');
			$errorcode['response_status'] = 'ok';
			$errorcode['response_data'] = array('done' => 1);
		}

		echo json_token($errorcode);
		exit();
	}

	/**
	 * 父母修改孩子的名字接口
	 */
	public function change_child_name() {
		$child_id = $this->input->post('child_id',true);
		$name=trim($this->input->post("urname",true));

		$errorcode = array('response_status'=>'error');
		//父母是不是有修改孩子信息的权限.
		if (!$this->is_parents_child($child_id)) {
			$errorcode['errorcode'] = false;
			$errorcode['error'] = $errorcode["response_error_message"] = $this->lang->line('error_not_parents_child');
			echo json_token($errorcode);
			exit();
		}

		$errorcode = $this->register_model->update_name($child_id, $name);

		if(!$errorcode['errorcode']) {
			$errorcode['error'] = $errorcode["response_error_message"] = $this->lang->line('error_change_default');
		} else {
			$errorcode['response_status'] = 'ok';
			$errorcode['response_data'] = array('done' => 1);
			$errorcode['error']=$this->lang->line('success_change_default');
		}
		echo json_token($errorcode);
		exit();
	}

	/**
	 * 学生加入班级的api(父母操作)
	 */
	public function sign_class(){
		$alpha_class_id = $this->input->post('class_id',true);
		$child_id = $this->input->post('child_id',true);
		$class_id= alpha_id($alpha_class_id, true);

		$errorcode = array('response_status'=>'error');
		//判断班级是不是存在
		if ($class_id <= 0) {
			$errorcode["errorcode"] = -1;
			$errorcode["error"] = $errorcode["response_error_message"] = "班级不存在.";
			echo json_token($errorcode);
			exit();
		}

		//父母是不是有修改孩子信息的权限.
		if (!$this->is_parents_child($child_id)) {
			$errorcode['errorcode'] = -7;
			$errorcode['error'] = $errorcode["response_error_message"] = $this->lang->line('error_not_parents_child');
			echo json_token($errorcode);
			exit();
		}

		//$this->load->helper('json');

		$this->load->model('class/classes');
		$this->load->model('class/classes_student_create');

		$class_info = $this->classes->get($class_id);
		$class_number = $this->classes_student_create->total($class_id);

		if (null === $class_info) {
			$errorcode["errorcode"] = -6;
			$errorcode["error"] = $errorcode["response_error_message"] = "班级不存在.";
		} elseif ($class_info['class_status']) {
			$errorcode["errorcode"] = -2;
			$errorcode["error"] = $errorcode["response_error_message"] = "该班级已经被班级创始人解散,您现在无法加入它.";
		} elseif ($class_info['close_status']) {
			$errorcode["errorcode"] = -3;
			$errorcode["error"] = $errorcode["response_error_message"] = "该班级已经关闭学生加入.";
		} elseif (($class_info['stu_count'] + $class_number) >= Constant::CLASS_MAX_HAVING_STUDENT) {
			$errorcode["errorcode"] = -4;
			$errorcode["error"] = $errorcode["response_error_message"] = "该班级的人数已经达到了".Constant::CLASS_MAX_HAVING_STUDENT."个,已经不能再加入更多的学生.";
		} else {
			$this->load->model('class/classes_student');
			$student_id = $this->classes_student->add($class_id, $child_id, time(), Classes_student::JOIN_METHOD_REGISTER);
			if (false === $student_id) {
				$errorcode["errorcode"] = -5;
				$errorcode["error"] = $errorcode["response_error_message"] = "您已经加入过班级了,请尝试刷新页面.";
			} else {
				$errorcode["errorcode"] = 1;
				$errorcode['response_status'] = 'ok';
				$errorcode['response_data'] = array('done' => 1);
				$errorcode["error"] = "您已经成功加入该班级";
			}
		}
		echo json_token($errorcode);
		exit();
	}

	/**
	 * 学生退出班级api(父母操作)
	 */
	public function dropout_class() {
		$child_id = $this->input->post('child_id',true);

		$errorcode = array('response_status'=>'error');
		//父母是不是有修改孩子信息的权限.
		if (!$this->is_parents_child($child_id)) {
			$errorcode['errorcode'] = -3;
			$errorcode['error'] = $errorcode["response_error_message"] = $this->lang->line('error_not_parents_child');
			echo json_token($errorcode);
			exit();
		}


		$this->load->model('login/register_model');
		$this->load->model('class/classes_student');
		$class_student = $this->classes_student->userid_get($child_id);

		if (isset($class_student[0])){
			$class_student = $class_student[0];
			if (true === $this->classes_student->remove($class_student["id"], $class_student["class_id"])){
				//退出成功给所有老师都发送消息通知
				$this->load->model("class/classes");
				$this->load->model("class/classes_teacher");
				$this->load->model("class/classes_notify");
				$user_info = $this->register_model->get_user_info($child_id);
				$class_info = $this->classes->get($class_student["class_id"], "classname");
				$realname = $user_info["user"]->name;
				$classname = $class_info["classname"];
				$idct = $this->classes_teacher->get_idct($class_student["class_id"], "teacher_id");
				$msg = "学生{$realname}已经退出您的班级：{$classname}";
				foreach ($idct as $value){
					$this->classes_notify->add($value["teacher_id"], $msg, time());
				}
				$errorcode["errorcode"] = 1;
				$errorcode['response_status'] = 'ok';
				$errorcode['response_data'] = array('done' => 1);
				$errorcode["error"] = "退出班级成功.";
			} else {
				$errorcode["error"] = -2;
				$errorcode["error"] = $errorcode["response_error_message"] = "系统忙,请稍后再试.";
			}
		} else {
			$errorcode["errorcode"] = -1;
			$errorcode["error"] = $errorcode["response_error_message"] = "该处理已经更新,请尝试刷新页面.";
		}
		echo json_token($errorcode);
		exit();
	}

	/** 判断孩子是不是属于parents
	 * @param $parents_child
	 * @param $child_id
	 * @return bool
	 */
	private function is_parents_child($child_id){
		$this->load->model('login/parent_model');
		$parents_child = $this->parent_model->get_kids($this->user_id);

		foreach ($parents_child as $vp) {
			if ($vp['id'] == $child_id) {
				return true;
			}
		}
		return false;
	}
	/*
	 * 手机号
	 */
	public function change_phone()
	{
		$phone=$this->input->post("phone",true);
		$authcode=$this->input->post("code",true);
		$password=$this->input->post("password",true);
		$nocheck=$this->input->post('nocheck',true);
		$errorcode=array('errorcode'=>false,'response_status'=>'error','error'=>$this->lang->line('error_change_default'),'error_password'=>false);

		if(!preg_phone($phone))
		{
			$errorcode['error']=$errorcode['response_error_message']=$this->lang->line('error_invalid_phone');
			$errorcode['response_error_code'] = 0;
			echo json_token($errorcode);
			exit();
		}

		//不传递验证码时,verified为3
		if($nocheck)
		{
			$phone_verified=3;
		}
		else
		{
			$phone_verified=1;
		}

		if($password)
		{
			$password_errorcode=$this->register_model->verify_password($user_id,$password);
		}
		else
		{
			$password_errorcode['errorcode']=true;
		}

		if($password_errorcode['errorcode'])
		{
			$code_type=$this->verify_model->verify_authcode_phone($authcode,$phone);

			if($code_type['code_type']==Constant::CODE_TYPE_CHANGE_PHONE||$nocheck)
			{
				if($code_type['errorcode']||$nocheck)
				{
					$check_phone=$this->register_model->check_phone($phone);
					if($check_phone['errorcode'])
					{
						if($check_phone['user_id']==$this->user_id)
						{
							$update_verified=$this->register_model->update_phone_verified($this->user_id,$phone_verified);
							if(!$update_verified)
							{
								$errorcode['error']=$errorcode['response_error_message']=$this->lang->line('error_change_default');
								$errorcode['response_error_code'] = 0;
							}
							else
							{
								$errorcode['errorcode']=true;
								$errorcode['response_status']='ok';
								$errorcode['response_data'] = array('done' => 1);
								$errorcode['error']=$this->lang->line('success_change_default');
							}
						}
						else
						{
							$errorcode['error']=$errorcode['response_error_message']=$this->lang->line('error_exist_phone');
							$errorcode['response_error_code'] = 0;
						}
					}
					else if($check_phone['user_id'] == -127)
					{
						$errorcode['error']=$errorcode['response_error_message']=$this->lang->line('error_change_default');
						$errorcode['response_error_code'] = 0;
					}
					else
					{
						$errorcodes = $this->register_model->update_phone($this->user_id,$phone,$phone_verified);
						if(!$errorcodes['errorcode']) {
							$errorcode['error']=$errorcode['response_error_message']=$this->lang->line('error_change_default');
							$errorcode['response_error_code'] = 0;
						}
						else {
							$errorcode['response_status']='ok';
							$errorcode['response_data'] = array('done' => 1);
							$errorcode['error']=$this->lang->line('success_change_default');
						}
					}
				}
				else
				{
					$errorcode['error']=$errorcode['response_error_message']=$this->lang->line('error_auth_code');
					$errorcode['response_error_code'] = 0;
				}
			}
			else
			{
				$errorcode['error']=$errorcode['response_error_message']=$this->lang->line('error_auth_code');
				$errorcode['response_error_code'] = 0;
			}
		}
		else
		{
			$errorcode['error_password']=true;
			$errorcode['error']=$errorcode['response_error_message']=$this->lang->line('error_password');
			$errorcode['response_error_code'] = 0;
		}

		echo json_token($errorcode);
		exit();
	}
	/*
	 * 改密码
	 */
	public function change_password()
	{
		$password=$this->input->post("password",true);
		$new_password=$this->input->post("new",true);
		$new_password1=$this->input->post("confirm",true);

		//debug
		if(strlen($password) != 32) $password=md5('ti'.$password.'zi');
		if(strlen($new_password) != 32) $new_password=md5('ti'.$new_password.'zi');
		if(strlen($new_password1) != 32) $new_password1=md5('ti'.$new_password1.'zi');

		$errorcode=array('errorcode'=>false,'response_status'=>'error','error'=>$this->lang->line('error_change_default'));

		if($new_password==$new_password1)
		{
			$password_errorcode=$this->register_model->verify_password($this->user_id,$password);
			if($password_errorcode['errorcode'])
			{
				$errorcode=$this->register_model->update_password($this->user_id,$new_password);
				if(!$errorcode['errorcode']) {
					$errorcode['error']=$errorcode['response_error_message'] = $this->lang->line('error_change_default');
					$errorcode['response_error_code'] = 0;
				}
				else {
					$errorcode['response_status'] = 'ok';
					$errorcode['response_data'] = array('done' => 1);
					$errorcode['error']=$this->lang->line('success_change_default');
				}
			}
			else
			{
				$errorcode['error']=$errorcode['response_error_message']=$this->lang->line('error_password');
				$errorcode['response_error_code'] = 0;
			}
		}
		else
		{
			$errorcode['error']=$errorcode['response_error_message']=$this->lang->line('error_reg_confirm_password');
			$errorcode['response_error_code'] = 0;
		}

		echo json_token($errorcode);
		exit();
	}

//	/**
//	 * 重置密码
//	 */
//	public function reset_password () {
//		$password = $this->input->post('password');
//		$errorcode=array('errorcode' => false, 'error' => '');
//
//		if (!$password) {
//			$errorcode['error'] = '传参有误！';
//			echo json_token($errorcode);
//			exit;
//		}
//
//		$reset_pwd = $this->register_model->update_password($this->user_id, $password);
//
//		if (!$reset_pwd['errorcode']) {
//			$errorcode['error'] = '更改密码失败！';
//			echo json_token($errorcode);
//			exit;
//		}
//
//		$errorcode['error'] = '更改密码成功！';
//		$errorcode['errorcode'] = true;
//		echo json_token($errorcode);
//		exit;
//	}



	public function change_avatar () {
		$file_name = $this->input->post('avater');
		$error_code = array('errorcode' => false,'response_status' => 'error','response_error_code' => 99, 'response_error_message' => '', 'error' => '');
		if (!$file_name) {
			echo json_token(array("code"=>0, "msg"=>"参数有误！"));
			$error_code['error'] = $error_code['response_error_message'] = '参数有误！';
			exit;
		}

		$this->load->helper('upload');
		$aq_image_upload = oss_image_upload($file_name);
		switch ($aq_image_upload){
			case -1:$response_json = json_token(array("code"=>$aq_image_upload, "msg"=>"上传失败"));break;
			case -2:$response_json = json_token(array("code"=>$aq_image_upload, "msg"=>"格式错误"));break;
			case -3:$response_json = json_token(array("code"=>$aq_image_upload, "msg"=>"文件过大"));break;
			default:
				// 修改用户图像
				$this->load->model('dafen/user_model', 'm_user');
				if( $this->m_user->s_user_avatar($this->user_id, $aq_image_upload) )
				{
					$response_json = json_token(array("code"=>1, "msg"=>"上传成功", "img_path"=>$aq_image_upload));
				}
				else
				{
					$response_json = json_token(array("code"=>-4, "msg"=>"图像修改失败"));
				}
				break;
		}

		echo $response_json;
		exit;
	}

}

/* End of file user_info.php */
/* Location: ./application/controllers/user/user_info.php */

