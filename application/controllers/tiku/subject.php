<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
class Subject extends MY_Controller
{
    public function __construct() {
        parent::__construct();
        $this->load->model('tiku/subject_model');
        $this->load->model('tiku/knowledge_model');
        $this->load->model('tiku/user_model');
        $this->load->model('login/session_model');
        $this->load->model('study/tiku_model');
        $this->load->library('Tiku');#题库的公共类库
    }
        
    /*
     * 根据session_id获取用户的uid、昵称
     * 参数：session_id
     */
    protected function checkUser($session_id = '')
    {
        $session_id = $this->input->post('session_id');
    	$api_type = Constant::API_TYPE_TIKU;
    	$userInfo = $this->session_model->get_api_session($session_id,$api_type,"");
    	if(isset($userInfo['session_id']) && $userInfo['session_id']!='')
    	{
            return $userInfo;
    	}else{
 			echo json_encode(array('response_status'=>'error','response_error_code'=>'10033','response_error_message'=>'您的账号在另外一台设备登陆了，请重新登陆。'));exit;
    	}
    }
    
    /**
     * 获取用户的学科数据
     * @param 
     */
    //http://api.tizi.net/index.php/tiku/subject/?action=getSubjects&a=1&dataType=array
    public function getSubjects()
    {
        $userInfo = $this->checkUser();#验证用户
        $userId = $userInfo['user_id'];
        $subjectType= $this->tiku_model->getUserSubjectType($userId);
        $subjectArr = $this->subject_model->getSubjects($subjectType);
        if($subjectArr && is_array($subjectArr)) {
            $response = $this->tiku->formatResponse(1, $subjectArr);
        } else {
            $response = $this->tiku->formatResponse(0, '', 10005, '获取学科列表失败');
        }
        
        echo json_encode($response);
    }
    
    /**
     * 学科首页接口
     */
    public function getUserSubjectIndex()
    {
        $userInfo = $this->checkUser();#验证用户
        $userId = $userInfo['user_id'];
        $responseData = array();
        #首先获取用户的基本数据
        $userBaseInfo = $this->tiku_model->getUserBaseInfo($userId);
        $locationId = $userBaseInfo['locationId'];
        $subjectId  = $this->input->post('subject_id');
        if(!$locationId) {
            $responseData = $this->tiku->formatResponse(0, array(), 10034, '无效的用户地区信息,请先设置地区学科分类');
        }
        $paramArr= array(
            'userId' => $userId,
            'locationId' => $locationId,
            'subjectId' => $subjectId,
            );
        #获取用户该学科预测分、打败用户百分比这些数据
        $userSubjectInfo = $this->subject_model->getUserSubjectInfo($paramArr);
        if(empty($userSubjectInfo)) {
            #新用户没有学科数据默认都时0
            $userSubjectInfo = array('prePoints' => 0.00, 'beatOthers' => 0.00);
        }
        #获取知识点树
        $knowledgeTree = $this->knowledge_model->getUserKnowledgeTree($paramArr);
        
        if($knowledgeTree && $userBaseInfo && $userSubjectInfo) {
            $responseData['user_info']['experience'] = round($userBaseInfo['experience']);
            $responseData['user_info']['forecast_score'] = $userSubjectInfo['prePoints'];
            $responseData['user_info']['beat'] = $userSubjectInfo['beatOthers'];
            $responseData['user_info']['pet_id'] = $userBaseInfo['petId'];
            $petStatus = $this->user_model->getPetMood($userId);
            $responseData['user_info']['pet_status'] = $petStatus;
            $responseData['knowledge_info'] = $knowledgeTree;
            $responseData = $this->tiku->formatResponse(1, $responseData);
        } else {
            $responseData = $this->tiku->formatResponse(0, array(), 10006, '获取用户学科概况失败');
        }
        
        echo  json_encode($responseData);
    }
    
    
    /**
     * 获取题库所有地区对应的学科和知识点数据组成的列表
     * 此方法只在初始化APP的配置文件时用到,所有不用担心效率问题
     */
    public function getAllRegionSubjectDetail()
    {
        $this->checkUser();
        $regionList = $this->user_model->getLocationList();
        $catalogDetail = array();
        $subjectTypeArr = array(1 => '理科', 2 => '文科');
        foreach($regionList as $region) {
            foreach($subjectTypeArr as $subjectType => $typeName) {
                $arr = array();
                $arr['region_catalog_id'] = $region['id'] . '_' . $subjectType;
                $arr['reguon_catalog_arr'] = $region['name'] . '(' . $typeName . ')';
                $subjectArr = $this->subject_model->getSubjects($subjectType);
                foreach($subjectArr as $subject) {
                    $subjectInfo = array();
                    $subjectInfo['subject_id'] = $subject['subject_id'];
                    $subjectInfo['name'] = $subject['name'];
                    $knowledgeArr = $this->knowledge_model->getKnowledgeInLocation($subject['subject_id'], $region['id']);
                    $sortKId = $sortSequence = array();
                    foreach($knowledgeArr as $val) {
                        $sortKId[] = $val['kId'];
                        $sortSequence[] = $val['sequence'];
                    }
                    #知识点数据按kId正序排列
                    array_multisort($sortSequence, SORT_DESC, $sortKId, SORT_ASC, $knowledgeArr);
                    $knowledgeTree = array();
                    foreach($knowledgeArr as $val) {
                        if($val['parentId'] != 0) {
                            #二级知识点归纳进上级知识点的数组中
                            $knowledgeTree[$val['parentId']]['sub_knowledge'][] = $val;
                        } else {
                            $knowledgeTree[$val['kId']] = isset($knowledgeTree[$val['kId']]) ? $knowledgeTree[$val['kId']] : array();
                            $knowledgeTree[$val['kId']] = array_merge($knowledgeTree[$val['kId']], $val);
                        }
                    }
                    $subjectInfo['knowledge_info'] = array_values($knowledgeTree);
                    $arr['subject_catalog_info'][] = $subjectInfo;
                }
                $catalogDetail['region_catalog_info'][] = $arr;
            }
        }
        $responseData = $this->tiku->formatResponse(1, $catalogDetail);
        
        echo json_encode($responseData);die;
    }
}
