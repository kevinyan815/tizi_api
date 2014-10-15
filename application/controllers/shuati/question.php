<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once APPPATH.'core/Shuati_Controller.php';
/**
 * 爱刷题题目控制器
 */
class Question extends Shuati_Controller
{
    /**
     * 每次出题数目为10的科目Id
     */
    private $_ten_question_subjects = array(13, 23, 33);
    
    /**
     * 题目类型是材料的知识点
     * key是知识点Id,value为知识点名
     */
    private $_material_knowledge = array(
        '177' => '初中同步阅读理解', '178' => '初一阅读', '179' => '初二阅读',
        '180' => '初中同步完形填空', '181' => '初一完形', '182' => '初二完形',
        '131' => '中考阅读理解', '132' => '中考完型填空',
        '331' => '高中同步阅读理解', '371' => '高一阅读理解', '372' => '高二阅读理解', '373' => '高一阅读理解7选5', '高二阅读理解7选5',
        '330' => '高中完型填空', '369' => '高一完型填空', '370' => '高二完型填空',
        '104' => '中考古诗文阅读', '105' => '古诗词阅读', '106' => '文言文阅读',
        '107' => '中考现代文阅读', '108' => '记叙类文本阅读', '109' => '议论类文本阅读', '110' => '说明类文本阅读',
        '347' => '高中诗文阅读', '348' => '诗歌鉴赏', '349' => '文言文阅读',
        '350' => '高中现代文阅读', '351' => '论述类文本阅读', '352' => '文学类文本阅读', '353' => '实用类文本阅读',
    );
    
    private $_promoted_knowledge = array();#记录练习中有提升的知识点Id
    private $_subject_points = 0;#学科预测分
    private $_user_experience;

    public function __construct() 
    {
        parent::__construct();
        $this->load->model('shuati/question_model');
        $this->load->model('shuati/knowledge_model');
        $this->load->model('shuati/subject_model');
        $this->load->model('shuati/user_model');
        $this->load->library('Tiku');
    }
    
    /**
     * 根据知识点出题
     */
    public function get_knowledge_question_list()
    {
        $this->_check_user_login();
        $user_id = $this->_user_info['user_id'];
        $subject_id = intval($this->input->post('subject_id'));
        $knowledge_id = intval($this->input->post('knowledge_id'));
        if(empty($user_id) || empty($subject_id) || empty($knowledge_id)) {
            $response = self::format_response(Constant::ERROR, array(), '', '参数错误');
            self::json_output($response);
        }
        $params = array(
            'user_id' => $user_id,
            'subject_id' => $subject_id,
            'knowledge_id' => $knowledge_id
        );
        if(isset($this->_material_knowledge[$knowledge_id])) {
            $question_data = $this->get_material_questions($params);
        } else {
            $question_data = $this->get_normal_questions($params);
        }
        $response = self::format_response(Constant::SUCCESS, $question_data);
        self::json_output($response);
    }
    
    /**
     * 学科快速综合练
     */
    public function get_subject_question_list()
    {
        $this->_check_user_login();
        $user_id = $this->_user_info['user_id'];
        $subject_id = intval($this->input->post('subject_id'));
        if(empty($user_id) || empty($subject_id)) {
            $response = self::format_response(Constant::ERROR, array(), '', '参数错误');
            self::json_output($response);
        }
        $params = array(
            'user_id' => $user_id,
            'subject_id' => $subject_id
        );
        $question_data = $this->get_normal_questions($params);
        $response = self::format_response(Constant::SUCCESS, $question_data);
        self::json_output($response);
    }
    
