<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

include 'base.php';
class Sync extends Base {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('dafen/sync_model', 'm_sync');
    }


    /**
     * 获取服务端同步的ID
     *
     * @return int 同步ID
     */
    public function pull_sync_id()
    {
        // 获取老师SessionID
        $session_id = $this->input->get_post('session', true);
        // 获取类型
        $sync_type  = $this->input->get_post('sync_type', true);
        ///////////////////////////////////////////////////////////////////////
        $session_id = 'd708c4e069a16a8f5b9212e63bc13b405f6a7dcd';
        $sync_type = 1;
        //////////////////////////////////////////////////////////////////////
        empty($session_id) && json_output(array('response_status' => 'error', 'response_error_message' => '老师信息有误', 'response_error_code' => 99, 'response_data' => array('done' => 0)));
        empty($sync_type) && json_output(array('response_status' => 'error', 'response_error_message' => '同步有误', 'response_error_code' => 98, 'response_data' => array('done' => 0)));

        // 获取老师ID，并验证存在
        $teacher_id = $this->get_user_id_by_session_id($session_id);
        empty($teacher_id) && json_output(array('response_status' => 'error', 'response_error_message' => '老师信息有误', 'response_error_code' => 97, 'response_data' => array('done' => 0)));

        // 获取ID
        $sync_id = $this->m_sync->g_sync_id_by_condition(array('teacher_id' => $teacher_id, 'sync_type' => $sync_type));
        json_output(array('response_status' => 'ok', 'response_data' => array('done' => 1, 'sync_id' => $sync_id)));
    }

}