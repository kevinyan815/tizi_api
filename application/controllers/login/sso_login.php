<?php
/**
 * Created by JetBrains PhpStorm.
 * User: 91waijiao
 * Date: 14-5-26
 * Time: 下午4:47
 * To change this template use File | Settings | File Templates.
 */
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class Sso_Login extends MY_Controller{
	public function __construct(){
		parent::__construct();
	}
	//http://api.tizi.cn/sso/login?sso_user_id=15964632&auth_id=a0Dok56KisyT05K&phone=13301250585
	public function login() {
		$data = array();
		$data['sso_user_id'] = $this->input->post('sso_user_id', true);
		$data['phone'] = $this->input->post('phone', true);
		$data['email'] = $this->input->post('email', true);
		$auth_id = $this->input->post('auth_id', true);

		$error_code = array('status' => 0, 'error' => '');

		if (!$data['sso_user_id'] || !$auth_id) {
			$error_code['error'] = '传递的参数有误！';
			echo json_token($error_code);
			exit;
		}
//echo $this->config->item('encryption_key');exit;
		$client_ip = get_remote_ip();
		$data['open_id'] = sha1(md5($data['sso_user_id'] . $auth_id) . $this->config->item('encryption_key'));//唯一open_id
		$this->load->model('sso/sso_auth_model');
		$sso_auth = $this->sso_auth_model->get_sso_auth_by_open_id($auth_id);
//print_r($sso_auth);exit;
		if (empty($sso_auth)) {
			$error_code['status'] = 2;
			$error_code['error'] = '合作公司不存在！';
			echo json_token($error_code);
			exit;
		}

		if (strpos($sso_auth->ip_list, $client_ip) === false) {
			$error_code['status'] = 3;
			$error_code['error'] = 'ip不正确非法操作！';
			echo json_token($error_code);
			exit;
		}

		$this->load->model('sso/sso_model');
		$sso_info = $this->sso_model->get_sso_by_open_id($data['open_id']);

		$data['platform'] = $sso_auth->id;
		$data['access_token'] = sha1(md5($data['sso_user_id']).uniqid().mt_rand(1000000,5555555));
		$data['generate_time'] = time();


		if (!empty($sso_info)) {
			//更新
			$return = $this->sso_model->update_sso($sso_info->id, $data);
		} else {
			//插入
			$return = $this->sso_model->insert_sso($data);
		}
		if (!$return) {
			$error_code['status'] = 4;
			$error_code['error'] = '数据操作失败！';
			echo json_token($error_code);
			exit;
		}
		$error_code['status'] = 1;
		$error_code['error'] = '操作成功！';
		$error_code['sso_data'] = array('access_token' => $data['access_token'], 'open_id' => $data['open_id']);
		echo json_token($error_code);
		exit;
	}
}