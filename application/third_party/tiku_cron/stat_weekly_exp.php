<?php
/***
   * 统计用户的本周获取的经验值, 每小时跑一次
   */
/* var_dump($_SERVER['argv']); */
require_once('connect.inc.php');
require_once('function.php');
error_reporting(E_ALL & ~E_NOTICE);
$weekday = date('w');
#date 返回0时是周日
$weekday = $weekday ? $weekday : 7;
$dayStr = -($weekday - 1) . 'day';
#北京时区是东八区，所以这里要减去8个小时的秒数，才是本周开始时的UNIX时间戳
$beginning = strtotime($dayStr) - strtotime($dayStr) % 86400 - 3600 * 8;
#获取本周获得过经验值的用户
$userIds = getWeeklyUserIds($beginning);
#统计用户本周获得的经验
countWeeklyExp($userIds, $beginning);
