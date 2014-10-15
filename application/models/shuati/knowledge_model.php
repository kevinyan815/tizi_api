<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
require_once APPPATH.'core/Shuati_Model.php';
/**
 * 爱刷题,学科Model
 * @author Kevin Yan <kevinyan815@gmail.com>
 */
class Knowledge_Model extends Shuati_Model
{
    public function __construct($database = 'tiku') 
    {
        parent::__construct($database);
        $this->load->model('redis/redis_model', 'redis');
        $this->load->model('common/shuati/common_knowledge');
    }
    
    /**
     * 获取用户某一学科下的知识点树形结构
     * @param int $user_id 用户Id
     * @param int $subject_id 学科Id
     */
    public function get_user_knowledge_tree($user_id, $subject_id)
    {
        $knowledge_arr = $this->common_knowledge->get_knowledge_list('id, name, parentId, sequence, subjectId', array(), array('subjectId' => $subject_id));
        if(is_array($knowledge_arr)) {
            foreach($knowledge_arr as &$knowledge) {
                $user_knowledge_stat = $this->get_user_knowledge_cache($user_id, $knowledge['id']);
                if(!$user_knowledge_stat) {
                    $user_knowledge_stat = $this->common_knowledge->get_user_knowledge_statistics('kLevel,questionNums,score', array(), array('userId' => $user_id, 'kId' => $knowledge['id']), '', array(1));
                    $user_knowledge_stat = empty($user_knowledge_stat) ? array('kLevel' => 0, 'questionNums' => 0, 'score' => 0.0) : $user_knowledge_stat;
                    $cache_data = array_merge($knowledge, $user_knowledge_stat);
                    #将用户的知识点统计缓存到redis
                    $this->set_user_knowledge_cache($user_id, $knowledge['id'], $cache_data);
                }
                $knowledge['kId'] = $knowledge['id'];//ios里id是特殊标示,这里加下返回kId
                $knowledge['questionNums'] = $user_knowledge_stat['questionNums'];
                #一级知识点等级上限是6二级知识点的等级上限是4
                $knowledge['finish_rate']  = $knowledge['parentId'] == 0 ? sprintf('%4.2f' , (intval($user_knowledge_stat['kLevel']) / 6)) : sprintf('%4.2f', (intval($user_knowledge_stat['kLevel']) / 4));
                $sort_id[] = $knowledge['id'];
                $sort_sequence[] = $knowledge['sequence'];
            }
        }
        array_multisort($sort_sequence, SORT_DESC, $sort_id, SORT_ASC, $knowledge_arr);
        unset($knowledge);//上一个foreach对$knowledge进行了引用声明,要在这里断开引用
        #format to tree form
        $knowledge_tree = array();
        foreach($knowledge_arr as $val) {
            if($val['parentId'] != 0) {
                #二级知识点归纳进上级知识点的数组中
                $knowledge_tree[$val['parentId']]['sub_knowledge'][] = $val;
            } else {
                $knowledge_tree[$val['id']] = isset($knowledge_tree[$val['id']]) ? $knowledge_tree[$val['id']] : array();
                $knowledge_tree[$val['id']] = array_merge($knowledge_tree[$val['id']], $val);
            }
        }

        return array_values($knowledge_tree);
    }
    
    /**
     * 获取学科的知识点树形结构
     * 此方法区别于上一个方法,不包含用户的知识点数据
     * @param int $subject_id 学科Id
     */
    public function get_subject_knowledge_tree($subject_id)
    {
        $knowledge_arr = $this->common_knowledge->get_knowledge_list('id, name, parentId, sequence', array(), array('subjectId' => $subject_id));
        if(is_array($knowledge_arr)) {
            foreach($knowledge_arr as &$knowledge) {
                $knowledge['kId'] = $knowledge['id'];//ios里id是特殊标示,这里加下返回kId
                $sort_id[] = $knowledge['id'];
                $sort_sequence[] = $knowledge['sequence'];
            }
        }
        array_multisort($sort_sequence, SORT_DESC, $sort_id, SORT_ASC, $knowledge_arr);
        unset($knowledge);//上一个foreach对$knowledge进行了引用声明,要在这里断开引用
        #format to tree form
        $knowledge_tree = array();
        foreach($knowledge_arr as $val) {
            if($val['parentId'] != 0) {
                #二级知识点归纳进上级知识点的数组中
                $knowledge_tree[$val['parentId']]['sub_knowledge'][] = $val;
            } else {
                $knowledge_tree[$val['id']] = isset($knowledge_tree[$val['id']]) ? $knowledge_tree[$val['id']] : array();
                $knowledge_tree[$val['id']] = array_merge($knowledge_tree[$val['id']], $val);
            }
        }
        
        return array_values($knowledge_tree);
    }
    
    /**
     * 获取题目关联的知识点数据
     * @param int $question_id 题目Id
     */
    public function get_question_relevant_knowledge($question_id)
    {
        $knowledge_info = array();
        if($question_id) {
            $relation_table = Constant::TABLE_SHUATI_KNOWLEDGE_QUESTION_RELATION;
            $knowledge_table = Constant::TABLE_SHUATI_KNOWLEDGE;
            $str_fields = $knowledge_table. '.id,subjectId as subject_id,name,'. $knowledge_table .'.grade';
            $arr_join = array($knowledge_table => $relation_table.'.kId='.$knowledge_table.'.id');
            $arra_where = array('qId' => $question_id);
            $knowledge_info = $this->common_knowledge->get_question_relevant_knowledge($str_fields, $arr_join, $arra_where);
        }
        
        return $knowledge_info;
    }
    
