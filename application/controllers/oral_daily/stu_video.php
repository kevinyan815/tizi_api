<?php
/**
 * Created by JetBrains PhpStorm.
 * User: 91waijiao
 * Date: 14-2-22
 * Time: 上午11:57
 * To change this template use File | Settings | File Templates.
 */
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Stu_Video extends MY_Controller {
	public function __construct() {
		parent::__construct();
	}

	/**
	 * 获得每日口语的视频
	 */
	public function get_video_list() {
		$grade = $this->input->get('grade', true);
		$page_num = $this->input->get('page_num', true);

		if (!$grade || ($grade > count(Constant::grade_video()))) $grade = 1;

		$playlist=array('errorcode'=>false,'error'=>'','playlist'=>array());

		$this->load->model('video/stu_video_model');

		$video_list = $this->stu_video_model->get_stu_video($grade, $page_num);
		if (!$video_list) {
			$playlist['error']=$this->lang->line('error_get_page');
			echo json_token($playlist);
			exit();
		}

		$this->load->helper('img_helper');
		$this->load->config('api');

		$this->load->library('rc4');
		$this->rc4->setKey($this->config->config['api']['rc4_key']);

		foreach ($video_list as $kv => $vv) {
			$file = path2video($vv->video_uri);
			$image_str_s = path2video($vv->thumb50_uri);
			$image_str = path2video($vv->thumb_uri);
//echo $file . "<br />";
			$this->rc4->crypt($file);
			$this->rc4->crypt($image_str_s);
			$this->rc4->crypt($image_str);

//print_r(($file));exit;
			$playlist['playlist'][$kv]['file']=urlencode($file);
//			$playlist['playlist'][$kv]['file']=$file;
			$playlist['playlist'][$kv]['image']=urlencode($image_str);
//			$playlist['playlist'][$kv]['image']=$image_str;
			$playlist['playlist'][$kv]['image_s']=urlencode($image_str_s);
//			$playlist['playlist'][$kv]['image_s']=$image_str_s;

			$playlist['playlist'][$kv]['title']=$vv->title;
			$playlist['playlist'][$kv]['description']=$vv->date>"0000-00-00 00:00:00"?date("Y-m-d",strtotime($vv->date)):"";
//			if($video_id&&$video_id==$v->id) $playlist['default_video']=$kv;
			$playlist['playlist'][$kv]['id']=$vv->id;
			$playlist['playlist'][$kv]['play_times'] = $vv->play_times;
		}
		if (isset($playlist['playlist'])) $playlist['errorcode'] = true;

		echo json_token($playlist);
		exit();

	}
}