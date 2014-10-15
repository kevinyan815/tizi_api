<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * 移动题库题目模型
 */
class Question_model extends MY_Model
{
    public function __construct($database = 'tiku') {
        parent::__construct($database);
    }
    
    /**
     * 插入用户答题记录
     * @param array $answerData 用户的答题数据
     * @return int $insertId 返回记录Id 
     */
    public function insertUserAnswer($answerData)
    {
        $this->db->insert('stu_answer_log', $answerData);
        
        return $this->db->insert_id();
    }
    
    
    /**
     * 获取用户答题卡
     * @param array $param
     *              $param['userId']用户Id
     *              $param['practiceId'] 练习Id
     *              $param['subjectId']  学科Id
     *              $param['time']      时间戳
     *              $param['type']  类型 1:错题本, 2:收藏夹, 3:练习记录
     * @return array
     */
    public function getUserAnswerCard($param)
    {
        extract($param);
        if(!($userId && $subjectId && $type)) {
            return false;
        }
        #每个类型对应的数据表
        $tables = array(1 => 'stu_answer_log', 2 => 'user_favorites', 3 => 'stu_answer_log');
        $table = $tables[$type];
        $condition = " userId={$userId} AND subjectId={$subjectId}";
        switch($type) {
            case 1:#错题本
                $sql = "SELECT qId AS qid, isCorrect AS `right` FROM {$table} WHERE {$condition} AND date='" . date('Y-m-d', $time) . "' AND isCorrect=0 ORDER BY id ASC";
                break;
            case 2:#收藏夹
                $sql = "SELECT qId AS qid FROM {$table} WHERE {$condition} AND date='" . date('Y-m-d', $time) . "' ORDER BY id ASC";
                break;
            case 3:#练习记录
                $sql = "SELECT qId AS qid, isCorrect AS `right` FROM {$table} WHERE {$condition} AND practiceId={$practiceId} ORDER BY id ASC";
                break;
        }
        $data = $this->db->query($sql)->result_array();
        if($type == 2 && is_array($data)) {
            #收藏题目列表中加上题目的答题结果
            foreach($data as &$val) {
                $sql = "SELECT isCorrect AS `right` FROM stu_answer_log WHERE qId={$val['qid']} AND userId={$userId}  ORDER BY id DESC LIMIT 1";
                $row = $this->db->query($sql)->row_array();
                $val['right'] = intval($row['right']);
            }
            unset($val);#断开引用
        }
        
        if(is_array($data)) {
            foreach($data as &$val) {
                #列表中加入,题目是否是材料题
                $sql = "SELECT materialId FROM questions WHERE id={$val['qid']}";
                $row = $this->db->query($sql)->row_array();
                $val['has_article'] = $row['materialId'] ? 1 : 0;
            }
        }
        
        return $data;
    }
    
    
    /**
     * 获取用户历史做题记录详细题目
     * @param array $param
     *        $param['userId']用户id
     *        $param['subjectId']学科Id
     *        $param['start']开始序号
     *        $param['end']结束序号
     *        $param['type'] 类型1,表示错题本试题，2表示收藏夹试题，3表示历史练习题 
     *        $param['time'] 时间戳
     *        $param['practiceId'] 练习Id, 只在type=3时起作用     
     */
    public function getHistoricQuestions($param = array())
    {
        $defaults = array(#参数默认值
            'userId' => 0,
            'qIds'   => 0,
        );
        
        $param = array_merge($defaults, $param);
        extract($param);
        $qIdArr = explode(',', $qIds);
        $questionArr = array();
        foreach($qIdArr as $qId) {
            $question = $this->buildQuestionArray($qId, $userId);
            if($question['title'] && $question['content'])
            {
                #把材料数据都放到article_info下
                $question['article_info']['title'] = $question['title'];
                $question['article_info']['content'] = $question['content'];
                $question['article_info']['article_id'] = $question['article_id'];
                $question['is_article'] = 1;
            }
            unset($question['title'], $question['content'], $question['article_id']);
            #获取试题的解析
            $resolve_info = $this->questionAnalysis(array($qId), $userId);
            if(isset($resolve_info[0])) {
                $question['resolve_info'] = $resolve_info[0];
            }
            $questionArr[] = $question;
            
        }
        
        return $questionArr;
    }
	
