<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * 移动题库 用户控制器
 */

class User extends MY_Controller
{
    public function __construct() {
        parent::__construct();
        $this->load->model('study/tiku_model');
        $this->load->model('study/pet_model');
        $this->load->model('tiku/user_model');
        $this->load->model('tiku/question_model');
        $this->load->model('tiku/knowledge_model');
        $this->load->model('tiku/subject_model');
        $this->load->model('login/session_model');
        $this->db = $this->load->database('tiku', true);

        $this->load->library('Tiku');
    }
    
    
    /**
     * 存放做题后有提高的知识点Id
     */
    protected $_imporvedKnowledge = array();
    
    /**
     * 用户当前经验值
     */
    protected $_currentExp = 0;
    
    /**
     * 用户的学科预测分 
     */
    protected $_prePoints = 0;
    
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
     * 获取高考省市分类接口
     * region_catalog_id 采用3_1这种形式,3是地区Id,1是学科种类Id
     */
    public function regionCatalog()
    {
        $userInfo = $this->checkUser();#验证用户
        
        $regions = $this->user_model->getLocationList();
        if(!$regions) {
            $responseData = $this->tiku->formatResponse(0, array(), 10003, '获取地区学科分类失败');
            exit(json_encode($responseData));
        }
        $regionCatalog = array();
        foreach($regions as $val) {
            $arr['region_catalog_id'] = $val['id'] . '_1';
            $arr['region_catalog'] = $val['name'] . '(理科)';
            $regionCatalog[] = $arr;
            $arr['region_catalog_id'] = $val['id'] . '_2';
            $arr['region_catalog'] = $val['name'] . '(文科)';
            $regionCatalog[] = $arr;
        }
        
        $responseData = $this->tiku->formatResponse(1, $regionCatalog);
        echo json_encode($responseData);
    }
    
    /**
     * 获取用户练习页面的数据
     */
    public function getPracticeIndex()
    {
        $userInfo = $this->checkUser();#验证用户
        $userId = $userInfo['user_id'];
        $subjectId = $this->input->post('subject_id');
        $paracticeData = $this->user_model->getPracticeIndex($userId, $subjectId);
        if(is_array($paracticeData)) {
            $responseData = $this->tiku->formatResponse(1, $paracticeData);
        } else {
            $responseData = $this->tiku->formatResponse(0, array(), 10007, '我的练习获取失败');
        }
        
        echo json_encode($responseData);
    }
    
    /**
     * 获取用户错题本或者收藏夹列表
     */
    public function userBookListData()
    {
        $userInfo = $this->checkUser();//用户验证
        $bookTypeArr = array('error', 'favorite');
        
        $bookType = $this->input->post('bookType') && in_array($this->input->post('bookType'), $bookTypeArr) ? $this->input->post('bookType') : 'error';
        $subjectId = (int)$this->input->post('subject_id');
        $num = (int)$this->input->post('num');
        $offset = (int)$this->input->post('offset');
        $paramArr = array('userId' => $userInfo['user_id'], 'subjectId' => $subjectId, 'bookType' => $bookType, 'offset' => $offset, 'num' => $num);
        $data = $this->user_model->getUserBookList($paramArr);
        $responseData = array();
        if(is_array($data)) {
            foreach($data as $val) {
                $arr['day'] = strtotime($val['date']);
                $arr['count'] = $val['questionNums'];
                $responseData[] = $arr;
            }
            
            $responseData = $this->tiku->formatResponse(1, $responseData);
        } else {
            $responseData = $this->tiku->formatResponse(0, array(), 10008, '题目历史记录获取失败');
        }
        
        echo json_encode($responseData);
    }
    
    /**
     * 获取用户学科的练习记录列表
     */
    public function userPracticeList()
    {
        $userInfo = $this->checkUser();//用户验证
        $subjectId = (int)$this->input->post('subject_id');
        $num = (int)$this->input->post('num');
        $offset = (int)$this->input->post('offset');
        $paramArr = array('userId' => $userInfo['user_id'], 'subjectId' => $subjectId, 'num' => $num, 'offset' => $offset);
        
        $responseData = $this->user_model->getUserPracticeList($paramArr);
        if(is_array($responseData)) {
            $responseData = $this->tiku->formatResponse(1, $responseData);
        } else {
            $responseData = $this->tiku->formatResponse(0, array(), 10010, '练习历史获取失败');
        }
        
        echo json_encode($responseData);
    }
    
