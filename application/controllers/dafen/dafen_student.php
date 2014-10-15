<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
include_once 'base.php';

class Dafen_Student extends Base {

    public function __construct()
    {
        parent::__construct();
        $this->load->helper('json');
    }


    /**
     * 根据班级ID获取学生列表
     *
     * @return JSON 学生数据
     */
    public function student_list()
    {
        // 获取班级ID
        $class_id = max((int)$this->input->get_post('class_id', true), 0);
        empty($class_id) && json_output(array('response_status' => 'error', 'response_error_message' => '班级信息有误', 'response_error_code' => 99, 'response_data' => array('done' => 0)));

        // 获取指定班级学生基本信息(Tizi表)
        $this->load->model('class/classes_student_create');
        $tmp_student_info = $this->classes_student_create->get($class_id);
        empty($tmp_student_info) && json_output(array('response_status' => 'ok', 'response_data' => array('done' => 1, 'list' => '')));

        // 获取指定班级学生准考证号信息(Dafen表)
        $this->load->model('dafen/dafen_student_model', 'm_dafen_student');
        $student_ticket_info = $this->m_dafen_student->g_student_by_class_id($class_id);
        empty($student_ticket_info) && json_output(array('response_status' => 'ok', 'response_data' => array('done' => 1, 'list' => '')));

        // 获取班级准考证前缀
        $this->load->model('dafen/dafen_class_model', 'm_dafen_class');
        $class_ticket = $this->m_dafen_class->g_ticket_by_class_id($class_id);

        // 整理数据
        $student_info = array();
        foreach ($tmp_student_info as $tsi_v)
        {
            $student_info[$tsi_v['student_id']] = $tsi_v;
        }
        unset($tmp_student_info);
        foreach ($student_ticket_info as $sti_v)
        {
            $ticket = $sti_v['ticket'] > 9 ? $sti_v['ticket'] : '0' . $sti_v['ticket'];
            $student_list[] = array(
                'student_id' => $sti_v['student_id'],
                'student_ticket' => $class_ticket . $ticket,
                'student_class_id' => $sti_v['class_id'],
                'student_name' => $student_info[$sti_v['student_id']]['student_name'],
            );
        }
        unset($student_ticket_info);

        json_output( array('response_status' => 'ok', 'response_data' => array('done' => 1, 'list' => $student_list)) );
    }
}
