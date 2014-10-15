<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
 * 题库统计model
 * @author      <yanhengjia@tizi.com>
 * @copyright   April 19th, 2014
 */
class Stat_model extends MY_Model 
{
    public function __construct($database = 'tiku') {
        parent::__construct($database);
    }
    
    /**
     * 获得本周获取过经验的用户Id
     * 需要查移动题库和梯子学堂两个项目中本周获取经验的用户的并集
     * @param int $weekBeginning 本周开始的时间戳
     * @renturn array $userIdArr 
     */
    public function getWeeklyUserIds($weekBeginning)
    {
        if(!$weekBeginning) {
            return false;
        }
        $startDate = date('Y-m-d', $weekBeginning);
        #加载梯子的数据库
        $_dbTizi = $this->load->database('tizi', true);
        #移动题库中本周获取经验的用户
        $sql = "SELECT DISTINCT userId FROM stu_practice_log WHERE date > '{$startDate}' AND exp > 0";
        $tikuArr = $this->db->query($sql)->result_array();
        $tikuUserArr = $xueUserArr = array();
        foreach($tikuArr as $val) {
            $tikuUserArr[] = $val['userId'];
        }
        #梯子学堂本周获取经验的用户
        $sql = "SELECT DISTINCT  user_id FROM study_history WHERE end_time > {$weekBeginning}";
        $xuetangArr = $_dbTizi->query($sql)->result_array();
        foreach($xuetangArr as $val) {
            $xueUserArr[] = $val['user_id'];
        }

        $userIdArr = array_unique(array_merge($tikuUserArr, $xueUserArr));
        return $userIdArr;
    }

    /**
     * 统计用户获得的周经验值
     * @param array $userIds 要统计的用户id组成的数组
     * @param int   $weekBeginning 本周起始时间的UNIX时间戳
     * @return bool 
     */
    public function countWeeklyExp ($userIds, $weekBeginning)
    {
        if(!is_array($userIds) || !$weekBeginning) {
            return false;
        }
        $_dbTizi = $this->load->database('tizi', true);#加载梯子的数据库        
        $startDate = date('Y-m-d', $weekBeginning);
        #清空上周数据
        $this->cleanUserWeekStat();
        foreach($userIds as $id) {
            #查出用户在题库本周获得的经验
            $sql = "SELECT SUM(exp) AS tikuExp FROM stu_practice_log WHERE userId={$id} AND date > '{$startDate}' ";
            $res = $this->db->query($sql)->row_array();
            $tikuExp = $res['tikuExp'];
            #查出用户在学堂本周获得的经验
            $sql = "SELECT SUM(make_exp) AS xueExp FROM study_history  WHERE user_id={$id} AND end_time > {$weekBeginning}";
            $res = $_dbTizi->query($sql)->row_array();
            $xueExp = $res['xueExp'];
            $weeklyExp = $tikuExp + $xueExp;

            #将用户本周获得的经验统计到用户周经验统计表里
            $updateSql = "REPLACE INTO study_user_week_stat SET userId={$id}, exp={$weeklyExp}";
            $execRes = $_dbTizi->query($updateSql);
            if($execRes) {
                echo 'count userId '. $id . '\'s weekly experience successfully' . chr(10);
            } else {
                echo 'defeat execution in userId ' . $id .chr(10);
            }
        }
        
        exit('mission accomplished');
    }
    
    /**
     * 清空用户周经验统计表
     */
    protected function cleanUserWeekStat()
    {
        $weekday = date('w');
        $hour    = date('H');
        #周一的第一次统计时将上周的统计数据清空
        if($weekday == 1 && $hour < '01') {
            $sql = "DELETE FROM study_user_week_stat";
            $_dbTizi = $this->load->database('tizi', true);#加载梯子的数据库
            $_dbTizi->query($sql);
        }
        
        return ;
    }
}
