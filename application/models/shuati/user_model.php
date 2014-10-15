<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
require_once APPPATH.'core/Shuati_Model.php';
/**
 * 爱刷题,用户Model
 */
class User_Model extends Shuati_Model
{
    public function __construct($database = 'tiku')
    {
        parent::__construct($database);
        $this->load->model('common/shuati/common_user');
        $this->load->model('common/shuati/common_question');
        $this->load->model('redis/redis_model', 'redis');
    }
    
    /**
     * 获取用户当前宠物的情绪,主要根据用户上次闯关成功的时间来计算.
     * @param int $user_id 用户Id
     * @param string $app_name App名称
     * @return int 1:代表宠物高兴,2:宠物饥饿,3:宠物冷淡
     */
    public function get_pet_mood($user_id, $app_name)
    {
        #取出用户统计缓存 缓存中last_success_time是上一次闯关成功的时间, last_practice_time 是上一次闯关的时间
        $user_stat_cache = $this->get_user_stat_cache($user_id, $app_name, array('last_success_time', 'last_practice_time'));
        if(empty($user_stat_cache['last_success_time'])) {
            #如果闯关没有成功过,查出用户第一次练习的时间
            if(empty($user_stat_cache['last_practice_time'])) {
                #用户没有做过题,宠物状态默认为高兴
                return 1;
            } else {
                $date = $user_stat_cache['last_practice_time'];
            }
        } else {
            $date = $user_stat_cache['last_success_time'];
        }
        $time_interval = time() - $date;
        if($time_interval <= 2 * 86400) {
            return 1;
        } else if ($time_interval < 5 * 86400){
            return 2;
        } else {
            return 3;
        }
    }
    
    /**
     * 用户基本信息
     * @param $user_id 用户Id
     * @param $app_name APP名称
     */
    public function get_user_data($user_id, $app_name)
    {
        $user_data = $this->common_user->get_user_data_from_db($user_id);
        $user_data['pet_status'] = $this->get_pet_mood($user_id, $app_name);
        
        return $user_data;
    }
    
    /**
     * 检查用户是否存在
     * @param int $user_id 用户Id
     */
    public function check_user_existence($user_id)
    {
        $result = $this->common_user->get_user_data_from_db($user_id);
        
        return !empty($result) ? true : false;
    }
    
    /**
     * 获取用户主页上要用的数据
     * 查看自己的主页是user_id等于my_id, 查看其他人的主页是user_id是被查看用户的用户Id
     * @param int $user_id 
     * @param int $my_id 
     * @param string $app_name App名称
     */
    public function user_home_page($user_id, $my_id, $app_name)
    {
        $home_page_data = $user_info = $knowledge_arr = array();
        if($user_id != $my_id) {
            #查看其他人的主页时加上是否是好友关系的字段
            $user_info['is_follow'] = $this->common_user->check_friendship($my_id, $user_id) ? 1 : 0;
        }
        $user_data = $this->user_model->get_user_data($user_id, $app_name);
        if(empty($user_data)) {
            return $home_page_data;
        }
        $user_info['user_id'] = $user_data['user_id'];
        $user_info['nick_name'] = $user_data['nick_name'];
        $user_info['experience'] = round($user_data['experience']);
        $user_info['pet_id'] = $user_data['pet_id'];
        $user_info['pet_status'] = $user_data['pet_status'];
        $row = $this->common_user->get_user_relation('count(id) as total_friend', array('userId' => $user_id), '', array(1));
        $user_info['total_friend'] = empty($row['total_friend']) ? 1 : $row['total_friend'];#朋友数
        $user_info['rank'] = $this->common_user->get_my_rank_in_friends($user_id);#我在好友中的排名
        $user_stat_data = $this->get_user_stat_cache($user_id, $app_name);
        $user_info['finish'] = intval($user_stat_data['answer_question_nums']);
        $right_question_nums = intval($user_stat_data['right_question_nums']);
        $user_info['right_rate'] = (empty($user_info['finish']) || empty($right_question_nums)) ? 0 : round($right_question_nums / $user_info['finish'], 2);
        $user_info['total_use'] = intval($user_stat_data['days_in_use']);#用户使用App的天数
        if(!empty($user_stat_data['promoted_knowledge'])) {
            #返回用户等级大于0的一级知识点
            $knowledge_id_list = json_decode($user_stat_data['promoted_knowledge'], true);
            $this->load->model('shuati/knowledge_model');
            $subject_arr = Constant::$shuati_subjects;
            $knowledge_arr = $sort_arr = array();
            foreach($knowledge_id_list as $key => $knowledge_id) {
                $knowledge_info = $this->knowledge_model->get_user_knowledge_cache($user_id, $knowledge_id);
                $knowledge_arr[$key]['name'] = strval($knowledge_info['name']);
                $knowledge_arr[$key]['knowledge_id'] = $knowledge_id;
                $knowledge_arr[$key]['level'] = intval($knowledge_info['kLevel']);
                $knowledge_arr[$key]['max_level'] = 6;#一级知识点的最大等级固定,都是6级
                $knowledge_arr[$key]['subject_name'] = strval($subject_arr[$knowledge_info['subjectId']]);
                $sort_arr[] = $knowledge_info['kLevel'];
            }
            array_multisort($sort_arr, SORT_DESC, $knowledge_arr);
        }
        $home_page_data['user_info'] = $user_info;
        $home_page_data['knowledge_info'] = $knowledge_arr;
        
        return $home_page_data;
    }
    
