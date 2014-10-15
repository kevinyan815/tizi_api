<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * 移动题库Question控制器
 */
class Question extends MY_Controller
{
    public function __construct() {
        parent::__construct();
        
        $this->load->model('tiku/question_model');
        $this->load->model('tiku/knowledge_model');
        $this->load->model('tiku/subject_model');
        $this->load->model('tiku/user_model');
        $this->load->model('login/session_model');
        $this->load->library('Tiku');
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
     * 获取做题历史数据
     */
    public function questionHistory()
    {
        $userInfo = $this->checkUser();
        $paramArr = array();
        $paramArr['userId'] = $userInfo['user_id'];
        $paramArr['qIds'] = $this->input->post('question_ids');#题目Id组成的串,以','分割
        
        $questionHistory = $this->question_model->getHistoricQuestions($paramArr);
        if(!$questionHistory) {
            $data = $this->tiku->formatResponse(0, '', 10017, '试题获取失败,请重试');
        } else {
            $data = $this->tiku->formatResponse(1, $questionHistory);
        }
        
        echo json_encode($data);
    }
    
    /*
     * 根据uid，获取出题范围
     * uid		:用户id
     */
    public function getQuestionRange($uid)
    {
    	$result = $this->question_model->getQuestionRange($uid);
    	return $result;
    }
    
    /*
     * 根据知识点id查找知识点名称、学科id
     * kid		:知识点id
     */
    public function getKnowledgeInfo($kid)
    {
    	$result = $this->question_model->getKnowledgeInfo($kid);
    	return $result;
    }
    
    /*
     * 获取题目列表(知识点)
     * kid			:知识点id
     * subject_id	:学科id
     */
    public function getQuestionListByKid()
    {			
    	$kid 		= $this->input->post("knowledge_id",true);		
    	$subject_id = $this->input->post("subject_id",true);
    	$location_id= $this->input->post("location_id",true);
    	if (empty($kid) || empty($subject_id) || empty($location_id)){
    		$response = $this->tiku->formatResponse('0','','','参数错误');
    		echo json_encode($response);exit();
    	}
    	$location_id = explode("_",$location_id);
    	//判断地区id是否有效
    	$flag = $this->checkLocationId($location_id[0]);
    	if (!$flag)
    	{
    		$response = $this->tiku->formatResponse('0','','','无效的地区id');
    		echo json_encode($response);exit();
    	}
    	//判断session_id是否为有效的用户
    	$userInfo = $this->checkUser();
    	//获取出题范围
    	$questionRange = $this->getQuestionRange($userInfo['user_id']);		
    	if (empty($questionRange))
    	{
    		$response = $this->tiku->formatResponse('0','','10038','该用户没有选择出题范围');
    		echo json_encode($response);exit();
    	}
    	//根据知识点id查找知识点名称、学科id
    	$result = $this->getKnowledgeInfo($kid);
    	$params = array(
			'kid'=>intval($kid),
			'uid'=>$userInfo['user_id'],
			'subject_id'=>intval($subject_id),
			'location_id'=>intval($location_id[0]),
			'grade'=>$result['grade']
		);
        //出材料题的知识点id
        $kidData = array(14,15,16,17,18,19,20,21,512,409,412,413,414,417,514);
    	if ( in_array($result['id'],$kidData) )
    	{
    	    $return = $this->MaterialQuestion($params,$questionRange);	//材料题
    	} else {
    	    $return = $this->unMaterialQuestion($params,$questionRange);//非材料题
    	}
    	echo json_encode($return);exit();
    }
    
   /*
    * 材料题
    * 选出该学科下的知识点的所有材料id（如果一级知识点下有二级知识点，
    * 点击一级知识点出题过滤掉该地区不考的二级知识点所对应题的材料id，
    * 如果是二级知识点或者只有以及知识点出题，直接根据知识点选出对应题的材料id），去重，过滤用户没选的出题范围的题--数组1
    * 选出已做题目对应的材料id(去重)--数组2
    * 从数组1-数组2（不为空）中选取一个材料id,然后出该材料id的题.为空，从数组1，中选取一个材料id,然后出该材料id的题.
    */
    public function MaterialQuestion($params,$questionRange)
    {
    	$part_array = $this->getDoneMaterialId($params);					//(该知识点下)已做题目的材料id
    	$all_array  = $this->getAllMaterialId($params,$questionRange);		//(该知识点下)所有题目的材料id
    	
    	$arr_diff = array_diff($all_array,$part_array); 					//剔除共有的材料id
    	$count = count($arr_diff);		
    	$arr_one = array();
    	if ($count > 0) {
            shuffle($arr_diff);
            $arr_one = array_slice($arr_diff,0,1);
    	} else {
            #所有题目都做过一遍了,传给客户端一个状态
            $finish_flag = TRUE;
            shuffle($all_array);
            $arr_one = array_slice($all_array,0,1);
    	}
    	$MaterialId = $arr_one['0'];
    	return $this->getQuestionByMaterialId($params,$MaterialId, $finish_flag); 		//根据这个材料id，来出一个完整的材料题
    }
    
    /*
     * 材料题
     * 获取用户已做题目的材料id(去重,该学科下的知识点)
     */
    public function  getDoneMaterialId($params)
    {
    	$result = $this->question_model->getDoneMaterialId($params);
    	return $result;
    }
    
    /*
     * 材料题
     * 获取所有题目的材料id(去重,该学科下的知识点)
     */
    public function getAllMaterialId($params,$questionRange)
    {
    	if ($params['grade'] == '1')
    	{
    		$lists = $this->getKidNotInLocation($params);
    		if ( empty($lists) ) {
    			$return = $this->handleAllMaterialId($params,$questionRange);
    		} else {
    			$ids = $this->getMaterialIdNotInLocation($lists);
    			$temp = $this->handleAllMaterialId($params,$questionRange);
    			 
    			if (!$temp['status']){
    				$response = $this->tiku->formatResponse('0','','',$temp['errMsg']);
    				echo json_encode($response);exit();
    			}
    			return array_diff($temp['all_ids'],$ids);
    		}
    		 
    	} else if ($params['grade'] == '2') {
    		$return = $this->handleAllMaterialId($params,$questionRange);
    	}
    	
    	if (!$return['status']){
    		$response = $this->tiku->formatResponse('0','','',$return['errMsg']);
    		echo json_encode($response);exit();
    	}
    	return $return['all_ids'];
    }
    
    /*
	 * 材料题
	 * 获取该知识点对应的所有题目的材料id(审核通过的,材料id去重)
     */
    function handleAllMaterialId($params,$questionRange)
    {
    	$result = $this->question_model->handleAllMaterialId($params,$questionRange);
    	return $result;
    }
    
    /*
     * 材料题
     * 如果一级知识点下有二级知识点，找出不在该地区的二级知识点对应的材料题id
     */
    public function getMaterialIdNotInLocation($data)
    {
    	$result = $this->question_model->getMaterialIdNotInLocation($data);
    	return $result;
    }
    
    /*
     * 材料题
     * 通过一个材料id，获取一个完整的材料题
     */
    public function getQuestionByMaterialId($params,$MaterialId, $finishFlage = FALSE)
    {
    	$result = $this->question_model->getQuestionByMaterialId($params,$MaterialId);
    	if (empty($result)){
    		$response = $this->tiku->formatResponse('0','','','该材料下没有题目');
    		echo json_encode($response);exit();
    	} else {
    		$list =array();
                $list['finish_all'] = $finishFlage ? 1 : 0;
    		$list['question_detail'][] = $result;
    		$response = $this->tiku->formatResponse('1',$list,'','');
    		return $response;
    	}
    }
    
   /*
    * 非材料题出题规则
    * 选出该学科下的知识点的所有题目id,出题范围（如果一级知识点下有二级知识点，
    * 点击一级知识点出题过滤掉该地区不考的二级知识点所对应的题，
    * 如果是二级知识点或者只有以及知识点出题，直接根据知识点选出对应的题），过滤用户没选的出题范围的题--数组1
    * 选出该学科下的知识点的已做题目id--数组2，再从数组1-数组2中剔除已做的题目id，选择5或者10个--数组4
    * 然后循环数组4，组装出题数组
    */
    public function unMaterialQuestion($params,$questionRange)
    {
    	$part_array = $this->getDoneQuestionByKid($params);//通过知识点id获取该知识点已做过的题目id,出题范围
    	$all_array  = $this->getAllQuestionByKid($params,$questionRange);//通过知识点id获取该知识点所有的题目id
    	
	$question_num = $this->getQuestionNums($params);//获取出题数目
    	$arr_diff = array_diff($all_array,$part_array);
    	$count = count($arr_diff);
    	$arr_one = array();//保存差集数组里面含有题目id
    	if ($count > 0) {
                shuffle($arr_diff);
    		$arr_one = array_slice($arr_diff,0,$question_num);
    	} else {
    		#所有题目都做过一遍了,传给客户端一个状态
                $finish_flag = TRUE;
                shuffle($all_array);
    		$arr_one = array_slice($all_array,0,$question_num);
    	}
    	return $this->buildQuestionArray($arr_one,$params['uid'], $finish_flag);
    }
    
    /*
     * 获取出题数
     */
    function getQuestionNums($params){
    	if ($params['subject_id'] == 2) {
    		$question_num = Constant::QUESTION_NUM_WENKE;
    	} else {
    		$question_num = Constant::QUESTION_NUM_LIKE;
    	}
    	return $question_num;
    }
    
    /*
     * 非材料题
     * 通过知识点id获取该知识点已做过的题目id
     */
    public function getDoneQuestionByKid($params)
    {
    	$result = $this->question_model->getDoneQuestionByKid($params);
    	return $result;
    }
    
    /*
     * 非材料题
     * 通过知识点id获取该知识点所有的题目id，出题范围
     */
    public function getAllQuestionByKid($params,$questionRange)
    {
    	if ($params['grade'] == '1')
    	{
    		$lists = $this->getKidNotInLocation($params);
    		if ( empty($lists) ) {
    			$return = $this->handleAllQid($params,$questionRange);
    		} else {
    			$ids = $this->getQidNotInLocation($lists);
    			$temp = $this->handleAllQid($params,$questionRange);
    			
    			if (!$temp['status']){
    				$response = $this->tiku->formatResponse('0','','',$temp['errMsg']);
    				echo json_encode($response);exit();
    			}
    			return array_diff($temp['all_ids'],$ids);
    		}
    			
    	} elseif ($params['grade'] == '2') {
    		$return = $this->handleAllQid($params,$questionRange);
    	}
    	
    	if (!$return['status']){
    		$response = $this->tiku->formatResponse('0','','',$return['errMsg']);
    		echo json_encode($response);exit();
    	}
    	return $return['all_ids'];
    }
    
    /*
     * 根据一级知识点找出旗下的二级知识点,将不在该地区的的二级知识点放在list数组中
     */
    public function getKidNotInLocation($params)
    {
    	$result = $this->question_model->getKidNotInLocation($params);
    	return $result;
    }

    /*
     * 非材料题
     * 如果一级知识点下有二级知识点，找出不在该地区的二级知识点对应的题目
     */
    public function getQidNotInLocation($data){
    	$result = $this->question_model->getQidNotInLocation($data);
    	return $result;
    }
    

    /*
     * 非材料题
     *获取该知识点下的所有题目(学生选择的出题范围内的题)
     */
    public function handleAllQid($params,$questionRange){
    	$result = $this->question_model->handleAllQid($params,$questionRange);
    	return $result;
    }
    
    /**
     * 非材料题
     * 通过数组里面的题目id 组装题目信息
     * @param  array $qIdArr 题目Id数组
     * @param  int   $userId 用户Id
     * @param  bool  $finishFlag 题目是否已做完,默认为false  
     */
    public function buildQuestionArray($qIdArr,$userId, $finishFlag = FALSE)
    {
    	if (isset($qIdArr) && is_array($qIdArr)) {
            $list = array();
            $list['finish_all'] = $finishFlag ? 1 : 0;//加入题目是否已经全做过一遍的标志位
            foreach ($qIdArr as $k=>$v) {
                $list['question_detail'][] = $this->question_model->buildQuestionArray($v,$userId);
            }
            if (is_array($list)) {
                return $this->tiku->formatResponse('1',$list,'','');   			
            } 
            $response = $this->tiku->formatResponse('0','','','出题数据错误');
            echo json_encode($response);exit();
    	} else {
            $response = $this->tiku->formatResponse('0','','','题目id,数据错误');
            echo json_encode($response);exit();
    	}
    }
    
    /*
     * 根据学科id出题：根据地区+学科，选出出题的所有知识点，在根据这些知识点
     * 找出对应的题（去重），过滤掉用户不选的出题范围的题，数组1
     * 找出用户在该学科下已经做过的题，数组2，从数组1-数组2中，选出5或10题
     * subject_id	:学科id
     */
    public function getQuestionListBysubjectId()
    {
    	$subject_id =$this->input->post("subject_id",true);
    	$location_id= $this->input->post("location_id",true);
    	if (empty($subject_id) || empty($location_id)){
    		$response = $this->tiku->formatResponse('0','','','参数错误');
    		echo json_encode($response);exit();
    	}
    	$location_id = explode("_",$location_id);
    	//判断地区id是否有效
    	$flag = $this->checkLocationId($location_id[0]);
    	if (!$flag)
    	{
    		$response = $this->tiku->formatResponse('0','','','无效的地区id');
    		echo json_encode($response);exit();
    	}
    	$userInfo = $this->checkUser();
    	//获取出题范围
    	$questionRange = $this->getQuestionRange($userInfo['user_id']);				
    	if (empty($questionRange))
    	{
    		$response = $this->tiku->formatResponse('0','','10038','该用户没有选择出题范围');
    		echo json_encode($response);exit();
    	}
    	$params = array(
            'uid'=>$userInfo['user_id'],
            'subject_id'=>intval($subject_id),
            'location_id'=>intval($location_id)
        );
    	
    	$part_array = $this->getDoneQuestionBySubjectId($params);					//根据学科id获取该用户已经做过的题
    	$all_array  = $this->getAllQuestionBySubjectId($params,$questionRange);		//根据学科id获取所有的题

        if (!$all_array['status']){
                $response = $this->tiku->formatResponse('0','','',$all_array['errMsg']);
                echo json_encode($response);exit();
        }
        $question_num = $this->getQuestionNums($params);							//获取出题数目
    	$arr_diff = array_diff($all_array['all_ids'],$part_array);					//所有题中过滤掉的题-该用户已经做过的题
    	$count = count($arr_diff);
    	$arr_one = array(); 														//保存差集数组里面含有题目id
    	if ($count > 0) {
            shuffle($arr_diff);
            $arr_one = array_slice($arr_diff,0,$question_num);
    	} else {
            #所有题目都做过一遍了,传给客户端一个状态
            $finish_flag = TRUE;
            shuffle($all_array['all_ids']);
            $arr_one = array_slice($all_array['all_ids'],0,$question_num);
    	}
    	$response =  $this->buildQuestionArray($arr_one, $params['uid'], $finish_flag);			//组装出题的题目格式
    	echo json_encode($response);exit();
    }
    
    /*
     * 根据学科id获取该用户已经做过的题
     */
    public function getDoneQuestionBySubjectId($params)
    {
    	$result = $this->question_model->getDoneQuestionBySubjectId($params);
    	return $result;
    }
    
    /*
     * 根据学科id获取所有的题
     */
    public function getAllQuestionBySubjectId($params,$questionRange)
    {
    	//根据学科id和地区id,得到所有知识点id
    	$knowledgeArr = $this->knowledge_model->getKnowledgeInLocation($params['subject_id'],$params['location_id']);
    	//根据所有的知识点id找出对应的题目id   	
    	$result = $this->question_model->getAllQuestionBySubjectId($knowledgeArr,$questionRange);
    	return $result;
    }
    
    /*
     * 设置出题范围
     * range		:出题范围
     */
    public function setQuestionRange()
    {
    	$range 	  = $this->input->post("range",true);
    	if (empty($range))
    	{
    		$response = $this->tiku->formatResponse('0','','','出题范围串为空');
    		echo json_encode($response);exit();
    	}
    	$range	   = explode(',', $range);
    	$questionRange = Constant::questionRange();
    	foreach ($range as $k=>$v)
    	{
    		if (!in_array($v, $questionRange))
    		{
    			$response = $this->tiku->formatResponse('0','','10039','选择的出题范围有误,请重新选择');
    			echo json_encode($response);exit();
    		}
    		
    	}
    	$userInfo  = $this->checkUser();
    	$return = $this->question_model->setQuestionRange($userInfo,$range);
    	if ($return['done'])
    	{
    		$response = $this->tiku->formatResponse('1',$return,'','');
    	} else {
    		$response = $this->tiku->formatResponse('0','','10011','设置出题范围失败');
    	}
    	echo json_encode($response);exit();
    }
    
    /*
     * 题目解析
	 * question_ids	:问题ID串，使用逗号拼接
     */
    public function questionAnalysis()
    {
    	$question_ids	= $this->input->post("question_ids",true);
    	if (empty($question_ids))
    	{
    		$response = $this->tiku->formatResponse('0','','','题目id串为空');
    		echo json_encode($response);exit();
    	}
    	$question_ids	= explode(',', $question_ids);
    	$userInfo = $this->checkUser();
    	$return = $this->question_model->questionAnalysis($question_ids,$userInfo['user_id']);
    	if (is_array($return))
    	{
    		$response = $this->tiku->formatResponse('1',$return,'','');
    	} else {
    		$response = $this->tiku->formatResponse('0','','10013','题目解析数据出错');
    	}
    	echo json_encode($response);exit();
    }
    
    /*
     * 解析题目收藏
     * question_id	:试题ID
     * subject_id	:学科id
     */
    public function questionCollect()
    {
    	$question_id	= $this->input->post("question_id",true);
    	$subject_id		= $this->input->post("subject_id",true);
    	if (empty($question_id) || empty($subject_id))
    	{
    	    $response = $this->tiku->formatResponse('0','','','参数错误');
    	    echo json_encode($response);exit();
    	}
        $qFlag = $this->checkQuestionId($question_id);
    	if (!$qFlag){
    	    $response = $this->tiku->formatResponse('0','','','题目id错误');
    	    echo json_encode($response);exit();
    	}
    	$sFlag = $this->checkSubjectId($subject_id);
    	if (!$sFlag){
    	    $response = $this->tiku->formatResponse('0','','','学科id错误');
    	    echo json_encode($response);exit();
    	}
    	$userInfo 		= $this->checkUser();
    	$return = $this->question_model->questionCollect($userInfo,$question_id,$subject_id);
    	if ($return)
    	{
    		$response = $this->tiku->formatResponse('1',array('done'=>1),'','');
    	} else {
    		$response = $this->tiku->formatResponse('0','','10015','收藏失败,请重试');
    	}
    	echo json_encode($response);exit();
    }
    
    /*
     * 解析题目取消收藏
     * question_id	:试题ID
     * subject_id	:学科id
     */
    public function questionUnCollect()
    {
    	$question_id	= $this->input->post("question_id",true);
    	$subject_id		= $this->input->post("subject_id",true);
    	if (empty($question_id) || empty($subject_id))
    	{
    	    $response = $this->tiku->formatResponse('0','','','参数错误');
    	    echo json_encode($response);exit();
    	}
    	$qFlag = $this->checkQuestionId($question_id);
    	if (!$qFlag){
    	    $response = $this->tiku->formatResponse('0','','','题目id错误');
    	    echo json_encode($response);exit();
    	}
    	$sFlag = $this->checkSubjectId($subject_id);
    	if (!$sFlag){
    	    $response = $this->tiku->formatResponse('0','','','学科id错误');
    	    echo json_encode($response);exit();
    	}
    	$userInfo 		= $this->checkUser();
    	$return = $this->question_model->questionUnCollect($userInfo,$question_id,$subject_id);
    	if ($return)
    	{
    		$response = $this->tiku->formatResponse('1',array('done'=>1),'','');
    	} else {
    		$response = $this->tiku->formatResponse('0','','10016','取消收藏失败,请重试');
    	}
    	echo json_encode($response);exit();
    }
    
    /*
     * 判断题目id是否有效
     */
    public function checkQuestionId($question_id)
    {
        return $this->question_model->checkQuestionId($question_id);
    }
    
    /*
     * 判断学科id是否有效
     */
    public function checkSubjectId($subject_id)
    {
        $subjects = $this->tiku->subjectArray();
        if (array_key_exists($subject_id, $subjects)) {
            return true;
        } else {
            return false;
        }
    }
    
    /*
     * 判断地区id是否有效
     */
    protected function checkLocationId($location_id){
    	return $this->question_model->checkLocationId($location_id);
    }
    
    /**
     * 题目错误信息反馈
     */
    public function feedback()
    {
        #验证用户
        $userInfo = $this->checkUser();
        $userId = $userInfo['user_id'];
        $name = $userInfo['name'];
        $subjectId = (int)$this->input->post('subject_id');
        $questionId = (int)$this->input->post('question_id');
        $materailId = (int)$this->input->post('article_id');
        $phoneNumber = $this->input->post('phone');
        $content = $this->input->post('content');
        if(!$userId || !$questionId || !$phoneNumber || !$content) {
            $response = $this->tiku->formatResponse(0, array(), 0, '参数错误');
        }
        $time = date('Y-m-d H:i:s');
        $param = array(
            'userId' => $userId,
            'name'   => $name,
            'qId'    => $questionId,
            'subjectId' => $subjectId,
            'materialId'=> $materailId,
            'phoneNumber'=> $phoneNumber,
            'content'    => $content,
            'inputTime'  => $time,
        );
        
        $res = $this->question_model->addQuestionFeedback($param);
        if($res) {
            $response = $this->tiku->formatResponse(1, array('done' => 1));
        } else {
            $response = $this->tiku->formatResponse(0, array(), 10040, '提交题目反馈失败,请稍后重试');
        }
        
        echo json_encode($response);die;
    }
    
    /**
     * 当用户放弃练习时调用此接口
     * 主要是为了统计信息,分析是什么问题导致的用户放弃做题
     */
    public function give_up()
    {
        #验证用户
        $userInfo = $this->checkUser();
        $param['userId'] = $userInfo['user_id'];
        $param['subjectId'] = intval($this->input->post('subject_id'));
        $rightDetailStr = trim($this->input->post('right_detail'));
        $userAnswerStr  = trim($this->input->post('user_answer'));
        $param['rightDetail'] = $this->tiku->parseAnswerStr($rightDetailStr,1);
        $param['userAnswer'] = $this->tiku->parseAnswerStr($userAnswerStr,2);
        $param['cancelTime'] = date('Y-m-d H:i:s');
        $this->question_model->logPracticeGiveUp($param);
        $response = $this->tiku->formatResponse(1, array('done' => 1));
        exit(json_encode($response));
    }
}