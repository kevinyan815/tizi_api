<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

include 'base.php';
class Statistics extends Base {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('dafen/statistics_model', 'm_statistics');
    }


    /**
     * 拉取老师扫描信息
     *
     * @return [type] [description]
     */
    public function pull_scan_info()
    {
        // 获取老师ID根据session信息
        ($teacher_id = $this->get_teacher_id_by_session()) || json_output(array('response_status' => 'error', 'response_error_message' => '老师信息有误', 'response_error_code' => 99, 'response_data' => array('done' => 0)));

        // 获取扫描信息
        $scan_info = $this->m_statistics->g_teacher_scan_info($teacher_id);
        (false === $scan_info) && json_output(array('response_status' => 'error', 'response_error_message' => '获取老师扫描张数失败', 'response_error_code' => 98, 'response_data' => array('done' => 0)));
        if( empty($scan_info) )
        {
            $scan_info = array('teacher_id' => $teacher_id, 'scan_number' => 0, 'scan_sheets' => 0);
        }

        json_output(array('response_status' => 'ok', 'response_data' => array('done' => 1, 'list' => array('number' => $scan_info['scan_number'], 'sheets' => $scan_info['scan_sheets']) )));
    }

    /**
     * 推送老师扫了次数
     *
     * @return [type] [description]
     */
    public function push_scan_info()
    {
        // 获取老师ID根据session信息
        ($teacher_id = $this->get_teacher_id_by_session()) || json_output(array('response_status' => 'error', 'response_error_message' => '老师信息有误', 'response_error_code' => 99, 'response_data' => array('done' => 0)));
        // 获取扫描的次数
        $number = max((int)$this->input->get_post('number', true), 0);
        ( 0 > $number ) && json_output(array('response_status' => 'error', 'response_error_message' => '老师扫描次数有误', 'response_error_code' => 98, 'response_data' => array('done' => 0)));
        // 获取扫描的张数
        $sheets = max((int)$this->input->get_post('sheets', true), 0);
        ( 0 > $sheets ) && json_output(array('response_status' => 'error', 'response_error_message' => '老师扫描张数有误', 'response_error_code' => 97, 'response_data' => array('done' => 0)));

        // 执行修改
        $this->set_scan_info($teacher_id, $number, $sheets) || json_output(array('response_status' => 'error', 'response_error_message' => '推送老师扫描信息失败', 'response_error_code' => 96, 'response_data' => array('done' => 0)));

        json_output(array('response_status' => 'ok', 'response_data' => array('done' => 1, 'list' => '')));
    }


    /**
     * 设置老师的扫描信（修改已有信息或插入新信息）
     *
     * @param  int $teacher_id 老师ID
     * @param  int $number     扫描的次数
     * @param  int $sheets     扫描的张数
     * @return int             受影响的行数或新插入的ID
     */
    private function set_scan_info($teacher_id, $number, $sheets)
    {
        // 查询老师推送信息是否存在（存在则设置，否则新建）
        return $this->m_statistics->g_teacher_scan_info($teacher_id) ? $this->m_statistics->u_scan_info($teacher_id, $number, $sheets) : $this->m_statistics->i_scan_info($teacher_id, $number, $sheets);
    }
}