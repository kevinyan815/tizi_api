<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
require_once APPPATH.'core/Shuati_Model.php';
/**
 * 爱刷题里关于用户数据库的基本操作
 */
class Common_User extends Shuati_Model
{
    public function __construct($database = 'tiku') 
    {
        parent::__construct($database);
    }
    
    /**
     * 获取用户练习记录
     * @param string $str_fields 要查询的字段
     * @param array  $arr_join  要关联的表和连接条件
     * @param array  $arr_where 查询条件
     * @param string $order_by  排序
     * @param array  $limit 个数和偏移量
     * @param array  $group_by 分组
     * @param string $app_name APP名
     */
    public function get_user_practice_log($str_fields = '*', $arr_join = array(), $array_where = array(), $order_by = '', $limit = array(), $group_by = array(), $app_name = 'shuati')
    {
        $table = $app_name == 'tiku' ? '' : Constant::TABLE_SHUATI_USER_PRACTICE_LOG;//选择数据表
        $result = $this->_get_from_db($table, $str_fields, $arr_join, $array_where, $order_by, $limit, $group_by);
        return $result;
    }
    
    /**
     * 查询user_data表中的用户数据
     * @param int $user_id
     * @return array
     */
    public function get_user_data_from_db($user_id)
    {
        $sql = 'SELECT user_id,exp AS experience,pet_id,location_id,subject_type FROM ' . Constant::TABLE_SHUATI_USER .'  
                WHERE user_id=' . $user_id;
        $this->db_tizi = $this->load->database('tizi', TRUE);
        $user_data = $this->db_tizi->query($sql)->row_array();
        if(!empty($user_data)) {
            #昵称在梯子的用户表里,表太大这里分着查一次
            $sql = "SELECT name FROM user WHERE id=" . $user_id;
            $row = $this->db_tizi->query($sql)->row_array();
            $user_data['nick_name'] = $row['name'];
        }
        return $user_data;
    }
    
    /**
     * 更新用户表里的数据
     * @param array $update_data 要更新的数据
     * @param array $arr_where   条件
     * @return bool
     */
    public function update_shuati_user_data($update_data, $arr_where)
    {
        $this->db_tizi = $this->load->database('tizi', TRUE);
        $this->db_tizi->update(Constant::TABLE_SHUATI_USER, $update_data, $arr_where);
        $int_affected_rows = $this->db_tizi->affected_rows();
        return $int_affected_rows > 0 ? true :false;
    }
    
    /**
     * 通用的获取用户题目收藏的方法
     * @param string $str_fields 要查询的字段
     * @param array  $arr_join  要join的表和join条件
     * @param array  $arr_where 查询条件
     * @param string $order_by  排序
     * @param array  $limit     个数和偏移量
     * @param array  $group_by  分组
     * @param string $app_name APP名
     */
    public function get_user_favorites($str_fields = '*', $arr_join = array(), $array_where = array(), $order_by = '', $limit = array(), $group_by = array(), $app_name = 'shuati')
    {
        $table = $app_name == 'tiku' ? '' : Constant::TABLE_SHUATI_USER_FAVORITES;
        $result = $this->_get_from_db($table, $str_fields, $arr_join, $array_where, $order_by, $limit, $group_by);
        
        return $result;
    }
    
    /**
     * 查询用户的答题记录表
     * @param string $str_fields 要查询的字段
     * @param array  $arr_join  要join的表和join条件
     * @param array  $arr_where 查询条件
     * @param string $order_by  排序
     * @param array  $limit     个数和偏移量
     * @param array  $group_by  分组
     * @param string $app_name APP名
     */
    public function get_user_answer_log($str_fields = '*', $arr_join = array(), $array_where = array(), $order_by = '', $limit = array(), $group_by = array(), $app_name = 'shuati')
    {
        $table = $app_name == 'tiku' ? '' : Constant::TABLE_SHUATI_USER_ANSWER_LOG;
        $result = $this->_get_from_db($table, $str_fields, $arr_join, $array_where, $order_by, $limit, $group_by);
        
        return $result;
    }
    