    /**
     * 用户做完练习后提交上来要的做的一系列写入操作
     * 提交做题结果到服务器后要写入数据的流程
     * 1. 写入用户练习记录表
     * 2. 拿到练习id后把题目写入用户答题记录表,如果有收藏写入用户收藏表
     * 3. 计算每道题对应的知识点的掌握程度分数和等级
     * 4. 更新个人的做题数和做题经验
     * 
     * 返回个人经验、预测分、和有提高的一级知识点
     */
    public function commitPractice()
    {
        $userInfo = $this->checkUser();//用户验证
        $commitedData = array();//提交上来的做题数据都放到这个数组里
        $rightDetailStr = $this->input->post('right_detail');
        $userAnswerStr  = $this->input->post('user_answer');
        $collectStr     = $this->input->post('collect');
        $commitedData['right_count'] = (int)$this->input->post('right_count');
        $commitedData['wrong_count'] = (int)$this->input->post('wrong_count');
        $commitedData['experience'] = $this->input->post('experience');
        $commitedData['question_num'] = (int)$this->input->post('question_num');
        $commitedData['practice_name'] = $this->input->post('practice_name');
        $commitedData['subject_id'] = (int)$this->input->post('subject_id');
        $commitedData['is_pass'] = (int)$this->input->post('is_pass');
        $commitedData['right_detail'] = $rightDetailStr ? $this->tiku->parseAnswerStr($rightDetailStr,1) : '';
        $commitedData['user_answer']  = $userAnswerStr ? $this->tiku->parseAnswerStr($userAnswerStr,2) : '';
        $commitedData['collect']      = $collectStr ? $this->tiku->parseAnswerStr($collectStr,1) : '';
        $commitedData['seconds'] = (int)$this->input->post('seconds');
        $commitedData['userId'] = $userInfo['user_id'];
        if(!$commitedData['right_detail'] || !$commitedData['user_answer']) {
            return false;
        }

        #写入用户练习记录表
        $practiceId = $this->insertPracticeLog($commitedData);
        if(!$practiceId) {
            $response = $this->tiku->formatResponse(0, array());
            echo json_decode($response);die;
        }
        $commitedData['practiceId'] = $practiceId;
        #写入用户答题记录表
        $res1 = $this->insertAnswerLog($commitedData);
        if(!$res1) {
            $response = $this->tiku->formatResponse(0, array());
            echo json_encode($response);die;
        }
        #更新用户知识点掌握程度的数据
        $res2 = $this->newUpdateKnowledgeInfo($commitedData);
        if(!$res2) {
            $response = $this->tiku->formatResponse(0, array());
            echo json_encode($response);die;
        }
        #更新用户学科预测分
        $res3 = $this->updateSubjectInfo($commitedData);
        if(!$res3) {
            $response = $this->tiku->formatResponse(0, array());
            echo json_decode($response);die;
        }
        #更新用户经验值等数据
        $this->updateUserBaseStat($commitedData);
        #添加用户收藏
        $this->addUserFavorite($commitedData);
        
        $improvedKIds = $this->_imporvedKnowledge;
        $improvedKIds = array_unique($improvedKIds);
        $improvedKnowledge = array();
        foreach($improvedKIds as $kId) {
            $improvedKnowledge[] = $this->knowledge_model->getOneUserKnowledge($commitedData['userId'], $kId);
        }
        $responseData['experience'] = $this->_currentExp;
        $responseData['forcast_socre'] = $this->_prePoints;
        $responseData['improve'] = $improvedKnowledge;
        $responseData = $this->tiku->formatResponse(1, $responseData);
        echo json_encode($responseData);
    }
    
    /**
     * 写入用户练习记录表
     * @param $commitedData  用户做题后提交上来的数据
     * @return $practiceId 返回新写入的练习Id
     */
    public function insertPracticeLog($commitedData)
    {
        $practiceData['practiceName'] = $commitedData['practice_name'];
        $practiceData['correctNums'] = $commitedData['right_count'];
        $practiceData['incorrectNums'] = $commitedData['wrong_count'];
        $practiceData['exp'] = $commitedData['experience'];
        $practiceData['result'] = $commitedData['is_pass'];
        $practiceData['userId'] = $commitedData['userId'];
        $practiceData['subjectId'] = $commitedData['subject_id'];
        $practiceData['date'] = date('Y-m-d H:i:s');
        $practiceData['seconds'] = $commitedData['seconds'];
        $practiceId = $this->user_model->insertPractice($practiceData);
        if(!$practiceId) {
            return false;
        } else {
            return $practiceId;
        }
    }
    
    /**
     * 写入用户答题记录
     * @param array $commitedData 用户做题后提交上来的数据
     */
    public function insertAnswerLog($commitedData)
    {
        #循环插入提交上来的每道题
        $userAnswer = $commitedData['user_answer'];
        $rightDetail = $commitedData['right_detail'];
        $order = 1;
        foreach($userAnswer as $questionId => $answer)
        {
            $answerData['userId'] = $commitedData['userId'];
            $answerData['practiceId'] = $commitedData['practiceId'];
            $answerData['qId'] = $questionId;
            $answerData['subjectId'] = $commitedData['subject_id'];
            $answerData['selected'] = $answer;
            $answerData['isCorrect'] = $rightDetail[$questionId];
            $answerData['date'] = date('Y-m-d');
            $answerData['time'] = time();
            $logId = $this->question_model->insertUserAnswer($answerData);
            if(!$logId) {
                return FALSE;
            }
//            $res = $this->updateKnowledgeInfo($commitedData['userId'], $questionId, $commitedData['question_num'], $rightDetail[$questionId]);
//            if(!$res) {
//                return FALSE;
//            }
        }
        
        return TRUE;
    }
    
