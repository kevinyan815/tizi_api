<?php

    /**
     * 获得本周获取过经验的用户Id
     * 需要查移动题库和梯子学堂两个项目中本周获取经验的用户的并集
     * @param int $weekBeginning 本周开始的时间戳
     * @renturn array $userIdArr 
     */
    function getWeeklyUserIds($weekBeginning)
    {
    	global $db_tiku, $db_xuetang;
        if(!$weekBeginning) {
            return false;
        }
        $startDate = date('Y-m-d H:i:s', $weekBeginning);
        #移动题库中本周获取经验的用户
        $sql = "SELECT DISTINCT userId FROM stu_practice_log WHERE date > '{$startDate}' AND exp > 0";
        $tikuArr = $db_tiku->get_results($sql);
        $tikuUserArr = $xueUserArr = array();
        if($tikuArr && is_array($tikuArr)) {
            foreach($tikuArr as $val) {
                $tikuUserArr[] = $val['userId'];
            }    
        }
        #爱刷题其他三个版本本周获得经验的用户
        $sql = "SELECT DISTINCT userId FROM shuati_stu_practice_log WHERE date > '{$startDate}' AND exp > 0";
        $shuatiArr = $db_tiku->get_results($sql);
        if($shuatiArr && is_array($shuatiArr)) {
            foreach($shuatiArr as $val) {
                $shuatiUserArr[] = $val['userId'];
            }    
        }
        #梯子学堂本周获取经验的用户
        $sql = "SELECT DISTINCT  user_id FROM study_history WHERE end_time > {$weekBeginning}";
        $xuetangArr = $db_xuetang->get_results($sql);
        if($xuetangArr && is_array($xuetangArr)) {
            foreach($xuetangArr as $val) {
                $xueUserArr[] = $val['user_id'];
            }  
        }

        $userIdArr = array_unique(array_merge($tikuUserArr, $xueUserArr, $shuatiUserArr));
        return $userIdArr;
    }
    
    
    
    /**
     * 统计用户获得的周经验值
     * @param array $userIds 要统计的用户id组成的数组
     * @param int   $weekBeginning 本周起始时间的UNIX时间戳
     * @return bool 
     */
    function countWeeklyExp ($userIds, $weekBeginning)
    {
        global $db_tiku, $db_xuetang;
        if(!is_array($userIds) || !$weekBeginning) {
            return false;
        }
        $startDate = date('Y-m-d H:i:s', $weekBeginning);
        #清空上周数据
        cleanUserWeekStat();
        foreach($userIds as $id) {
            #查出用户在题库本周获得的经验
            $sql = "SELECT SUM(exp) AS tikuExp FROM stu_practice_log WHERE userId={$id} AND date > '{$startDate}' ";
            $res = $db_tiku->get_row($sql);
            $tikuExp = $res['tikuExp'];
            #查出用户在学堂本周获得的经验
            $sql = "SELECT SUM(make_exp) AS xueExp FROM study_history  WHERE user_id={$id} AND end_time > {$weekBeginning}";
            $res = $db_xuetang->get_row($sql);
            $xueExp = $res['xueExp'];
            #查出用户在爱刷题中考版本周获得的经验
            $sql = "SELECT SUM(exp) AS shuatiExp FROM shuati_stu_practice_log WHERE userId={$id} AND date > '{$startDate}' ";
            $res = $db_tiku->get_row($sql);
            $shuatiExp = $res['shuatiExp'];
            $weeklyExp = $tikuExp + $xueExp + $shuatiExp;

            #将用户本周获得的经验统计到用户周经验统计表里
            $updateSql = "REPLACE INTO study_user_week_stat SET userId={$id}, exp={$weeklyExp}";
	    echo $updateSql .chr(10);
            $execRes = $db_xuetang->query($updateSql);
            if($execRes) {
                echo 'count userId '. $id . '\'s weekly experience successfully' . chr(10);
            } else {
                echo 'defeat execution in userId ' . $id .chr(10);
            }
        }
        
        exit('mission accomplished at ' . date('Y-m-d H:i:s'));
    }
    
   /**
     * 清空用户周经验统计表
     */
     function cleanUserWeekStat()
    {        
    
    	global $db_tiku, $db_xuetang;
        $weekday = date('w');
        $hour    = date('H');
        #周一的第一次统计时将上周的统计数据清空
        if($weekday == 1 && $hour < '01') {
            $sql = "DELETE FROM study_user_week_stat";
            $db_xuetang->query($sql);
        }
        
        return ;
    }
    
    /**
     * 获取移动题库的所有用户
     * 题库和学堂的用户共用同一个用户表,
     * 其中user_apps值为1或3的用户在使用题库
     */
    function getTikuUsers()
    {
        global $db_xuetang;
        $sql = "SELECT user_id FROM user_data WHERE user_apps = 1";
        $allUsers = $userArr1 = $userArr3 = array();
        $res = $db_xuetang->get_results($sql);
        if(is_array($res)) {
            foreach($res as $val) {
                $userArr1[] = $val['user_id'];
            }   
        }
        $sql = "SELECT user_id FROM user_data WHERE user_apps = 3";
        $res = $db_xuetang->get_results($sql);
        if(is_array($res)) {
            foreach($res as $val) {
                $userArr3[] = $val['user_id'];
            }   
        }
        
        $userArr = array_unique(array_merge($userArr1, $userArr3));
        return $userArr;
    }
    
    /**
     * 发送消息
     * @param array $noticeInfo 通知标题、内容、发送时间等信息
     * @param array $userIdArr 用户Id组成的数组
     * @return bool
     */
    function sendNoticeToUser($noticeInfo, $userIdArr) 
    {
        if(!is_array($noticeInfo) || !is_array($userIdArr)) {
            return false;
        }
        global $db_tiku;
        foreach($userIdArr as $userId) {
            $insertSql = "INSERT INTO mail_box (title, content, sendId, receiveId, time, type, url)
                          VALUES('{$noticeInfo['title']}', '{$noticeInfo['content']}', 0, {$userId}, '{$noticeInfo['inputTime']}',
                          {$noticeInfo['type']}, '{$noticeInfo['pageUrl']}')";
            $db_tiku->query($insertSql);
            $insertId = $db_tiku->last_insert_id();
            if(!$insertId) {
                echo 'failed to send notice "'. $noticeInfo['title'] .'" to user' . $userId .chr(10);
            }
        }
        return TRUE;
    }