<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Jxt extends MY_Controller {

    private $_method;
    private $_db_jxt;
    private $_db_tizi;
    private $_session_id;
    private $_user_id;
    private $static_url = '';
    private $time;

    function __construct() {
        parent::__construct();
        $this->_db_jxt = $this->load->database('jxt', true);
        $this->_db_tizi = $this->load->database('tizi', true);
        $this->_session_id = $this->input->get_post('session_id'); // 家长session_id
        if (!in_array($this->uri->segment(2), array('login', 'app_notice'))) {
            if (empty($this->_session_id) && $this->uri->segment(2) != 'update_name') {
                $this->error_message(array('response_error_message' => '该账号已在其他手机登录，请退出重新登录', 'response_error_code' => 99));
            }
            if(!($this->uri->segment(2) == 'update_name' && $this->input->get_post('user_invite_id'))){
                $this->ck_sid();
            }
            if(in_array($this->uri->segment(2), array('message_list', 'album_list', 'space_list', 'message_new'))){//检查班级是否被解散
                $this->is_class_disband();
            }
        }
        $this->time = time();
        $this->static_url = site_url('application/views/static/jxt');
    }
    
    private function ck_sid(){
        if(!$this->_session_id){
            $this->error_message(array('response_error_message' => '该账号已在其他手机登录，请退出重新登录', 'response_error_code' => 99));
        }
        $this->load->model("login/session_model");
        $user_id_info = $this->session_model->get_api_session($this->_session_id, Constant::API_TYPE_JXT, 'user_id');
        if (!isset($user_id_info['user_id']) || empty($user_id_info['user_id'])) {
            $this->error_message(array('response_error_message' => '该账号已在其他手机登录，请退出重新登录', 'response_error_code' => 99));
        }
        $this->_user_id = $user_id_info['user_id'];
    }

    // 加入课堂 http://192.168.11.215/jxt/join_class?class_code=P3333I&session_id=xxx,http://192.168.11.215/jxt/join_class?class_code=HM333I&session_id=xxx
    public function join_class() {
        $class_code = $this->input->get_post('class_code');
        $class_id = alpha_id_num(strtoupper($class_code), true);
        if (empty($class_id)) {
            $this->error_message(array('response_error_message' => '班级参数无效', 'response_error_code' => 30001));
        }
        $this->load->model("class/class_model");
        $class_info = $this->class_model->g_classinfo($class_id, 'id');
        if (empty($class_info)) {
            $this->error_message(array('response_error_message' => '班级参数无效', 'response_error_code' => 30005));
        }
        
        $class_info = $this->_db_tizi->from('classes')
                        ->select('class_status')
                        ->where("id = {$class_id}")
                        ->get()->row();
                        
        if($class_info->class_status == 1){
            $this->error_message(array('response_error_message' => '班级不存在', 'response_error_code' => 30005));
        }
//        echo $this->_user_id;die;
        
        ////////////////////////////加入班级逻辑梳理/////////////////////////////
        
        $ab = $this->_db_jxt->from('address_book')
                        ->where("user_id = {$this->_user_id} AND del = 0")
                        ->get()->row_array();
                        
        if ($ab) {//通讯录存在
            $ar = $this->_db_jxt->from('ab_relation')
                            ->where("ab_id = {$ab['id']} AND active = 1 AND del = 0")
                            ->get()->row_array();

            if (isset($ar['class_id']) && $ar['class_id'] == $class_id) {//已在本班
                $this->error_message(array('response_error_message' => '您已经在此班级', 'response_error_code' => 30003));
            } elseif ($ar) {//在别的班级
                $this->error_message(array('response_error_message' => '您已经在别的班级，请先退出班级', 'response_error_code' => 30099));
            }

            $ar_this = $this->_db_jxt->from('ab_relation')
                            ->where("ab_id = {$ab['id']} AND class_id = {$class_id} AND del = 0")
                            ->get()->row_array();

            if ($ar_this) {//与当前班级有未激活的关系
                //将用户与其他班级的关系解除
                $this->_db_jxt->where("ab_id = {$ab['id']} AND active = 1 AND del = 0")->update('ab_relation', array('active' => 2, 'update_time' => $this->time, 'update_way' => 2));
                //与本班建立关系
                $this->_db_jxt
                        ->where("ab_id = {$ab['id']} AND class_id = {$class_id} AND del = 0")
                        ->update('ab_relation', array('active' => 1, 'update_time' => $this->time, 'update_way' => 2));
                
            } else {//与当前班级无关系
                $map = array(
                    'class_id' => $class_id,
                    'ab_id' => $ab['id'],
                    'active' => 1,
                    'create_time' => $this->time,
                    'update_time' => $this->time,
                    'update_way' => 2
                );
                $this->_db_jxt->insert('ab_relation', $map);
            }
        } else {//通讯录不存在
            $this->error_message(array('response_error_message' => '该账号已在其他手机登录，请退出重新登录', 'response_error_code' => 99));
        }

        ////////////////////////////加入班级逻辑梳理/////////////////////////////
        
        
        // 下面代码要重构掉
        $sql = "SELECT `student_name`, `parent_name`, `class_id`, ar.active FROM `address_book` ab LEFT JOIN 
			`ab_relation` ar ON ab.id = ar.ab_id WHERE ab.`user_id` = {$this->_user_id} AND ab.del = 0 AND ar.del = 0 
			AND ar.active = 1 LIMIT 1";
        $jxt_user = $this->_db_jxt->query($sql)->row_array();
        $data['type'] = 3;
        $data['child_name'] = $jxt_user['student_name'];
        $data['parent_name'] = $jxt_user['parent_name'];
        $this->load->model("class/class_model");
        $class_info = $this->class_model->g_classinfo($jxt_user['class_id'], 'classname, school_id');
        (!empty($class_info) && isset($class_info['classname'])) && $data['classname'] = $class_info['classname'];
        $this->load->model("class/classes_schools");
        $school_name = $this->classes_schools->id_school($class_info['school_id']);
        $data['class_id'] = $jxt_user['class_id'];
        $data['school'] = $school_name;
        $data['user_id'] = $this->_user_id;
        $data['message_info'] = !empty($jxt_user['class_id']) ? $this->_count_message_new($jxt_user['class_id'], $this->_user_id) : array();
        $this->load->library("thrift");
        $phone = $this->thrift->get_phone($this->_user_id);
        $data['phone'] = $phone;
        $this->success_message($data);
    }

    // 退出课堂 http://192.168.11.215/jxt/abort_class?class_code=3&session_id=xxx,http://192.168.11.215/jxt/abort_class?class_code=610&session_id=xxx
    public function abort_class() {
//    	$class_code = $this->input->get_post('class_code');
//    	$class_id = alpha_id($class_code, true);
        $class_id = $this->input->get_post('class_id');
        if (empty($class_id)) {
            $this->error_message(array('response_error_message' => '参数无效', 'response_error_code' => 30005));
        }
        $ab = $this->_db_jxt->from('address_book')->where("user_id = {$this->_user_id} AND del = 0")->get()->row();
        $status = $this->_db_jxt->where("class_id = {$class_id} AND ab_id = {$ab->id} AND del = 0")->update('ab_relation', array(
            'active' => 2
        )); 
        $this->success_message(array('done' => 1));
    }

    //http://192.168.11.215/jxt/login?account=18651484714&password=123456
    public function login() {
        $this->load->model("login/login_model");
        $this->load->model("login/session_model");
        $username = $this->input->get_post("account", true);
        $password = $this->input->get_post("password", true);
        $user_id = $this->login_model->login($username, $password); //user_type(1-admin,2-student,3-teacher,4-parent)
        
        // type=1 手机号没验证情况，type=2 待加入班级 3个人信息不全，4全部ok
        if (isset($user_id['errorcode']) && $user_id['errorcode'] == Constant::LOGIN_SUCCESS && $user_id['user_type'] == Constant::USER_TYPE_PARENT) {
            $this->load->model("login/session_model");
            $this->_user_id = $user_id['user_id'];
            $session_id = $this->session_model->generate_api_session($this->_user_id, Constant::API_TYPE_JXT);
            $data = array('session_id' => $session_id);
            $this->load->library("thrift");
            $phone = $this->thrift->get_phone($user_id['user_id']);
            if (empty($phone) || $phone == -1 || $phone == -127) {
                $data['type'] = 1;
            } else {
                $ab = $this->_db_jxt->from('address_book')
                        ->where("user_id = {$this->_user_id} AND del = 0")
                        ->get()->row_array();
                        
                if($ab && $ab['id']){//通讯录存在
                    if(!$ab['student_name'] || !$ab['parent_name']){
                        $data['type'] = 2;
                    }else{
                        
                        $data = array_merge($data, array(
                            'phone' => $phone,
                            'user_id' => $this->_user_id,
                            'child_name' => $ab['student_name'],
                            'parent_name' => $ab['parent_name'],
                            'type' => 3
                        ));
                        
                        $ar = $this->_db_jxt->from('ab_relation')
                                ->where("ab_id = {$ab['id']} AND active = 1 AND del = 0")
                                ->get()->row_array();
                                
                        if($ar && $ar['class_id']){//已加入班级
                            
                            $class_info = $this->_db_tizi->from('classes')
                                    ->select('class_status')
                                    ->where("id = {$ar['class_id']}")
                                    ->get()->row();
                    
                            if($class_info->class_status == 1){
                                $this->_db_jxt->where("class_id = {$ar['class_id']}")->update('ab_relation', array('del' => 1));
                            }else{
                                $data['type'] = 4;
                                $this->load->model("class/class_model");
                                $class_info = $this->class_model->g_classinfo($ar['class_id'], 'classname, school_id');
                                (!empty($class_info) && isset($class_info['classname'])) && $data['classname'] = $class_info['classname'];
                                $this->load->model("class/classes_schools");
                                $school_name = $this->classes_schools->id_school($class_info['school_id']);
                                $data['class_id'] = $ar['class_id'];
                                $data['school'] = $school_name;
                            }
                        }
                    }
                    
                }else{//通讯录不存在，需创建
                    $map = array(
                        'user_id' => $this->_user_id,
                        'phone' => $phone,
                        'create_time' => $this->time,
                        'update_time' => $this->time,
                        'update_way' => 2
                    );
                    $this->_db_jxt->insert('address_book', $map);
                    $data['type'] = 2;
                }
            }
            $this->success_message($data);
        } elseif (isset ($user_id['errorcode']) && $user_id['errorcode'] == Constant::LOGIN_ERROR_USERNAME_OR_PASSWORD) {
            $this->error_message(array('response_error_message' => '用户不存在或密码错误', 'response_error_code' => 10001));
        } elseif (isset($user_id['errorcode']) && $user_id['errorcode'] == Constant::LOGIN_SUCCESS && $user_id['user_type'] != Constant::USER_TYPE_PARENT) {
            $this->error_message(array('response_error_message' => '不是家长帐号', 'response_error_code' => 10001));
        } else {
            if(!preg_phone($username)){//非手机号不可在移动端登录临时账号
                $this->error_message(array('response_error_message' => '用户不存在', 'response_error_code' => 10003));
            }
            $this->load->model("login/user_invite_model");
            $user_invite = $this->user_invite_model->login($username, $password, "phone", true);
            if ($user_invite["id"] > 0) {
                if ($user_invite["user_type"] == Constant::USER_TYPE_PARENT) {
                    $ab = $this->_db_jxt->from('address_book')
                            ->where("phone = {$username} AND del = 0")
                            ->get()->row_array();
                    $data = array(
                        'type' => 2,
                        'user_invite_id' => $user_invite["id"]
                    );
                    
                    if($ab){
                        $ab['student_name'] && $data['child_name'] = $ab['student_name'];
                        $ab['parent_name'] && $data['parent_name'] = $ab['parent_name'];
                    }
                    
                    $this->success_message($data);
                } else {
                    $this->error_message(array('response_error_message' => '不是家长帐号', 'response_error_code' => 10001));
                }
            } else {
                $this->error_message(array('response_error_message' => '账号不存在或密码错误', 'response_error_code' => 10003));
            }
        }
    }

    // 家长在app端修改自己和学生的姓名,http://192.168.11.215/jxt/update_name?student_name=abc&parent_name=xxx&session_id=xxxx
    public function update_name() {
        $student_name = $this->input->get_post('student_name');
        $parent_name = $this->input->get_post('parent_name');
        $need_detail = $this->input->get_post('need_detail');
        $user_invite_id = $this->input->get_post("user_invite_id");
        $phone_os = $this->input->get_post("phone_os");

        if ($student_name === '' || $parent_name === '') {
            $this->error_message(array('response_error_message' => '家长姓名和学生姓名不可空', 'response_error_code' => 10004));
        }
        
        if(!preg_match('/^[\x{4e00}-\x{9fa5}A-Za-z]+$/u', $student_name) || !preg_match('/^[\x{4e00}-\x{9fa5}A-Za-z]+$/u', $parent_name)){
            $this->error_message(array('response_error_message' => '姓名只能为汉字或英文字母', 'response_error_code' => 10004));
        }
        
        if(intval($user_invite_id) > 0){//临时账号登录
            $this->load->model("login/user_invite_model");
            $info = $this->user_invite_model->get($user_invite_id);
            
            if ($info["user_id"] > 0 || $info["user_type"] != Constant::USER_TYPE_PARENT) {
                $this->error_message(array('response_error_message' => '该账号已在其他手机登录，请退出重新登录', 'response_error_code' => 99));
            }

            $password = md5("ti" . $info["password"] . "zi");
            $userdata = array(
				'register_origin' => $phone_os == 'android' ? Constant::REG_ORIGIN_ANDROID_JXT : Constant::REG_ORIGEN_IOS_JXT
			);

            $this->load->model("login/register_model");
            $res = $this->register_model->insert_register($info["phone"], $password, $parent_name, Constant::INSERT_REGISTER_PHONE, Constant::USER_TYPE_PARENT, $userdata);

            if ($res["user_id"] > 0) {
                $this->user_invite_model->update($user_invite_id, $res["user_id"], date("Y-m-d H:i:s"));
                $this->load->model("jxt/ab_jxt_model");
                $this->load->model("login/session_model");
                $data = array(
                    "user_id" => $res["user_id"],
                    "parent_name" => $parent_name,
                    "student_name" => $student_name
                );
                $callback = $this->ab_jxt_model->set_ab_id($info["phone"], $data);
                if (true !== $callback) {
                    $error_data = array(
                        "user_id" => $res["user_id"],
                        "phone" => $info["phone"],
                        "user_invite_id" => $info["id"]
                    );
                    log_message("error_tizi", "10502:Can not callback jxt data", $error_data);
                }
                $this->_user_id = $res['user_id'];
                $session_id = $this->session_model->generate_api_session($this->_user_id, Constant::API_TYPE_JXT);
                $data = array(
                    'session_id' => $session_id,
                    'phone' => $info["phone"],
                    'user_id' => $this->_user_id,
                    'type' => 3
                );
                
                $ab = $this->_db_jxt->from('address_book')
                        ->where("user_id = {$res["user_id"]} AND del = 0")
                        ->get()->row_array();
                        
                if($ab && $ab['id']){//通讯录存在
                    $data['child_name'] = $ab['student_name'];
                    $data['parent_name'] = $ab['parent_name'];
                    
                    $ar = $this->_db_jxt->from('ab_relation')
                            ->where("ab_id = {$ab['id']} AND active = 1 AND del = 0")
                            ->get()->row_array();
                            
                    if($ar && $ar['class_id']){//已经加入班级
                        $data['type'] = 4;
                        $this->load->model("class/class_model");
                        $class_info = $this->class_model->g_classinfo($ar['class_id'], 'classname, school_id');
                        (!empty($class_info) && isset($class_info['classname'])) 
                        && $data['classname'] = $class_info['classname'];
                        $this->load->model("class/classes_schools");
                        $school_name = $this->classes_schools->id_school($class_info['school_id']);
                        $data['class_id'] = $ar['class_id'];
                        $data['school'] = $school_name;
                        $data['message_info'] = !empty($ar['class_id']) ? $this->_count_message_new($ar['class_id'], $this->_user_id) : array();
                    }
                    
                }else{//通讯录不存在，添加通讯录
                    include_once LIBPATH . 'third_party/first_cw/first_cw.php';
                    $map = array(
                        'user_id' => $this->_user_id,
                        'initial' => get_initial($student_name),
                        'student_name' => $student_name,
                        'parent_name' => $parent_name,
                        'phone' => $info["phone"],
                        'create_time' => $this->time,
                        'update_time' => $this->time,
                        'update_way' => 2
                    );
                    
                    $this->_db_jxt->insert('address_book', $map);
//                    $ab_id = $this->_db_jxt->insert_id();
                }
                $this->success_message($data);
                
            }else{
                $this->error_message(array('response_error_message' => '该账号已在其他手机登录，请退出重新登录', 'response_error_code' => 99));
            }
        } else {//正式账号
            $this->ck_sid();
            include_once LIBPATH . 'third_party/first_cw/first_cw.php';
            $this->_db_jxt->where("user_id = {$this->_user_id}")
                    ->update('address_book', array(
                        'student_name' => $student_name,
                        'initial' => get_initial($student_name),
                        'parent_name' => $parent_name
                    ));
            
            if($need_detail != 1){
                $this->success_message(array('done' => 1));
            }
            $ab = $this->_db_jxt->from('address_book ab')
                    ->join('ab_relation ar', 'ab.id = ar.ab_id')
                    ->select('ar.class_id')
                    ->where("ab.user_id = {$this->_user_id} AND ab.del = 0 AND ar.del = 0")
                    ->get()->row_array();
                    
            $data['type'] = 3;
            $data['child_name'] = $student_name;
            $data['parent_name'] = $parent_name;
            $data['user_id'] = $this->_user_id;
            $data['session_id'] = $this->_session_id;
            $this->load->library("thrift");
            $phone = $this->thrift->get_phone($this->_user_id);
            $data['phone'] = $phone;
            
            if($ab && $ab['class_id']){
                $data['type'] = 4;
                $this->load->model("class/class_model");
                (!empty($class_info) && isset($class_info['classname'])) && $data['classname'] = $class_info['classname'];
                $this->load->model("class/classes_schools");
                $data['class_id'] = $ab['class_id'];
                $school_name = $this->classes_schools->id_school($class_info['school_id']);
                $data['school'] = $school_name;
                $data['message_info'] = !empty($ab['class_id']) ? $this->_count_message_new($ab['class_id'], $this->_user_id) : array();
            }
            $this->success_message($data);
        }
    }

    //http://192.168.11.215/jxt/message_new?class_id=4
    public function message_new() {
        $class_id = (int) $this->input->get_post('class_id');
        $sql = 'SELECT ar.class_id cid FROM ab_relation ar '
                . 'LEFT JOIN address_book ab ON ar.ab_id = ab.id '
                . 'WHERE ab.user_id = ' . $this->_user_id . ' AND ab.del = 0 AND ar.del = 0 AND ar.active = 1';
        
        $class = $this->_db_jxt->query($sql)->row_array();
        
        if (!$class || !$class_id || $class_id != $class['cid']) {
            $this->error_message(array('response_error_message' => '班级已被解散，请退出重新登录', 'response_error_code' => 99));
        }
        $data = $this->_count_message_new($class['cid'], $this->_user_id);
        $this->success_message($data);
    }

    private function _count_message_new($class_id, $user_id) {
        $data = array();
        $sql = "SELECT COUNT(1) total FROM `message_relation` mr LEFT JOIN message m ON mr.message_id = m.id WHERE `read_status` = 0 
			AND `sendee` = {$user_id} AND `class_id` = {$class_id} AND m.`send_time` < {$this->time} AND mr.`del` = 0 AND m.del = 0";
                        
        $res = $this->_db_jxt->query($sql)->row_array();
        $data['message_count'] = $res['total'];
        
        $last_photo = $this->_db_jxt->from('photo_read')
                ->select('MAX(last_photo_id) pid')
                ->where("user_id = {$this->_user_id} AND del = 0")
                ->get()->row();
                
        $last_pid = max(array(0, intval($last_photo->pid)));
        
        $unknown_photo = $this->_db_jxt->from('photo')
                ->where("class_id = {$class_id} AND id > {$last_pid} AND del = 0")
                ->get()->row();
                
        $data['class_album_has_new'] = $unknown_photo ? 1 : 0;
        
        $last_article = $this->_db_jxt->from('article_read')
                ->select('MAX(last_article_id) aid')
                ->where("user_id = {$this->_user_id} AND del = 0")
                ->get()->row();
                
        $last_aid = max(array(0, intval($last_article->aid)));
                
        $unknown_article = $this->_db_jxt->from('article a')
                ->join('article_relation ar', 'a.id = ar.article_id')
                ->where("ar.class_id = {$class_id} AND a.id > {$last_aid} AND a.format_operate = 1 AND ar.del = 0 AND a.del = 0")
                ->get()->row();
        
        $data['class_space_has_new'] = $unknown_article ? 1 : 0;
        return $data;
    }

    // http://192.168.11.215/jxt/message_list?class_id=3&count=2&time=1392970662,http://192.168.11.75:8090/jxt/message_list?class_id=3&count=a
    public function message_list() {
        $class_id = (int) $this->input->get_post('class_id');
        if (empty($class_id)) {
            $this->error_message(array('response_error_message' => '参数错误，请退出重新登录', 'response_error_code' => 99));
        }
        $time = $this->input->get_post('time') ? (int) $this->input->get_post('time') : 0;
        $type = $this->input->get_post('type') ? (int) $this->input->get_post('type') : 1;
        $count = $this->input->get_post('count') && $type == 1 ? (int) $this->input->get_post('count') : FALSE;

        $where = '';
        if ($time) {
            $type == 1 && $where = ' AND m.send_time < ' . $time;
            $type == 2 && $where = ' AND m.send_time > ' . $time . ' AND m.send_time < ' . $this->time;
        }else{
            $where = ' AND m.send_time < ' . $this->time;
        }
        $sql = "SELECT m.id message_id, m.sender, m.content, m.send_time, mr.read_status, m.`type`, m.call_parent FROM message_relation mr
		LEFT JOIN message m ON mr.message_id = m.id WHERE mr.sendee = {$this->_user_id} AND mr.class_id = {$class_id} AND
		m.del = 0 AND mr.del = 0 {$where} ORDER BY m.send_time DESC";
                
        $count && $sql .= ' LIMIT ' . $count;
        $res = $this->_db_jxt->query($sql)->result_array();
        $data = array();
        if (!empty($res)) {
            $i = 0;
            $this->load->model("class/classes");
            $this->load->model("class/class_model");
            $class_info = $this->class_model->g_classinfo($class_id, 'classname');
            $parent_info = $this->_get_address_book($this->_user_id, 'parent_name');
            $my_name = $this->_db_jxt->from('address_book')->select('parent_name')->where("user_id = {$this->_user_id} AND del = 0")->get()->row();
            foreach ($res as $key => $val) {
                $data[$i]['message_id'] = $val['message_id'];
                $data[$i]['sender'] = $this->classes->get_realname($val['sender']); // 真实姓名
                if ($val['type'] == 1) {
                    $data[$i]['sendee'] = $class_info['classname'];
                } else {
                    $data[$i]['sendee'] = $parent_info['parent_name'];
                }
                if($val['call_parent']){
                    $val['content'] = $my_name->parent_name . '家长，您好！' . $val['content'];
                }
                $data[$i]['content'] = $val['content'];
                $data[$i]['had_read'] = $val['read_status'];
                $data[$i]['time'] = $val['send_time'];
                $this->_update_message_receive_status($val['message_id'], $val['sender']);
                $i ++;
            }
        }

        $this->success_message($data);
    }

    private function _update_message_receive_status($message_id, $sender) {
        $sql = "UPDATE `message_relation` SET receive_status = 1 WHERE
			 message_id = {$message_id} AND sendee = {$sender} LIMIT 1";
        $this->_db_jxt->query($sql);
    }

    private function _get_manu_name($menu_id) {
        $sql = "SELECT name FROM menu WHERE id = {$menu_id} AND del = 0 LIMIT 1";
        $res = $this->_db_jxt->query($sql)->row_array();
        return ($res && isset($res['name'])) ? $res['name'] : '';
    }

    private function _get_address_book($user_id, $fields = "*") {
        $sql = "SELECT {$fields} FROM address_book WHERE user_id = {$user_id} AND del = 0 LIMIT 1";
        $res = $this->_db_jxt->query($sql)->row_array();
        return $res;
    }

    //http://192.168.11.215/jxt/message_search?query=1&class_id=3
    public function message_search() {
        $query = $this->input->get_post('query');
        $class_id = (int) $this->input->get_post('class_id');
        if (empty($query)) {
            $this->error_message(array('response_error_message' => '搜索内容不能为空', 'response_error_code' => 70004));
        }
        $sql = "SELECT m.id message_id, m.sender, m.content, m.send_time, mr.read_status, m.`type` FROM message_relation mr
		LEFT JOIN message m ON mr.message_id = m.id WHERE mr.sendee = {$this->_user_id} AND mr.class_id = {$class_id} AND
		m.del = 0 AND mr.del = 0 AND m.`content` LIKE '%{$query}%' ORDER BY m.send_time DESC";
        $res = $this->_db_jxt->query($sql)->result_array();
        $data = array();
        if (!empty($res)) {
            $i = 0;
            $this->load->model("class/classes");
            $this->load->model("class/class_model");
            $class_info = $this->class_model->g_classinfo($class_id, 'classname');
            $parent_info = $this->_get_address_book($this->_user_id, 'parent_name');
            foreach ($res as $key => $val) {
                if ($val['type'] == 1) {
                    $data[$i]['sendee'] = $class_info['classname'];
                } else {
                    $data[$i]['sendee'] = $parent_info['parent_name'];
                }
                $data[$i]['message_id'] = $val['message_id'];
                $data[$i]['sender'] = $this->classes->get_realname($val['sender']); // 真实姓名
                $data[$i]['content'] = $val['content'];
                $data[$i]['had_read'] = $val['read_status'];
                $data[$i]['time'] = $val['send_time'];
                $i ++;
            }
        }
        $this->success_message($data);
    }

    //http://192.168.11.215/jxt/message_read?ids=30
    public function message_read() {
        $ids = $this->input->get_post('ids');
        if (!empty($ids)) {
            $sql = "UPDATE message_relation SET read_status = 1 WHERE message_id IN ($ids) AND `sendee` = {$this->_user_id}";
            $this->_db_jxt->query($sql);
            $affect = $this->_db_jxt->affected_rows();
            $this->success_message(array('message_id' => $ids));
        } else {
            $this->error_message(array('response_error_message' => '未传入数据', 'response_error_code' => 20001));
        }
    }

    //http://192.168.11.215/jxt/album_list?class_id=671&count=2&offset=1
    public function album_list() {
        $class_id = (int) $this->input->get_post('class_id');
        $offset = (int) $this->input->get_post('offset');
        $count = $this->input->get_post('count') ? (int) $this->input->get_post('count') : 20;
        $start = $offset ? ($offset - 1) * $count : 0;
        
        $res = $this->_db_jxt->from('photo')
                ->where("class_id = {$class_id} AND del = 0")
                ->order_by('id', 'desc')
                ->limit($count, $start)
                ->get()->result_array();
        $data = array();
        if (!empty($res)) {
            $last_id = $this->_db_jxt->from('photo_read')
                    ->select('MAX(last_photo_id) pid')
                    ->where("user_id = {$this->_user_id} AND class_id = {$class_id} AND del = 0")
                    ->get()->row();

            if(intval($res[0]['id']) > intval($last_id->pid)){
                $this->_db_jxt->insert('photo_read', array(
                    'last_photo_id' => $res[0]['id'],
                    'user_id' => $this->_user_id,
                    'class_id' => $class_id,
                    'create_time' => $this->time
                ));
            }
                    
            $this->load->model("login/register_model", 'reg');
            $i = 0;
            
            $this->load->library('qiniu_jxt');
            foreach ($res as $val) {
                $teacher = $this->reg->get_user_info($val['creator']);
                $data[$i]['id'] = $val['id'];
                $data[$i]['creator'] = $teacher['user']->name;
                $data[$i]['time'] = $val['create_time'];
                $data[$i]['name'] = $val['name'];
                $data[$i]['big_url'] = $this->qiniu_jxt->qiniu_img_thumbw($val['photo'], 640);
//                $data[$i]['small_url'] = $this->qiniu_jxt->qiniu_img_thumbr($val['photo'], 200, 200);
                $i ++;
            }
        }
        $this->success_message($data);
    }

    //http://192.168.11.215/jxt/space_list?class_id=3&count=2&offset=1
    public function space_list() {
        $class_id = (int) $this->input->get_post('class_id');
        $offset = (int) $this->input->get_post('offset');
        $count = (int) $this->input->get_post('count');
        $start = $offset ? ($offset - 1) * $count : 0;
//        $sql = "SELECT a.id, a.title, a.summary, a.author, a.edit_time, ar.menu_id, a.cover FROM
//			article_send_relation asr 
//			LEFT JOIN article_relation ar ON asr.article_id = ar.article_id
//			LEFT JOIN article a ON asr.article_id = a.id 
//			WHERE asr.class_id = {$class_id} AND asr.del = 0 AND a.del = 0 AND ar.del = 0 AND asr.sendee = {$this->_user_id}
//			ORDER BY a.`edit_time` DESC LIMIT {$start}, {$count}";
        
//        $res = $this->_db_jxt->query($sql)->result_array();
        $res = $this->_db_jxt->from('article a')
                ->join('article_relation ar', 'a.id = ar.article_id')
                ->where("ar.class_id = {$class_id} AND format_operate = 1 AND a.del = 0 AND ar.del = 0")
                ->select('a.*, ar.menu_id')
                ->order_by('a.id', 'desc')
                ->limit($count, $start)
                ->get()->result_array();

        $data = array();
        if (!empty($res)) {
            $last_aid = $this->_db_jxt->from('article_read')
                    ->select('MAX(last_article_id) aid')
                    ->where("user_id = {$this->_user_id} AND class_id = {$class_id} AND del = 0")
                    ->get()->row();
            
            if(intval($res[0]['id']) > intval($last_aid->aid)) {
                $this->_db_jxt->insert('article_read', array(
                    'last_article_id' => $res[0]['id'],
                    'user_id' => $this->_user_id,
                    'class_id' => $class_id,
                    'create_time' => $this->time
                ));
            }


            $i = 0;
            $this->load->library('qiniu_jxt');
            foreach ($res as $val) {
                $data[$i]['article_id'] = $val['id'];
                $data[$i]['title'] = $val['title'];
                $this->load->model("login/register_model");
                $user_info = $this->register_model->get_user_info($val['author']);
                $data[$i]['author'] = $user_info['user']->name; //$val['session_idor'];
                $data[$i]['subject'] = $this->_get_manu_name($val['menu_id']);
                $data[$i]['content'] = $val['summary'];
                if($val['cover']){
                    if(preg_match('/http\:\/\//', $val['cover'])){
                        $data[$i]['image_url'] = $val['cover'];
                    }else{
                        $data[$i]['image_url'] = $this->qiniu_jxt->qiniu_img_thumbr($val['cover'], 200, 200);
                    }
                }else{
                    $data[$i]['image_url'] = '';
                }
                
                $data[$i]['time'] = $val['edit_time'];
                $i ++;
            }
            $this->success_message($data);
        } else {
            $this->success_message();
        }
    }

    //http://192.168.11.215/jxt/space?article_id=1&session_id=aaa
    public function space() {
        $article_id = (int) $this->input->get_post('article_id');
        $sql = "SELECT a.title, a.summary, a.author, a.edit_time, ac.content FROM `article` a 
		LEFT JOIN article_content ac ON a.id = ac.article_id 
		WHERE a.id = {$article_id} AND a.del = 0";
        $res = $this->_db_jxt->query($sql)->row_array();
        
        if (!empty($res)) {
            $sql = "UPDATE `article` SET `viewnum` = `viewnum` + 1 WHERE `id` = {$article_id}";
            $this->_db_jxt->query($sql);
            $this->load->model("login/register_model");
            $user_info = $this->register_model->get_user_info($res['author']);
            
            if (isset($res['content']) && $res['content']) {
                $res['content'] = preg_replace('/\sdata\-original\=[\'\"][^\'|^\"]*[\'\"]\s/', '', $res['content']);
                preg_match_all('/(\<img.*?)data-jxt_key=[\'"](.*?)[\'"](.*?)\>/i', $res['content'], $matches);
                
                $this->load->library('qiniu_jxt');
                $treated = array();
                foreach ($matches[2] as $value) {
                    if(in_array($value, $treated)){
                        continue;
                    }
                    $treated[] = $value;
                    $img_url = $this->qiniu_jxt->qiniu_img_thumbw($value, 640);
                    
                    $box = '<div class="content-full"><div class="content-img"><a href="' 
                            . site_url('jxt/show_img') 
                            . '?session_id=' 
                            . $this->_session_id 
                            . '&key=' 
                            . base64_encode($value) . '">$1</a></div></div>';

                    $value = str_replace('/', '\/', $value);
                    $res['content'] = preg_replace("/(\<img[^\>]*data\-jxt_key\=[\'\"]{$value}[\'\"]\s)([^\>]*\/\>)/", "$1data-original=\"{$img_url}\" $2", $res['content']);
                    
                    //preg_replace('/\<p\>(\<img.*data\-jxt_key\=[\'\"](.*?)[\'\"].*\>)\<\/p\>/', $box, $acontent)
                    $res['content'] = preg_replace("/(\<img[^\>]*data\-jxt_key\=[\'\"]{$value}[\'\"]\s[^\>]*\/\>)/", $box, $res['content']);
                }
            }
            
            $acontent = '<!DOCTYPE html><html lang="en">'
                    . '<head>'
                    . '<meta charset="utf-8">'
                    . '<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">'
                    . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
                    . '<title>'
                    . $res['title']
                    . '</title>'
                    . '<link href="' . $this->static_url . '/style/reset.css?1.5" rel="stylesheet">'
                    . '<link href="' . $this->static_url . '/style/style.css?1.5" rel="stylesheet">
                        <!--[if lt IE 9]>
                            <script src="' . $this->static_url . '/script/html5.js"></script>
                        <![endif]-->
                        </head>
                        <body>
                        <!--主体页面开始-->
                        <div id="wrap" class="wrap">
                        <header>
                        <!--顶部导航头开始-->
                        <div class="navbar" role="navigation">
                        <h1>' . $res['title'] . '</h1>
                            <p>' . $user_info['user']->name . ' ' . date('m-d H:i', $res['edit_time']) . '</p>
                                <!--顶部导航头结束-->
                                </div>
                                </header>
                                <!--页面主体开始-->
                                <section class="content"><div class="wrapcontent">';
            
            if (isset($res['content']) && $res['content']) {
                $acontent .= $res['content'];
            }

            $acontent .= '</div></section>
</div>
                </body>
                <script type="text/javascript" src="' . $this->static_url . '/script/jquery.min.js"></script>
                    <script type="text/javascript" src="' . $this->static_url . '/script/jquery.lazyload.min.js"></script>
                        <script type="text/javascript">
                        $(function(){
                        $("img.lazy").lazyload();
                        });
                        </script>
                        </html>';
//            echo $acontent;die;
//            echo preg_match('/width\:[^\;]*\;/', $acontent);die;
//            $str = 'width: 159px;';
//            <div class="content-full">
//       		 <div class="content-img"><a href="javascript:void(0);"><img data-original="style/img/emu.jpg" src="style/img/emu.jpg" class="lazy" style="display: inline-block;"></a></div>
//             <div class="content-img"><a href="javascript:void(0);"><img data-original="http://pic.baomihua.com/photos/201109/m_6_634528119806562500_15733917.jpg" src="http://pic.baomihua.com/photos/201109/m_6_634528119806562500_15733917.jpg" class="lazy" style="display: inline-block; width: 100px;"></a></div>
//        </div>
//            $box = '<div class="content-full"><div class="content-img"><a href="' . site_url('jxt/show_img') . '/$2">$1</a></div></div>';
            $acontent = preg_replace('/style=\"[^\"]*\"/', '', $acontent);
//            echo preg_replace('/\<p\>(\<img.*data\-jxt_key\=[\'\"](.*?)[\'\"].*\>)\<\/p\>/', $box, $acontent);
            echo $acontent;
//            echo $acontent;
        } else {
            
        }
    }

    // 给标哥那边的接口,http://192.168.11.215/jxt/app_notice?type=1&version=1&channel_name=test
//    public function app_notice() {
//        $type = (int) $this->input->get_post('type'); // 1ios 2android
//        $version = (int) $this->input->get_post('version');
//        $channel_name = $this->input->get_post('channel_name');
//        if (empty($type) || empty($version)) {
//            $this->error_message(array('response_error_message' => '缺少参数', 'response_error_code' => 40001));
//        }
//        $where = !empty($channel_name) ? " AND `channel_name` = '{$channel_name}'" : '';
//        $sql = "SELECT * FROM `app_notice` WHERE `type` = {$type} {$where}
//			 ORDER BY version DESC LIMIT 1";
//        $res = $this->_db_jxt->query($sql)->row_array();
//        if (!empty($res)) {
//            $data = array();
//            $data['op'] = $res['version'] > $version ? 2 : 1;
//            if ($res['version'] > $version) {
//                $data['verison_url'] = $res['url'];
//                $data['update_tip'] = $res['update_tip'];
//                $data['description'] = $res['description'];
//                $data['app_name'] = $res['app_name'];
//                $data['version_num'] = $res['version'];
//            }
//            $this->success_message($data);
//        } else {
//            $this->error_message(array('response_error_message' => '没有数据', 'response_error_code' => 40002));
//        }
//    }

    // 成功信息
    private function success_message($response_data = array()) {
        $data['response_status'] = 'ok';
        $data['response_data'] = $response_data;
        self::json_output($data);
    }

    // 报错信息
    private function error_message($data = array()) {
        $data['response_status'] = 'error';
        self::json_output($data);
    }

    static protected function json_output($data) {
        header('Content-Type: application/json');
        if (isset($_GET['callback'])) {
            $callback = $_GET['callback'];
            echo "{$callback}(", json_encode($data), ")";
            die;
        }
        echo json_encode($data);
        die;
    }

    public function register() {
        $this->load->model("login/register_model");
        var_dump($this->register_model->insert_register('13000000000', '123456', 'lijie_test', 2, 4));
    }

    public function show_img(){
        $key = $this->input->get_post('key');
        $this->load->library('qiniu_jxt');
        $img_url = $this->qiniu_jxt->qiniu_img_thumbw(base64_decode($key), 640);
        echo json_encode(array('status' => 1, 'url' => $img_url));
    }
    
    //班级是否被解散
    private function is_class_disband(){
        $class_id = $this->input->get_post('class_id');
        $user_class = $this->_db_jxt->from('ab_relation ar')
                ->join('address_book ab', 'ar.ab_id = ab.id')
                ->select('ar.class_id')
                ->where("ab.user_id = {$this->_user_id} AND ar.active = 1 AND ab.del = 0 AND ar.del = 0")
                ->get()->row();
                
        if($user_class->class_id != $class_id){
            $this->error_message(array('response_error_message' => '班级已被解散，请退出重新登录', 'response_error_code' => 99));
        }        
        if($user_class && $user_class->class_id && $class_id){
            $class_info = $this->_db_tizi->from('classes')
                    ->select('class_status')
                    ->where("id = {$user_class->class_id}")
                    ->get()->row();
                    
            if($class_info->class_status == 1){
                $this->_db_jxt->where("class_id = {$user_class->class_id}")->update('ab_relation', array('del' => 1));
                $this->error_message(array('response_error_message' => '班级已被解散，请退出重新登录', 'response_error_code' => 99));
            }
        }else{
            $this->error_message(array('response_error_message' => '班级已被解散，请退出重新登录', 'response_error_code' => 99));
        }
    }
}
