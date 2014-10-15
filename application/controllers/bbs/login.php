<?php
/**
 * @date   2014-05-27
 * @authoer  zhangxiaoming@tizi.com
 * @description BBS登录请求页面
 *
 */
class Login extends MY_Controller{
	public function __construct(){
        parent::__construct();
		$this->load->model('bbs/bbs_model');
		//$this->load->library('Discuz');
    }

    public function index(){
		
		$uid=$this->session->userdata('user_id') ? $this->session->userdata('user_id') : '-1';
		if($uid>0){
			if($_GET['u']){
				$nickname=urldecode($_GET['u']);
			}else $nickname='';
			
			echo $this->bbs_model->loginbbs($uid,$nickname);
		}else{
			echo $this->bbs_model->logoutbbs();
		}
    }
	
	public function logout(){
		if(!$this->session->userdata('user_id')){
			$this->bbs_model->logoutbbs();
		}
    }
	
	public function returnuid(){
		//echo $this->tizi_uid;exit;
		if($this->session->userdata('user_id')){
			echo $this->bbs_model->checkbbsreg($this->session->userdata('user_id'));
		}else{
			echo -3;//未登录
		}
	}
	
}	
