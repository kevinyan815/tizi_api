<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 关于爱刷题题目的基本操作
 */
require_once APPPATH.'core/Shuati_Model.php';
class Common_Question extends Shuati_Model
{
    public function __construct($database = 'tiku') 
    {
        parent::__construct($database);
        
    }
    
    /**
     * 获取题目信息 获取单条题目用
     * @param $question_id
     * @return array
     */
    public function get_question_basic_info($question_id)
    {
        $question = array();
        if(!empty($question_id)) {
            $sql = 'SELECT q.id AS question_id,q.multiOpts AS multi_answer,
                    q.answer,q.question,q.subjectId AS subject_id,qs.briefName AS tag,
                    q.materialId AS article_id,q.analysis AS resolve,m.title,m.content FROM '. Constant::TABLE_SHUATI_QUESTION .' q
                    LEFT JOIN '. Constant::TABLE_SHUATI_MATERAIL .' m ON q.materialId=m.id
                    LEFT JOIN '. Constant::TABLE_SHUATI_QUESTION_SOURCE .' qs ON q.sourceId=qs.id WHERE q.id='.$question_id;
            $question = $this->db->query($sql)->row_array();
        }
        
        return $question;
    }
       
    /**
     * 获取题目的选项信息
     * @param $question_id 题目Id
     * @return array
     */
    public function get_question_options($question_id)
    {
        $options = array();
        if(!empty($question_id)) {
            $sql = 'SELECT opt,optContent  FROM '. Constant::TABLE_SHUATI_OPTION .' WHERE qid ='.$question_id.' ORDER BY opt ASC';
            $options = $this->db->query($sql)->result_array();
        }
        
        return $options;
    }
    
    /**
     * 获取题目相关的考点
     * @param $question_id 题目Id
     * @return array
     */
    public function get_question_relevant_knowledge($question_id)
    {
        $relevant_knowledge = array();
        if(!empty($question_id)) {
            $sql = 'SELECT k.name FROM '. Constant::TABLE_SHUATI_KNOWLEDGE_QUESTION_RELATION .' kqr
                    LEFT JOIN '. Constant::TABLE_SHUATI_KNOWLEDGE .' k ON kqr.kId = k.id
                    WHERE kqr.qId = '.$question_id;
            $relevant_knowledge = $this->db->query($sql)->result_array();
        }
        
        return $relevant_knowledge;
    }
    
    /**
     * 通用的获取题目数据的方法
     * @param string $str_fields 要查询的字段
     * @param array  $arr_join  要join的表和join条件
     * @param array  $arr_where 查询条件
     * @param string $order_by  排序
     * @param array  $limit     个数和偏移量
     * @param string $app_name APP名
     */
    public function get_question_list($str_fields = '*', $arr_join = array(), $array_where = array(), $order_by = '', $limit = array(), $app_name = 'shuati')
    {

        $table = $app_name == 'tiku' ? '' : Constant::TABLE_SHUATI_QUESTION;
        $result = $this->_get_from_db($table, $str_fields, $arr_join, $array_where, $order_by, $limit);

        return $result;
    }
    
    /**
     * 获取材料列表
     * @param string $str_fields 要查询的字段
     * @param array  $arr_join  要join的表和join条件
     * @param array  $arr_where 查询条件
     * @param string $order_by  排序
     * @param array  $limit     个数和偏移量
     * @param string $app_name APP名
     */
    public function get_material_list($str_fields = '*', $arr_join = array(), $array_where = array(), $order_by = '',$limit = array(), $app_name = 'shuati')
    {
        $table = $app_name == 'tiku' ? '' : Constant::TABLE_SHUATI_MATERAIL;
        $material_id_list = $this->_get_from_db($table, $str_fields, $arr_join, $array_where, $order_by, $limit);

        return $material_id_list;
    }
 
}