    /**
     * 获取常规的题目列表
     * @param array $params 参数数组
     *              $params['user_id'] 用户Id
     *              $params['subject_id'] 学科Id
     *              $params['knowledge_id'] 知识点Id
     */
    public function get_normal_questions($params)
    {
        $question_data = array();
        extract($params);
        if(!empty($user_id) && !empty($subject_id)) {
            if(!empty($knowledge_id)) {
                #通过知识点获取知识点下的题目Id和用户已经做过的题目的Id
                $question_id_list = $this->question_model->get_knowledge_question_id_list($knowledge_id);
                $answered_questions = $this->question_model->get_subject_answered_questions($user_id, $subject_id);
                $answered_questions = array_unique($answered_questions);
            } else {
                #通过学科获取学科下的题目Id和用户已经做过的题目的Id
                $question_id_list = $this->question_model->get_subject_question_id_list($subject_id);
                $answered_questions = $this->question_model->get_subject_answered_questions($user_id, $subject_id);
                $answered_questions = array_unique($answered_questions);
            }
            if(empty($question_id_list)) {
                $response = self::format_response(Constant::ERROR, array(), '', '该知识点下还没有题目');
                self::json_output($response);
            }
            
            $question_nums = in_array($subject_id, $this->_ten_question_subjects) ? 10 : 5;
            #求出未被用户答过的题目Id
            $unanswerd_questions = array_diff($question_id_list, $answered_questions);
            if(count($unanswerd_questions) > 0) {
                $question_data['finish_all'] = 0;
                shuffle($unanswerd_questions);
                $question_ids = array_slice($unanswerd_questions, 0, $question_nums);
            } else {
                #题目都被作答过,需要返回finsh_all给客户端
                $question_data['finish_all'] = 1;
                shuffle($question_id_list);
                $question_ids = array_slice($question_id_list, 0, $question_nums);
            }
            if(!empty($question_ids) && is_array($question_ids)) {
                $question_data['question_detail'] = $this->question_model->build_question_response($question_ids, $user_id);
            }
        }
        
        return $question_data;
    }
    
    /**
     * 获取材料的题目列表
     */
    public function get_material_questions($params)
    {
        $material_questoin_data = array();
        extract($params);
        if(!empty($user_id) && !empty($subject_id) && !empty($knowledge_id)) {
            #找出该知识点下的所有材料id
            $material_id_list = $this->question_model->get_knowledge_material_id_list($knowledge_id);
            if(empty($material_id_list)) {
                $response = self::format_response(Constant::ERROR, array(), '', '该知识点下还没有题目');
                self::json_output($response);
            }
            
            #查出用户已经做过的材料,然后求出用户还未做过的材料的Id
            $answered_materials = $this->question_model->get_subject_answered_materials($user_id, $subject_id);
            $unanswered_materails = array_diff($material_id_list, $answered_materials);
            if(count($unanswered_materails) > 0) {
                $material_questoin_data['finish_all'] = 0;
                shuffle($unanswered_materails);
                $material_id = $unanswered_materails[0];
            } else {
                $material_questoin_data['finish_all'] = 1;#知识点下的材料都被做过一遍了,把finish_all置为1返给客户端
                shuffle($material_id_list);
                $material_id = $material_id_list[0];
            }
            #查出该阅读材料下的题目Id
            $question_id_list = $this->question_model->get_material_question_id_list($material_id);
            $material_questoin_data['question_detail'][] = $this->question_model->build_material_question_response($question_id_list, $user_id, $material_id);
        }
        
        return $material_questoin_data;
    }
    
