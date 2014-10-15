<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * 关于题库知识点的模型
 */
Class Knowledge_model extends MY_Model
{
    public function __construct($database = 'tiku') {
        parent::__construct($database);
    }
    
    /**
     * 学科列表
     */
    protected static $_subjects = array(1 => '语文', 2 => '英语', 3 => '理数', 4 => '文数', 5 => '物理', 6 => '化学', 7=> '生物', 8 => '历史', 9 => '地理', 10 => '政治');
    
    
    /**
     * 获取用户某一学科的知识点树
     * @param int $param['subjectId'] 学科Id
     * @param int $param['userId'] 用户Id
     * @param int $param['locationId'] 地区Id
     */
    public function getUserKnowledgeTree($param = array())
    {
        if(!isset(self::$_subjects[$param['subjectId']]) || !$param['userId'] || !$param['locationId']) {
            return false;
        }
        
        #首先获取用户所选地区下该学科的所有知识点
        $knowledgeArr = $this->getKnowledgeInLocation($param['subjectId'], $param['locationId']);
        $sortColumn = array();
        foreach($knowledgeArr as &$val) {
            $sql = "SELECT questionNums, kLevel FROM stu_knowledge WHERE kId={$val['kId']} AND userId={$param['userId']}";
            $query = $this->db->query($sql);
            $res = $query->row_array();
            $res['kLevel'] = isset($res['kLevel']) ? $res['kLevel'] : 0;#用户还没有做过题的知识点把klevel和questionNums给默认值0
            $res['questionNums'] = isset($res['questionNums']) ? $res['questionNums'] : 0;
            $val['questionNums'] = $res['questionNums'];
            #一级知识点的掌握程度是kLevel÷6  二级是kLevel÷4
            $val['finish_rate'] = $val['parentId'] == 0 ? sprintf('%3.2f' , (intval($res['kLevel']) / 6)) : sprintf('%3.2f', (intval($res['kLevel']) / 4));
            $query->free_result();           
            $sortKId[] = $val['kId'];
            $sortSequence[] = $val['sequence'];
        }
        array_multisort($sortSequence, SORT_DESC, $sortKId, SORT_ASC, $knowledgeArr);
        unset($val);//上一个foreach对$val进行了引用声明,要在这里断开引用
        #format to tree form
        $formatedData = array();
        foreach($knowledgeArr as $val) {
            if($val['parentId'] != 0) {
                #二级知识点归纳进上级知识点的数组中
                $formatedData[$val['parentId']]['sub_knowledge'][] = $val;
            } else {
                $formatedData[$val['kId']] = isset($formatedData[$val['kId']]) ? $formatedData[$val['kId']] : array();
                $formatedData[$val['kId']] = array_merge($formatedData[$val['kId']], $val);
            }
        }

        return array_values($formatedData);
    }
    
    /**
     * 获取某一地区某个学科的所有知识点
     * @param int $subjectId 学科Id
     * @param int $locationID  地区Id
     */
    public function getKnowledgeInLocation($subjectId, $locationId)
    {
        if(!$subjectId || !$locationId) {
            return false;
        }

        $sql = "SELECT k.id AS kId, k.name, k.parentId, k.sequence FROM knowledge k LEFT JOIN knowledge_in_location l
                ON k.id=l.kId WHERE l.locationId={$locationId} AND k.subjectId={$subjectId}";
        $knowledgeArr = $this->db->query($sql)->result_array();
        
        return $knowledgeArr;
    }
    
    /**
     * 获取一个题目对应的所有知识点Id
     * @param int $qId 题目Id
     */
    public function getRelatedKnowledge($qId)
    {
        $sql = "SELECT kId, grade FROM knowledge_question_rel WHERE qId={$qId}
                ORDER BY grade DESC, kId ASC";
        return $this->db->query($sql)->result_array();
    }
    
    /**
     * 检查用户知识点记录是否存在
     * @param int $userId 用户id
     * @param int $kId    知识点Id
     * @return bool true:存在   false:不存在
     */
    public function checkExistence($userId, $kId)
    {
        $sql = "SELECT COUNT(*) AS num FROM stu_knowledge WHERE userId={$userId} AND kId={$kId}";
        $query = $this->db->query($sql);
        $res = $query->row_array($sql);
        $query->free_result();
        if($res['num']) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * 插入新纪录到用户知识点掌握程度表
     * @param array  $insertData  要插入的数据
     */
    public function logNewknowledge($insertData)
    {
        $this->db->insert('stu_knowledge', $insertData);
    }
    
    /**
     * 更新用户的知识点情况
     * @param int $userId 用户Id
     * @param int $kId 知识点Id
     * @param int $scoreVariation  分数变动值
     * @param int $grade 知识点层级
     */
    public function updateUserKnowledge($userId, $kId, $scoreVariation, $grade)
    {
        $kLevel_scores = array(1 => 3, 2 => 9, 3 => 18, 4 => 30, 5 => 45, 6 => 63);
        $gradeLevels = array(1 => 6, 2 => 4);#一级知识点的掌握程度等级上线为6，二级上线为4
        
        $improve = FALSE;#升级标志位
        $table = 'stu_knowledge';
        $sql = "SELECT kLevel,score,questionNums FROM {$table} WHERE userId={$userId} AND kId={$kId}";
        $knowledgeInfo = $this->db->query($sql)->row_array();
        
        $newScore = $knowledgeInfo['score'] + $scoreVariation;
        $newScore = $newScore < 0 ? 0 : $newScore;
        #确保不要超出等级上限
        $nextKLevel = $knowledgeInfo['kLevel'] + 1 > $gradeLevels[$grade] ? $knowledgeInfo['kLevel'] : $knowledgeInfo['kLevel'] + 1;
        #升到下一级所需要的分数
        $neededScores = $kLevel_scores[$nextKLevel];
        if(($newScore >= $neededScores) && ($knowledgeInfo['kLevel'] < $gradeLevels[$grade])) {
            $newKLevel = $nextKLevel;
            $grade == 1 && $improve = TRUE;//一级知识点掌握程度升级后需要返回
        } else {
            #否则级数保持不变
            $newKLevel = $knowledgeInfo['kLevel'];
        }
        $newQuestionNums = $knowledgeInfo['questionNums'] + 1;
        $sql = "UPDATE {$table} SET kLevel={$newKLevel}, score={$newScore}, questionNums={$newQuestionNums} WHERE userId={$userId} AND kId={$kId}";
        $res = $this->db->query($sql);
        if(!$res) {
            return false;
        } else if($res && $improve) {
            #知识点升级了则返回kId
            return array('status' => true, 'kId' => $kId);
        } else {
            return true;
        }
    }
    
        
    /**
     * 更新用户的知识点情况
     * @param int $userId 用户Id
     * @param int $kId 知识点Id
     * @param int $scoreVariation  分数变动值
     * @param int $questionNums  题目数
     * @param int $grade 知识点层级
     */
    public function newUpdateUserKnowledge($userId, $kId, $scoreVariation, $questionNums, $grade)
    {
        $kLevel_scores = array(1 => 3, 2 => 9, 3 => 18, 4 => 30, 5 => 45, 6 => 63);
        $gradeLevels = array(1 => 6, 2 => 4);#一级知识点的掌握程度等级上线为6，二级上线为4
        
        $improve = FALSE;#升级标志位
        $table = 'stu_knowledge';
        $sql = "SELECT kLevel,score,questionNums FROM {$table} WHERE userId={$userId} AND kId={$kId}";
        $knowledgeInfo = $this->db->query($sql)->row_array();
        
        $newScore = $knowledgeInfo['score'] + $scoreVariation;
        $newScore = $newScore < 0 ? 0 : $newScore;
        #确保不要超出等级上限
        $nextKLevel = $knowledgeInfo['kLevel'] + 1 > $gradeLevels[$grade] ? $knowledgeInfo['kLevel'] : $knowledgeInfo['kLevel'] + 1;
        #升到下一级所需要的分数
        $neededScores = $kLevel_scores[$nextKLevel];
        if(($newScore >= $neededScores) && ($knowledgeInfo['kLevel'] < $gradeLevels[$grade])) {
            $newKLevel = $nextKLevel;
            $grade == 1 && $improve = TRUE;//一级知识点掌握程度升级后需要返回
        } else {
            #否则级数保持不变
            $newKLevel = $knowledgeInfo['kLevel'];
        }
        $newQuestionNums = $knowledgeInfo['questionNums'] + $questionNums;
        $sql = "UPDATE {$table} SET kLevel={$newKLevel}, score={$newScore}, questionNums={$newQuestionNums} WHERE userId={$userId} AND kId={$kId}";
        $res = $this->db->query($sql);
        if(!$res) {
            return false;
        } else if($res && $improve) {
            #知识点升级了则返回kId
            return array('status' => true, 'kId' => $kId);
        } else {
            return true;
        }
    }
    
    /**
     * 根据用户和知识点Id获取单个知识点的信息
     * @param int $userId 用户Id
     * @param int $kId 知识点Id
     */
    public function getOneUserKnowledge($userId, $kId)
    {
        $sql = "SELECT k.name, k.id AS knowledge_id, stu.kLevel AS level FROM knowledge k LEFT JOIN stu_knowledge stu
                ON k.id=stu.kId WHERE stu.userId={$userId} AND stu.kId={$kId}";
                
        $knowledgeInfo = $this->db->query($sql)->row_array();
        
        return $knowledgeInfo;
    }

}
