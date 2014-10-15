<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once APPPATH.'core/Shuati_Controller.php';
/**
 * 爱刷题消息控制器
 * @author Keivn Yan<kevinyan815@gmail.com>
 */
class Message extends Shuati_Controller
{
    public function __construct() 
    {
        parent::__construct();
        $this->load->model('shuati/message_model');
    }
    
    /**
     * 获取用户的消息列表
     */
    public function get_list()
    {
        $this->_check_user_login();
        $app_type = $this->input->post('app_type');
        $app_name = $this->_get_app_name($app_type);
        $user_id = $this->_user_info['user_id'];
        $message_list = $this->message_model->get_user_message_list($user_id, $app_type);
        if(is_array($message_list)) {
            $response = self::format_response(Constant::SUCCESS, $message_list);
        } else {
            $response = self::format_response(Constant::ERROR, array(), '', '加载消息列表失败');
        }
        self::json_output($response);
    }
    
    /**
     * 将消息置为已读
     */
    public function change_to_read()
    {
        $this->_check_user_login();
        $message_id = intval($this->input->post('message_id'));
        $upadate_data = array('readStatus' => 1);
        $arr_where = array('id' => $message_id);
        $this->message_model->update_message($upadate_data, $arr_where);
        $response = self::format_response(Constant::SUCCESS, array('message_id' => $message_id));
        self::json_output($response);
    }
    
    /**
     * 获取未读消息数接口
     */
    public function new_message_count()
    {
        $this->_check_user_login();
        $app_type = $this->input->post('app_type');
        $app_name = $this->_get_app_name($app_type);
        $new_message_count = $this->message_model->get_new_message_count($this->_user_info['user_id'], $app_type);
        $response = self::format_response(Constant::SUCCESS, array('new_count' => $new_message_count));
        self::json_output($response);
    }
}