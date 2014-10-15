<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Statistics_Model extends MY_Model {

    /**
     * 根据老师ID获取老师扫描信息
     *
     * @param  int $teacher_id 老师ID
     * @return array           老师扫描信息
     */
    public function g_teacher_scan_info($teacher_id)
    {
        return $this->db->select('teacher_id, scan_number, scan_sheets')
            ->from('dafen_statistics')
            ->where('teacher_id', $teacher_id)
            ->get()->row_array();
    }


    /**
     * 设置老师的扫描信息
     *
     * @param  int $teacher_id 老师ID
     * @param  int $number     扫描的次数
     * @param  int $sheets     扫描的张数
     * @return int             受影响的行数
     */
    public function u_scan_info($teacher_id, $number, $sheets)
    {
        return $this->db->where('teacher_id', $teacher_id)->update('dafen_statistics', array('scan_number' => $number, 'scan_sheets' => $sheets));
    }


    /**
     * 插入老师扫描信息
     *
     * @param  int $teacher_id 老师ID
     * @param  int $number     扫描的次数
     * @param  int $sheets     扫描的张数
     * @return int             插入的ID
     */
    public function i_scan_info($teacher_id, $number, $sheets)
    {
        $this->db->insert('dafen_statistics', array('teacher_id' => $teacher_id, 'scan_number' => $number, 'scan_sheets' => $sheets));

        return $this->db->insert_id();
    }
}