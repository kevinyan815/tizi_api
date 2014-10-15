<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 爱刷题项目的所有控制器的父类
 * @author yanhengjia@tiz.com
 */
class Shuati_Controller extends CI_Controller
{
    /**
     * 用户信息
     */
    protected $_user_info = array();


    public function __construct()
    {
        parent::__construct();
        $this->load->model('login/session_model');
        if(empty($_POST)) $_POST = $_GET;
    }
    
    /**
     * 以json格式输出
     * @param array $arr_param
     */
    protected static function json_output($arr_param)
    {
        $arr_data = is_array($arr_param) ? $arr_param : array();
        exit(isset($_GET['callback']) ? $_GET['callback'].'('.json_encode($arr_data).')' : json_encode($arr_data));
    }
    
    /**
     * 站内用户 登录
     * @param $login_send_data 登陆时发送到服务器的数据
     * @param $third_party_login 是否是第三方登陆
     */
    protected function _site_user_login($login_send_data, $third_party_login = false)
    {
        $login_return = array();
        $post_fields = array(
            'app_type'=>Constant::API_TYPE_TIKU,
            'app_name'=>Constant::APP_TIKU_NAME
        );
        $login_send_data = array_merge($post_fields, $login_send_data);
        $url = $third_party_login ? base_url().'oauth/login' : base_url().'login/user_login';
        $response = $this->_send_curl_request($url, $login_send_data);
        $arr_response = json_decode($response, TRUE);
        if(is_array($arr_response)) {
            if(!empty($arr_response['errorcode'])) {
                $user_id = $arr_response['response_data']['user_info']['id'];
                $session_id = $arr_response['response_data']['session_id'];
                //用户的宠物心情
                $pet_mood = $this->user_model->get_pet_mood($user_id, $login_send_data['app_name']);
                //用户信息
                $user_info = array(
                    'user_id'=>$user_id,
                    'nick_name'=>$arr_response['response_data']['user_info']['name'],
                    'experience'=>$arr_response['response_data']['user_data_info']['exp'],
                    'pet_id'=>$arr_response['response_data']['user_data_info']['pet_id'],
                    'pet_status'=>$pet_mood,
                    'email'=>$arr_response['response_data']['user_info']['email']
                );
                $arr_return = array(
                    'session_id'=>$session_id,
                    'user_info'=>$user_info
                );
                if($third_party_login) $arr_return['done'] = 1;#第三方登陆时需要加这个标示
                $login_return = array('status' => Constant::SUCCESS, 'data' => $arr_return);
            } else {
                $login_return = array('status' => Constant::ERROR, 'error_message' => $arr_response['response_error_message']);
            }
        }
        
        return $login_return;
    }
    
    /**
     * 用户注册
     * @param $user_name 用户名(邮箱)
     * @param $password  密码
     * @param $name      昵称
     * @param $phone_os  手机操作系统
     * @return array
     */
    protected function _user_register($register_send_data)
    {
        $register_return = array();
        $post_fields = array(
            'user_type'=>Constant::USER_TYPE_STUDENT,//用户类型--学生
            'register_type'=>Constant::INSERT_REGISTER_EMAIL,//注册类型--邮箱
            'app_name'=>  Constant::APP_TIKU_NAME,
            'send_email' => 0
        );
        $register_send_data = array_merge($post_fields, $register_send_data);
        $url = base_url().'register/submit/tizi';
        $response = $this->_send_curl_request($url, $register_send_data);
        $arr_response = json_decode($response, TRUE);
        if(!empty($arr_response['errorcode'])) {
            $register_return = array('status' => Constant::SUCCESS);
        } else {
            $register_return = array('status' => Constant::ERROR, 'error_message' => $arr_response['error']);
        }
        
        return $register_return;
    }
    
    /**
     * 第三方登陆
     * @param $third_uid  第三方的用户Id
     * @param $token
     * @param $platform 第三方的平台
     * @param $name   第三方用户的昵称
     * @param $app_name APP名称
     */
    protected function _third_party_login($third_uid, $token, $platform, $name, $app_name)
    {
        $arr_return = array();
        $post_fields = array(
            'open_id'=>$third_uid,
            'platform'=>$platform,
            'access_token'=>$token
        );
        #调用第三方登陆接口
        $request_url = base_url().'oauth/callback';
        $response = $this->_send_curl_request($request_url, $post_fields);
        $arr_response = json_decode($response, true);
        switch ($arr_response['errorcode'])
        {
            case 1:
                $arr_return = array('status' => Constant::ERROR, 'error_message' => $arr_response['error']);
                break;
            case 2:
                #未绑定
                $arr_return = array('status' => Constant::SUCCESS, 'data' => array('done'=>0,'oauth_id'=>$arr_response['oauth_data']['oauth_id']));
                break;
            case 3:
                #已绑定,去登陆
                $login_send_data = array(
                    'app_type'=>Constant::API_TYPE_TIKU,
                    'user_id'=>$arr_response['oauth_data']['user_id'],
                    'name'=>$name,
                    'app_name'=>$app_name
                );
                $arr_return = $this->_site_user_login($login_send_data, TRUE);
                break;
        }
        
        return $arr_return;
    }

