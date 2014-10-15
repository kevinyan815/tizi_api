<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
require_once APPPATH.'core/Shuati_Model.php';
/**
 * 爱刷题,题目Model
 */
class Question_Model extends Shuati_Model
{
    public function __construct($database = 'tiku')
    {
        parent::__construct($database);
        $this->load->model('common/shuati/common_question');
        $this->load->model('common/shuati/common_user');
        $this->load->model('redis/redis_model', 'redis');
    }
    
    /**
     * 获取知识点下的题目ID列表
     * @param $knowledge_id 知识点Id
     */
    public function get_knowledge_question_id_list($knowledge_id)
    {
        $question_table = Constant::TABLE_SHUATI_QUESTION;
        $question_relation_table = Constant::TABLE_SHUATI_KNOWLEDGE_QUESTION_RELATION;
        $str_fields = $question_table .'.id';
        $arr_join  = array($question_relation_table => "{$question_table}.id={$question_relation_table}.qId");
        $arr_where = array('kId' => $knowledge_id, 'status' => 2);
        $question_id_arr = $this->common_question->get_question_list($str_fields, $arr_join, $arr_where);
        $question_id_list = array();
        foreach($question_id_arr as $val) {
            $question_id_list[] = $val['id'];
        }
        
        return array_unique($question_id_list);
    }
    
    /**
     * 获取学科下的题目ID列表
     */
    public function get_subject_question_id_list($subject_id)
    {
        $question_id_list = array();
        if($subject_id) {
            $arr_where = array('subjectId' => $subject_id, 'status' => 2);
            $question_id_arr = $this->common_question->get_question_list('id', array(), $arr_where);
            foreach($question_id_arr as $val) {
                $question_id_list[] = $val['id'];
            }
            $question_id_list = array_unique($question_id_list);
        }
        
        return $question_id_list;
    }
    
    /**
     * 获取阅读材料下的题目Id列表
     */
    public function get_material_question_id_list($material_id)
    {
        $question_id_list = array();
        if($material_id) {
            $arr_where = array('materialId' => $material_id);
            $question_id_arr = $this->common_question->get_question_list('id', array(), $arr_where, 'id asc');
            foreach($question_id_arr as $val) {
                $question_id_list[] = $val['id'];
            }
            $question_id_list = array_unique($question_id_list);
        }
        
        return $question_id_list;
    }
    /**
     * 根据知识点获取知识点下的阅读材料ID列表
     * @param int $knowledge_id 知识点Id
     */
    public function get_knowledge_material_id_list($knowledge_id)
    {
        $material_id_list = array();
        if($knowledge_id) {
            $material_table = Constant::TABLE_SHUATI_MATERAIL;
            $question_table = Constant::TABLE_SHUATI_QUESTION;
            $question_relation_table = Constant::TABLE_SHUATI_KNOWLEDGE_QUESTION_RELATION;
            $str_fields = Constant::TABLE_SHUATI_MATERAIL.'.id';
            $arr_join = array(
                $question_table => $material_table. '.id=' .$question_table. '.materialId',
                $question_relation_table => $question_table. '.id=' .$question_relation_table. '.qId'
            );
            $arr_where = array($question_relation_table.'.kId' => $knowledge_id, $question_table.'.status' => 2);
//            $arr_where = array($question_relation_table.'.kId' => $knowledge_id);
            $material_id_arr = $this->common_question->get_material_list($str_fields, $arr_join, $arr_where);
            foreach($material_id_arr as $val) {
                $material_id_list[] = $val['id'];
            }
        }
        
        return array_unique($material_id_list);
    }

    /**
     * 获取某学科下用户已经做过的题目的Id列表
     * @param int $user_id
     * @param int $subject_id
     * @return array
     */
    public function get_subject_answered_questions($user_id, $subject_id)
    {
        $cache_key = 'user_'. $user_id .'_subject_'. $subject_id .'_answered_questions';
        $this->redis->connect('shuati');
        $answered_qeustions = array();
        if($list_len = $this->cache->redis->llen($cache_key)) {
            $answered_qeustions = $this->cache->redis->lrange($cache_key, 0, $list_len - 1);
        }
        
        return $answered_qeustions;
    }
    