    /**
     * 提交练习
     * 练习提交上来后服务器要写入数据的流程
     * 1. 写入用户练习记录表
     * 2. 拿到练习id后把题目写入用户答题记录表,如果有收藏写入用户收藏表
     * 3. 计算每道题对应的知识点的掌握程度分数和等级
     * 4. 更新个人的做题数和做题经验
     * 
     * 返回个人经验、预测分、和有提高的一级知识点
     */
    public function commit_practice()
    {
        $this->_check_user_login();
        $right_detail_str = trim($this->input->post('right_detail', true));
        $user_answer_str  = trim($this->input->post('user_answer', true));
        $user_collect_str = trim($this->input->post('collect', true));
        $commit_data = array();//提交上来的做题数据都放到这个数组里
        $commit_data['right_count']   = intval($this->input->post('right_count',true));
        $commit_data['wrong_count']   = intval($this->input->post('wrong_count', true));
        $commit_data['experience']    = floatval($this->input->post('experience', true));
        $commit_data['question_num']  = intval($this->input->post('question_num', true));
        $commit_data['practice_name'] = trim($this->input->post('practice_name', true));
        $commit_data['subject_id']    = intval($this->input->post('subject_id', true));
        $commit_data['article_id']    = intval($this->input->post('article_id', true));
        $commit_data['is_pass']       = intval($this->input->post('is_pass', true));
        $commit_data['right_detail']  = $right_detail_str ? $this->tiku->parseAnswerStr($right_detail_str,1) : '';
        $commit_data['user_answer']   = $user_answer_str ? $this->tiku->parseAnswerStr($user_answer_str,2) : '';
        $commit_data['collect']       = $user_collect_str ? $this->tiku->parseAnswerStr($user_collect_str,1) : '';
        $commit_data['app_type']      = intval($this->input->post('app_type', true));
        $commit_data['app_name']      = $this->_get_app_name($commit_data['app_type']);
        $commit_data['user_id']       = $this->_user_info['user_id'];
        if(empty($commit_data['right_detail']) || empty($commit_data['user_answer']) || empty($commit_data['app_name'])) {
            $response = self::format_response(Constant::ERROR, array(), '', '参数错误');
            self::json_output($response);
        }
        if(!empty($commit_data['article_id'])) {
            #如果是材料题将材料Id记录到用户已做材料的list里
            $this->question_model->cache_subject_answered_materials($commit_data['user_id'], $commit_data['subject_id'], $commit_data['article_id']);
        }
        #添加练习记录
        $commit_data['practice_id'] = $this->_add_practice_log($commit_data);
        #记录用户本次练习中做的题目
        $this->_add_answer_log($commit_data);
        #更新用户的知识点掌握情况
        $this->_update_knowledge_stat($commit_data);
        #更新用户的学科统计
        $this->_update_subject_stat($commit_data);
        #更新用户的经验等数据
        $this->_update_user_basic_data($commit_data);
        #添加用户收藏
        $this->_add_user_favorite($commit_data);
        
        #组装返回内容
        $response_data = array();
        $improved_knowledge = array();
        if($this->_promoted_knowledge) {
            foreach(array_unique($this->_promoted_knowledge) as $promoted_knowledge_id) {
                $my_knowledge_info = $this->knowledge_model->get_user_knowledge_cache($commit_data['user_id'], $promoted_knowledge_id);
                $my_knowledge_info['knowledge_id'] = $my_knowledge_info['id'];
                $improved_knowledge[] = $my_knowledge_info;
            }
        }
        $response_data['experience'] = $this->_user_experience;
        $response_data['forcast_score'] = $this->_subject_points;
        $response_data['improve'] = $improved_knowledge;
        
        $response = self::format_response(Constant::SUCCESS, $response_data);
        self::json_output($response);
    }
    
    /**
     * 添加用户练习记录
     * @param array $commit_data 用户提交上来的做题数据
     */
    protected function _add_practice_log($commit_data)
    {
        $practice_data['practiceName'] = $commit_data['practice_name'];
        $practice_data['correctNums']  = $commit_data['right_count'];
        $practice_data['incorrectNums']= $commit_data['wrong_count'];
        $practice_data['exp']    = $commit_data['experience'];
        $practice_data['result'] = $commit_data['is_pass'];
        $practice_data['userId'] = $commit_data['user_id'];
        $practice_data['appType'] = $commit_data['app_type'];
        $practice_data['subjectId'] = $commit_data['subject_id'];
        $practice_data['date'] = date('Y-m-d H:i:s');
        $practice_id = $this->user_model->insert_user_practice_log($practice_data);
        if(!$practice_id) {
            $response = self::format_response(Constant::ERROR, array(), '', '数据提交失败');
            self::json_output($response);
        } else {
            return $practice_id;
        }
    }
    
    /**
     * 添加用户答题记录
     * @param array $commit_data 用户提交上来的做题数据
     */
    protected function _add_answer_log($commit_data)
    {
        $user_answer  = $commit_data['user_answer'];
        $right_detail = $commit_data['right_detail'];
        foreach($user_answer as $question_id => $answer)
        {
            $answer_data['userId'] = $commit_data['user_id'];
            $answer_data['practiceId'] = $commit_data['practice_id'];
            $answer_data['appType'] = $commit_data['app_type'];
            $answer_data['qId'] = $question_id;
            $answer_data['subjectId'] = $commit_data['subject_id'];
            $answer_data['selected'] = $answer;
            $answer_data['isCorrect'] = $right_detail[$question_id];
            $answer_data['date'] = date('Y-m-d');
            $answer_data['time'] = time();
            $insert_id  = $this->question_model->insert_user_answer_log($answer_data);
            if(!$insert_id) {
                log_message('error_tizi', 'It has faild to insert shuati_stu_answer_log', $answer_data);
            } else {
                #做题记录插入数据库成功后,将题目Id添加到统计用户学科已做题目的list里
                $this->question_model->cache_subject_answered_questions($commit_data['user_id'], $commit_data['subject_id'], $question_id);
            }
        }
        
        return true;
    }
    
