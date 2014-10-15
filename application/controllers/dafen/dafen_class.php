<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
include_once 'base.php';

class Dafen_Class extends Base {

    public function __construct()
    {
        parent::__construct();
        $this->load->helper('json');
        $this->load->model('dafen/classes_model', 'm_classes');
    }


    /**
     * 根据老师ID获取学生列表
     *
     * @return JSON 学生数据
     */
    public function class_list()
    {
        // 获取老师ID根据session信息
        ($teacher_id = $this->get_teacher_id_by_session()) || json_output(array('response_status' => 'error', 'response_error_message' => '老师信息有误', 'response_error_code' => 99, 'response_data' => array('done' => 0)));

        // 获取班级列表
        false !== ($class_list = $this->get_class_list($teacher_id)) && json_output(array('response_status' => 'ok', 'response_data' => array('done' => 1, 'list' => $class_list)));

        json_output(array('response_status' => 'error', 'response_error_message' => '获取班级列表失败', 'response_error_code' => 98, 'response_data' => array('done' => 0)));
    }


    /**
     * 获取班级列表（包含学生）
     *
     * @return [type] [description]
     */
    public function class_list_and_stutents()
    {
        // 获取老师ID根据session信息
        ($teacher_id = $this->get_teacher_id_by_session()) || json_output(array('response_status' => 'error', 'response_error_message' => '老师信息有误', 'response_error_code' => 99, 'response_data' => array('done' => 0)));

        // 获取班级列表
        $class_list = $this->get_class_list($teacher_id);
        if( false !== $class_list )
        {
            // 循环获取学生信息
            $this->load->model('class/classes_student_create');
            $this->load->model('dafen/dafen_student_model', 'm_dafen_student');
            $this->load->model('dafen/dafen_class_model', 'm_dafen_class');
            foreach ($class_list as &$cl_v)
            {
                // 初始化变量
                $tmp_tizi_student = $tmp_dafen_student = $student_list = array();
                // 获取指定班级学生基本信息(Tizi表)
                $tmp_tizi_student = $this->classes_student_create->get($cl_v['class_id']);
                if( ! empty($tmp_tizi_student) )
                {
                    // 获取指定班级学生信息(Dafen表)
                    $tmp_dafen_student = $this->m_dafen_student->g_student_by_class_id($cl_v['class_id']);
                    if( ! empty($tmp_dafen_student) )
                    {
                        // 获取班级准考证前缀
                        $class_ticket = $this->m_dafen_class->g_ticket_by_class_id($cl_v['class_id']);
                        // 整理Tizi学生数据(以学生ID为下标)
                        $tizi_student = array();
                        foreach ($tmp_tizi_student as $tts_v)
                        {
                            $tizi_student[$tts_v['student_id']] = $tts_v;
                        }

                        // 整理Dafen学生数据
                        foreach ($tmp_dafen_student as $tds_v)
                        {
                            $ticket = $tds_v['ticket'] > 9 ? $tds_v['ticket'] : '0' . $tds_v['ticket'];
                            $student_list[] = array(
                                'student_id' => $tds_v['student_id'],
                                'student_ticket' => $class_ticket . $ticket,
                                'student_class_id' => $tds_v['class_id'],
                                'student_name' => $tizi_student[$tds_v['student_id']]['student_name'],
                            );
                        }
                    }
                }

                // 将学生信息赋值给班级列表
                $cl_v['student_list'] = $student_list;
            }

            json_output(array('response_status' => 'ok', 'response_data' => array('done' => 1, 'list' => $class_list)));
        }

        json_output(array('response_status' => 'error', 'response_error_message' => '获取班级列表失败', 'response_error_code' => 98, 'response_data' => array('done' => 0)));
    }


    /**
     * 根据老师ID获取班级信息（已整理的班级数据）
     *
     * @param  int $teacher_id 老师ID
     * @return mixed           存在返回array，不存在返回false
     */
    private function get_class_list($teacher_id)
    {
        if( empty($teacher_id) )
        {
            return false;
        }

        // 初始化变量
        $tizi_class_list = $class_list = array();

        // 获取班级信息(Tizi表)
        $this->load->model('class/class_model');
        $tmp_class_list = $this->class_model->g_teacher_classinfo($teacher_id);
        if( ! empty($tmp_class_list) )
        {
             // 获取班级信息(Dafen)
            $class_list = $this->m_classes->g_class_by_teacher_id($teacher_id, 'class_id');
            if( ! empty($class_list) )
            {
                // 整理数据
                foreach ($tmp_class_list as $ci_v)
                {
                    $tizi_class_list[$ci_v['class_id']] = array(
                        'class_name' => $ci_v['classname'],
                        'class_grade' => $ci_v['class_grade'],
                    );
                }
                unset($tmp_class_list);

                // 获取年级信息
                $this->load->model('constant/grade_model', 'm_grade');
                $grade_info = $this->m_grade->get_grade();
                // 整理数据
                $grades = array();
                foreach ($grade_info as $gi_v)
                {
                    foreach ($gi_v as $g_k => $g_v)
                    {
                        if( 0 != $g_k )
                        {
                            $grades[$g_k] = $g_v;
                        }
                    }
                    $grades['5'] = '高二';
                    $grades['6'] = '高三';
                }

                // 整理班级数据
                foreach ($class_list as  &$cti_v)
                {
                    if( ! empty($tizi_class_list[$cti_v['class_id']]) )
                    {
                        $cti_v['class_name'] = $grades[$tizi_class_list[$cti_v['class_id']]['class_grade']] . $tizi_class_list[$cti_v['class_id']]['class_name'];
                    }
                }

                // 获取每个班级的人数总数
                $this->load->model('class/classes_student_create');
                $this->load->model('dafen/dafen_student_model', 'm_dafen_student');
                foreach ($class_list as &$cl_v)
                {
                    // 学生总数
                    $student_total = 0;
                    // 获取指定班级学生基本信息(Tizi表)
                    $tmp_tizi_student  = $this->classes_student_create->get($cl_v['class_id']);
                    // 获取指定班级学生准考证号信息(Dafen表)
                    $tmp_dafen_student = $this->m_dafen_student->g_student_by_class_id($cl_v['class_id']);
                    if( ! empty($tmp_tizi_student) && ! empty($tmp_dafen_student) )
                    {
                        // 整理梯子的学生数据
                        $tizi_student = array();
                        foreach ($tmp_tizi_student as $tzs_v)
                        {
                            $tizi_student[$tzs_v['student_id']] = $tzs_v;
                        }
                        unset($tmp_tizi_student);

                        // 计算总数
                        foreach ($tmp_dafen_student as $dfs_v)
                        {
                            if( ! empty($tizi_student[$dfs_v['student_id']]) && ! empty($tizi_student[$dfs_v['student_id']]['student_name']) )
                            {
                                $student_total++;
                            }
                        }
                    }

                    $cl_v['student_total'] = $student_total;
                }
            }
        }

        return $class_list;
    }
}