    /**
     * 获取用户某学科下已经做过的材料题的材料Id列表
     * @param int $user_id   用户Id
     * @param int $subject_id
     */
    public function get_subject_answered_materials($user_id, $subject_id)
    {
        $cache_key = 'user_'. $user_id .'_subject_'. $subject_id .'_answered_materials';
        $this->redis->connect('shuati');
        $answered_materials = array();
        if($llen = $this->cache->redis->llen($cache_key)) {
            $answered_materials = $this->cache->redis->lrange($cache_key, 0, $llen - 1);
        }
        
        return $answered_materials;
    }
    
    
    /**
     * 组装题目响应数据
     * @param array $question_ids 题目Id列表
     * @param int   $user_id      用户Id
     * @param int   $material_id  包含阅读材料信息(默认false不包含, 只有题目历史接口调用该方法时才需要包含材料信息)
     * @return array $question_arr 组装好的题目数据
     */
    public function build_question_response($question_ids, $user_id, $contain_material = false)
    {
        $question_arr = array();
        if(!is_array($question_ids)) $question_ids = array($question_ids);
        foreach($question_ids as $question_id) {
            #迭代查出每个题目的详细数据
            $question_table = Constant::TABLE_SHUATI_QUESTION;
            $source_table   = Constant::TABLE_SHUATI_QUESTION_SOURCE;
            $str_fields = $question_table. '.id AS question_id,multiOpts AS multi_answer,
                           answer,question,'. $question_table .'.subjectId AS subject_id,briefName AS tag,
                           materialId AS article_id';
            $arr_join = array($source_table => $question_table.'.sourceId='.$source_table.'.id');
            $arr_where = array($question_table.'.id' => $question_id, 'status' => 2);
            $limit = array('1');
            $question_info = $this->common_question->get_question_list($str_fields, $arr_join, $arr_where, '', $limit);
            $option_info = $this->common_question->get_question_options($question_id);
            $option = array();
            foreach($option_info as $val) {
                $option[] = array('key' => $val['optContent']);
            }
            $question_info['option'] = $option;
            $has_collected = $this->db->query('SELECT id FROM '. Constant::TABLE_SHUATI_USER_FAVORITES .' WHERE userId='. $user_id .' AND qId='. $question_id .' LIMIT 1')->num_rows();
            $question_info['is_collect'] = $has_collected > 0 ? 1 : 0;
            $question_info['is_article'] = $question_info['article_id'] ? 1 : 0;#材料题需要将is_article设置为1
            if(!empty($question_info['article_id']) && $contain_material) {
                #如果是材料题并且要求题目中包含材料信息
                $material_info = $this->common_question->get_material_list('title, content', array(), array('id' => $question_info['article_id']), '', array(1));
                $question_info['article_info']['article_id'] = $question_info['article_id'];
                $question_info['article_info']['title'] = $material_info['title'];
                $question_info['article_info']['content'] = $material_info['content'];
            }
            $question_arr[] = $question_info;
        }
        
        return $question_arr;
    }
    
    /**
     * 组装材料题的响应数据
     * @param int $question_ids 题目Id
     * @param int $user_id      用户Id
     * @param int $material_id  材料Id
     */
    public function build_material_question_response($question_ids, $user_id, $material_id)
    {
        #查询出材料的title和content
        $material_question_info = $this->common_question->get_material_list('id AS article_id, title, content', array(), array('id' => $material_id), '', array(1));
        if($material_question_info) {
            $material_question_info['is_article'] = 1;
            $material_question_info['article_id'] = $material_id;
            foreach($question_ids as $question_id) {
                $question_table = Constant::TABLE_SHUATI_QUESTION;
                $source_table   = Constant::TABLE_SHUATI_QUESTION_SOURCE;
                $str_fields = $question_table. '.id as question_id,multiOpts as multi_answer,'. $question_table .'.subjectId as subject_id,
                              question,answer,'.$source_table.'.briefName as tag';
                $arr_join = array($source_table => $question_table.'.sourceId='.$source_table.'.id');
                $arr_where = array($question_table.'.id' => $question_id);
                $limit = array(1);
                $question_info = $this->common_question->get_question_list($str_fields, $arr_join, $arr_where, '', $limit);
                $option_info = $this->common_question->get_question_options($question_id);
                $option = array();
                foreach($option_info as $val) {
                    $option[] = array('key' => $val['optContent']);
                }
                $question_info['option'] = $option;
                $has_collected = $this->db->query('SELECT id FROM '. Constant::TABLE_SHUATI_USER_FAVORITES .' WHERE userId='. $user_id .' AND qId='. $question_id .' LIMIT 1')->num_rows();
                $question_info['is_collect'] = $has_collected > 0 ? 1 : 0;
                $material_question_info['question_info'][] = $question_info;
            }
        }
        
        return is_array($material_question_info) ? $material_question_info : array();
    }
    
    /**
     * 添加用户做题记录
     * @param type $insert_data
     */
    public function insert_user_answer_log($insert_data)
    {
        $this->db->insert(Constant::TABLE_SHUATI_USER_ANSWER_LOG, $insert_data);
        $insert_id = $this->db->insert_id();
        
        return $insert_id;
    }
    
    /**
     * 将用户某学科下已经做过的题目记录到redis
     * @param int $user_id 用户Id
     * @param int $knowledge_id 知识点Id
     * @param int $question_id  题目Id
     */
    public function cache_subject_answered_questions($user_id, $subject_id, $question_id)
    {
        $this->redis->connect('shuati');
        $cache_key = 'user_'. $user_id .'_subject_' . $subject_id .'_answered_questions';
        $this->cache->redis->lpush($cache_key, $question_id);
    }
    