    /**
     * 更新用户的知识点掌握程度
     * @param int $qId 题目Id
     * @param int $userId 用户Id
     * @param int $questionNums 本次练习的出题数
     * @param int $isCorrect 本次答题是否正确
     */
    public function updateKnowledgeInfo($userId, $qId, $questionNums, $isCorrect)
    {
        $variation = $questionNums > 7 ? '0.5' : 1;
        $variation = $isCorrect ? $variation : -$variation;
        #首先查
        #找出题目Id对应的所有知识点
        $knowledgeArr = $this->knowledge_model->getRelatedKnowledge($qId);
        foreach ($knowledgeArr as $kInfo) {
            #先判断该用户在统计表中是否有记录
            $existence = $this->knowledge_model->checkExistence($userId, $kInfo['kId']);
            if(!$existence) {
                $insertData['userId'] = $userId;
                $insertData['kId'] = $kInfo['kId'];
                $insertData['score'] = $variation > 0 ? $variation : 0;
                $insertData['questionNums'] = 1;
                $this->knowledge_model->logNewKnowledge($insertData);
                
            } else {
                $result = $this->knowledge_model->updateUserKnowledge($userId, $kInfo['kId'], $variation, $kInfo['grade']);
                if($result && is_array($result)) {
                    #记录升级的知识点
                    array_push($this->_imporvedKnowledge, $result['kId']);
                } else if(!$result){
                    return FALSE;
                }
            }
        }
        
        return TRUE;
    }
    
    /**
     * 更新用户的知识点掌握程度(新版, 这个版本是把练习题目对应的所有知识点都取出来,
     * 计算好每个知识点的变动情况后再往数据库写入,与上版相比减少了MySql插入次数)
     * @param type $commitedData
     * @return boolean
     */
    public function newUpdateKnowledgeInfo($commitedData)
    {
        $userAnswer = $commitedData['user_answer'];
        $rightDetail = $commitedData['right_detail'];
        $knowledgeSet = array();//存放本次练习题目对应的知识点以及做题结果对知识点统计产生的影响
        foreach($userAnswer as $qId => $answer) {
            $filterArray = array();
            $variation = $commitedData['question_num'] > 7 ? '0.5' : 1;
            $variation = $rightDetail[$qId] ? $variation : -$variation;
            #找出题目Id对应的所有知识点
            $knowledgeArr = $this->knowledge_model->getRelatedKnowledge($qId);
            foreach($knowledgeArr as $kInfo) {
                if(in_array($kInfo['kId'], $filterArray)) {
                    continue;//录题时有重复关联知识点的情况,这里过滤下
                }
                
                if(!isset($knowledgeSet[$kInfo['kId']])) {
                    $knowledgeSet[$kInfo['kId']]['scoreChanged'] = floatval($variation);
                    $knowledgeSet[$kInfo['kId']]['questionNums'] = 1;
                    $knowledgeSet[$kInfo['kId']]['grade'] = $kInfo['grade'];
                } else {
                    $knowledgeSet[$kInfo['kId']]['scoreChanged'] = $knowledgeSet[$kInfo['kId']]['scoreChanged'] + $variation;
                    $knowledgeSet[$kInfo['kId']]['questionNums']++;
                }
                #记录这个题目已经统计过的知识点Id
                array_push($filterArray, $kInfo['kId']);
            }
        }
        foreach ($knowledgeSet as $kId => $val) {
            #先判断该用户在统计表中是否有记录
            $existence = $this->knowledge_model->checkExistence($commitedData['userId'], $kId);
            if(!$existence) {
                $insertData['userId'] = $commitedData['userId'];
                $insertData['kId'] = $kId;
                $insertData['score'] = $val['scoreChanged'] > 0 ? $val['scoreChanged'] : 0;
                if($insertData['score'] >=3) {
                    #学霸第一次联系就有可能把题全做对,知识点一下升两级,所以这里判断下
                    $insertData['kLevel'] = $insertData['score'] >= 9 ? 2 : 1;
                    #记录下升级的一级知识点Id
                    $val['grade'] == 1 && array_push($this->_imporvedKnowledge, $kId); 
                } 
                $insertData['questionNums'] = $val['questionNums'];
                $this->knowledge_model->logNewKnowledge($insertData);
                
            } else {
                $result = $this->knowledge_model->newUpdateUserKnowledge($commitedData['userId'], $kId, $val['scoreChanged'], $val['questionNums'], $val['grade']);
                if($result && is_array($result)) {
                    #记录升级的知识点
                    array_push($this->_imporvedKnowledge, $result['kId']);
                } else if(!$result){
                    return FALSE;
                }
            }
        }
        
        return TRUE;
    }
    