    /**
     * 查看用户跟我是否是好友
     * @param int $my_id 我的用户Id
     * @param int $friend_id 其他用户的Id
     */
    public function check_friendship($my_id, $friend_id)
    {
        $str_field = 'count(*) as is_friend';
        $arr_where = array('userId' => $my_id, 'friendId' => $friend_id);
        $result = $this->get_user_relation($str_field, $arr_where, '', array(1));
        
        return $result['is_friend'] ? true : false;
    }
    
    /**
     * 获取我在好友中的排名
     * @param int $user_id 用户Id
     */
    public function get_my_rank_in_friends($user_id)
    {
        #先查出所有好友的Id
        $friends = $this->get_user_relation('friendId as friend_id', array('userId' => $user_id));
        if(is_array($friends) && $friends) {
            $list_exp = array();
            foreach($friends as $val) {
                $user_week_exp = $this->get_user_weekly_exp($val['friend_id']);
                $list_exp[] = $user_week_exp;
            }
            $my_week_exp = $this->get_user_weekly_exp($user_id);
            $list_exp[] = $my_week_exp;
            rsort($list_exp);
            $rank = array_search($my_week_exp, $list_exp) + 1;
        } else {
            $rank = 1;
        }
        
        return $rank;
    }
    
    /**
     * 查询用户好友关系
     * @param string $str_fields 要查询的字段
     * @param array  $arr_where 查询条件
     * @param string $order_by  排序
     * @param array  $limit     个数和偏移量
     * @param array  $group_by  分组
     */
    public function get_user_relation($str_fields = '*', $array_where = array(), $order_by = '', $limit = array(), $group_by = array())
    {
        $result = array();
        $this->db_tizi = $this->load->database('tizi', TRUE);
        $table = Constant::TABLE_SHUATI_USER_RELATION;
        $result = $this->_get_from_db($table, $str_fields, array(), $array_where, $order_by, $limit, $group_by, 'left', $this->db_tizi);
        return $result;
    }
    
    /**
     * 查询获取爱刷题用户列表的通用方法
     * @param string $str_fields 要查询的字段
     * @param array  $arr_join  要join的表和join条件
     * @param array  $arr_where 查询条件
     * @param string $order_by  排序
     * @param array  $limit     个数和偏移量
     * @param string $group_by  分组
     * @param string $join_type 连接类型
     */
    public function get_shuati_user_list($str_fields = '*', $arr_join = array(), $array_where = array(), $order_by = '',$limit = array(), $group_by = '', $join_type = 'left')
    {
        $result = array();
        $this->db_tizi = $this->load->database('tizi', TRUE);
        $select_table = Constant::TABLE_SHUATI_USER;
        $result = $this->_get_from_db($select_table, $str_fields, $arr_join, $array_where, $order_by, $limit, $group_by, $join_type, $this->db_tizi);

        return $result;
    }
    
    /**
     * 获取用户的本周经验
     * @param int $user_id 用户Id
     */
    public function get_user_weekly_exp($user_id)
    {
        $this->db_tizi = $this->load->database('tizi', TRUE);
        $sql = "SELECT exp FROM study_user_week_stat WHERE userId=". $user_id;
        $result = $this->db_tizi->query($sql)->row_array($sql);
        
        return empty($result['exp']) ? 0 : $result['exp'];
    }
    
    /**
     * 添加用户关系
     * @param int $user_id 用户Id
     * @param int $friedn_id 好友Id
     */
    public function add_user_relation($user_id, $friend_id)
    {
        $this->db_tizi = $this->load->database('tizi', TRUE);
        $insert_data = array('userId' => $user_id, 'friendId' => $friend_id);
        $this->db_tizi->insert(Constant::TABLE_SHUATI_USER_RELATION, $insert_data);
        return $this->db_tizi->insert_id() ? true : false;
    }
    
    /**
     * 删除用户关系
     * @param int $user_id 用户Id
     * @param int $friedn_id 好友Id
     */
    public function delete_user_relation($user_id, $friend_id)
    {
        $this->db_tizi = $this->load->database('tizi', TRUE);
        $arr_where = array('userId' => $user_id, 'friendId' => $friend_id);
        $this->db_tizi->delete(Constant::TABLE_SHUATI_USER_RELATION, $arr_where);
    }
}