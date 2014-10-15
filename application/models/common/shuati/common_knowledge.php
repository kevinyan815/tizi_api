<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
require_once APPPATH.'core/Shuati_Model.php';
/**
 * 爱刷题知识点的数据库Model
 */
class Common_Knowledge extends Shuati_Model
{
    public function __construct($database = 'tiku') 
    {
        parent::__construct($database);
    }
    
    /**
     * 获取知识点列表
     * @param string $str_fields 要查询的字段
     * @param array  $arr_join  要join的表和join条件
     * @param array  $arr_where 查询条件
     * @param string $order_by  排序
     * @param array  $limit     个数和偏移量
     * @param string $app_name APP名
     */
    public function get_knowledge_list($str_fields = '*', $arr_join = array(), $array_where = array(), $order_by = '', $limit = array(), $app_name = 'shuati')
    {
        $table = $app_name == 'tiku' ? '' : Constant::TABLE_SHUATI_KNOWLEDGE;
        $knowledge_arr = $this->_get_from_db($table, $str_fields, $arr_join, $array_where, $order_by, $limit);
        
        return $knowledge_arr;
    }
    
    /**
     * 获取用户知识点的详细数据
     * @param string $str_fields 要查询的字段
     * @param array  $arr_join  要join的表和join条件
     * @param array  $arr_where 查询条件
     * @param string $order_by  排序
     * @param array  $limit     个数和偏移量
     * @param string $app_name APP名
     */
    public function get_user_knowledge_statistics($str_fields = '*', $arr_join = array(), $array_where = array(), $order_by = '', $limit = array(), $app_name = 'shuati')
    {
        $table = $app_name == 'tiku' ? '' : Constant::TABLE_SHUATI_STU_KNOWLEDGE;//选择数据表
        $result = $this->_get_from_db($table, $str_fields, $arr_join, $array_where, $order_by, $limit);

        return $result;
    }
    
    /**
     * 通用的查询题目相关知识点数据的方法
     * @param string $str_fields 要查询的字段
     * @param array  $arr_join  要join的表和join条件
     * @param array  $arr_where 查询条件
     * @param string $order_by  排序
     * @param array  $limit     个数和偏移量
     * @param string $app_name APP名
     */
    public function get_question_relevant_knowledge($str_fields = '*', $arr_join = array(), $array_where = array(), $order_by = '', $limit = array(), $app_name = 'shuati')
    {

        $table = $app_name == 'tiku' ? '' : Constant::TABLE_SHUATI_KNOWLEDGE_QUESTION_RELATION;
        $result = $this->_get_from_db($table, $str_fields, $arr_join, $array_where, $order_by, $limit);

        return $result;
    }

    /**
     * 更新用户知识点统计
     * @param array $update_data 要更新的数据
     * @param array $arr_where   条件
     * @return bool
     */
    public function update_user_knowledge($update_data, $arr_where, $app_name = 'shuati')
    {
        $user_knowledge_table = $app_name =='tiku' ? '' : Constant::TABLE_SHUATI_STU_KNOWLEDGE;
        $this->db->update($user_knowledge_table, $update_data, $arr_where);
        $int_affected_rows = $this->db->affected_rows();
        return $int_affected_rows > 0 ? true :false;
    }
}