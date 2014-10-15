<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once APPPATH.'core/Shuati_Controller.php';
/*
 * @todo 爱刷题User相关的操作
 */

class User extends Shuati_Controller
{
    public function __construct() 
    {
        parent::__construct();
        $this->load->model('shuati/user_model');
        $this->load->library('Tiku');
    }
    
    public function login()
    {
        $user_name = trim($this->input->post('username', true));
        $password  = trim($this->input->post('password', true));
        $app_type  = intval($this->input->post('app_type'));
        $app_name  = $this->_get_app_name($app_type);
        if(empty($user_name) || empty($password) || empty($app_name)) {
            $response = self::format_response(Constant::ERROR, array(), '', '参数不正确');
            self::json_output($response);
        }
        if(!preg_email($user_name)) {
            $response = self::format_response(Constant::ERROR, array(), '', '用户名需为正确的邮箱');
            self::json_output($response);
        }
        if(strlen($password) != 32) $password=md5('ti'.$password.'zi');
        $login_send_data = array('username' => $user_name, 'password' => $password, 'app_name' => $app_name);
        $login_return = $this->_site_user_login($login_send_data);
        if($login_return['status'] == Constant::SUCCESS) {
            $response = self::format_response($login_return['status'], $login_return['data']);
        }else {
            $response = self::format_response($login_return['status'], array(), '', $login_return['error_message']);            
        }
        
        self::json_output($response);
    }
    
    public function register()
    {
        $user_name = trim($this->input->post('username', true));
        $password  = trim($this->input->post('password', true));
        $name 	   = trim($this->input->post("name",true));
        $phone_os  = trim($this->input->post("phone_os", true));
        $app_type  = intval($this->input->post('app_type'));
        $app_name  = $this->_get_app_name($app_type);
        if(empty($user_name) || empty($password) || empty($name) || empty($app_name)) {
            $response = self::format_response(Constant::ERROR, array(), '', '参数不正确');
            self::json_output($response);
        }
        if(!preg_email($user_name)) {
            $response = self::format_response(Constant::ERROR, array(), '', '用户名需为正确的邮箱');
            self::json_output($response);
        }
        if(strlen($password) != 32) $password=md5('ti'.$password.'zi');
        $register_send_data = array(
            'username' => $user_name,
            'password' => $password,
            'name'     => $name,
            'app_name' => $app_name,
            'phone_os' => $phone_os
        );
        $register_return = $this->_user_register($register_send_data);
        if($register_return['status'] == Constant::SUCCESS) {
            #注册成功直接去登陆然后返回用户的信息
            $login_send_data = array('username' => $user_name, 'password' => $password, 'app_name' => $app_name);
            $login_return = $this->_site_user_login($login_send_data);
            if($login_return['status'] == Constant::SUCCESS) {
                $response = self::format_response($login_return['status'], $login_return['data']);
            }else {
                $response = self::format_response($login_return['status'], array(), '', $login_return['error_message']);
            }
        } else {
            $response = self::format_response($register_return['status'], array(), '', $register_return['error_message']);
        }
        
        self::json_output($response);
    }
    
    /**
     * 第三方用户登陆
     */
    public function third_party_login()
    {
        $third_uid = trim($this->input->post("third_uid",true));
        $token     = trim($this->input->post("token",true));
        $platform  = intval($this->input->post("platform"));
        $name      = trim($this->input->post("name",true));
        $app_type  = intval($this->input->post('app_type'));
        $app_name  = $this->_get_app_name($app_type);
        if(empty($third_uid) || empty($token) || empty($platform) || empty($name) || empty($app_name)) {
            $response = self::format_response(Constant::ERROR, array(), '', '参数不正确');
            self::json_output($response);
        }
        $login_return = $this->_third_party_login($third_uid, $token, $platform, $name, $app_name);
        if($login_return['status'] == Constant::SUCCESS) {
            $response = self::format_response($login_return['status'], $login_return['data']);
        } else {
            $response = self::format_response($login_return['status'], array(), '', $login_return['error_message']);
        }
        self::json_output($response);
    }
    
