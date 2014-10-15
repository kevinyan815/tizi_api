<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
require_once APPPATH.'core/Shuati_Model.php';
/**
 * 爱刷题,学科Model
 * @author Kevin Yan <kevinyan815@gmail.com>
 */
class Subject_Model extends Shuati_Model
{
    public function __construct($database = 'tiku') 
    {
        parent::__construct($database);
        $this->load->model('redis/redis_model', 'redis');
        $this->load->model('common/shuati/common_subject');
    }
    
    /**
     * 获取学科列表
     */
    public function get_subject_list($app_type)
    {
        $subject_list = array();
        if($app_type) {
            $this->db->select('id AS subject_id, name')->from(Constant::TABLE_SHUATI_SUBJECT)->where('appType', $app_type);
            $subject_list = $this->db->get()->result_array();   
        }
        
        return $subject_list;
    }
    
    /**
     * 获取用户的学科统计 学科预测分和打败百分比都从redis中来取
     * @param $user_id      用户Id
     * @param $subject_id   学科Id
     * @return array
     */
    public function get_user_subject_statistics($user_id, $subject_id)
    {
        $this->redis->connect('shuati');
        $cache_key = 'subject_' . $subject_id .'_points_rank';
        $subject_points = $this->cache->redis->zscore($cache_key, $user_id);
        $subject_points = empty($subject_points) ? 0 : $subject_points;
        if($subject_points == 0) {
            #如果用户的学科预测分是0,那么打败用户百分比不需计算也应该是0%
            $beat_others = 0;
        } else {
            #通过用户在有序集合中的排名算出打败用户百分比
            $amount = $this->cache->redis->zcard($cache_key);
            $my_rank = $this->cache->redis->zrevrank($cache_key, $user_id);
            $weaker_than_me = $amount - ($my_rank + 1);
            $beat_others = round($weaker_than_me / $amount, 2);
        }
        $user_subject_statistics = array('prePoints' => $subject_points, 'beatOthers' => $beat_others);
        
        return $user_subject_statistics;
    }
    
    /**
     * 检查用户学科统计是否存在
     */
    public function check_user_subject_existence($user_id, $subject_id)
    {
        $str_fields = 'COUNT(*) AS existence';
        $arr_where  = array('userId' => $user_id, 'subjectId' => $subject_id);
        $arr_limit  = array(1);
        $exsitence = $this->common_subject->get_user_subject_statistics($str_fields, $arr_where, '',$arr_limit);
        
        return $exsitence['existence'] > 0 ? true : false;
    }
    
    /**
     * 创建用户的学科统计
     * @param array $insert_data 要插入的数据
     */
    public function create_user_subject_stat($insert_data)
    {
        $this->db->insert(Constant::TABLE_SHUATI_STU_SUBJECT, $insert_data);
        
        return $this->db->insert_id();
    }
    
    
    /**
     * 更新用户的学科预测分和打败学生百分比
     * @param int $user_id 用户Id
     * @param int $subject_id 学科Id
     * @param bool $recalculate 是否需要重新计算学科预测分
     * @return int $points 学科预测分
     */
    public function update_user_subject_stat($user_id, $subject_id, $recalculate = false)
    {
        $points = 0;
        if($recalculate) {
            #如果用户的一级知识点有提高那么需要重新计算学科预测分
            $points = $this->caculate_subject_points($user_id, $subject_id);
            $cache_key = 'subject_' . $subject_id .'_points_rank';
            $this->redis->connect('shuati');
            #将用户这个学科的分数更新到有序集合中
            $this->cache->redis->zadd($cache_key, $points, $user_id);
            #用户学科统计表里现在只放用户的预测分,打败百分比直接通过redis计算
            $stat_data = array('prePoints' => $points);
            $arr_where = array('userId' => $user_id, 'subjectId' => $subject_id);
            if(!$this->check_user_subject_existence($user_id, $subject_id)) {
                $stat_data = array_merge($stat_data, $arr_where);
                $this->create_user_subject_stat($stat_data);
            } else {
                $this->db->update(Constant::TABLE_SHUATI_STU_SUBJECT, $stat_data, $arr_where);
            }
        } else {
            $subject_stat = $this->get_user_subject_statistics($user_id, $subject_id);
            $points = $subject_stat['prePoints'];
        }
        
        return $points;
    }
    
    /**
     * 计算用户的学科预测分
     * @param int $user_id 用户Id
     * @param int $subject_id 学科Id
     * @return int $pints 分数
     */
    public function caculate_subject_points($user_id, $subject_id)
    {
        $knowledge_table = Constant::TABLE_SHUATI_KNOWLEDGE;
        $user_knowledge_table = Constant::TABLE_SHUATI_STU_KNOWLEDGE;
        $select = $knowledge_table. '.id AS knowledge_id,percent,kLevel';
        $arr_join = array($knowledge_table => $knowledge_table. '.id=' .$user_knowledge_table. '.kId');
        $arr_where = array('userId'=> $user_id, 'subjectId' => $subject_id, 'grade' => '1');
        $knowledge_arr = $this->common_knowledge->get_user_knowledge_statistics($select, $arr_join, $arr_where);
        $points = 0;
        foreach($knowledge_arr as $info) {
            $points += $info['kLevel'];
        }
        $points = round($points);
        
        return $points;
    }
    
}