    /**
     * 将用户某学科下已经做过的阅读材料的Id记录到redis
     * @param int $user_id 用户Id
     * @param int $subject_id 学科Id
     * @param int $material_id  材料Id
     */
    public function cache_subject_answered_materials($user_id, $subject_id, $material_id)
    {
        $this->redis->connect('shuati');
        $cache_key = 'user_'. $user_id .'_subject_' . $subject_id .'_answered_materials';
        $this->cache->redis->lpush($cache_key, $material_id);
    }
    
    /**
     * 组装好试题解析中需要的数据返回给客户端
     * @param mix $question_id_list 题目Id
     * @param int $user_id 用户Id
     */
    public function build_question_analysis($question_id_list, $user_id)
    {
        if(!is_array($question_id_list)) $question_id_list = array($question_id_list);
        $arr_analysis = array();
        foreach($question_id_list as $key => $question_id) {
            #试题解析
            $analysis = $this->common_question->get_question_list('analysis', array(), array('id' => $question_id), '', array(1));
            if(empty($analysis['analysis'])) continue;
            $arr_analysis[$key]['question_id'] = $question_id;
            $arr_analysis[$key]['resolve'] = $analysis['analysis'];
            #用户选择的答案
            $user_selected = $this->common_user->get_user_answer_log('selected', array(), array('qId' => $question_id, 'userId' => $user_id), 'id desc', array(1));
            $arr_analysis[$key]['selected'] = strval($user_selected['selected']);
            $arr_analysis[$key][statistics] = $this->get_question_statistics($question_id);
            $this->load->model('shuati/knowledge_model');#这里用下knowledge_model里的方法
            $knowledge_info = $this->knowledge_model->get_question_relevant_knowledge($question_id);
            $arr_knowledge_name = array();
            foreach($knowledge_info as $val) {
                $arr_knowledge_name[] = $val['name'];
            }
            $arr_analysis[$key]['relative_knowledge'] = join(',', $arr_knowledge_name);
        }
        
        return $arr_analysis;
    }
    
    /**
     * 获取题目的统计信息（被做的次数、正确率、易错项）
     * @param int $question_id
     */
    public function get_question_statistics($question_id)
    {
        $stat = $this->common_user->get_user_answer_log('count(*) as total', array(), array('qId' => $question_id), '', array(1));
        $total_num = $stat['total'];
        $stat = $this->common_user->get_user_answer_log('count(*) as right_num', array(), array('qId' => $question_id, 'isCorrect' => 1), '', array(1));
        $right_num = $stat['right_num'];
        $right_rate = $right_num == 0 ? 0 : round($right_num / $total_num, 2) * 100;
        #算出题目的易错项
        $stat = $this->common_user->get_user_answer_log('selected as frequent_error, count(id) as num', array(), array('qId' => $question_id, 'isCorrect' => 0), 'num desc', array(1), array('selected'));
        $frequent_error = strval($stat['frequent_error']);
        $str_return = "本题总共作答". $total_num ."次\n正确率 ". $right_rate;"%\n易错项为".$frequentError['selected'];
        $str_return .= $frequent_error ? "%\n易错项为". $frequent_error : '';
        
        return $str_return;
    }
    
    /**
     * 获取用户做过的题目历史数据
     * @param mix $question_id_list 题目Id
     * @param int $user_id 用户Id
     */
    public function get_user_question_history($question_id_list, $user_id)
    {
        if(!is_array($question_id_list)) $question_id_list = array($question_id_list);
        $arr_question = array();
        foreach($question_id_list as $question_id) {
            #题目的基本信息
            $question_base = $this->build_question_response($question_id, $user_id, true);
            if(!$question_base) continue;
            #题目解析和统计数据相关的数据
            $question_analysis = $this->build_question_analysis($question_id, $user_id);
            $history_analysis['resolve_info'] = $question_analysis[0];
            $arr_question[] = array_merge($question_base[0], $history_analysis);
        }
        
        return $arr_question;
    }
    
    /**
     * 添加题目返回
     * @param int $user_id 用户Id
     * @param int $name 昵称
     * @param int $subject_id 学科Id
     * @param int $question_id 题目Id
     * @param int $material_id 材料Id
     * @param string $phone 电话号码
     * @param type $content 反馈内容
     * @param int  $app_type App类型 1.初中 2.中考 3.高中
     */
    public function add_question_feedback($user_id, $name, $subject_id, $question_id, $material_id, $phone, $content, $app_type)
    {
        $feedback_data = array(
            'userId' => $user_id, 'name' => $name, 'subjectId' => $subject_id, 'materialId' => $material_id, 'qId' => $question_id,
            'phoneNumber' => $phone, 'content' => $content, 'appType' => $app_type, 'inputTime' => date('Y-m-d H:i:s')
        );
        $this->db->insert(Constant::TABLE_SHUATI_QUESTION_FEEDBACK, $feedback_data);
        return $this->db->insert_id() ? true : false;
    }
}