    /**
     * 第三方用户创建爱刷题账号并登陆
     */
    public function third_party_add_account()
    {
        $user_name = trim($this->input->post("username",true));
        $name 	   = trim($this->input->post("name",true));
        $oauth_id  = intval($this->input->post("oauth_id"));
        $phone_os  = trim($this->input->post("phone_os", true));
        $platfrom  = intval($this->input->post("platform"));
        $app_type  = intval($this->input->post('app_type'));
        $app_name  = $this->_get_app_name($app_type);
        if(empty($user_name) || empty($name) || empty($oauth_id) || empty($app_name) || empty($phone_os) || empty($platfrom)) {
            $response = self::format_response(Constant::ERROR, array(), '', '参数不正确');
            self::json_output($response);
        }
        $register_return = $this->_third_user_create_relation($user_name, $name, $oauth_id, $phone_os, $platfrom);
        if($register_return['status'] == Constant::SUCCESS) {
            #注册关联成功,直接去登陆然后返回用户信息
            $login_send_data = array(
                'app_type'=>Constant::API_TYPE_TIKU,
                'user_id'=>$register_return['data']['user_id'],
                'name'=>$name,
                'app_name'=>$app_name
            );
            $login_return = $this->_site_user_login($login_send_data, TRUE);
            if($login_return['status'] == Constant::SUCCESS) {
                $response = self::format_response($login_return['status'], $login_return['data']);
            }else {
                $response = self::format_response($login_return['status'], array(), '', $login_return['error_message']);
            }
        } else {
            $response = self::format_response($register_return['status'], array(), '', $register_return['error_message']);
        }
        
        self::json_output($response);
    }
    
    /**
     * 我的练习
     * 我的练习是用户在某个学科下的做题历史概览
     */
    public function my_practice()
    {
        $this->_check_user_login();
        $user_id = $this->_user_info['user_id'];
        $subject_id = intval($this->input->post('subject_id'));
        $my_practice_data = $this->user_model->get_my_practice_index($user_id, $subject_id);
        if(!empty($my_practice_data)) {
            $response = self::format_response(Constant::SUCCESS, $my_practice_data);
        } else {
            $response = self::format_response(Constant::ERROR, array(), '', '我的练习获取失败');
        }
        self::json_output($response);
    }
    
    /**
     * 错题本
     */
    public function error_notebook()
    {
        $this->_check_user_login();
        $user_id    = $this->_user_info['user_id'];
        $subject_id = intval($this->input->post('subject_id'));
        if(!$subject_id) {
            $response = self::format_response(Constant::ERROR, array(), '', '参数错误');
            self::json_output($response);
        }
        $offset = intval($this->input->post('offset'));
        $num    = intval($this->input->post('num'));
        $offset = $offset ? $offset : 0;
        $num    = $num ? $num : 50;
        $error_book = $this->user_model->get_error_notebook($user_id, $subject_id, $offset, $num);
        if(is_array($error_book)) {
            $response = self::format_response(Constant::SUCCESS, $error_book);
        } else {
            $response = self::format_response(Constant::ERROR, array(), '', '加载错题本失败');
        }
        self::json_output($response);
    }
    
    /**
     * 我的收藏夹
     */
    public function my_favorites()
    {
        $this->_check_user_login();
        $user_id    = $this->_user_info['user_id'];
        $subject_id = intval($this->input->post('subject_id'));
        if(!$subject_id) {
            $response = self::format_response(Constant::ERROR, array(), '', '参数错误');
            self::json_output($response);
        }
        $offset = intval($this->input->post('offset'));
        $num    = intval($this->input->post('num'));
        $offset = $offset ? $offset : 0;
        $num    = $num ? $num : 50;
        $my_favorites = $this->user_model->get_my_favorites($user_id, $subject_id, $offset, $num);
        if(is_array($my_favorites)) {
            $response = self::format_response(Constant::SUCCESS, $my_favorites);
        } else {
            $response = self::format_response(Constant::ERROR, array(), '', '加载收藏夹失败');
        }
        self::json_output($response);
    }
    
    /**
     * 我的练习历史
     */
    public function my_practice_history()
    {
        $this->_check_user_login();
        $user_id    = $this->_user_info['user_id'];
        $subject_id = intval($this->input->post('subject_id'));
        if(!$subject_id) {
            $response = self::format_response(Constant::ERROR, array(), '', '参数错误');
            self::json_output($response);
        }
        $offset = intval($this->input->post('offset'));
        $num    = intval($this->input->post('num'));
        $offset = $offset ? $offset : 0;
        $num    = $num ? $num : 50;
        $practice_history = $this->user_model->get_my_practice_history($user_id, $subject_id, $offset, $num);
        if(is_array($practice_history)) {
            $response = self::format_response(Constant::SUCCESS, $practice_history);
        } else {
            $response = self::format_response(Constant::ERROR, array(), '', '加载练习历史失败');
        }
        self::json_output($response);
    }
    
