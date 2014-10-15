<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
require_once APPPATH.'core/Shuati_Model.php';
/**
 * 爱刷题学科的数据库Model
 */
class Common_Subject extends Shuati_Model
{
    public function __construct($database = 'tiku') 
    {
        parent::__construct($database);
    }
    
    /**
     * 查询用户的学科统计数据(预测分,打败百分比)
     * @param int $user_id
     * @param int $subject_id
     * @param string $app_name APP名
     * @return array
     */
    public function get_user_subject_from_db($user_id, $subject_id, $app_name = 'shuati')
    {
        $table = $app_name == 'tiku' ? '' : Constant::TABLE_SHUATI_STU_SUBJECT;//选择数据表
        $sql = "SELECT prePoints, beatOthers FROM {$table}
                WHERE userId={$user_id} AND subjectId={$subject_id}";
        $arr_return = $this->db->query($sql)->row_array();
        
        return $arr_return;
    }
    
    /**
     * 获取用户学科统计的数据
     * @param string $str_fields 要查询的字段
     * @param array  $arr_where 查询条件
     * @param string $order_by  排序
     * @param array  $limit     个数和偏移量
     * @param string $app_name APP名
     */
    public function get_user_subject_statistics($str_fields = '*', $array_where = array(), $order_by = '', $limit = array(), $app_name = 'shuati')
    {
        $table = $app_name == 'tiku' ? '' : Constant::TABLE_SHUATI_STU_SUBJECT;//选择数据表
        $result = $this->_get_from_db($table, $str_fields, array(), $array_where, $order_by, $limit);
        
        return $result;
    }
}