    /**
     * 检查用户的知识点统计信息是否存在
     * @param int $user_id  用户Id
     * @param int $knowledge_id 知识点Id
     */
    public function check_user_knowledge_existence($user_id, $knowledge_id)
    {
        $str_fields = 'COUNT(*) AS existence';
        $arr_where  = array('userId' => $user_id, 'kId' => $knowledge_id);
        $arr_limit  = array(1);
        $exsitence = $this->common_knowledge->get_user_knowledge_statistics($str_fields, array(), $arr_where, '',$arr_limit);
        return $exsitence['existence'] > 0 ? true : false;
    }
    
    /**
     * 新增用户知识点统计记录
     * @param array $insert_data
     */
    public function add_user_knowledge_statistics($insert_data)
    {
        $this->db->insert(Constant::TABLE_SHUATI_STU_KNOWLEDGE, $insert_data);
        $last_insert_id = $this->db->insert_id();

        return $this->db->insert_id();
    }
    
    /**
     * 更新用户的知识点统计
     * @param int $user_id 用户Id
     * @param int $subject_id 学科Id
     * @param int $knowledge_id 知识点Id
     * @param int $score_variation  分数变动值
     * @param int $question_nums  题目增加的数量
     * @param int $grade 知识点层级
     */
    public function update_user_knowledge_statistics($user_id, $subject_id, $knowledge_id, $score_variation, $question_nums, $grade)
    {
        $kLevel_scores = array(1 => 3, 2 => 9, 3 => 18, 4 => 30, 5 => 45, 6 => 63);
        $grade_levels = array(1 => 6, 2 => 4);#一级知识点的掌握程度等级上线为6，二级上线为4
        
        $improve = FALSE;#升级标志位
        $knowledge_info = $this->common_knowledge->get_user_knowledge_statistics('kLevel,score,questionNums', array(), array('userId' => $user_id, 'kId' => $knowledge_id), '', array(1));
        
        $new_score = $knowledge_info['score'] + $score_variation;
        $new_score = $new_score < 0 ? 0 : $new_score;
        #确保不要超出等级上限
        $next_Level = $knowledge_info['kLevel'] + 1 > $grade_levels[$grade] ? $knowledge_info['kLevel'] : $knowledge_info['kLevel'] + 1;
        #升到下一级所需要的分数
        $needed_scores = $kLevel_scores[$next_Level];
        if(($new_score >= $needed_scores) && ($knowledge_info['kLevel'] < $grade_levels[$grade])) {
            $new_level = $next_Level;
            $grade == 1 && $improve = TRUE;//一级知识点掌握程度升级后需要返回
        } else {
            #否则级数保持不变
            $new_level = $knowledge_info['kLevel'];
        }
        $new_question_nums = $knowledge_info['questionNums'] + $question_nums;
        $update_data = array('kLevel' => $new_level, 'score' => $new_score, 'questionNums' => $new_question_nums);
        $res = $this->common_knowledge->update_user_knowledge($update_data, array('userId' => $user_id, 'kId' => $knowledge_id));
        if(!$res) {
            return false;
        } else {
            #插入数据库成功后更新知识点统计的redis缓存
            $this->set_user_knowledge_cache($user_id, $knowledge_id, $update_data);
            if($improve) {
                #返回升级的一级知识点的id
                return array('status' => true, 'promoted_knowledge' => $knowledge_id);
            } else {
                return true;
            }
        }
    }
    
    /**
     * 获取用户知识点统计的缓存
     * @param int $user_id 用户Id
     * @param int $knowledge_id 知识点Id
     * @return array
     */
    public function get_user_knowledge_cache($user_id, $knowledge_id)
    {
        $cache_key = $cache_key = 'user_' . $user_id . '_knowledge_statistics';
        $this->redis->connect('shuati');
        $knowledge_stat_arr = array();
        if($knowledge_stat_str = $this->cache->redis->hget($cache_key, $knowledge_id)) {
            $knowledge_stat_arr = json_decode($knowledge_stat_str, true);
        }
        
        return $knowledge_stat_arr;
    }
    /**
     * 更新用户知识点统计的缓存
     * @param int $user_id
     * @param int $knowledge_id
     * @param array $cache_data
     */
    public function set_user_knowledge_cache($user_id, $knowledge_id, $cache_data)
    {
        $cache_key = $cache_key = 'user_' . $user_id . '_knowledge_statistics';
        $knowledge_stat_arr = $this->get_user_knowledge_cache($user_id, $knowledge_id);
        if(empty($knowledge_stat_arr['name']) && empty($cache_data['name'])) {
            #确保知识点id和名称要存在,其他地方要用
            $knowledge_info = $this->common_knowledge->get_knowledge_list('id, name, subjectId', array(), array('id' => $knowledge_id), '', array(1));
            $knowledge_stat_arr['id'] = $knowledge_info['id'];
            $knowledge_stat_arr['name'] = $knowledge_info['name'];
            $knowledge_stat_arr['subjectId'] = $knowledge_info['subjectId'];
        }
        $new_knowledge_stat_arr = array_merge($knowledge_stat_arr, $cache_data);
        return $this->cache->redis->hset($cache_key, $knowledge_id, json_encode($new_knowledge_stat_arr));
    }
}