    /**
     * 更新用户的经验和做题数
     * @param array $commitedData 用户做完题后提交上来的数据
     */
    public function updateUserBaseStat($commitedData)
    {
        $userData['userId'] = $commitedData['userId'];
        $userData['exp'] = $commitedData['experience'];
        
        $newExp = $this->tiku_model->updateUserExp($userData);
        if($newExp) {
            $this->_currentExp = $newExp;
            return TRUE;
        } else {
            return FALSE;
        }
    }
    
    /**
     * 更新用户学科概况
     * @param array $commitedData 用户做完题后提交上来的数据
     */
    public function updateSubjectInfo($commitedData)
    {
        $subjectId = $commitedData['subject_id'];
        $userId = $commitedData['userId'];
        $recalculate = !empty($this->_imporvedKnowledge) && is_array($this->_imporvedKnowledge) ? 1 : 0;
        #首先检查用户是否有该学科的统计信息
        $existence = $this->subject_model->checkExistence($userId, $subjectId);
        if(!$existence) {
            $prePoints = $this->subject_model->addUserSubject($userId, $subjectId);
        } else {
            $prePoints = $this->subject_model->updateUserSubject($userId, $subjectId, $recalculate);
        }
        
        $prePoints && $this->_prePoints = $prePoints;
        if($prePoints !== false) {
            return TRUE;
        } else {
            return FALSE;
        }
    }
    
    /**
     * 添加用户收藏题目
     * @param array $commitedData 用户做完题提交上来的数据
     */
    public function addUserFavorite($commitedData)
    {
        if($commitedData['collect'] && is_array($commitedData['collect'])) {
            foreach($commitedData['collect'] as $qId => $act) {
                if($act) {
                    $res = $this->user_model->addUserFavorite($commitedData['userId'], $qId, $commitedData['subject_id']);
                    if(!$res) {
                        return FALSE;
                    }
                }
            }
        }
    }
    
    /**
     * 设置地区学科分类
     */
    public function setRegionCatalog()
    {
        $userInfo = $this->checkUser();
        $regionCatalogId = $this->input->post('region_catalog_id');
        $userId = $userInfo['user_id'];
        $arr = explode('_', $regionCatalogId);
        if(count($arr) < 2 || !($arr[0] && $arr[1])) {
            $response = $this->tiku->formatResponse(0, array(), 10029, '无效地区');
            echo json_encode($response);die;
        }
        
        $locationId = intval($arr[0]);
        $subjectType = intval($arr[1]);
        $res = $this->tiku_model->setUserRegionCatalog($userId, $locationId, $subjectType);    
        if(!$res) {
            $response = $this->tiku->formatResponse(0, array(), 10004, '设置地区学科信息失败');
        } else {
            $response = $this->tiku->formatResponse(1, array('done' => 1));
        }
        
        echo json_encode($response);die;
    }
    
    /*
	 * 移动题库--用户注册
	 * username		:用户名
	 * password		:密码
	 * name			:昵称
	 * type=1普通登陆，type=2第三方登陆
	 */
	public function tiku_register()
	{
		$username = trim($this->input->post("username",true));
		$password = trim($this->input->post("password",true));
		$name 	  = trim($this->input->post("name",true));
                $phone_os       = trim($this->input->post("phone_os", true));
		if (empty($username) || empty($password) || empty($name)){
			$response = $this->tiku->formatResponse('0','','','参数错误');
			echo json_encode($response);exit();
		}
	    if( !preg_email($username) ) 
	    {
    		$response = $this->tiku->formatResponse('0','','','邮箱格式不正确');
    		echo json_encode($response);exit();
    	}
    	if(strlen($password) != 32) $password=md5('ti'.$password.'zi');
		$data = array(
				'username'=>$username,
				'password'=>$password,
				'name'=>$name,
				'user_type'=>Constant::USER_TYPE_STUDENT,//用户类型--学生
				'register_type'=>Constant::INSERT_REGISTER_EMAIL,//注册类型--邮箱
                                'app_name'=>  Constant::APP_TIKU_NAME,
                                'phone_os' => $phone_os,
                                'send_email' => 0
				);
		$url = base_url().'register/submit/tizi';
		$reg_return  = json_decode($this->vpost($url,$data),true);
		if($reg_return['errorcode'])
		{
			$u = base_url().'login/user_login';
			$post = array(
					'username'=>$username,
					'password'=>$password,
					'app_type'=>Constant::API_TYPE_TIKU,
					'app_name'=>Constant::APP_TIKU_NAME
			);
			$type = 1;
			$response = $this->tiziLoginApi($u,$post,$type);
		} else {
			$response = $this->tiku->formatResponse('0','','',$reg_return['error']);
		}
		echo json_encode($response);exit();
	}
	