    /**
     * 答题卡
     */
    public function answer_card()
    {
        $this->_check_user_login();
        $user_id = $this->_user_info['user_id'];
        $subject_id = intval($this->input->post('subject_id'));
        $practice_id = intval($this->input->post('practice_id'));
        $type = intval($this->input->post('type'));
        #type: 1表示错题本, 2表示收藏夹, 3表示练习记录
        $type = in_array($type, array(1,2,3)) ? $type : 1;
        $time = (int)$this->input->post('time');
        $answer_card = $this->user_model->get_user_answer_card($user_id, $subject_id, $practice_id, $time, $type);
        if($answer_card) {
            $response = self::format_response(Constant::SUCCESS, $answer_card);
        } else {
            $response = self::format_response(Constant::ERROR, array(), '', '加载答题卡失败');
        }
        self::json_output($response);
    }
    
    /**
     * 用户个人主页
     */
    public function home_page()
    {
        $this->_check_user_login();
        $app_type  = intval($this->input->post('app_type'));
        $other_uid = intval($this->input->post('user_id'));#这个是查看其他用户的主页时被查看用户的Id
        $app_name  = $this->_get_app_name($app_type);
        if(!empty($other_uid)) {
            $existence = $this->user_model->check_user_existence($other_uid);
            if($existence) $user_id = $other_uid;
        } else {
            $user_id = $this->_user_info['user_id'];
        }
        $home_page_data = $this->user_model->user_home_page($user_id, $this->_user_info['user_id'], $app_name);
        if(!empty($home_page_data)) {
            $response = self::format_response(Constant::SUCCESS, $home_page_data);
        } else {
            $response = self::format_response(Constant::ERROR, array(), '', '加载用户中心失败,请稍后重试');
        }
        self::json_output($response);
    }
    
    /**
     * 收藏题目
     */
    public function collect_question()
    {
        $this->_check_user_login();
        $user_id = $this->_user_info['user_id'];
        $subject_id = intval($this->input->post('subject_id'));
        $question_id = intval($this->input->post('question_id'));
        if(!$subject_id || !$question_id) {
            $response = self::format_response(Constant::ERROR, array(), '', '参数错误');
            self::json_output($response);
        }
        $this->user_model->add_user_favorite($user_id, $subject_id, $question_id);
        $response = self::format_response(Constant::SUCCESS, array('done' => 1));
        self::json_output($response);
    }
    
    /**
     * 取消题目收藏
     */
    public function cancel_question_collection()
    {
        $this->_check_user_login();
        $user_id = $this->_user_info['user_id'];
        $subject_id = intval($this->input->post('subject_id'));
        $question_id = intval($this->input->post('question_id'));
        if(!$subject_id || !$question_id) {
            $response = self::format_response(Constant::ERROR, array(), '', '参数错误');
            self::json_output($response);
        }
        $this->user_model->del_user_favorite($user_id, $subject_id, $question_id);
        $response = self::format_response(Constant::SUCCESS, array('done' => 1));
        self::json_output($response);
    }
    
    /**
     * 用户更换宠物
     */
    public function change_pet()
    {
        $this->_check_user_login();
        $user_id = $this->_user_info['user_id'];
        $pet_id = intval($this->input->post('pet_id'));
        if(!$pet_id) {
            $response = self::format_response(Constant::ERROR, array(), '', '参数错误');
            self::json_output($response);
        }
        $chang_ret = $this->user_model->change_user_pet($user_id, $pet_id);
        if($chang_ret) {
            $response = self::format_response(Constant::SUCCESS, array('done' => 1));
        } else {
            $response = self::format_response(Constant::ERROR, array(), '', '宠物更换失败,请检查网络');
        }
        self::json_output($response);
    }
    
    /**
     * 当用户放弃练习时调用此接口
     * 主要是为了统计信息,分析是什么问题导致的用户放弃做题
     */
    public function give_up_practice()
    {
        $this->_check_user_login();
        $user_id = $this->_user_info['user_id'];
        $subject_id = intval($this->input->post('subject_id'));
        $right_detail_str = trim($this->input->post('right_detail'));
        $user_answer_str  = trim($this->input->post('user_answer'));
        $right_detail = $this->tiku->parseAnswerStr($right_detail_str, 1);
        $user_answer = $this->tiku->parseAnswerStr($user_answer_str, 2);
        $app_type = intval($this->input->post('app_type'));
        $this->user_model->log_give_up($user_id, $subject_id, $user_answer, $right_detail, $app_type);
        $response = self::format_response(Constant::SUCCESS, array('done' => 1));
        self::json_output($response);
    }
}