    /**
     * 更新用户的知识点掌握情况
     * @param array $commit_data 用户提交上来的做题数据
     */
    protected function _update_knowledge_stat($commit_data)
    {
        $user_answer = $commit_data['user_answer'];
        $right_detail = $commit_data['right_detail'];
        $knowledge_set = array();//存放本次练习,题目对应的知识点以及做题结果对知识点统计产生的分数影响
        foreach($user_answer as $question_id => $answer) {
            $filter_arr = array();//存放当前题目已经被统计过的知识点Id
            $variation = $commit_data['question_num'] > 7 ? '0.5' : 1;//出题数大于7,每道题的知识点分数按照0.5算否则按1算
            $variation = $right_detail[$question_id] ? $variation : -$variation;
            #找出题目关联的所有知识点
            $knowledge_arr = $this->knowledge_model->get_question_relevant_knowledge($question_id);
            foreach($knowledge_arr as $knowledge) {
                if(in_array($knowledge['id'], $filter_arr)) {
                    continue;//录题时有重复关联知识点的情况,这里过滤下
                }
                
                if(!isset($knowledge_set[$knowledge['id']])) {
                    $knowledge_set[$knowledge['id']]['score_changed'] = floatval($variation);
                    $knowledge_set[$knowledge['id']]['question_nums'] = 1;
                    $knowledge_set[$knowledge['id']]['grade'] = $knowledge['grade'];
                } else {
                    $knowledge_set[$knowledge['id']]['score_changed'] = $knowledge_set[$knowledge['id']]['score_changed'] + $variation;
                    $knowledge_set[$knowledge['id']]['question_nums']++;
                }
                #记录这个题目已经统计过的知识点Id
                array_push($filter_arr, $knowledge['id']);
            }
        }
        foreach ($knowledge_set as $knowledge_id => $val) {
            #先判断该用户在统计表中是否有记录
            $existence = $this->knowledge_model->check_user_knowledge_existence($commit_data['user_id'], $knowledge_id);
            if(!$existence) {
                $insert_data['userId'] = $commit_data['user_id'];
                $insert_data['kId'] = $knowledge_id;
                $insert_data['kLevel'] = 0;
                $insert_data['score'] = $val['score_changed'] > 0 ? $val['score_changed'] : 0;
                if($insert_data['score'] >=3) {
                    #学霸们第一次练习就有可能把题全做对,知识点一下升两级,所以这里判断下
                    $insert_data['kLevel'] = $insert_data['score'] >= 9 ? 2 : 1;
                    #记录下升级的一级知识点Id
                    $val['grade'] == 1 && array_push($this->_promoted_knowledge, $knowledge_id); 
                } 
                $insert_data['questionNums'] = $val['question_nums'];
                $this->knowledge_model->add_user_knowledge_statistics($insert_data);
                unset($insert_data['userId'], $insert_data['kId']);//redis里不要这两个数据
                $this->knowledge_model->set_user_knowledge_cache($commit_data['user_id'], $knowledge_id, $insert_data);
            } else {
                $result = $this->knowledge_model->update_user_knowledge_statistics($commit_data['user_id'],$commit_data['subject_id'], $knowledge_id, $val['score_changed'], $val['question_nums'], $val['grade']);
                if($result && is_array($result)) {
                    #记录升级的知识点
                    array_push($this->_promoted_knowledge, $result['promoted_knowledge']);
                } else if(!$result){
                    $error_info = array('knowledge_id' => $knowledge_id, 'changed' => $val);
                    log_message('error_tizi', 'encounter errors when update user_knowledge', $error_info);
                }
            }
        }
        
        return true;
    }
    
    /**
     * 更新用户的学科统计数据 还有当前学科做对的题目数和总做题数
     * @param array $commit_data 用户做完题提交上来的数据
     */
    protected function _update_subject_stat($commit_data)
    {
        #如果一级知识点有提高那么需要重新计算用户的学科预测分
        $recalculate = !empty($this->_promoted_knowledge) && is_array($this->_promoted_knowledge) ? true : false;
        $subject_points = $this->subject_model->update_user_subject_stat($commit_data['user_id'], $commit_data['subject_id'], $recalculate);
        $subject_points && $this->_subject_points = $subject_points;
    }
    