    /**
     * 添加用户练习记录
     * @param array $practice_data 要写入的练习数据
     * @return int  $practice_log  插入后生成的练习Id
     */
    public function insert_user_practice_log($practice_data)
    {
        $this->db->insert(Constant::TABLE_SHUATI_USER_PRACTICE_LOG, $practice_data);
        $practice_id = $this->db->insert_id();
        
        return $practice_id;
    }
    
    /**
     * 获取用户的“我的练习"首页上的数据
     * @param int $user_id 用户Id
     * @param int $subject_id 学科Id
     */
    public function get_my_practice_index($user_id, $subject_id)
    {
        $my_practice_data = array();
        if($user_id && $subject_id) {
            #错题数
            $error_count = $this->common_user->get_user_answer_log('COUNT(*) AS error_count', array(), array('userId' => $user_id, 'subjectId' => $subject_id, 'isCorrect' => 0), '', array(1));
            $my_practice_data['error_count'] = $error_count['error_count'];
            #收藏数
            $favorite = $this->common_user->get_user_favorites('COUNT(*) AS collect_count', array(), array('userId' => $user_id, 'subjectId' => $subject_id), '', array(1));
            $my_practice_data['collect_count'] = $favorite['collect_count'];
            #练习数
            $practice = $this->common_user->get_user_practice_log('COUNT(*) AS practice_count', array(), array('userId' => $user_id, 'subjectId' => $subject_id), '', array(1));
            $my_practice_data['practice_count'] = $practice['practice_count'];
            #当前学科下的刷题数
            $total_question = $this->common_user->get_user_answer_log('COUNT(*) AS total_question', array(), array('userId' => $user_id, 'subjectId' => $subject_id), '', array(1));
            $my_practice_data['total_question'] = $total_question['total_question'];
            $correct_num = $my_practice_data['total_question'] - $my_practice_data['error_count'];
            $correct_rate =  $my_practice_data['total_question'] ? $correct_num / $my_practice_data['total_question'] : 0;
            $my_practice_data['correct_rate'] = sprintf('%4.2f', $correct_rate);
        }
        
        return $my_practice_data;
    }
    
    /**
     * 更新用户的经验值
     * @param array $user_data 用户数据
     */
    public function update_experience($user_data)
    {
        $current_exp = $this->common_user->get_user_data_from_db($user_data['user_id']);
        $new_exp  = $current_exp['experience'] + $user_data['experience'];
        $arr_where = array('user_id' => $user_data['user_id']);
        $this->common_user->update_shuati_user_data(array('exp' => $new_exp), $arr_where);#更新user_data表里的用户经验
        
        return $new_exp;
    }
    
    /**
     * 更新redis里的用户统计信息缓存
     * @param array  $cache_data 要更新的缓存数据
     * @param int    $user_id    用户Id
     * @param string $app_name   App名称
     */
    public function update_user_stat_cache(array $cache_data, $user_id, $app_name)
    {
        $cache_fields = array(
            'promoted_knowledge', 'answer_question_nums', 'right_question_nums', 'last_practice_time', 'last_success_time', 'days_in_use'
        );
        $user_cache_key = $app_name . '_user_' .$user_id .'_statistics';
        $this->redis->connect('shuati');
        foreach($cache_data as $field => $val) {
            if(in_array($field, $cache_fields)) {
                if($field == 'promoted_knowledge') {
                    $old_knowledge_str = $this->cache->redis->hget($user_cache_key, $field);
                    $old_knowledge_arr = json_decode($old_knowledge_str, true);
                    $old_knowledge_arr = empty($old_knowledge_arr) ? array() : $old_knowledge_arr;
                    $new_knowledge_arr = array_unique(array_merge($old_knowledge_arr, $val));
                    $this->cache->redis->hset($user_cache_key, $field, json_encode($new_knowledge_arr));
                } else if ($field == 'days_in_use') {
                    #更新用户的使用天数,这个一定要在更新last_practice_time之前来更新
                    $last_practice_time = $this->cache->redis->hget($user_cache_key, 'last_practice_time');
                    if(!$last_practice_time || date('Y-m-d', $last_practice_time) < date('Y-m-d')) {
                        $this->cache->redis->hincrby($user_cache_key, $field, 1);
                    }
                } else if ($field == 'answer_question_nums' || $field == 'right_question_nums') {
                    $this->cache->redis->hincrby($user_cache_key, $field, $val);
                } else {
                    $this->cache->redis->hset($user_cache_key, $field, $val); 
                }
            }
        }
    }
    
