<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');


/**
 * 打分项目的基础继承控制器
 */
class Base extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->helper('json');
    }


    /**
     * 根据客户端SESSION ID获取当前登录的用户ID
     *
     * @param  string $session_id SESSION ID
     * @return int                用户ID
     */
    protected function get_user_id_by_session_id($session_id)
    {
        // 根据session_id获取老师信息
        $this->load->model('login/session_model', 'm_session');
        $teacher_id = $this->m_session->get_api_session($session_id, Constant::API_TYPE_DAFEN, 'user_id');
        if( empty($teacher_id['user_id']) )
        {
            return false;
        }
        return $teacher_id['user_id'];
    }


    /**
     * 通过session信息获取老师ID
     *
     * @return mixed   成功返回老师ID，失败返回false
     */
    protected function get_teacher_id_by_session()
    {
        // 获取老师SessionID
        $session_id = $this->input->get_post('session_id', true);
        if( ! empty($session_id) )
        {
            // 根据session_id获取老师信息
            $this->load->model('login/session_model', 'm_session');
            $teacher_id = $this->m_session->get_api_session($session_id, Constant::API_TYPE_DAFEN, 'user_id');
            if( ! empty($teacher_id) )
            {
                return $teacher_id['user_id'];
            }
        }

        return false;
    }
}