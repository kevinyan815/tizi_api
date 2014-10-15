<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');


Class User_model extends MY_Model
{
    private static $_userTable = 'user_info';

    public function __construct($database = 'tiku') {
        parent::__construct($database);
    }
       
    /**
     * 获取用户当前宠物的情绪,主要根据用户上次闯关成功的时间来计算.
     * @param type $userId 用户Id
     * return int 1:代表宠物高兴,2:宠物饥饿,3:宠物冷淡
     */
    public function getPetMood($userId)
    {
        if(!$userId) {
            return false;
        }
        
        $sql = "SELECT date FROM stu_practice_log WHERE userId={$userId} AND result=1
                ORDER BY date DESC LIMIT 1";
        $res = $this->db->query($sql)->row_array();
        if(!$res || !$res['date']) {
            #如果练习记录中没有成功记录,查出用户第一次练习的时间
            $sql = "SELECT date FROM stu_practice_log WHERE userId={$userId} AND result=0
                    ORDER BY date ASC LIMIT 1";
            $row = $this->db->query($sql)->row_array();
            if(!$row['date']) {
                #用户没有做过题,宠物状态默认为高兴
                return 1;
            } else {
                $date = $row['date'];
            }
        } else {
            $date = $res['date'];
        }
        $timeInterval = time() - strtotime($date);
        if($timeInterval <= 2 * 86400) {
            return 1;
        } else if ($timeInterval < 5 * 86400){
            return 2;
        } else {
            return 3;
        }
    }
    
    /**
     * 获取用户练习首页的数据
     * @param int $userId 用户Id
     * @param int $subjectId 学科Id
     * @return array 返回用户错题本,收藏夹,练习本对应的题目数目(练习本是练习数目)
     */
    public function getPracticeIndex($userId, $subjectId)
    {
        if(!$userId || !$subjectId) {
            return false;
        }
        
        $condition = " AND userId={$userId} AND subjectId={$subjectId}";
        $data = array();
        #错题本
        $sql = "SELECT COUNT(*) AS wrongNums FROM stu_answer_log WHERE 1 $condition AND isCorrect=0";
        $res = $this->db->query($sql)->row_array();
        $data['error_count'] = $wrongNums = $res['wrongNums'];
        #收藏夹
        $sql = "SELECT COUNT(*) AS favNums FROM user_favorites WHERE 1 $condition";
        $res = $this->db->query($sql)->row_array();
        $data['collect_count'] = $res['favNums'];
        #练习本
        $sql = "SELECT COUNT(*) AS practiceNums FROM stu_practice_log WHERE 1 $condition";
        $res = $this->db->query($sql)->row_array();
        $data['practice_count'] = $res['practiceNums'];
        #当前学科下的刷题数
        $sql = "SELECT COUNT(*) AS total_question FROM stu_answer_log WHERE 1 $condition";
        $res = $this->db->query($sql)->row_array();
        $data['total_question'] = $totalNums = $res['total_question'];
        #正确率
        $correctNums = $totalNums - $wrongNums;
        $correctRate = $totalNums ? ($correctNums / $totalNums) : 0;
        $data['correct_rate'] = sprintf('%4.2f', $correctRate);
        
        return $data;
        
    }
    
    
    /**
     * 获取用户练习本列表数据
     * @param  array $param
     *         $param['userId] 用户Id
     *         $param['subjectId] 学科Id
     */
    public function getUserPracticeList($param)
    {
        $defaultParam = array(
            'userId' => 0,
            'subjectId' => 0,
			'offset' => 0,
			'num' => 50,
        );
        $param = array_merge($defaultParam, $param);
        extract($param);
        if(!$userId || !$subjectId) {
            return false;
        }
        
        $sql = "SELECT id AS practice_id, practiceName AS topic,(correctNums + incorrectNums) AS total_count, incorrectNums AS error_count, result AS is_pass, date
                FROM stu_practice_log WHERE userId={$userId} AND subjectId={$subjectId} ORDER BY date DESC";
        $res = $this->db->query($sql)->result_array();
        if(is_array($res)) {
            foreach($res as &$val) {
                $val['time'] = strtotime($val['date']);
            }
        }
       
        return $res;
    }
    
    /**
     * 写入用户练习记录
     * @param array $insertData 要插入的数据. key为字段名,值为字段值
     * @return int $practiceId 练习Id
     */
    public function insertPractice($insertData)
    {
        if(!$insertData) {
            return false;
        }
        
        $this->db->insert('stu_practice_log', $insertData);
        return $this->db->insert_id();
    }
    
    /**
     * 更新用户的基本统计信息(exp, questionNums)
     * @param array $userData  用户要更新的数据
     * @reutnr int|bool  成功后返回用户当前经验值,失败返回false
     */
    public function updateBaseUserInfo($userData)
    {
        $sql = "SELECT exp FROM user_info WHERE userID={$userData['userId']}";
        $userInfo = $this->db->query($sql)->row_array($sql);
        $currentExp = $userData['exp'] + $userInfo['exp'];
        $sql = "UPDATE user_info SET exp={$currentExp} WHERE userId={$userData['userId']}";
        $res = $this->db->query($sql);
        if($res) {
            return $currentExp;
        } else {
            return false;
        }
    }
    
    /**
     * 添加用户题目收藏
     * @param int $userId 用户Id
     * @param int $questionId 题目Id
     * @param int $subjectId 学科Id
     */
    public function addUserFavorite($userId, $questionId, $subjectId)
    {
        $data = array(
            'userId' => $userId,
            'qId'    => $questionId,
            'subjectId' => $subjectId,
            'date' => date('Y-m-d'),
            'time' => time(),
        );
        
        $result = $this->db->insert('user_favorites', $data);
        
        return $result ? TRUE : false;
    }
       
    /**
     * 获取省市地区列表
     * @return array
     */
    public function getLocationList()
    {
        $sql = "SELECT id, name FROM location ORDER BY spell ASC";
        
        return $this->db->query($sql)->result_array();
    }
    
    /**
     * 设置用户的地区和学科分类信息
     * @param int $userId 用户Id
     * @param int $locationId 地区Id
     * @param int $subjectType 学科分类
     * @return bool TRUE|FALSE
     */
    public function setUserRegionCatalog($userId, $locationId, $subjectType)
    {
        $sql = "UPDATE user_info SET locationId={$locationId}, subjectType={$subjectType}
                WHERE userId={$userId}";
                
        return $this->db->query($sql);
    }
	
	/*
	 * 个人主页 用户题目信息
	 */
	function getUserInfoAbout($user_id)
	{
		$person = array();
		//用户的总做题数
		$question_nums = $this->db->query('select count(id) as nums from stu_answer_log where userId = '.$user_id)->row_array();
		$person['finish'] = empty($question_nums) ? 0 :$question_nums['nums'];
		//用户做对题目的总数
		$Correct_nums = $this->db->query('select count(id) as nums from stu_answer_log where userId = '.$user_id.' and isCorrect=1')->row_array();
		$Correct_nums = empty($Correct_nums) ? 0 :$Correct_nums['nums'];
		$person['right_rate'] = $person['finish'] == 0 ? 0 : round($Correct_nums/$person['finish'],2);
		//使用总天数
		$days =  $this->db->query('select date,count(date) from stu_answer_log where userId = '.$user_id .' group by date')->result_array();
		if(empty($days)){
			$person['total_use'] = 0;
		}else{
			$arr = array();
			foreach ($days as $k=>$v){
				$arr[] = $v['date'];
			}
			$person['total_use'] = count($arr);
		}
		//宠物心情
		$petMood = $this->getPetMood($user_id);
		$person['pet_status'] = $petMood;
		
		//超过0级的知识点
		$knowledge = $this->getScoredKnowledge($user_id);
		$return = array();
		$return['user_info'] = $person;
		$return['knowledge_info'] = $knowledge;
		return $return;
	}
	

	
	/*
	 * 添加战友发送消息
	 */
	public function sendMessage($userInfo,$data)
	{
		$this->db->insert('mail_box',$data);
		$return = $this->db->affected_rows();
		return $return > 0 ? true :false;
	}
	
	/**
	 * 获取用户的地区名称+文理科信息
         * @param int $locationId 地区Id
         * @param int $subjectType  学科类型
	 */
	public function getLocationNameSubjectType($locationId,$subjectType)
	{
            if($locationId && $subjectType) {
                $locationName = $this->db->query("select name from location where id=".$locationId)->row_array();
                switch ($subjectType) {
                    case 1:$subject_type = '理科';break;
                    case 2:$subject_type = '文科';break;
                    case 3:$subject_type = '文理综合';break;
                    default:'';break;
                }
                $data = $locationName['name'].$subject_type;
            } else {
                $data = '';
            }
		
            return $data;
	}
	
	
	
	/*
	 * 获取消息列表
	 */
	public function getMessageList($userInfo)
	{
		$result = $this->db->query("select title,type,id as message_id,content,time,url,readStatus as had_read,
				sendId as user_id from mail_box where receiveId = ".$userInfo['user_id']." and delStatus!=2 order by id desc")->result_array();
		return $result;
	}
	
	/*
	 * 将消息置为已读
	 */
	public function changeMessageStatus($userInfo,$message_id)
	{
		$this->db->update('mail_box',array('readStatus'=>'1'),array('id'=>$message_id));
		$result = $this->db->affected_rows();
		return $result > 0 ? true : false;
	}

	
	/**
	* 获取错题本或者收藏夹列表
	* @param  array $param
	*         $param['userId] 用户Id
	*         $param['subjectId] 学科Id
	*         $param['bookType'] error:错题本 favorite:收藏夹
	* @return array
	*/
	public function getUserBookList($param = array())
	{
		$defaultParam = array(
		'userId' => 0,
		'subjectId' => 0,
		'bookType' => 'error',
		'offset' => 0,
		'num' => 50
		);
		$param = array_merge($defaultParam, $param);
		extract($param);
		
		if(!$userId || !$subjectId) {
			return false;
		 }
		
		 $tables['error']    = 'stu_answer_log';
		 $tables['favorite'] = 'user_favorites';
		 $where = '';
		 if ($bookType == 'error')
		 {
		 	$where .=' and isCorrect=0 ';
		 }
		 $sql = "SELECT COUNT(id) AS questionNums, date FROM {$tables["{$bookType}"]} 
		 WHERE userId={$userId} AND subjectId={$subjectId} ".$where." 
		 GROUP BY date ORDER BY date DESC LIMIT {$offset}, {$num}";
		 $result = $this->db->query($sql)->result_array();
		 return $result;
	}
		
	
	
	/**
	 * 获取用户完全掌握的知识点(目前只显示一级知识点)
	 * @param int $userId 用户Id
	 * @return array 返回已完全掌握的知识点, 没有则返回空数组
	 */
	public function getMaxLevelKnowledge($userId)
	{
		if(!$userId) {
			return false;
		}
		#已掌握的知识点就是kLevel=6的知识点
		$sql = "SELECT k.name, k.id AS knowledge_id FROM knowledge k LEFT JOIN stu_knowledge stu
		ON k.id=stu.kId   WHERE stu.userId={$userId} AND stu.kLevel = 6";
		$query = $this->db->query($sql);
	
		return $query->result_array();
	}
        
        /**
         * 获取用户知识点等级大于0的所有知识点,包括各知识点的等级情况
         * @param int $userId 用户Id
         * @return array 知识点组成的数组,没有则返回空数组
         */
        public function getScoredKnowledge($userId)
        {
            if(!$userId) {
                return false;
            }
            $subjectArr = array(1 => '语文', 2 => '英语', 3 => '理数', 4 => '文数', 5 => '物理', 6 => '化学', 7 => '生物', 8 => '历史', 9 => '地理', 10 => '政治');
            $sql = "SELECT k.name, k.id AS knowledge_id, stu.kLevel AS level, k.grade, k.subjectId FROM knowledge k LEFT JOIN stu_knowledge stu
                    ON k.id=stu.kId WHERE stu.userId={$userId} AND k.grade=1 AND stu.kLevel > 0";
            $query = $this->db->query($sql);
            $result = $query->result_array();
            $sortCol = $knowledgeArr = array();
            foreach($result as $val) {
                $sortCol[] = $val['level'];
                $val['subject_name'] = $subjectArr[$val['subjectId']];
                $val['max_level'] = $val['grade'] == 1 ? 6 : 4;//父知识点的上限是6,子知识点是4
                $knowledgeArr[] = $val;
            }
            array_multisort($sortCol, SORT_DESC, $knowledgeArr);//以知识点的level倒序排列

            return $knowledgeArr;
        }
        
        /**
         * 获取用户未读消息数
         * @param int $userId 用户Id
         * @return int $newCount 用户未读消息树
         */
        public function getNewMessageCount($userId)
        {
            if(!$userId) {
                return false;
            }
            $sql = "SELECT COUNT(*) AS new_count FROM mail_box WHERE receiveId={$userId}  AND readStatus = 0 AND delStatus != 2";
            $res = $this->db->query($sql)->row_array();
            $newCount = $res['new_count'];
            
            return $newCount;
        }
        
        /**
         * 更新app包用户数量统计表
         * @param $package_1 包1代表猿题库
         */
        public function update_package_stat($package_1 = 0)
        {
            if($package_1) {
                $sql = "UPDATE app_package_statistics SET userAmount = userAmount + 1 WHERE id = 1";
                $this->db->query($sql);
            }
        }
        	
}