    /**
     * 获取用户统计信息缓存里的字段
     * @param mix $fields 要获取的字段
     * @param int $user_id 用户Id
     * @param string $app_name App名称
     */
    public function get_user_stat_cache($user_id, $app_name, $field = array())
    {
        $user_cache_key = $app_name . '_user_' .$user_id .'_statistics';
        $this->redis->connect('shuati');
        if(is_string($field)) {
            $cache_data = $this->cache->redis->hget($user_cache_key, $field);
        } else if(is_array($field) && !empty($field)) {
            $cache_data = $this->cache->redis->hmget($user_cache_key, $field);
        } else if(empty($field)) {
            $cache_data = $this->cache->hgetall($user_cache_key);
        }
        
        return $cache_data;
    }


    /**
     * 添加用户收藏
     * @param int $user_id 用户Id
     * @param int $subject_id 学科Id
     * @param mix $quesiton_id_list
     */
    public function add_user_favorite($user_id, $subject_id, $question_id_list)
    {
        if(!is_array($question_id_list)) $question_id_list = array($question_id_list);
        foreach($question_id_list as $question_id) {
            $date = date('Y-m-d');
            if(!$this->check_collected($user_id, $question_id)) {
                $insert_data = array('userId' => $user_id, 'subjectId' => $subject_id, 'qId' => $question_id, 'date' => $date, 'time' => time());
                $this->db->insert(Constant::TABLE_SHUATI_USER_FAVORITES, $insert_data);
            } else {
                $update_data = array('date' => $date, 'time' => time());
                $arr_where   = array('userId' => $user_id, 'qId' => $question_id);
                $this->db->update(Constant::TABLE_SHUATI_USER_FAVORITES, $update_data, $arr_where);
            }
        }
    }
    
    /**
     * 删除用户收藏
     * @param int $user_id 用户Id
     * @param int $subject_id 学科Id
     * @param int $question_id 题目Id
     */
    public function del_user_favorite($user_id, $subject_id, $question_id) {
        $arr_where = array('userId' => $user_id, 'subjectId' => $subject_id, 'qId' => $question_id);
        $this->db->delete(Constant::TABLE_SHUATI_USER_FAVORITES, $arr_where);
    }
    
    /**
     * 检查用户是否已经收藏某个题目
     * @param int $user_id 用户Id
     * @param int $question_id 题目Id
     */
    public function check_collected($user_id, $question_id)
    {
        $str_fields = 'id';
        $arr_where  = array('userId' => $user_id, 'qId' => $question_id);
        $row = $this->common_user->get_user_favorites($str_fields, array(), $arr_where, '', array(1));
        
        return empty($row['id']) ? false : true;
    }
    
    /**
     * 获取错题本列表
     * @param int $user_id 用户Id
     * @param int $subject_id 学科Id
     * @param int $offset  偏移量
     * @param int $num     数量
     * @return array
     */
    public function get_error_notebook($user_id, $subject_id, $offset, $num)
    {
        $str_fields = 'COUNT(id) AS question_nums,date';
        $arr_where  = array('userId' => $user_id, 'subjectId' => $subject_id, 'isCorrect' => 0);
        $group_by   = array('date');
        $limit      = array($num, $offset);
        $data = $this->common_user->get_user_answer_log($str_fields, array(), $arr_where, 'date desc', $limit, $group_by);
        $error_notebook = array();
        if($data) {
            foreach($data as $key => $val) {
                $error_notebook[$key]['count'] = $val['question_nums'];
                $error_notebook[$key]['day']   = strtotime($val['date']);
            }
        }
        return $error_notebook;
    }
    
