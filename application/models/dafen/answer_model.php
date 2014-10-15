<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Answer_Model extends MY_Model {

    /**
     * 插入回答信息（多条）
     *
     * @param  array  $answer_info 回答的信息，数组
     * @return int                 受影响的行数
     */
    public function i_answer_info(array $answer_info)
    {
        $this->db->insert_batch('dafen_answer', $answer_info);
        return $this->db->affected_rows();
    }
}
