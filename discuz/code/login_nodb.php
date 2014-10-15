<?php
/**
 * UCenter 应用程序开发 Example
 *
 * 应用程序无数据库，用户登录的 Example 代码
 * 使用到的接口函数：
 * uc_user_login()	必须，判断登录用户的有效性
 * uc_authcode()	可选，借用用户中心的函数加解密 Cookie
 * uc_user_synlogin()	可选，生成同步登录的代码
 */

if(empty($_POST['submit'])) {
	//登录表单
	if($_GET['ReturnURL']){
		$ReturnURL = "&ReturnURL={$_GET['ReturnURL']}";
	}

	echo '<form method="post" action="'.$_SERVER['PHP_SELF'].'?example=login'.$ReturnURL.'">';
	echo '登录:';
	echo '<dl><dt>用户名</dt><dd><input name="username"></dd>';
	echo '<dt>密码</dt><dd><input name="password" type="password"></dd>';
	echo '<input name="submit" type="submit"> ';
	echo '</form>';
} else {
	//通过接口判断登录帐号的正确性，返回值为数组
	$username=$_POST['username'];
	$password=$_POST['password'];
	$email=rand(3000,30000000)."@qq.com";
	$uid=rand(20,20000);
	//$uid=5326;
	//检测UId是否存在
	if($data = uc_getuserbyuid($uid)){//如果存在的话
		//echo '<pre>';print_r($data);exit;
		$salt=$data['salt'];
		$new_pwd =md5(md5($password).$salt);
		$row_fect = uc_updatepwdbyuid($uid,$new_pwd);
		list($uid) = uc_user_login($uid,$password,1);
	}else{//如果不存在该uid,就注册
		if($uid = uc_user_insert($uid,$username, $password, $email)){
			list($uid) = uc_user_login($uid,$password,1);
		}else{
			echo "注册论坛失败。";
			exit;
		}
	}
	
	setcookie('Example_auth', '', -86400);
	if($uid > 0) {
		//用户登陆成功，设置 Cookie，加密直接用 uc_authcode 函数，用户使用自己的函数
		setcookie('Example_auth', uc_authcode($uid."\t".$username, 'ENCODE'));
		//生成同步登录的代码
		$ucsynlogin = uc_user_synlogin($uid);
		if($_GET['ReturnURL']){
			echo $ucsynlogin."<script>window.location.href='{$_GET['ReturnURL']}'</script>";
			exit;
		}
		echo '登录成功'.$ucsynlogin.'<br><a href="'.$_SERVER['PHP_SELF'].'">继续</a>';
		exit;
	} elseif($uid == -1) {
		echo '用户不存在,或者被删除';
	} elseif($uid == -2) {
		echo '密码错';
	} else {
		echo '未定义';
	}
}

?>