    /**
     * 获取我的收藏列表
     * @param int $user_id 用户Id
     * @param int $subject_id 学科Id
     * @param int $offset  偏移量
     * @param int $num     数量
     * @return array
     */
    public function get_my_favorites($user_id, $subject_id, $offset, $num)
    {
        $str_fields = 'COUNT(id) AS question_nums,date';
        $arr_where  = array('userId' => $user_id, 'subjectId' => $subject_id);
        $group_by   = array('date');
        $limit      = array($num, $offset);
        $data = $this->common_user->get_user_favorites($str_fields, array(), $arr_where, 'date desc', $limit, $group_by);
        $error_notebook = array();
        if($data) {
            foreach($data as $key => $val) {
                $error_notebook[$key]['count'] = $val['question_nums'];
                $error_notebook[$key]['day']   = strtotime($val['date']);
            }
        }
        return $error_notebook;
    }
    
    /**
     * 获取我的练习历史
     * @param int $user_id 用户Id
     * @param int $subject_id 学科Id
     * @param int $offset  偏移量
     * @param int $num     数量
     * @return array
     */
    public function get_my_practice_history($user_id, $subject_id, $offset, $num)
    {
        $str_fields = 'id AS practice_id, practiceName AS topic,(correctNums + incorrectNums) AS total_count, incorrectNums AS error_count, result AS is_pass, date';
        $arr_where  = array('userId' => $user_id, 'subjectId' => $subject_id);
        $limit      = array($num, $offset);
        $practice_history = $this->common_user->get_user_practice_log($str_fields, array(), $arr_where, 'date desc', $limit);
        if(is_array($practice_history)) {
            foreach($practice_history as &$val) {
                $val['time'] = strtotime($val['date']);
            }
            unset($val);#断开引用
        }
        return $practice_history;
    }
    
    /**
     * 获取用户的答题卡
     * @param int $user_id 用户Id
     * @param int $subject_id 学科Id
     * @param int $practice_id 练习Id
     * @param int $time 时间戳
     * @param int $type 答题卡类型 1表示错题本, 2表示收藏夹, 3表示练习记录
     */
    public function get_user_answer_card($user_id, $subject_id, $practice_id, $time, $type)
    {
        switch ($type) {
            case 1:
                $arr_where = array('userId' => $user_id, 'subjectId' => $subject_id, 'date' => date('Y-m-d', $time), 'isCorrect' => 0);
                $data = $this->common_user->get_user_answer_log('qId AS `question_id`, isCorrect AS `right`', array(), $arr_where, 'id asc');
                break;
            case 2:
                $arr_where = array('userId' => $user_id, 'subjectId' => $subject_id, 'date' => date('Y-m-d', $time));
                $data = $this->common_user->get_user_favorites('qId as `question_id`', array(), $arr_where, 'id asc');
                foreach($data as &$val) {
                    #收藏夹的题目要加入用户是否回答正确
                    $row = $this->common_user->get_user_answer_log('isCorrect AS `right`', array(), array('qId' => $val['question_id'], 'userId' => $user_id), 'id desc', array(1));
                    $val['right'] = $row['right'];
                }
                unset($val);#断开对$val的引用
                break;
            case 3:
                $arr_where = array('practiceId' => $practice_id);
                $data = $this->common_user->get_user_answer_log('qId AS `question_id`, isCorrect AS `right`', array(), $arr_where, 'id asc');
                break;
        }
        if(is_array($data)) {
            foreach($data as &$val) {
                $row = $this->common_question->get_question_list('materialId', array(), array('id' => $val['question_id']), '', array(1));
                $val['has_article'] = empty($row['materialId']) ? 0 : 1;
            }
            unset($val);#断开对$val的引用
        }
        
        return $data;
    }
    
    /**
     * 更换用户的宠物
     * @param int $user_id 用户Id
     * @param int $pet_id  宠物Id
     */
    public function change_user_pet($user_id, $pet_id)
    {
        $update_data = array('pet_id' => $pet_id);
        $arr_where = array('user_id' => $user_id);
        $result = $this->common_user->update_shuati_user_data($update_data, $arr_where);
        return $result;
    }
    
    /**
     * 记录用户放弃做题时的数据
     * @param int $user_id
     * @param int $subject_id
     * @param array $user_answer
     * @param array $right_detail
     * @param int   $app_type App类型 1.初中 2.中考 3.高中
     */
    public function log_give_up($user_id, $subject_id, $user_answer = array(), $right_detail = array(), $app_type)
    {
        $time = date('Y-m-d H:i:s');
        foreach ($user_answer as $question_id => $selected) {
            $insert_data = array();
            $insert_data['qId'] = $question_id;
            $insert_data['selected'] = $selected;
            $insert_data['subjectId'] = $subject_id;
            $insert_data['isCorrect'] = $right_detail[$question_id];
            $insert_data['userId'] = $user_id;
            $insert_data['cancelTime'] = $time;
            $insert_data['appType'] = $app_type;
            $this->db->insert(Constant::TABLE_SHUATI_GIVE_UP, $insert_data);
        }
        
        return true;
    }
}