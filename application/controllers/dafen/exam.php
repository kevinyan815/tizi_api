<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
include_once 'base.php';

class Exam extends Base {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('dafen/exam_model', 'm_exam');
        $this->load->model('dafen/question_model', 'm_question');
        $this->load->model('dafen/answer_model', 'm_answer');
    }


    /**
     * 向服务端推送测验信息
     *         一次推送一套测验信息（包含测验题目）
     *
     * @return JSON  服务端存储情况
     */
    public function push_exam_info()
    {
        // 获取老师ID根据session信息
        ($teacher_id = $this->get_teacher_id_by_session()) || json_output(array('response_status' => 'error', 'response_error_message' => '老师信息有误', 'response_error_code' => 99, 'response_data' => array('done' => 0)));

        // 获取测试卷基本信息
        $exam_info     = $this->input->get_post('exam', true);
        // 获取测试卷详情信息
        $question_info = $this->input->get_post('question', true);

        empty($exam_info) && json_output(array('response_status' => 'error', 'response_error_message' => '答题卡信息有误', 'response_error_code' => 98, 'response_data' => array('done' => 0)));
        empty($exam_info) && json_output(array('response_status' => 'error', 'response_error_message' => '答题卡信息有误', 'response_error_code' => 97, 'response_data' => array('done' => 0)));

        $exam_info['teacher_id'] = $teacher_id;

        // 执行添加
        if( $this->m_exam->i_exam_info($exam_info, $question_info) )
        {
            json_output(array('response_status' => 'ok', 'response_data' => array('done' => 1)));
        }
        else
        {
            json_output(array('response_status' => 'error', 'response_error_message' => '推送测试信息失败', 'response_error_code' => 1, 'response_data' => array('done' => 0)));
        }
    }


    /**
     * 向服务端推送学生答题信息
     *         一次推送一组
     *
     * @return JSON  服务端存储情况
     */
    public function push_answer_info()
    {
        // 获取老师ID根据session信息
        ($teacher_id = $this->get_teacher_id_by_session()) || json_output(array('response_status' => 'error', 'response_error_message' => '老师信息有误', 'response_error_code' => 99, 'response_data' => array('done' => 0)));
        // 获取回答的信息数组
        ($answer_info = $this->input->get_post('aswer', true)) && json_output(array('response_status' => 'error', 'response_error_message' => '答案信息有误', 'response_error_code' => 98, 'response_data' => array('done' => 0)));

        // 整理数据
        foreach ($answer_info as &$ai_v)
        {
            // 获取考试卷ID
            $exam_info = $this->m_exam->g_exam_info_by_teacher_id_and_exam_server_id($teacher_id, $ai_v['exam_server_id'], 'id');
            $ai_v['exam_id']   = empty($exam_info['id']) ? 0 : $exam_info['id'];
            $ai_v['teacher_id'] = $teacher_id;

            // 获取所回答问题的ID
            $question_info = $this->m_question->g_question_info_by_teacher_id_and_exam_server_id($teacher_id, $ai_v['exam_server_id'], 'id');
            $ai_v['question_id'] = empty($question_info['id']) ? 0 : $question_info['id'];
        }

        // 执行插入
        if( $this->m_answer->i_answer_info($answer_info) )
        {
            json_output(array('response_status' => 'ok', 'response_data' => array('done' => 1)));
        }
        else
        {
            json_output(array('response_status' => 'error', 'response_error_message' => '推送回答信息失败', 'response_error_code' => 1, 'response_data' => array('done' => 0)));
        }
    }


    /**
     * 修改测验的某个题目答案
     *
     * @return [type] [description]
     */
    public function modify_exam_question()
    {
        // 获取老师ID根据session信息
        ($teacher_id = $this->get_teacher_id_by_session()) || json_output(array('response_status' => 'error', 'response_error_message' => '老师信息有误', 'response_error_code' => 99, 'response_data' => array('done' => 0)));
        // 获取问题对应考题的服务器端ID
        $exam_server_id = $this->input->get_post('exam_server', true);
        // 获取问题题目的索引
        $question_index = $this->input->get_post('index', true);
        // 修改的内容
        $option         = $this->input->get_post('option', true);

        empty($exam_server_id) && json_output(array('response_status' => 'error', 'response_error_message' => '服务端ID有误', 'response_error_code' => 98, 'response_data' => array('done' => 0)));
        empty($question_index) && json_output(array('response_status' => 'error', 'response_error_message' => '题目索引有误', 'response_error_code' => 97, 'response_data' => array('done' => 0)));
        empty($option) && json_output(array('response_status' => 'error', 'response_error_message' => '修改信息有误', 'response_error_code' => 96, 'response_data' => array('done' => 0)));

        // 查询当前题目是否存在
        if( ! empty($this->m_question->g_question_info_by_condition(array('teacher_id' => $teacher_id, 'exam_server_id' => $exam_server_id, 'index' => $question_index))) )
        {
            // 修改题目信息
            if( $this->m_question->u_question_by_condition(array('answer_option' => $option), array('teacher_id' => $teacher_id, 'exam_server_id' => $exam_server_id, 'index' => $question_index)) )
            {
                json_output(array('response_status' => 'ok', 'response_data' => array('done' => 1)));
            }
            else
            {
                json_output(array('response_status' => 'error', 'response_error_message' => '修改题目信息失败', 'response_error_code' => 3, 'response_data' => array('done' => 0)));
            }
        }
        else
        {
            json_output(array('response_status' => 'error', 'response_error_message' => '题目信息不存在', 'response_error_code' => 2, 'response_data' => array('done' => 0)));
        }
    }


    /**
     * 删除某次测试
     * @return [type] [description]
     */
    public function delete_exam()
    {
        // 获取老师ID根据session信息
        ($teacher_id = $this->get_teacher_id_by_session()) || json_output(array('response_status' => 'error', 'response_error_message' => '老师信息有误', 'response_error_code' => 99, 'response_data' => array('done' => 0)));
        // 获取问题对应考题的服务器端ID
        $exam_server_id = $this->input->get_post('exam_server', true);

        empty($exam_server_id) && json_output(array('response_status' => 'error', 'response_error_message' => '服务端ID有误', 'response_error_code' => 98, 'response_data' => array('done' => 0)));

        // 查询当前测试是否存在
        if( ! empty($this->m_exam->g_exam_info_by_condition(array('teacher_id' => $teacher_id, 'exam_server_id' => $exam_server_id))) )
        {
            // 删除测试信息（标记删除位）
            if( $this->m_exam->u_exam_by_condition(array('status' => 1), array('teacher_id' => $teacher_id, 'exam_server_id' => $exam_server_id)) )
            {
                json_output(array('response_status' => 'ok', 'response_data' => array('done' => 1)));
            }
            else
            {
                json_output(array('response_status' => 'error', 'response_error_message' => '删除测试信息失败', 'response_error_code' => 3, 'response_data' => array('done' => 0)));
            }
        }
        else
        {
            json_output(array('response_status' => 'error', 'response_error_message' => '测试信息不存在', 'response_error_code' => 2, 'response_data' => array('done' => 0)));
        }
    }


    /**
     * 删除测验的某个题目
     *
     * @return [type] [description]
     */
    public function delete_question()
    {
        // 获取老师ID根据session信息
        ($teacher_id = $this->get_teacher_id_by_session()) || json_output(array('response_status' => 'error', 'response_error_message' => '老师信息有误', 'response_error_code' => 99, 'response_data' => array('done' => 0)));
        // 获取问题对应考题的服务器端ID
        $exam_server_id = $this->input->get_post('exam_server', true);
        // 获取问题题目的索引
        $question_index = $this->input->get_post('index', true);

        empty($exam_server_id) && json_output(array('response_status' => 'error', 'response_error_message' => '服务端ID有误', 'response_error_code' => 98, 'response_data' => array('done' => 0)));
        empty($question_index) && json_output(array('response_status' => 'error', 'response_error_message' => '题目索引有误', 'response_error_code' => 97, 'response_data' => array('done' => 0)));

        // 查询当前题目是否存在
        if( ! empty($this->m_question->g_question_info_by_condition(array('teacher_id' => $teacher_id, 'exam_server_id' => $exam_server_id, 'index' => $question_index))) )
        {
            // 删除题目信息（标记删除位）
            if( $this->m_question->u_question_by_condition(array('status' => 1), array('teacher_id' => $teacher_id, 'exam_server_id' => $exam_server_id, 'index' => $question_index)) )
            {
                json_output(array('response_status' => 'ok', 'response_data' => array('done' => 1)));
            }
            else
            {
                json_output(array('response_status' => 'error', 'response_error_message' => '删除题目信息失败', 'response_error_code' => 3, 'response_data' => array('done' => 0)));
            }
        }
        else
        {
            json_output(array('response_status' => 'error', 'response_error_message' => '题目信息不存在', 'response_error_code' => 2, 'response_data' => array('done' => 0)));
        }
    }
}