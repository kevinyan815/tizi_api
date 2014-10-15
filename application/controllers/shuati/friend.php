<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once APPPATH.'core/Shuati_Controller.php';
/*
 * 爱刷题朋友控制器
 */
class Friend extends Shuati_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('shuati/user_model');
        $this->load->model('shuati/friend_model');
        $this->load->model('shuati/message_model');
        $this->load->model('common/shuati/common_user');
    }
    
    /**
     * 搜索战友
     */
    public function search_comrade()
    {
        $this->_check_user_login();
        $query = trim($this->input->post('query', true));
        $app_type = intval($this->input->post('app_type'));
        $app_name = $this->_get_app_name($app_type);
        if(!$query || !$app_name) {
            self::json_output(self::format_response(Constant::ERROR, array(), '', '请输入正确的查询条件'));
        }
        $user_list = $this->friend_model->search_user($query);
        if(!empty($user_list) && is_array($user_list)) {
            foreach($user_list as &$user) {
                $user['is_follow'] = $this->common_user->check_friendship($this->_user_info['user_id'], $user['user_id']) ? 1 : 0;
                $user['pet_status'] = $this->user_model->get_pet_mood($user['user_id'], $app_name);
            }
            unset($user);#断开引用
            $response = self::format_response(Constant::SUCCESS, $user_list);
        } else {
            $response = self::format_response(Constant::ERROR, array(), 10022, '没有找到您要搜索的用户');
        }
        
        self::json_output($response);
    }
    
    /**
     * 添加战友
     */
    public function add()
    {
        $this->_check_user_login();
        $app_type = intval($this->input->post('app_type'));
        $app_name = $this->_get_app_name($app_type);
        $friend_u_id = intval($this->input->post('user_id'));
        if($friend_u_id == $this->_user_info['user_id']) {
            self::json_output(self::format_response(Constant::ERROR, array(), '', '不能把自己添加为好友'));
        }
        if(!$this->user_model->check_user_existence($friend_u_id)) {
            self::json_output(self::format_response(Constant::ERROR, array(), '', '该用户不存在'));
        }
        if($this->common_user->check_friendship($this->_user_info['user_id'], $friend_u_id)) {
            self::json_output(self::format_response(Constant::ERROR, array(), '', '你们已经是好友了'));
        }
        $msg_title = $this->_user_info['name']. '关注了你';
        $msg_content = $this->_user_info['name']. '关注了你快去看看吧';
        $msg_send_uid = $this->_user_info['user_id'];
        $msg_receive_uid = $friend_u_id;
        $msg_type = 2;
        $add_ret = $this->friend_model->add_friend($this->_user_info['user_id'], $friend_u_id);
        if($add_ret) {
            $this->message_model->send_message($msg_title, $msg_content, $msg_send_uid, $msg_receive_uid, $msg_type, $app_type);
            $response = self::format_response(Constant::SUCCESS, array('done' => 1));
        } else {
            $response= self::format_response(Constant::ERROR, array(), '', '添加战友失败');
        }
        self::json_output($response);
    }
    
    /**
     * 移除战友
     */
    public function delete()
    {
        $this->_check_user_login();
        $friend_u_id = intval($this->input->post('user_id'));
        $this->friend_model->delete_friendship($this->_user_info['user_id'], $friend_u_id);
        $response = self::format_response(Constant::SUCCESS, array('done' => 1));
        self::json_output($response);
    }
    
    /**
     * 战友排行榜
     */
    public function friends_rank()
    {
        $this->_check_user_login();
        $rank_type = intval($this->input->post('type'));#1代表周经验排行,2代表总经验排行
        $rank_type = in_array($rank_type, array(1, 2)) ? $rank_type : 1;
        $app_type  = intval($this->input->post('app_type'));
        $app_name  = $this->_get_app_name($app_type);
        $friends_rank = $this->friend_model->get_friends_rank($this->_user_info['user_id'], $rank_type, $app_name);
        if(empty($friends_rank)) {
            $response = self::format_response(Constant::ERROR, array(), '', '加载好友排行榜失败');
        } else {
            $response = self::format_response(Constant::SUCCESS, $friends_rank);
        }
        
        self::json_output($response);
    }
    
    /**
     * 学霸推荐
     * 学霸推荐只推荐本应用中经验值高的学生
     */
    public function top_student_list()
    {
        $this->_check_user_login();
        $app_type = intval($this->input->post('app_type'));
        $app_name = $this->_get_app_name($app_type);
        if(!$app_name) {
            self::json_output(self::format_response(Constant::ERROR, array(), '', '参数错误'));
        }
        $student_list = $this->friend_model->get_app_top_student_list($app_type);
        if(is_array($student_list)) {
            $top_student_list = $sort_exp = array();
            foreach($student_list as $student) {
                $user_data = $this->user_model->get_user_data($student['user_id'], $app_name);
                if(empty($user_data['user_id'])) {
                    unset($student);continue;
                }
                $student_detail['user_id'] = $user_data['user_id'];
                $student_detail['experience'] = round($user_data['experience']);
                $student_detail['nick_name'] = $user_data['nick_name'];
                $student_detail['pet_id'] = $user_data['pet_id'];
                $student_detail['pet_status'] = $user_data['pet_status'];
                $student_detail['is_follow'] = $this->common_user->check_friendship($this->_user_info['user_id'], $student['user_id']) ? 1 : 0;
                $top_student_list[] = $student_detail;
                $sort_exp[] = $student_detail['experience'];
            }
            array_multisort($sort_exp, SORT_DESC, $top_student_list);
            $response = self::format_response(Constant::SUCCESS, $top_student_list);
        } else {
            $response = self::format_response(Constant::ERROR, array(), '', '加载学霸列表失败,请稍后再试!');
        }
        self::json_output($response);
    }
}