<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
require_once APPPATH.'core/Shuati_Model.php';
/**
 * 爱刷题,朋友Model
 */
class Friend_Model extends Shuati_Model
{
    public function __construct($database = 'tiku')
    {
        parent::__construct($database);
        $this->load->model('common/shuati/common_user');
    }
    
    /**
     * 通过昵称搜索用户
     * @param string $query
     */
    public function search_user($query)
    {
        $is_email = preg_email($query);
        $user_table = 'user';
        $shuati_user_table = Constant::TABLE_SHUATI_USER;
        $str_fields = $user_table. '.id as user_id, name as nick_name, ' .$shuati_user_table. '.pet_id';
        $arr_join = array($user_table => $shuati_user_table. '.user_id=' .$user_table. '.id');
        if($is_email) {
            #根据邮箱搜索用户
            $arr_where = array($user_table. '.email' => $query);
        } else {
            #根据昵称搜索用户
            $arr_where = array($user_table. '.name like' => $query. '%');
        }
        $user_list = $this->common_user->get_shuati_user_list($str_fields, $arr_join, $arr_where);
        
        return $user_list;
    }
    
    /**
     * 添加好友
     * @param int $my_id 我的用户Id
     * @param int $friend_id 要添加的好友的用户Id
     */
    public function add_friend($my_id, $friend_id)
    {
        $res = $this->common_user->add_user_relation($my_id, $friend_id);
        return $res;
    }
    
    /**
     * 移除好友
     * @param int $my_id 我的用户Id
     * @param int $friend_id 要添加的好友的用户Id
     */
    public function delete_friendship($my_id, $friend_id)
    {
        $res = $this->common_user->delete_user_relation($my_id, $friend_id);
        return true;
    }
    
    /**
     * 获取好友经验排行榜
     * @param int $user_id 用户Id
     * @param int $rank_type 排行榜类型, 1:周经验榜, 2:总经验榜
     * @param string $app_name App名称
     */
    public function get_friends_rank($user_id, $rank_type, $app_name)
    {
        $this->load->model('shuati/user_model');
        $friend_id_list = $this->common_user->get_user_relation('friendId as friend_id', array('userId' => $user_id));
        #把自己放入好友列表
        array_push($friend_id_list, array('friend_id' => $user_id));
        $rank_friends_info = $sort_exp = array();
        foreach($friend_id_list as $val) {
            $user_info = $this->user_model->get_user_data($val['friend_id'], $app_name);
            if(empty($user_info['user_id'])) continue;
            if($rank_type == 1) {
                #周经验排行中需要查出用户本周获得的经验替换数据中原来的个人总经验
                $user_info['experience'] = $this->common_user->get_user_weekly_exp($val['friend_id']);
            }
            
            $user_info['experience'] = round($user_info['experience']);#客户端返回的经验要是整数
            $user_info['pet_status'] = $user_info['pet_status'];#宠物状态
            $sort_exp[] = $user_info['experience'];#拼装经验值的排序数组
            $rank_friends_info[] = $user_info;
        }
        array_multisort($sort_exp, SORT_DESC, $rank_friends_info);#按经验值倒叙排列用户列表
        
        return $rank_friends_info;
    }
    
    /**
     * 获取应用内学霸推荐列表
     * 方法:查询用户练习记录表,按用户在某一应用中获取的经验值总和来倒序排列得到学霸
     * @param int $app_type App类型
     * @return array
     */
    public function get_app_top_student_list($app_type, $offset = 0, $num = 20)
    {
        $str_fields = 'sum(exp) as experience,userId as user_id';
        $arr_where = array('appType' => $app_type, 'exp >' => '0');
        $order_by = 'experience desc';
        $group_by = array('userId');
        $limit = array($num, $offset);
        $student_list = $this->common_user->get_user_practice_log($str_fields, array(), $arr_where, $order_by, $limit, $group_by);
        return $student_list;
    }
}