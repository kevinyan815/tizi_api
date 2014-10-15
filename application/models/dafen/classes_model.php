<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Classes_Model extends MY_Model {

    /**
     * 根据老师ID获取他的所有班级信息
     *
     * @param int $teacher_id 老师ID
     * @return array 班级信息数组
     */
    public function g_class_by_teacher_id($teacher_id, $field = '')
    {
        ! empty($field) && $this->db->select($field);
        return $this->db->where('teacher_id', $teacher_id)
            ->from('dafen_class_ticket')
            ->get()->result_array();
    }
}
