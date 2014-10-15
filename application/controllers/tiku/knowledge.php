<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * @todo 关于移动题库知识点数据的操作都写在这里
 * @copyright  March 24, 2014
 */

class Knowledge extends MY_Controller
{
    public function __construct() {
        parent::__construct();
        
        $this->load->model('tiku/knowledge_model');
        $this->load->model('tiku/user_model');
    }
        
    /**
     * 显示各个地区的知识点树(检查知识点用)
     */
    public function showKnowledgeTree()
    {
        $data['regions'] = $this->user_model->getLocationList();
        $data['subjects'] = array(1 => '语文', 2 => '英语', 3 => '理数', 4 => '文数', 5 => '物理', 6 => '化学', 7 => '生物', 8 => '历史', 9 => '地理', 10 => '政治');
        $knowledgeTree = array();
        if($this->input->get('locationId') && $this->input->get('subjectId')) {
            $subjectId = $this->input->get('subjectId');
            $locationId = $this->input->get('locationId');
            #获取该地区学科下得知识点列表
            $knowledgeList = $this->knowledge_model->getKnowledgeInLocation($subjectId, $locationId);
            $sortKId = $sortSequence = array();
            foreach($knowledgeList as $val) {
                $sortKId[] = $val['kId'];
                $sortSequence[] = $val['sequence'];
            }
            #按知识点Id正序排列
            array_multisort($sortSequence, SORT_DESC, $sortKId, SORT_ASC, $knowledgeList);
            foreach($knowledgeList as $val) {
                if($val['parentId'] != 0) {
                    #二级知识点归纳进上级知识点的数组中
                    $knowledgeTree[$val['parentId']]['sub_knowledge'][] = $val;
                } else {
                    $knowledgeTree[$val['kId']] = isset($knowledgeTree[$val['kId']]) ? $knowledgeTree[$val['kId']] : array();
                    $knowledgeTree[$val['kId']] = array_merge($val, $knowledgeTree[$val['kId']]);
                }
            }
        }
        $data['subjectId'] = isset($subjectId) ? $subjectId : 0;
        $data['locationId'] = isset($locationId) ? $locationId : 0;
        $data['knowledgeTree'] = $knowledgeTree;
        $this->load->view('tiku/inspectKnowledge', $data);
    }
    
    /**
     * 生成知识点的excel
     */
    public function generate_knowledge_excel()
    {
        $data['regions'] = $this->user_model->getLocationList();
        $data['subjects'] = array(1 => '语文', 2 => '英语', 3 => '理数', 4 => '文数', 5 => '物理', 6 => '化学', 7 => '生物', 8 => '历史', 9 => '地理', 10 => '政治');
        $knowledgeTree = array();
        if($this->input->get('subjectId')) {
            $subjectId = $this->input->get('subjectId');
//            if($subjectId && $locationId) {
//                #获取该地区学科下得知识点列表
//                $knowledgeList = $this->knowledge_model->getKnowledgeInLocation($subjectId, $locationId);
//            }
            if($subjectId) {
                $this->db = $this->load->database('tiku', TRUE);
                $sql = "SELECT k.id AS kId, k.name, k.parentId FROM knowledge k WHERE k.subjectId={$subjectId}";
                $knowledgeList = $this->db->query($sql)->result_array();
            }
            $sortCol = array();
            foreach($knowledgeList as $val) {
                $sortCol[] = $val['kId'];
            }
            #按知识点Id正序排列
            array_multisort($sortCol, SORT_ASC, $knowledgeList);
            foreach($knowledgeList as $val) {
                $questionAmount = $this->get_qnums_of_knowledge($val['kId']);
                $val['yilun'] = $questionAmount['yilun'];
                $val['moni']  = $questionAmount['moni'];
                if($val['parentId'] != 0) {
                    #二级知识点归纳进上级知识点的数组中
                    $knowledgeTree[$val['parentId']]['sub_knowledge'][] = $val;
                } else {
                    $knowledgeTree[$val['kId']] = isset($knowledgeTree[$val['kId']]) ? $knowledgeTree[$val['kId']] : array();
                    $knowledgeTree[$val['kId']] = array_merge($val, $knowledgeTree[$val['kId']]);
                }
            }
        }
        
//        mb_convert_variables('GBK', 'UTF-8', $knowledgeTree);
        header("Content-type:application/vnd.ms-excel");
        header("Content-Disposition:attachment;filename={$data['subjects'][$subjectId]}.xls" );
        $excelStr = "一级知识点\t二级知识点\t一轮题\t模拟题\t\n";
        foreach($knowledgeTree as $val) {
            $excelStr .= $val['name'] . "\t\t". $val['yilun']  ."\t". $val['moni'] .  "\t\n";
            if(is_array($val['sub_knowledge'])) {
                foreach($val['sub_knowledge'] as $v) {
                    $excelStr .= "\t" . $v['name'] . "\t" . $v['yilun'] . "\t" . $v['moni'] .  "\t\n";
                }
            }
        }
        $excelStr = iconv("UTF-8", "GBK//IGNORE", $excelStr);
        echo $excelStr;die;
    }
    
    function get_qnums_of_knowledge($kId)
    {
        $sql = "SELECT COUNT(*) as total FROM questions q LEFT JOIN knowledge_question_rel k ON q.id=k.qId 
                WHERE k.kId={$kId}";
        $this->db = $this->load->database('tiku', TRUE);
        $res = $this->db->query($sql)->row_array();
        $total = $res['total'];
        $sql = "SELECT COUNT(*) as yilun FROM questions q LEFT JOIN knowledge_question_rel k ON q.id=k.qId 
                LEFT JOIN question_source s ON q.sourceId=s.id
                WHERE k.kId={$kId} AND s.questionRange=3";
        $res = $this->db->query($sql)->row_array();
        $yilun = $res['yilun'];
        $moni  = $total - $yilun;
        return array('yilun' => $yilun, 'moni' => $moni);
    }
    
    public function download()
    {
        $this->load->view('tiku/download');
    }
}