	/*
	 * 移动题库--登录
	 * username		:用户名
	 * password		:密码
	 * type=1普通登陆，type=2第三方登陆
	 */
	public function tiku_login()
	{
		$username = trim($this->input->post("username",true));
		$password = trim($this->input->post("password",true));
		
		if (empty($username) || empty($password)) {
			$response = $this->tiku->formatResponse('0','','','参数不正确');
		} elseif (!preg_email($username)) {
			$response = $this->tiku->formatResponse('0','','10036','用户名需为正确的邮箱格式');
		} else {
		    if(strlen($password) != 32) $password=md5('ti'.$password.'zi');
			$url = base_url().'login/user_login';
			$data = array(
					'username'=>$username,
					'password'=>$password,
					'app_type'=>Constant::API_TYPE_TIKU,
					'app_name'=>Constant::APP_TIKU_NAME
			);
			$type = 1;
			$response = $this->tiziLoginApi($url,$data,$type);
		}
		echo json_encode($response);exit();
	}
	
	/*
	 * 调用梯子的登陆接口
	 */
	public function tiziLoginApi($url,$data,$type)
	{
		$login_return = json_decode($this->vpost($url,$data),true);
		if ($login_return['errorcode'])
		{
			if ($login_return['response_data']['user_data_info'] && $login_return['response_data']['user_data_info']['location_id'] && $login_return['response_data']['user_data_info']['subject_type'])
			{
				$region_catalog_id = $login_return['response_data']['user_data_info']['location_id'].'_'.$login_return['response_data']['user_data_info']['subject_type'];
			} else {
				$region_catalog_id = '';
			}
			//用户user_id
			$user_id = $login_return['response_data']['user_info']['id'];
			$session_id = $login_return['response_data']['session_id'];
			//用户的宠物心情
			$petMood = $this->user_model->getPetMood($user_id);
			//用户的出题范围
			$range = $this->question_model->getQuestionRange($user_id);
			if (!empty($range)){
				$range = implode(',', $range);
			} else {
			    $range = 0;
			}
			//用户信息
			$user_info = array(
				'user_id'=>$user_id,
				'nick_name'=>$login_return['response_data']['user_info']['name'],
				'region_catalog_id'=>$region_catalog_id,
				'experience'=>$login_return['response_data']['user_data_info']['exp'],
				'pet_id'=>$login_return['response_data']['user_data_info']['pet_id'],
				'pet_status'=>$petMood,
				'range'=>$range,
		        'email'=>$login_return['response_data']['user_info']['email']
			);
			//返回给手机端的参数数组
			if ($type =='1')
			{
				$arr = array(
					'session_id'=>$session_id,
					'user_info'=>$user_info
				);
			} else if ($type == '2') {
				$arr = array(
					'session_id'=>$session_id,
					'done'=>1,
					'user_info'=>$user_info
			    );
			}

			$response = $this->tiku->formatResponse('1',$arr,'','');
		} else {
			$response = $this->tiku->formatResponse('0','','',$login_return['response_error_message']);
		}
		return $response;
	}
	
	/*
	 * 第三方登陆与账户绑定
	 * third_uid 	: 第三方的用户Id
	 * token
	 * third_code 	:第三方标识码 1:新浪微博 2:QQ…
	 * name			:昵称
	 * type=1普通登陆，type=2第三方登陆
	 */
	public function thirdLogin()
	{
		$third_uid 	= $this->input->post("third_uid",true);
		$token 		= $this->input->post("token",true);
		$third_code = $this->input->post("third_code",true);
		$name 		= trim($this->input->post("name",true));
		if (empty($third_uid) || empty($token) || empty($third_code) || empty($name)){
			$response = $this->tiku->formatResponse('0','','','参数错误');
			echo json_encode($response);exit();
		}
		$data = array(
				'open_id'=>$third_uid,
				'platform'=>$third_code,
				'access_token'=>$token
				);
		//第三方登陆账号是否绑定
		$url = base_url().'oauth/callback';
		$return = json_decode($this->vpost($url,$data),true);
		switch ($return['errorcode'])
		{
			case 1:
				$response = $this->tiku->formatResponse('0','','',$return['error']);
				break;
			case 2:
				$response = $this->tiku->formatResponse('1',array('done'=>0,'oauth_id'=>$return['oauth_data']['oauth_id']),'','');
				break;
			case 3:
				$mdata = array(
						'app_type'=>Constant::API_TYPE_TIKU,
						'user_id'=>$return['oauth_data']['user_id'],
						'name'=>$name,
						'app_name'=>Constant::APP_TIKU_NAME
				);
				$murl = base_url().'oauth/login';
				$type = 2;
				$response = $this->tiziLoginApi($murl,$mdata,$type);
				break;
		}
		echo json_encode($response);exit();
	}
	
