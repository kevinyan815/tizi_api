<?php

class OauthDbModel{

	private static $_oauth_db_model;

	private static $_db;

	private static $_table;

	private function __construct(){
	
	}

	public static function get_instance(){
	
		if( isset(self::$_oauth_db_model) 
			&& self::$_oauth_db_model instanceof OauthDbModel)
		return self::$_oauth_db_model;	

		return self::$_oauth_db_model = new OauthDbModel();	

	}

	public static function db(){
	
		if( isset(self::$_db) && !empty(self::$_db) ){
			
			return self::$_db;
		}
		
		//return a new db instance
        $ci = &get_instance();
		self::$_db = $ci->db;
		return  self::$_db;

	}

	public static function table($table){
	
		self::$_table = $table;

		return self::get_instance();

	}

	public function insert($data){
		
		if(self::db()->insert(self::$_table, $data)) 
			return true;

		return false;

	}

	public function replace($data){

		if(self::db()->replace(self::$_table, $data)) 
			return true;

		return false;
	
	}

	public function insertGetId($data){
	
		if($this->insert($data))
			return self::$_db->insert_id();

		return false;

	}

	public function del($data){
		
		return self::db()->delete(self::$_table, $data); 	

	}

	public function update($where, $data){
		
		self::db()->where($where);
		return self::db()->update(self::$_table, $data);
		
	}

	public function get($data, $select = "*"){
	
		self::db()->select($select);
		self::db()->from(self::$_table);
		self::db()->where($data);
		$query = self::db()->get();

		return $query->result_array();
	
	}

	public function first($data, $select = "*"){
	
		$result = $this->get($data, $select);

		return !empty($result) && isset($result[0]) ? $result[0] :  array();

	}






}

