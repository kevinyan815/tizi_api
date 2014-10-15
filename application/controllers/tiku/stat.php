<?php

/*
 * 关于移动题库要统计的数据操作都写在这里
 * @author      <yanhengjia@tizi.com>
 * @copyright   April 19th, 2014
 */
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Stat extends MY_Controller 
{
    public function __construct() {
        parent::__construct();
        $this->load->model('tiku/stat_model');
    }
    
    /**
     * 统计用户的周经验值
     */
    public function statUserWeekExp()
    {
        $weekday = date('w');
        #date 返回0时是周日
        $weekday = $weekday ? $weekday : 7;
        $dayStr = -($weekday - 1) . 'day';
        $beginning = strtotime($dayStr) - strtotime($dayStr) % 86400;
        #获取本周获得过经验值的用户
        $userIds = $this->stat_model->getWeeklyUserIds($beginning);
        #统计用户本周获得的经验
        $this->stat_model->countWeeklyExp($userIds, $beginning);
        
    }
}