	/*
	 * 第三方登陆与绑定 注册
	 * name			:昵称
	 * username		:用户名
	 * oauth_id		:服务端返回的第三方关联表的主键id
	 * type=1普通登陆，type=2第三方登陆
	 */
	public function addUserName()
	{
		$username 	= trim($this->input->post("username",true));
		$name 		= trim($this->input->post("name",true));
		$oauth_id 	= intval($this->input->post("oauth_id",true));
                $phone_os       = trim($this->input->post("phone_os", true));
		if (empty($username) || empty($oauth_id))
		{
			$response = $this->tiku->formatResponse('0','','','参数错误');
			echo json_encode($response);exit();
		}
                $this->load->model('oauth/oauth_model');
                $oauth_info = $this->oauth_model->id_get($oauth_id);
		$data = array(
				'username'=>$username,
				'password'=>'',
				'name'=>$name,
				'user_type'=>Constant::USER_TYPE_STUDENT,					//用户类型--学生
				'register_type'=>Constant::INSERT_REGISTER_EMAIL,			//注册类型--邮箱
				'oauth_id'=>$oauth_id,
                                'app_name' => Constant::APP_TIKU_NAME,
                                'phone_os' => $phone_os,
                                'platform' => $oauth_info['platform'],
                                'send_email' => 0
				);
		$url = base_url().'oauth/register';
		$reg_return = json_decode($this->vpost($url,$data),true);
		if ($reg_return['errorcode'])
		{
			$mdata = array(
					'app_type'=>Constant::API_TYPE_TIKU,
					'user_id'=>$reg_return['user_info']['user_id'],
					'name'=>$name,
					'app_name'=>'tiku'
			);
			$murl = base_url().'oauth/login';
			$type = 2;
			$response = $this->tiziLoginApi($murl,$mdata,$type);
		} else {
			$response = $this->tiku->formatResponse('0','','',$reg_return['error']);
		}
		echo json_encode($response);exit();
	}
	
	
	/*
	 * 个人主页        		获取用户信息
	 * user_id		:为空,查看自己的主页.不为空,查看别人的主页
	 */
	public function getUserInfo()
	{
		$other_user_id  = $this->input ->post("user_id",true);
		$userInfo = $this->checkUser();
		if ( empty($other_user_id) )
		{
		    $user_id = $userInfo['user_id'];
		} else {
		    $this->checkUserExists($other_user_id);
		    $user_id  = $other_user_id;
		}
		//获取用户的经验、宠物id、朋友总数、经验周排名、是否与别人是好友
		$result = $this->tiku_model->getUserInfo($user_id,$userInfo['user_id']);
		
		//获取用户的总共做题、总正确率、使用天数、宠物心情、已掌握的知识点
		$return = $this->user_model->getUserInfoAbout($user_id);
		if (is_array($return) && is_array($result))
		{
			$return['user_info'] = array_merge($result['user_info'],$return['user_info']);
			$response = $this->tiku->formatResponse('1',$return,'','');
		} else {
			$response = $this->tiku->formatResponse('0','','10018','用户主页加载失败,稍后请重试');
		}
		echo json_encode($response);exit();
	}
	
	/*
	 * 更换宠物
	 * pet_id		:宠物ID
	 */
	public function changePet()
	{
		$pet_id 	= $this->input->post("pet_id",true);
		if (empty($pet_id)){
			$response = $this->tiku->formatResponse('0','','','参数错误');
			echo json_encode($response);exit();
		}
		$userInfo 	= $this->checkUser();
		$return 	= $this->pet_model->changePet($userInfo,$pet_id);
		if ($return['status'])
		{
			$response = $this->tiku->formatResponse('1',array('done'=>1),'','');
		} else {
			$response = $this->tiku->formatResponse('0','','10026',$return['errMsg']);
		}
		echo json_encode($response);exit();
	}
	
