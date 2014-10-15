<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Sync_Model extends MY_Model {

    /**
     * 根据指定条件获取服务端同步ID
     * @param  array $where 条件
     * @return int
     */
    public function g_sync_id_by_condition($where)
    {
        $res = $this->db->from('dafen_sync')->where($where)->get()->result_array();

        if( ! empty($res['sync_id']) )
        {
            return $res['sync_id'];
        }
        else
        {
            return 0;
        }
    }
}