    /**
     * 用第三方账户注册一个本站用户并关联
     * @param string $user_name 账号名(邮箱)
     * @param string $name      昵称
     * @param int $oauth_id     该用户在session_oauth里的id
     * @param string $phone_os    手机系统
     * @param int $platfrom    第三方平台
     * @param int $send_email  是否发送邮件
     * @return array
     */
    protected function _third_user_create_relation($user_name, $name, $oauth_id, $phone_os, $platfrom, $send_email = 0)
    {
        $post_fields = array(
            'username'=>$user_name,
            'password'=>'',
            'name'=>$name,
            'user_type'=>Constant::USER_TYPE_STUDENT,//用户类型--学生
            'register_type'=>Constant::INSERT_REGISTER_EMAIL,
            'oauth_id'=>$oauth_id,
            'app_name' => $app_name,
            'phone_os' => $phone_os,
            'platform' => $platfrom,
            'send_email' => $send_email
        );
        $url = base_url().'oauth/register';
        $response = $this->_send_curl_request($url, $post_fields);
        $arr_response = json_decode($response, TRUE);
        if(!empty($arr_response['errorcode'])) {
            $arr_return = array('status' => Constant::SUCCESS, 'data' => array('user_id' => $arr_response['user_info']['user_id']));
        } else {
            $arr_return = array('status' => Constant::ERROR, 'error_message' => $arr_response['error']);
        }
        
        return $arr_return;
    }

    /**
     * 发起CURL请求并返回请求结果
     * @return array
     */
    protected function _send_curl_request($url, $post_fields = array())
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        if(!empty($post_fields) && is_array($post_fields)) {
            curl_setopt($curl, CURLOPT_POST, TRUE);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post_fields);
        }
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($curl);
        curl_close($curl);
        
        return $res;
    }
    
    /**
     * 按照格式规范来格式化相应数据
     * @param int $status 状态码
     * @param array $data 响应数据
     * @param int $error_code 错误码
     * @param string $error_message 错误信息
     * @return return formated data
     */
    protected static function format_response($status, $data, $error_code = 0, $error_message = '')
    {
        $response = array();
        switch($status) {
            case Constant::ERROR:
                $response['response_status'] = Constant::ERROR;
                $response['response_error_code'] = $error_code;
                $response['response_error_message'] = $error_message;
                break;
            case Constant::SUCCESS:
                $response['response_status'] = Constant::SUCCESS;
                $response['response_data'] = $data;
                break;
            defult:
                break;
        }
        
        return $response;
    }
    
    /**
     * 获取APP名称
     * @param app_type_id app类型ID
     * @return array
     */
    protected function _get_app_name($app_type_id)
    {
        $app_name = '';
        switch($app_type_id) {
            case 1:
                $app_name = Constant::APP_SHUATI_CHZH_NAME;
                break;
            case 2:
                $app_name = Constant::APP_SHUATI_ZHK_NAME;
                break;
            case 3:
                $app_name = Constant::APP_SHUATI_GZH_NAME;
                break;
        }
        if(!$app_name) {
            self::json_output(self::format_response(Constant::ERROR, array(), '10050', 'App参数错误'));
        }
        return $app_name;
    }
    
    /**
     * 检查用户登录
     */
    protected function _check_user_login($api_type = Constant::API_TYPE_TIKU)
    {
        $session_id = $this->input->post('session_id');
    	$user_info = $this->session_model->get_api_session($session_id,$api_type);
    	if(!empty($user_info['session_id']))
    	{
            $this->_user_info = $user_info;
    	}else{
            $response = self::format_response(Constant::ERROR, array(), 10033, '您的账号在另外一台设备登陆了，请重新登陆。');
            self::json_output($response);
    	}
    }
    
    /**
     * 获取缓存模块key名称的方法
     * @param string $module_name 模块名称
     * @param array  参数
     * @return string $cache_key
     */
    protected function _get_cache_module_key($module_name, $params)
    {
        $cache_key = '';
        $this->config->load('shuati_cache_module');
        $cache_modules = $this->config->item('shuati_cache_module');
        $cache_key = $cache_modules[$module_name];
        foreach($params as $key => $value) {
            $cache_key = str_replace('{'. strtoupper($key) .'}', $value, $cache_key);
        }
        return $cache_key;
    }
}
/* End of file Shuati_Controller.php */
/* Location: ./application/core/Shuati_Controller.php */