	/*
	 * 添加战友
	 * user_id		:待添加的战友ID
	 */
	public function addComrade()
	{
		$comrade_uid = $this->input->post("user_id",true);
		$userInfo 	 = $this->checkUser();
		$this->checkUserExists($comrade_uid);
		//自己不能添加自己为战友
		if ($userInfo['user_id'] == $comrade_uid){
		    $response = $this->tiku->formatResponse('0','','','不能添加自己为好友');
		    echo json_encode($response);exit();
		}
		$return 	 = $this->tiku_model->addComrade($userInfo,$comrade_uid);
		$data = array(
				'title'=>$userInfo['name']."关注了你",
				'content'=>$userInfo['name']."关注了你,快去看看吧",
				'sendId'=>$userInfo['user_id'],
				'receiveId'=>$comrade_uid,
				'time'=>date('Y-m-d H:i:s'),
				'type'=>2
		);
		$result = $this->user_model->sendMessage($userInfo,$data);
		if ($return && $result)
		{
			$response = $this->tiku->formatResponse('1',array('done'=>1),'','');
		} else {
			$response = $this->tiku->formatResponse('0','','10019','战友添加失败,稍后请重试');
		}
		echo json_encode($response);exit();
	}
	
	/*
	 * 移除战友
	 * user_id		:待移除的战友ID
	 */
	public function deleteComrade()
	{
		$comrade_uid 	= $this->input->post("user_id",true);
		$userInfo 		= $this->checkUser();
		$this->checkUserExists($comrade_uid);
		$return 		= $this->tiku_model->deleteComrade($userInfo,$comrade_uid);
		if ($return)
		{
			$response = $this->tiku->formatResponse('1',array('done'=>1),'','');
		} else {
			$response = $this->tiku->formatResponse('0','','10020','移除战友失败,稍后请重试');
		}
		echo json_encode($response);exit();
	}
	
	/*
	 * 搜索战友
	 * query		:查询串,是战友昵称
	 */
	public function searchComrade()
	{
		$search = $this->input->post("query",true);
		if (empty($search)){
                    $response = $this->tiku->formatResponse('0','','','参数错误');
                    echo json_encode($response);exit();
		}
		$userInfo = $this->checkUser();
                $userId = $userInfo['user_id'];
		$result = $this->tiku_model->searchComrade($search);
		if (!empty($result)) {
                    foreach($result as $key => $val) {
                        $othersId = $val['user_id'];
                        $result[$key]['is_follow'] = $this->tiku_model->isFollow($userId, $othersId);
                        $result[$key]['tag'] = $this->user_model->getLocationNameSubjectType($val['location_id'], $val['subject_type']);
                        $result[$key]['pet_status'] = $this->user_model->getPetMood($othersId);
                    }
		}
		if (is_array($result))
		{
			$response = $this->tiku->formatResponse('1',$result,'','');
		} else {
			$response = $this->tiku->formatResponse('0','','10022','搜索战友,数据出错');
		}
		echo json_encode($response);exit();
	}
	
	/*
	 * 战友排行榜
	 * type			:1表示周排行榜，2表示总排行
	 * rows			:limit row offset
	 * offset		:limit row offset
	 */
	public function rankComrade()
	{
		$type 	= $this->input->post("type",true);
		if (!in_array($type, array(1,2)))
		{
			$type = 1;
		}
		$userInfo = $this->checkUser();
		$rows	 = $this->input->post("rows",true);
		$offset	 = $this->input->post("offset",true);
		$rows    = ($rows == '' || $rows == 0) ? 500 : $rows;
		$offset	 = ($offset == '' || $offset == 0) ? 0 : $offset;
		$return = $this->tiku_model->rankComrade($userInfo,$type);
		if (!empty($return)) {
                    foreach ($return as $k=>$v){
                        //经验值四舍五入
                        $return[$k]['experience'] = round($v['experience']);
                        //二维数组结果集进行排序的列
                        $sort[$k] = round($v['experience']);
                        //加入用户宠物当前的心情
                        $return[$k]['pet_status'] = $this->user_model->getPetMood($v['user_id']);
                        //用户的地区学科信息
                        $return[$k]['tag'] = $this->user_model->getLocationNameSubjectType($v['location_id'], $v['subject_type']);
                    }
                    //二维数组结果集进行排序
                    array_multisort($sort, SORT_DESC, $return);	
		    $return = array_slice($return,$offset,$rows,true);
		}
		if (is_array($return))
		{
			$response = $this->tiku->formatResponse('1',$return,'','');
		} else {
			$response = $this->tiku->formatResponse('0','','10021','战友排行榜数据出错');
		}
		echo json_encode($response);exit();
	}
	
	/*
	 * 获取学霸推荐列表
	 * rows			:limit row offset
	 * offset		:limit row offset
	 */
	public function getTopstudentList()
	{
		$rows	 	= $this->input->post("rows",true);
		$offset		= $this->input->post("offset",true);
		$rows    = ($rows == '' || $rows == 0) ? 20 : $rows;
		$offset	 = ($offset == '' || $offset == 0) ? 0 : $offset;
		$userInfo = $this->checkUser();
                $userId = $userInfo['user_id'];
		$return 	= $this->tiku_model->getTopstudentList($userInfo,$rows,$offset);
		if (!empty($return)) {
                    foreach ($return as $k=>$v){
                        //经验值在前台显示整数
                        $return[$k]['experience'] = round($v['experience']);
                        //加入用户的宠物状态
                        $return[$k]['pet_status'] = $this->user_model->getPetMood($v['user_id']);
                        $return[$k]['is_follow'] = $this->tiku_model->isFollow($userId,$v['user_id']);
                    }
		}
		if (is_array($return))
		{
                    $response = $this->tiku->formatResponse('1',$return,'','');
		} else {
                    $response = $this->tiku->formatResponse('0','','10023','获取学霸推荐列表数据出错');
		}
		echo json_encode($response);exit();
	}
	