	/*
	 * 根据知识点id查找知识点名称、学科id、知识点的等级
	 */
	public function getKnowledgeInfo($kid)
	{
		return $this->db->query('select id,name,subjectId,grade  from knowledge where id = '.$kid)->row_array();
	}
	
	/*
	 * 根据一级知识点找出旗下的二级知识点,将不在该地区的的二级知识点放在list数组中
	 */
	public function getKidNotInLocation($params)
	{
		$kid_arr = $this->db->query("select id from knowledge where parentId = ".$params['kid'])->result_array();
		$list = array();
		if (!empty($kid_arr))
		{
			foreach ($kid_arr as $k=>$v)
			{
				$num = $this->db->query("select kId from knowledge_in_location where kId=".$v['id']." and locationId = ".$params['location_id'])->num_rows();
				if( $num == 0 )
				{
					$list[] = $v['id'];
				}
			}
		}
		return $list;
	}
	
	/*
	 * 材料题
	 * 如果一级知识点下有二级知识点，找出不在该地区的二级知识点对应的材料题id
	 */
	public function getMaterialIdNotInLocation($data){
		$result = array();
		foreach ($data as $key=>$val)
		{
			$result[] =  $this->db->query("select distinct q.materialId from questions q
			left join  knowledge_question_rel kqr on q.id=kqr.qId
			where  kqr.kId=".$val." and q.status = 2")->result_array();
		}
		$list = array();
		if (!empty($result)){
			foreach ($result as $k=>$v){
				if($v){
					foreach ($v as $kk=>$vv){
						$list[$vv['materialId']] = $vv['materialId'];
					}
				}
			}
		}
		return $list;
		

	}
	
	/*
	 * 材料题
	 * 获取该知识点对应的所有题目的材料id(审核通过的,材料id去重)
	 */
	public function handleAllMaterialId($params,$questionRange){
		$result =  $this->db->query("select distinct q.materialId,qs.questionRange from questions q
				left join  knowledge_question_rel kqr on q.id=kqr.qId
				left join question_source qs on q.sourceId=qs.id
				where  kqr.kId=".$params['kid']." and q.status = 2")->result_array();
		if (empty($result)) {
			return array('status'=>false,'errMsg'=>'该知识点下没有题目（材料题）');
		}
		$temp = array();
		foreach($result as $key=>$val)
		{
			if(in_array($val['questionRange'], $questionRange))
			{
				$temp[$val['materialId']] = $val['materialId'];
			}
		}
		if (empty($temp)) {
			return array('status'=>false,'errMsg'=>'根据知识点,该用户选择的出题范围内没有题目');
		}
		return array('status'=>true,'all_ids'=>$temp);
	}
	
	/*
	 * 材料题
	* 获取用户已做题目的材料id(去重,该学科下的知识点)
	*/
	public function getDoneMaterialId($params)
	{
		$result = $this->db->query("select distinct q.materialId from questions q
				left join  knowledge_question_rel kqr on q.id=kqr.qId
				left join stu_answer_log sal on q.id=sal.qId 
				where  sal.userId =".$params['uid']." and kqr.kId=".$params['kid'])->result_array();
		if (empty($result)) {
			return $result;
		}
		$return = array();
		foreach ($result as $k=>$v)
		{
			$return[$v['materialId']] = $v['materialId'];
		}
		return $return;
	}
	
	/*
	 * 材料题
	* 通过一个材料id，获取一个完整的材料题
	*/
	public function getQuestionByMaterialId($params,$MaterialId)
	{
		$material_info = $this->db->query('select title,content,id as article_id from materials where id='.$MaterialId)->row_array();
		$material_info['is_article'] = 1;				//材料题
		$question = $this->db->query("select q.id as question_id,q.multiOpts as multi_answer,q.subjectId as subject_id,
				q.question,q.answer,qs.briefName as tag from questions q
				left join question_source qs on q.sourceId=qs.id
				where q.materialId= ".$MaterialId."  order by q.m_question_order asc")->result_array();
	
		foreach ($question as $k=>$v)
		{
                    $question[$k]['question'] = !empty($v['tag']) ? '('. $v['tag'] .')' . $v['question'] : $v['question'];
                    $question[$k]['tag'] = '';
                    $result = $this->db->query('select id from user_favorites where qId='.$v['question_id'] . ' and userId=' . $params['uid'])->num_rows();
                    if ($result > 0)
                    {
                            $question[$k]['is_collect'] = 1;		//收藏
                    } else {
                            $question[$k]['is_collect'] = 0;		//未收藏
                    }
                    $result_opt = $this->db->query('select opt,optContent  from options where qid='.$v['question_id']." order by opt asc")->result_array();
                    $option = array();
                    foreach ($result_opt as $key=>$val){
                            $option[$key]['key'] = $val['optContent'];
                            //$option[$key]['key'] = htmlspecialchars($val['optContent']);
                    }
                    $question[$k]['option'] = $option;
		}
		//$material_info['content'] = htmlspecialchars($material_info['content'], ENT_QUOTES);
		$material_info['question_info'] = $question;
	
		return $material_info;
	}
	
	/*
	 * 非材料题
	 * 如果一级知识点下有二级知识点，找出不在该地区的二级知识点对应的题目
	 */
	public function getQidNotInLocation($data){
		$result = array();
		foreach ($data as $key=>$val)
		{
			$result[] =  $this->db->query("select q.id from questions q
					left join knowledge_question_rel kqr on q.id=kqr.qId
					where kqr.kId=".$val." and q.status = 2")->result_array();
		}
		$list = array();
		if (!empty($result)){
			foreach ($result as $k=>$v){
				if($v){
					foreach ($v as $kk=>$vv){
						$list[$vv['id']] = $vv['id'];
					}
				}
			}
		}
		return $list;
	}
	
	/*
	 * 非材料题
	 * 获取该知识点下的所有题目(学生选择的出题范围内的题)
	 */
	public function handleAllQid($params,$questionRange){
		$result =  $this->db->query("select q.id,qs.questionRange from questions q
				left join knowledge_question_rel kqr on q.id=kqr.qId
				left join question_source qs on q.sourceId=qs.id where kqr.kId=".$params['kid']." and q.status = 2")->result_array();
	   if (empty($result)) {
	   		return array('status'=>false,'errMsg'=>'该知识点下没有题目');
	   }
	   	$temp = array();
	   	foreach($result as $key=>$val)
	   	{
	   		if(in_array($val['questionRange'], $questionRange))
	   		{
	   			$temp[$val['id']] = $val['id'];
	   		}
	   	}
		if (empty($temp)) {
			return array('status'=>false,'errMsg'=>'根据知识点,该用户选择的出题范围内没有题目');
		}
		return array('status'=>true,'all_ids'=>$temp);
	}
	

	
	
	/*
	 * 非材料题
	 * 通过知识点id获取该知识点已做过的题目id
	 */
	public function getDoneQuestionByKid($params)
	{
		$result =  $this->db->query('select distinct sal.qId from stu_answer_log sal
				left join  knowledge_question_rel kqr on sal.qId=kqr.qId
				where  sal.userId ='.$params['uid'].' and  kqr.kId='.$params['kid'])->result_array();
		if (empty($result)) {
			return $result;
		}
		$return = array();
		foreach ($result as $k=>$v)
		{
			$return[$v['qId']] = $v['qId'];
		}
		return $return;
	}
	
	/*
	 * 非材料题
	* 通过数组里面的题目id 组装题目信息
	*/
	function buildQuestionArray($id,$uid)
	{
		//	根据题目id 获取题目信息
		$question = $this->db->query('select q.id as question_id,q.multiOpts as multi_answer,
				q.answer,q.question,q.subjectId as subject_id,qs.briefName as tag,
				q.materialId as article_id,m.title,m.content from questions q
				left join materials m on q.materialId=m.id
				left join question_source qs on q.sourceId=qs.id where q.id='.$id)->row_array();
		$question['is_article'] = 0;		//非材料题
		//	根据题目id 获取该题目的选项
		$result  = $this->db->query('select opt,optContent  from options where qid ='.$id." order by opt asc")->result_array();
		$option = array();
		if ($result) {
			foreach ($result as $k=>$v)
			{
				$option[$k]['key'] = $v['optContent'];
			}
		}
	
		//	根据题目id、用户id 获取该题目的收藏状态
		$collect = $this->db->query('select id from user_favorites where  qId='.$id.' and userId='.$uid)->num_rows();
		if ( $collect > 0) {
			$question['is_collect'] = 1;    //收藏
		} else {
			$question['is_collect'] = 0;	//未收藏
		}
	
		$question['option'] = $option;
                $question['question'] = !empty($question['tag']) ? '('. $question['tag'] .')' . $question['question'] : $question['question'];
                $question['tag'] = '';
		return $question;
	}
	


	/*
	 * 设置出题范围
	 */
	public function setQuestionRange($userInfo,$range)
	{
		$this->db->query('delete from user_question_range where userId = '.$userInfo['user_id']);
		foreach ($range as $k=>$v)
		{
			$data = array(
					'userId'=>$userInfo['user_id'],
					'questionRange'=>$v,
					'createTime'=>date('Y-m-d H:i:s')
					);
			$this->db->insert('user_question_range',$data);
			$result = $this->db->affected_rows();
		}
		return $result > 0 ? array('done'=>1) : array('done'=>0);
	}
	
	/*
	 * 根据uid，获取出题范围
	 */
	public function getQuestionRange($uid)
	{
		$result = $this->db->query('select questionRange  from user_question_range where userId = '.$uid)->result_array();
		$return = array();
		foreach ($result as $k=> $v)
		{
			$return[]  = $v['questionRange'];
		}
		return $return;
	}
	
	/*
	 * 试题解析
	 */
	public function questionAnalysis($question_ids,$user_id)
	{
		$arr	= array();
		$return = array();
		foreach ($question_ids as $k=>$v)
		{
		    //用户选择的答案
		    $info = $this->db->query("
		            select selected from stu_answer_log 
		            where userId = ".$user_id." and qId = ".$v." order by id desc limit 1")->row_array();
		    $arr['selected'] = $info['selected'];
			//解析内容
			$analysis = $this->db->query('select id,analysis from questions where id = '.$v)->row_array();
			if ( empty($analysis['id']) )
			{
			    continue;
			}
			$arr['question_id'] = $analysis['id'];
			$arr['resolve'] = $analysis['analysis'];
			//题目统计
			$statistics = $this->questionStatistics($v);
			//相关考点
			$query = $this->db->query('select k.name from knowledge_question_rel kqr
					left join knowledge k on kqr.kId = k.id
					where kqr.qId = '.$v)->result_array();
			$result = array();
			foreach ($query as $key=>$val)
			{
				$result[]= $val['name'];
			}
			$relative_knowledge = implode(',', $result);
			$arr['statistics'] = $statistics;
			$arr['relative_knowledge'] = $relative_knowledge;
			$return[] =$arr;
		}
		return $return;
	}
	
	/*
	 * 题目统计
	 */
	public function questionStatistics($question_id)
	{
		//题目被做总次数
		$total = $this->db->query('select count(userId) as nums from stu_answer_log where qId = '.$question_id)->row_array();
		$total['nums'] = empty($total) ? 0 :$total['nums'];
		//题目正确率
		$right = $this->db->query('select count(userId) as nums from stu_answer_log where qId = '.$question_id.' and isCorrect = 1')->row_array();
		$correctPercent = empty($total['nums']) ? 0 :round($right['nums']/$total['nums'],2)*100; 
		//易错项
		$frequentError = $this->db->query("SELECT selected,COUNT(id) as num FROM stu_answer_log 
				where qId=".$question_id." and isCorrect=0 GROUP BY selected ORDER BY num DESC LIMIT 1")->row_array();
		if ($frequentError)
		{
			$result = "本题总共作答".$total['nums']."次\n正确率 ".$correctPercent."%\n易错项为".$frequentError['selected'];
		} else {
			$result = "本题总共作答".$total['nums']."次\n正确率".$correctPercent."%.";
		}
		return $result;
	}
	
	/*
	 * 解析题目收藏
	 */
	public function questionCollect($userInfo,$question_id,$subject_id)
	{
		$result = $this->db->query('select id from user_favorites where  
				qId = '.$question_id.' and userId = '.$userInfo['user_id'])->row_array();
		$data = array(
				'userId'=>$userInfo['user_id'],
				'subjectId'=>$subject_id,
				'qId'=>$question_id,
				'date'=>date('Y-m-d',time()),
				'time'=>time()
		);
		if ($result)
		{
			$this->db->query("update user_favorites set date = '".date('Y-m-d',time())."',time = ".time()." where id = ".$result['id']);
			$rows = $this->db->affected_rows();
		} else {
			$this->db->insert('user_favorites',$data);
			$rows = $this->db->affected_rows();
		}
		return $rows > 0 ? true : false; 
	}
	
	/*
	 * 解析题目取消收藏
	 */
	public function questionUnCollect($userInfo,$question_id,$subject_id)
	{
		$this->db->query('delete from user_favorites where qId = '.$question_id.' and userId = '.$userInfo['user_id']);
		$rows = $this->db->affected_rows();
		return $rows > 0 ? true : false; 
	}
	
	/*
	 * 根据学科id获取该用户已经做过的题
	*/
	public function getDoneQuestionBySubjectId($params)
	{
		$result = $this->db->query('select distinct qId  from stu_answer_log 
				where userId = '.$params['uid'].' and subjectId = '.$params['subject_id'])->result_array();
		if (empty($result)) {
			return $result;
		}
		$return = array();
		foreach ($result as $k=>$v)
		{
			$return[$v['qId']] = $v['qId'];
		}
		return $return;
	}
	
	/*
	 * 根据学科id和地区id,得到所有知识点id,再根据这些知识点获取对应的题
	*/
	public function getAllQuestionBySubjectId($knowledgeArr,$questionRange)
	{ 
		$result = array();
		foreach ($knowledgeArr as $k=>$v)
		{
			$result[] =  $this->db->query("select q.id,qs.questionRange from questions q
					left join knowledge_question_rel kqr on q.id=kqr.qId
					left join question_source qs on q.sourceId=qs.id 
					where kqr.kId=".$v['kId']." and q.status = 2")->result_array();
			
		}
		if (empty($result)) {
			return array('status'=>false,'errMsg'=>'根据学科id获取所有的题数据出错');
		}
		$temp = array();
		foreach ($result as $key=>$val)
		{
			foreach ($val as $kk=>$vv){
				if(in_array($vv['questionRange'], $questionRange))
				{
					$temp[$vv['id']] = $vv['id'];
				}
			}
		}

		if (empty($temp)) {
			return array('status'=>false,'errMsg'=>'根据学科id,该用户选择的出题范围内没有题目');
		}
		return array('status'=>true,'all_ids'=>$temp);
	}
	
	/*
	 * 检查地区id是否有效
	 */
	public function checkLocationId($location_id){
		if (empty($location_id))
		{
			return false;
		}
		$return = $this->db->query("select id from location where id = ".$location_id)->num_rows();
		return $return > 0 ? true : false;
	}
	
	/*
	 * 检查题目id是否有效
	 */
	public function checkQuestionId($question_id)
	{
	    $num = $this->db->query("select id from questions where id = ".$question_id." and status=2")->row_array();
	    return $num > 0 ? true : false;
	}
        
        /**
         * 添加用户题目反馈
         * @param array $paramArr 要新增的反馈数据
         * @return boolean 
         */
        public function addQuestionFeedback($paramArr)
        {
            $this->db->insert('question_feedback', $paramArr);
            $insertId = $this->db->insert_id();
            
            return $insertId ? TRUE : FALSE;
        }
        
        /**
         * 记录用户放弃做题时的数据
         * @param array $paramArr 要新增的反馈数据
         * @return boolean 
         */
        public function logPracticeGiveUp($paramArr)
        {
            extract($paramArr);
            $insertData = array();
            foreach($userAnswer as $qId => $selected) {
                $insertData['qId'] = $qId;
                $insertData['selected'] = $selected;
                $insertData['isCorrect'] = $rightDetail[$qId];
                $insertData['subjectId'] = $subjectId;
                $insertData['cancelTime'] = $cancelTime;
                $insertData['userId'] = $userId;
                $this->db->insert('practice_give_up', $insertData);
                $insertId = $this->db->insert_id();
                if(!$insertId) {
                    return false;
                }
            }
            
            return true;
        }
}