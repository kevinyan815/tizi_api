<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
require_once APPPATH.'core/Shuati_Model.php';
/**
 * 爱刷题测试model
 */
class Test_Model extends Shuati_Model
{
    public function __construct($database = 'tiku') {
        parent::__construct($database);
        $this->load->model('common/shuati/common_question');
    }
    
    public function get_question_detail($question_id)
    {
        #获取题目基本信息
        $question = $this->common_question->get_question_basic_info($question_id);
        #选项信息
        $question_options = $this->common_question->get_question_options($question_id);
        $option = array();
        foreach ($question_options as $key => $val) {
            $arr_tmp = array('key' => $val['optContent']);
            $option[] = $arr_tmp;
        }
        $question['option'] = $option;
        #材料数据都放到article_info下
        if($question['title'] && $question['content']) {
            #把材料数据都放到article_info下
            $question['article_info']['title'] = $question['title'];
            $question['article_info']['content'] = $question['content'];
            $question['article_info']['article_id'] = $question['article_id'];
            $question['is_article'] = 1;
        }
        #相关考点
        $knowledge_arr = $this->common_question->get_question_relevant_knowledge($question_id);
        $relevant_knowledge = array();
        foreach($knowledge_arr as $val) {
            $relevant_knowledge[] = $val['name'];
        }
        $question['resolve_info']['relative_knowledge'] = implode(',', $relevant_knowledge);
        $question['resolve_info']['resolve'] = $question['resolve'];
        $question['resolve_info']['selected'] = 'A';
        $question['resolve_info']['statistics'] = "全站统计，暂无";
        $question['resolve_info']['question_id'] = $question_id;

        return $question;
    }
}