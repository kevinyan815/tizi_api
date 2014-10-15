<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * 移动题库学科知识点模型
 */

Class Subject_model extends MY_Model
{
    private static $_subjectTable = 'subjects';
    
    public function __construct($database = 'tiku') {
        parent::__construct($database);
    }
    
    /**
     * 获取学科
     * @param type $subjectType 学科类型 1:文科,2:理科
     *
     */
    public function getSubjects($subjectType)
    {
        if(!$subjectType) {
            return false;
        }
        $sql = "SELECT id AS subject_id, name FROM " . self::$_subjectTable . "
                WHERE type=3 OR type={$subjectType}";
        $res = $this->db->query($sql)->result_array();
        return $res;
    }
    
    /**
     * 获取用户学科概况
     * @param int $sparam['subjectId'] 学科Id
     * @param int $param['userId']     用户Id
     */
    public function getUserSubjectInfo($param)
    {
        if(!$param['userId'] || !$param['subjectId']) {
            return false;
        }
        extract($param);
        
        $sql = "SELECT prePoints, beatOthers FROM stu_subjects
                WHERE userId={$userId} AND subjectId={$subjectId}";
         
        return $this->db->query($sql)->row_array();
    }
    
    /**
     * 检查用户该学科是否已存在统计信息
     * @param int $userId    用户Id
     * @param int $subjectId 学科Id
     */
    public function checkExistence($userId, $subjectId)
    {
        $sql = "SELECT COUNT(*) AS num FROM stu_subjects WHERE userId={$userId} AND subjectId={$subjectId}";
        $row = $this->db->query($sql)->row_array();

        if($row['num']) {
            return TRUE;
        } else {
            return FALSE;
        }
    }
    
    /**
     * 添加用户学科信息
     * @param int $userId 用户id
     * @param int $subjectId 学科Id
     * @return bool | int 成功返回学科预测分, 失败返回false
     */
    public function addUserSubject($userId, $subjectId)
    {
        $data['userId'] = $userId;
        $data['subjectId'] = $subjectId;
        $data['prePoints'] = $this->calculateSubjectPoints($userId, $subjectId);
        $data['beatOthers'] = $this->calculateBeatOthers($userId, $subjectId, $data['prePoints']);
        
        $res = $this->db->insert('stu_subjects', $data);
        if($res) {
            return $data['prePoints'];
        } else {
            return false;
        }
    }
    
    /**
     * 更新用户学科数据
     * @param int $userId 用户Id
     * @param int $subjectId 学科Id
     * @param bool $recalculate 重新计算学科分数 
     * @return bool | int 成功返回学科预测分, 失败返回false
     */
    public function updateUserSubject($userId, $subjectId, $recalculate = false)
    {
        if($recalculate) {
            $subjectPoints = $this->calculateSubjectPoints($userId, $subjectId);
        } else {
            $subjectStat = $this->getUserSubjectInfo(array('userId' => $userId, 'subjectId' => $subjectId));
            $subjectPoints = $subjectStat['prePoints'];
        }
        $beatOthers = $this->calculateBeatOthers($userId, $subjectId, $subjectPoints);
        
        $sql = "UPDATE stu_subjects SET prePoints={$subjectPoints}, beatOthers={$beatOthers}
                WHERE userId={$userId} AND subjectId={$subjectId}";
                
        $res = $this->db->query($sql);
        if($res) {
            return $subjectPoints;
        } else {
            return FALSE;
        }
        
    }
    
    /**
     * 计算学科预测分
     * @param int $userId 用户Id
     * @param int $subjectId 学科Id
     */
    public function calculateSubjectPoints($userId, $subjectId)
    {
        #先查出该学科下一级知识点的掌握情况
        $sql = "SELECT k.id AS kId, k.percent, stu.kLevel FROM knowledge k LEFT JOIN stu_knowledge stu
                ON k.id=stu.kId  WHERE stu.userId={$userId} AND k.subjectId={$subjectId} AND k.grade=1";
        $knowledgeInfo = $this->db->query($sql)->result_array();
        $points = 0;
        foreach($knowledgeInfo as $info) {
            $points += ($info['kLevel'] / 6) * $info['percent'];
        }
        
        $points = $points * 100;//目前每科的总分都是100分
        $points = round($points);
//        $points = trim(sprintf('%3d', $points));//这里注意sprinf函数会默认在长度不够说明长度时往前加空字符串
        return $points;
    }
    
    /**
     * 计算打败其他用户百分比
     * @param int $userId 用户Id
     * @param int $subjectId 学科Id
     * @param int $points 用户的学科预测分
     */
    public function calculateBeatOthers($userId, $subjectId, $points)
    {
//        return 0.25;
        #首先计算该学科下有统计的所有人数
        $sql = "SELECT COUNT(*) AS total FROM stu_subjects WHERE subjectId={$subjectId}";
        $row = $this->db->query($sql)->row_array();
        $total = $row['total'];
        if($total == 0) {
            return 0;#表里没数据的时候直接返回0
        }
        $sql = "SELECT COUNT(*) AS num FROM stu_subjects WHERE subjectId={$subjectId} AND prePoints < {$points}";
        $row = $this->db->query($sql)->row_array();
        $num = $row['num'];
        $beatOthers = $num / $total;
        $beatOthers = sprintf('%4.2f' , $beatOthers);
        
        return $beatOthers;
    }
    
}
