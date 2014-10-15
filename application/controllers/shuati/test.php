<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once APPPATH.'core/Shuati_Controller.php';
/*
 * @todo 爱刷题里的测试接口,并不会放到应用里用
 * @copyright  March 24, 2014
 */

class Test extends Shuati_Controller
{
    public function __construct() 
    {
        parent::__construct();
        $this->load->model('shuati/test_model');
        $this->load->model('shuati/user_model');
        $this->load->library('Tiku');
    }
    
    public function check_question()
    {
        $question_data = array();
        $question_id = $this->input->post('question_id');
        if($question_id) {
            $question_data = $this->test_model->get_question_detail($question_id);
        }
        $status = !empty($question_data) ? 1 : 0;
        $response = $this->tiku->formatResponse($status, array($question_data));
        self::json_output($response);
    }
    
    public function test_db()
    {
        $user_id = $this->input->get('user_id');
        $this->user_model->getPetMood($user_id);
    }
    
    public function test_register()
    {
        $user_name = 'yanjia@qq.com';
        $password  = md5('ti' . 123123 . 'zi');
        $name = '王很圆';
        $phone_os = 'ios';
        $this->_user_register($user_name, $password, $name, $phone_os);
        
    }
}