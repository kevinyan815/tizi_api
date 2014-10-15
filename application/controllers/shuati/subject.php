<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once APPPATH.'core/Shuati_Controller.php';
/**
 * 学科控制器
 */
class Subject extends Shuati_Controller
{
    public function __construct() {
        parent::__construct();
        $this->load->model('shuati/subject_model');
        $this->load->model('shuati/user_model');
        $this->load->model('shuati/knowledge_model');
    }
    
    /**
     * 学科列表
     */
    public function subject_list()
    {
        $this->_check_user_login();
        $app_type = $this->input->post('app_type');
        $app_name = $this->_get_app_name($app_type);
        if(empty($app_name)) {
            $response = self::format_response(Constant::ERROR, array(), 10050, 'APP类型设置错误');
            self::json_output($response);
        }
        $subject_list = $this->subject_model->get_subject_list($app_type);
        $response = self::format_response(Constant::SUCCESS, $subject_list);
        self::json_output($response);
    }
    
    /**
     * 学科首页
     */
    public function index()
    {
        $this->_check_user_login();
        $subject_id = intval($this->input->post('subject_id'));
        $user_id = $this->_user_info['user_id'];
        $app_type = intval($this->input->post('app_type'));
        $app_name = $this->_get_app_name($app_type);
        if(empty($subject_id) || empty($app_name)) {
            $response = self::format_response(Constant::ERROR, array(), '', '参数不正确');
            self::json_output($response);
        }
        $subject_statistics = $this->subject_model->get_user_subject_statistics($user_id, $subject_id);
        $user_knowledge_tree = $this->knowledge_model->get_user_knowledge_tree($user_id, $subject_id);
        $user_data = $this->user_model->get_user_data($user_id, $app_name);
        if(empty($subject_statistics) || empty($user_knowledge_tree) || empty($user_data)) {
            $response = self::format_response(Constant::ERROR, array(), 10006, '获取用户学科概况失败');
            self::json_output($response);
        }
        $response_data['user_info']['experience'] = round($user_data['experience']);
        $response_data['user_info']['forecast_score'] = $subject_statistics['prePoints'];
        $response_data['user_info']['beat'] = $subject_statistics['beatOthers'];
        $response_data['user_info']['pet_id'] = $user_data['pet_id'];
        $response_data['user_info']['pet_status'] = $user_data['pet_status'];
        $response_data['knowledge_info'] = $user_knowledge_tree;
        
        $response = self::format_response(Constant::SUCCESS, $response_data);
        self::json_output($response);
    }
    
    /**
     * 获取所有学科的消息列表(数据格式、字段名等兼容之前的爱刷题高考版定的规则)
     */
    public function all_subject_detail()
    {
        $this->_check_user_login();
        $app_type = intval($this->input->post('app_type'));
        $app_name = $this->_get_app_name($app_type);
        if($app_name) {
            $catalog = $all_subject_arr = array();
            $all_subject_arr['region_catalog_id'] = '';
            $all_subject_arr['reguon_catalog_arr'] = '';
            $subject_list = $this->subject_model->get_subject_list($app_type);
            foreach($subject_list as $subject) {
                $subject_info = array();
                $subject_info['subject_id'] = $subject['subject_id'];
                $subject_info['name'] = $subject['name'];
                $subject_info['knowledge_info'] = $this->knowledge_model->get_subject_knowledge_tree($subject['subject_id']);
                $all_subject_arr['subject_catalog_info'][] = $subject_info;
            }
            $catalog['region_catalog_info'][] = $all_subject_arr;
            $response = self::format_response(Constant::SUCCESS, $catalog);
            self::json_output($response);
        }
    }
}