	/*
	 * 获取消息列表
	 */
	public function getMessageList()
	{
		$userInfo = $this->checkUser();
		$return   = $this->user_model->getMessageList($userInfo);
		if (is_array($return))
		{
			$response = $this->tiku->formatResponse('1',$return,'','');
		} else {
			$response = $this->tiku->formatResponse('0','','10024','获取消息列表,数据出错');
		}
		echo json_encode($response);exit();
	}
	
	/*
	 * 将消息置为已读
	 * message_id	:消息id
	 */
	public function changeMessageStatus()
	{
		$message_id = $this->input->post("message_id",true);
		if (empty($message_id)){
			$response = $this->tiku->formatResponse('0','','','参数错误');
			echo json_encode($response);exit();
		}
		$userInfo = $this->checkUser();
		$return = $this->user_model->changeMessageStatus($userInfo,$message_id);
		if ($return)
		{
			$response = $this->tiku->formatResponse('1',array('message_id'=>$message_id),'','');
		} else {
			$response = $this->tiku->formatResponse('0','','10025','将消息置为已读数据出错');
		}
		echo json_encode($response);exit();
	}
	
	/*
	 * 根据uid判断用户是否存在
	 */
	public function checkUserExists($user_id)
	{
		$result = $this->tiku_model->checkUserExists($user_id);
		if (!$result)
		{
			$response = $this->tiku->formatResponse('0','','10037','无效的用户Id');
			echo json_encode($response);exit();
		} 
	}
	
	/*
	 * curl的post方法
	 */
	public function vpost($url,$data)
	{ 																			// 模拟提交数据函数
		$curl = curl_init();											   	 	// 启动一个CURL会话
		curl_setopt($curl, CURLOPT_URL, $url); 									// 要访问的地址
		curl_setopt($curl, CURLOPT_POST, 1); 									// 发送一个常规的Post请求
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data); 							// Post提交的数据包
		curl_setopt($curl, CURLOPT_TIMEOUT, 30); 								// 设置超时限制防止死循环
		curl_setopt($curl, CURLOPT_HEADER, 0); 									// 显示返回的Header区域内容
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); 							// 获取的信息以文件流的形式返回
		$tmpInfo = curl_exec($curl); 											// 执行操作
		if (curl_errno($curl)) {
			echo 'Errno'.curl_error($curl);
		}
		curl_close($curl); 														// 关键CURL会话
		return $tmpInfo; 														// 返回数据
	}
    
    /**
     * 获取用户的未读消息数 
     */
    public function newMessageCount()
    {
        $userInfo = $this->checkUser();//用户验证
        $userId = $userInfo['user_id'];
        $newCount = $this->user_model->getNewMessageCount($userId);
        $formatedData = $this->tiku->formatResponse(1, array('new_count' => intval($newCount)));
        
        exit(json_encode($formatedData));
    }
    
    /*
     * 邀请好友页面
     */
    public function invite()
    {

    	$this->load->view('static/tiku/invite');
    }
    
    /**
     * 获取用户的答题卡
     */
    public function answerCard()
    {
        $userInfo = $this->checkUser();//用户验证
        $userId   = $userInfo['user_id'];
        $practiceId = (int)$this->input->post('practice_id');
        $subjectId  = (int)$this->input->post('subject_id');
        $time = (int)$this->input->post('time');
        $type = in_array($this->input->post('type'), array(1, 2, 3)) ? $this->input->post('type') : 1;
        $param = array('userId' => $userId, 'practiceId' => $practiceId, 'subjectId' => $subjectId, 'time' => $time, 'type' => $type);
        $answerCard = $this->question_model->getUserAnswerCard($param);
        if(!is_array($answerCard)) {
            $formatedData = $this->tiku->formatResponse(0, '', 10035, '获取答题卡失败,请返回重试');
        } else {
            $formatedData = $this->tiku->formatResponse(1, $answerCard);
        }
        
        exit(json_encode($formatedData));
    }
    
    /**
     * 更新爱刷题用户中安装其他应用的用户数量
     * (目前只统计猿题库的安装用户数量)
     */
    public function record_package_user_amount()
    {
        $package_1 = (int)$this->input->post('package_1');
        $this->user_model->update_package_stat($package_1);
    }
}
