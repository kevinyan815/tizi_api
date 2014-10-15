<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');
require_once(LIBPATH.'libraries/Constant.php');

Class Constant extends CI_Constant {

	const STU_VIDEO_PER_PAGE = 20;

	/*classes manage*/
	const TEACHER_CLASS_MAX_NUM = 20;//老师最大创建班级数
	const CLASS_MAX_HAVING_STUDENT = 200;//一个班级最多拥有的学生人数
	const CREATE_STUDENT_LIMIT = 200;//一次最多创建的学生数量

        const QUESTION_NUM_LIKE = 5;//理科出题数
	const QUESTION_NUM_WENKE =10;//文科出题数
	const APP_TIKU_NAME = 'tiku';//移动题库(爱刷题高考版)
	const APP_DAFEN_NAME = 'dafen';
        const APP_SHUATI_CHZH_NAME = 'shuati_chuzhong';//爱刷题初中同步
        const APP_SHUATI_ZHK_NAME = 'shuati_zhongkao';//爱刷题中考
        const APP_SHUATI_GZH_NAME = 'shuati_gaozhong';//爱刷题高中同步
        
        /*table name of love shuati*/
        const TABLE_SHUATI_QUESTION = 'shuati_questions';
        const TABLE_SHUATI_MATERAIL = 'shuati_materials';
        const TABLE_SHUATI_QUESTION_SOURCE = 'shuati_question_source';
        const TABLE_SHUATI_OPTION = 'shuati_options';
        const TABLE_SHUATI_KNOWLEDGE_QUESTION_RELATION = 'shuati_knowledge_question_rel';
        const TABLE_SHUATI_KNOWLEDGE = 'shuati_knowledge';
        const TABLE_SHUATI_USER_PRACTICE_LOG = 'shuati_stu_practice_log';
        const TABLE_SHUATI_USER_ANSWER_LOG = 'shuati_stu_answer_log';
        const TABLE_SHUATI_SUBJECT = 'shuati_subjects';
        const TABLE_SHUATI_STU_SUBJECT = 'shuati_stu_subjects';
        const TABLE_SHUATI_STU_KNOWLEDGE = 'shuati_stu_knowledge';
        const TABLE_SHUATI_USER = 'user_data';
        const TABLE_SHUATI_USER_FAVORITES = 'shuati_user_favorites';
        const TABLE_SHUATI_USER_RELATION = 'study_user_relation';
        const TABLE_SHUATI_MAIL_BOX = 'shuati_mail_box';
        const TABLE_SHUATI_USER_WEEK_EXP = 'study_user_week_stat';
        const TABLE_SHUATI_QUESTION_FEEDBACK = 'shuati_question_feedback';
        const TABLE_SHUATI_GIVE_UP = 'shuati_practice_give_up';
        
        const SUCCESS = 'ok';
        const ERROR = 'error';

        public static $shuati_subjects = array(
            '11' => '语文', '12' => '数学', '13' => '英语',#初中同步App
            '21' => '语文', '22' => '数学', '23' => '英语',#中考App
            '31' => '语文', '32' => '数学', '33' => '英语',#高中同步App
        );
	function __construct()
	{
		parent::__construct();
	}

	public static function grade_video($grade_id = 0){
		$grade_video = array(
			1	=> "小学1-3年级",
			2	=> "小学4-6年级",
			3	=> "初中",
			4	=> "高中"
		);
		return $grade_id === 0 ? $grade_video : (isset($grade_video[$grade_id]) ? $grade_video[$grade_id] : null);
	}

	//文理学科数组
	public static function getSubjectArray(){
		$subjectArray = array(
				'wenke'=>array(1,2,8,9,10),
				'like'=>array(3,4,5,6,7)
		);
		return $subjectArray;
	}
	
	//设置出题范围
	//题目范围Id 1:高考 2:模考 3:名校同步
	public static function questionRange()
	{
		$questionRange = array(1,2,3);
		return $questionRange;
	}
}
/* End of file Constant.php */
/* Location: ./application/libraries/Constant.php */
