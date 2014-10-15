<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Question_Model extends MY_Model {

    /**
     * 根据老师ID和服务端测试ID获取问题信息
     *
     * @param  int $teacher_id     老师ID
     * @param  int $exam_server_id 测试卷ID
     * @param  string $field       查询字段
     * @return array               查询结果
     */
    public function g_question_info_by_teacher_id_and_exam_server_id($teacher_id, $exam_server_id, $field = '')
    {
        ! empty($field) && $this->db->select($field);
        return $this->db->from('dafen_question')
            ->where(array('teacher_id' => $teacher_id, 'exam_server_id' => $exam_server_id))
            ->get()->row_array();
    }


    /**
     * 根据指定条件获取问题信息
     *
     * @param  mixed $condition 条件数组或字符串
     * @return array            符合条件的数据
     */
    public function g_question_info_by_condition($condition)
    {
        return $this->db->from('dafen_question')->where($condition)
            ->get()->row_array();
    }



    /**
     * 根据条件修改题目信息
     *
     * @param  array $data      修改的内容
     * @param  mixed $condition 条件数组或字符串
     * @return int              影响的行数
     */
    public function u_question_by_condition($data, $condition)
    {
        $this->db->where($condition)->update('dafen_question', $data);
        return $this->db->affected_rows();
    }
}