    /**
     * 添加用户的题目收藏
     */
    protected function _add_user_favorite($commit_data)
    {
        foreach($commit_data['collect'] as $question_id => $favorite) {
            if($favorite) {
                $question_id_list[] = $question_id;
            }
        }
        if(!empty($question_id_list)) {
            $this->user_model->add_user_favorite($commit_data['user_id'], $commit_data['subject_id'], $question_id_list);
        }
    }

    /**
     * 更新用户的经验等基本信息
     */
    protected function _update_user_basic_data($commit_data)
    {
        #更新经验
        $new_exp = $this->user_model->update_experience($commit_data);
        $this->_user_experience = $new_exp;
        #更新用户基本信息的redis缓存
        $user_cache['answer_question_nums'] = $commit_data['right_count'] + $commit_data['wrong_count'];
        $user_cache['right_question_nums']  = $commit_data['right_count'];
        $user_cache['days_in_use'] = 1;#这个字段一定要在last_practice_time前面
        $user_cache['last_practice_time']   = time();
        !empty($commit_data['is_pass']) && $user_cache['last_success_time'] = time();
        !empty($this->_promoted_knowledge) && $user_cache['promoted_knowledge'] = array_unique($this->_promoted_knowledge);
        $this->user_model->update_user_stat_cache($user_cache, $commit_data['user_id'], $commit_data['app_name']);
    }
    
        
    /**
     * 试题解析
     */
    public function question_analysis()
    {
        $this->_check_user_login();
        $user_id = $this->_user_info['user_id'];
        $question_id_str = trim($this->input->post('question_ids'), ',');
        if(empty($question_id_str)) {
            $response = self::format_response(Constant::ERROR, array(), '', '参数错误');
            self::json_output($response);
        }
        $question_ids = explode(',', $question_id_str);
        $analysis = $this->question_model->build_question_analysis($question_ids, $user_id);
        if(!empty($analysis)) {
            $response = self::format_response(Constant::SUCCESS, $analysis);
        } else {
            $response = self::format_response(Constant::ERROR, array(), '', '请求试题解析失败');
        }
        self::json_output($response);
    }
    
    /**
     * 历史试题
     */
    public function question_history()
    {
        $this->_check_user_login();
        $user_id = $this->_user_info['user_id'];
        $question_id_str = trim($this->input->post('question_ids'), ',');
        if(empty($question_id_str)) {
            $response = self::format_response(Constant::ERROR, array(), '', '参数错误');
            self::json_output($response);
        }
        $question_ids = explode(',', $question_id_str);
        $question_history = $this->question_model->get_user_question_history($question_ids, $user_id);
        if(!empty($question_history)) {
            $response = self::format_response(Constant::SUCCESS, $question_history);
        } else {
            $response = self::format_response(Constant::ERROR, array(), '', '请求题目历史失败');
        }
        self::json_output($response);
    }
    
    /**
     * 题目反馈
     */
    public function feedback()
    {
        $this->_check_user_login();
        $user_id = $this->_user_info['user_id'];
        $name = $this->_user_info['name'];
        $subject_id = intval($this->input->post('subject_id'));
        $question_id = intval($this->input->post('question_id'));
        $material_id = intval($this->input->post('article_id'));
        $phone = trim($this->input->post('phone'));
        $content = trim($this->input->post('content', true, true));
        $app_type = trim($this->input->post('app_type'));
        $app_name = $this->_get_app_name($app_type);
        if(!$subject_id || !$question_id || !$app_name) {
            self::json_output(self::format_response(Constant::ERROR, array(), '', '参数错误'));
        }
        $feedback_ret = $this->question_model->add_question_feedback($user_id, $name, $subject_id, $question_id, $material_id, $phone, $content, $app_type);
        if($feedback_ret) {
            $response = self::format_response(Constant::SUCCESS, array('done' => 1));
        } else {
            $response = self::format_response(Constant::ERROR, array(), '10040', '提交题目反馈失败,请稍后重试');
        }
        
        self::json_output($response);
    }
}