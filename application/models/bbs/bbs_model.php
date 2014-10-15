<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
include $_SERVER["DOCUMENT_ROOT"].'/discuz/config.inc.php';
include $_SERVER["DOCUMENT_ROOT"].'/discuz/uc_client/client.php';

class Bbs_Model extends CI_Model {
	
	function __construct()
	{
       	parent::__construct();
	}
	
	function loginbbs($uid,$username,$email=''){
		$password=time().rand(1,100000);
		if($username==''){
			$username='梯子_'.$uid;
		}
		if($email==''){
			$email='test_'.$uid.'@tizi.com';
		}
		//检测UId是否存在
		if($data = uc_getuserbyuid($uid)){//如果存在的话
			$salt=$data['salt'];
			$new_pwd =md5(md5($password).$salt);
			$row_fect = uc_updatepwdbyuid($uid,$new_pwd);
			if($row_fect<1){//UC_center用户存在，但discuz用户不存在
				return -5;
				exit;
			}
			//echo "<pre>";print_R($row_fect);exit;
			list($uid) = uc_user_login($uid,$password,1);
		}else{//如果不存在该uid,就注册
			
			if($uid_1 = uc_user_insert($uid,$username, $password, $email)){
				list($uid) = uc_user_login($uid,$password,1);
			}else{
				echo "error";
				exit;
			}
		}
		
		setcookie('Tizibbs_auth', '', -86400);
		if($uid > 0) {
			//用户登陆成功，设置 Cookie，加密直接用 uc_authcode 函数，用户使用自己的函数
			setcookie('Tizibbs_auth', uc_authcode($uid."\t".$username, 'ENCODE'));
			//生成同步登录的代码
			$ucsynlogin = uc_user_synlogin($uid);
			if($_GET['ReturnURL']){
				return $ucsynlogin."<script>window.location.href='{$_GET['ReturnURL']}'</script>";
				exit;
			}
			//echo "<pre>";print_r($_COOKIE);exit;
			return $ucsynlogin;
			exit;
		} elseif($uid == -1) {
			echo 'error';
		} elseif($uid == -2) {
			echo 'error';
		} else {
			echo 'error';
		}
	}
	
	function checkbbsreg($uid){
		if(!$data = uc_getuserbyuid($uid)){
			return -1;//未注册
		}else{
			if($groupid=uc_getgroupidbyuid($uid)){//所属组
				if($groupid>2){
					return 2;//已注册
				}else{
					return 3;//是管理组成员
				}
			}
		}
	}
	function logoutbbs(){
		setcookie('Tizibbs_auth', '', -86400);
		$ucsynlogout = uc_user_synlogout();
		return $ucsynlogout;
		exit;
	}
	
}
/* End of file bbs_model.php */
/* Location: application/models/bbs/bbs_model.php */
