<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Shuati_Model extends LI_Model 
{

    function __construct($database='tiku')
    {
            parent::__construct($database);
    }
    
    /**
     * 从数据库中查询数据的通用方法
     * @param string $select_table 要查询主表
     * @param string $str_fields 要查询的字段
     * @param array  $arr_join  要join的表和join条件
     * @param array  $arr_where 查询条件
     * @param string $order_by  排序
     * @param array  $limit     个数和偏移量
     * @param string $group_by  分组
     * @param string $join_type 连接类型
     * @param mixed  $db_link   数据库连接
     */
    protected function _get_from_db($select_table, $str_fields = '*', $arr_join = array(), $array_where = array(), $order_by = '',$limit = array(), $group_by = '', $join_type = 'left', $db_link = '')
    {
        $db_conn = is_object($db_link) ? $db_link : $this->db;
        $result = array();
        $db_conn->select($str_fields)->from($select_table);
        if(!empty($arr_join)) {
            foreach($arr_join as $join_table => $join_condition) {
                $db_conn->join($join_table, $join_condition, $join_type);
            }
        }
        if(!empty($array_where)) {
            $db_conn->where($array_where);
        }
        if(!empty($group_by)) {
            $db_conn->group_by($group_by);
        }
        if(!empty($order_by)) {
            $db_conn->order_by($order_by);
        }
        if(!empty($limit)) {
            if(count($limit < 2)) array_push($limit, 0);
            list($num, $offset) = $limit;
            $db_conn->limit($num, $offset);
        }
        
        if(!empty($num) && $num == 1) {
            $result = $db_conn->get()->row_array();
        } else {
            $result = $db_conn->get()->result_array();
        }

        return $result;
    }

}
// END Model Class

/* End of file LI_Model.php */
/* Location: ./library/core/LI_Model.php */