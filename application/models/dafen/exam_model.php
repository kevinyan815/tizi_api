<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Exam_Model extends MY_Model {

    public function i_exam_info($exam_info, $question_info)
    {
        $exam = array();

        // 开启事务
        $this->db->trans_start();
        // 插入测试基本信息
        $this->db->insert('dafen_exam', $exam_info);
        $exam_id = $this->db->insert_id();
        // 插入测试问题
        foreach ($question_info as &$q_v)
        {
            $q_v['exam_id'] = $exam_id;
            $q_v['teacher_id'] = $exam_info['teacher_id'];
            $q_v['exam_server_id'] = $exam_info['exam_server_id'];
        }
        $this->db->insert_batch('dafen_question', $question_info);
        // 更新服务端与客户端的同步表
        if( $this->db->from('dafen_sync')->where('teacher_id', $exam_info['teacher_id'])->where('sync_type', 1)->get()->row_array() )    // 存在则更新
        {
            $this->db->where('teacher_id', $exam_info['teacher_id'])
                ->where('sync_type', 1)
                ->update('dafen_sync', array('sync_id' => $exam_info['exam_server_id'], 'sync_time' => time()));
        }
        else    // 不存在则插入新数据
        {
            $this->db->insert('dafen_sync', array(
                        'teacher_id' => $exam_info['teacher_id'],
                        'sync_type'  => 1,
                        'sync_id'    => $exam_info['exam_server_id'],
                        'sync_time'  => time(),
                    ));
        }

        // 提交事务
        $this->db->trans_complete();
        // 事务执行失败
        return $this->db->trans_status();
        if( FALSE === $this->db->trans_status() )
        {
            return false;
        }
        return true;
    }


    /**
     * 根据老师ID和服务端测试ID获取测试信息
     *
     * @param  int $teacher_id     老师ID
     * @param  int $exam_server_id 测试卷ID
     * @param  string $field       查询字段
     * @return array               查询结果
     */
    public function g_exam_info_by_teacher_id_and_exam_server_id($teacher_id, $exam_server_id, $field = '')
    {
        ! empty($field) && $this->db->select($field);
        return $this->db->from('dafen_exam')
            ->where(array('teacher_id' => $teacher_id, 'exam_server_id' => $exam_server_id))
            ->get()->row_array();
    }


    /**
     * 根据指定条件获取测试信息
     *
     * @param  mixed $condition 条件数组或字符串
     * @return array            符合条件的数据
     */
    public function g_exam_info_by_condition($condition)
    {
        return $this->db->from('dafen_exam')->where($condition)
            ->get()->row_array();
    }


    /**
     * 根据条件修改测试信息
     *
     * @param  array $data      修改的内容
     * @param  mixed $condition 条件数组或字符串
     * @return int              影响的行数
     */
    public function u_exam_by_condition($data, $condition)
    {
        $this->db->where($condition)->update('dafen_exam', $data);
        return $this->db->affected_rows();
    }


    /**
     * 根据老师ID获取老师创建的答题卡（不联表）
     *
     * @param  int   $teacher_id  老师ID
     * @return array              答题卡信息
     */
    public function g_exam_by_teacher_id($teacher_id)
    {
        return $this->db->where('student_id', $student_id)
            ->from('dafen_exam')
            ->get()->result_array();
    }


    /**
     * 根据老师ID获取老师创建的答题卡信息（联表）
     *
     * @param  int   $teacher_id 老师ID
     * @return array             答题卡信息
     */
    public function g_exam_info_by_teacher_id($exam_idteacher_id)
    {
        $this->db->select('a.*,b.,b.index,b.correct_option,b.score')
            ->from('dafen_exam AS a')
            ->join('dafen_question AS b', 'a.id = b.exam_id', 'left')
            ->where('a.teacher_id', $teacher_id)
            ->get()->result_array();
    }
}
