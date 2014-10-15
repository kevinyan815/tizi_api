<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
require_once APPPATH.'core/Shuati_Model.php';
/**
 * 爱刷题,Message_Model
 */
class Message_Model extends Shuati_Model
{
    public function __construct($database = 'tiku') 
    {
        parent::__construct($database);
    }
    
    /**
     * 发送站内消息
     * @param string $title 标题
     * @param string $content 内容
     * @param int    $send_u_id 发送人的uid
     * @param int    $receive_u_id 接收人的uid
     * @param int    $msg_type 消息类型 1表示系统消息(活动类),用户点击跳到webview查看活动信息;2表示关注消息,用户点击后跳转到指定的用户详细信息;3 表示消息通知，点击标题后显示消息详细信息
     * @param int    $app_type App类型 1.初中 2.中考 3.高中
     * @param string $send_time 发送时间
     */
    public function send_message($title, $content, $send_u_id, $receive_u_id, $msg_type, $app_type, $send_time = '')
    {
        $send_time = empty($send_time) ? date('Y-m-d H:i:s') : $send_time;
        $msg_data = array('title' => $title, 'content' => $content, 'sendId' => $send_u_id, 'receiveId' => $receive_u_id, 'type' => $msg_type, 'time' => $send_time, 'appType' => $app_type);
        $this->db->insert(Constant::TABLE_SHUATI_MAIL_BOX, $msg_data);
        return $this->db->insert_id() ? true : false;
    }
    
    
    /**
     * 获取用户的站内消息列表
     * @param int $user_id 用户Id
     * @param int $app_type $app_type App类型 1.初中 2.中考 3.高中
     */
    public function get_user_message_list($user_id, $app_type)
    {
        $str_fields = 'title,type,id as message_id,content,time,url,readStatus as had_read,sendId as user_id';
        $arr_where = array('receiveId' => $user_id, 'delStatus !=' => 2, 'appType' => $app_type);
        $message_list = $this->_get_message($str_fields, array(), $arr_where, 'id desc');
        return $message_list;
    }
    
    /**
     * 获取用户未读消息的数量
     * @param int $user_id 用户Id
     * @param int $app_type $app_type App类型 1.初中 2.中考 3.高中
     */
    public function get_new_message_count($user_id, $app_type)
    {
        $str_fields = 'COUNT(*) as new_count';
        $arr_where  = array('receiveId' => $user_id, 'delStatus !=' => 2, 'readStatus' => 0, 'appType' => $app_type);
        $result = $this->_get_message($str_fields, array(), $arr_where, '', array(1));
        return $result['new_count'] > 0 ? $result['new_count'] : 0;
    }
    
    /**
     * 获取站内信的通用方法
     * @param string $str_fields 要查询的字段
     * @param array  $arr_join  要join的表和join条件
     * @param array  $arr_where 查询条件
     * @param string $order_by  排序
     * @param array  $limit     个数和偏移量
     */
    protected function _get_message($str_fields = '*', $arr_join = array(), $array_where = array(), $order_by = '', $limit = array())
    {
        $table = Constant::TABLE_SHUATI_MAIL_BOX;
        $result = $this->_get_from_db($table, $str_fields, $arr_join, $array_where, $order_by, $limit);
        return $result;
    }
    
    
    /**
     * 修改消息
     * @param array $update_data
     * @param array $arr_where
     * @return bool
     */
    public function update_message($update_data, $arr_where)
    {
        $this->db->update(Constant::TABLE_SHUATI_MAIL_BOX, $update_data, $arr_where);
        $affected_rows = $this->db->affected_rows();
        
        return $affected_rows > 0 ? true : false;
    }
}