<?php
/**
 * 定时检查要发送的全站消息,把编辑新写得全站消息发送到每个用户的邮箱
 */
require_once 'connect.inc.php';
require_once 'function.php';
error_reporting(E_ALL & ~E_NOTICE);
if(date('H') % 2 == 0) {
    $dbTiku = $db_tiku;
    $date = date('Y-m-d');
    #找出今日未发送的消息
    $sql = "SELECT id, title, content, pageUrl, type, inputTime FROM site_notice WHERE inputTime > '{$date}' AND sendState=1";
    $noticeArr = $dbTiku->get_results($sql);
    if($noticeArr && is_array($noticeArr)) {
        #获取移动题库的用户
        $userIdArr = getTikuUsers();
        foreach($noticeArr as $notice) {
            sendNoticeToUser($notice, $userIdArr);
            #发送完后将这条通知置为已发送状态
            $sql = "UPDATE site_notice SET sendState=2 WHERE id={$notice['id']}";
            $dbTiku->query($sql);
        }
        exit('mission accomplished at' . date('Y-m-d H:i:s') . chr(10));
    } else {
        exit('There\'s Nothing to send!' . chr(10));
    }
}
exit();
