<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Oauth_User extends MY_Controller {

	private $_server;
	private $authserver;

	public function __construct(){
	
		parent::__construct();

        $this->load->library('OauthServer');
		$this->_check_access_token();
	}

	public function me(){
		
		$openId = alpha_id($this->_server->getOwnerId());
		$openId = $this->authserver->makeOpenId($openId);
		$response = array('openid'=>$openId);
		$this->output
			->set_content_type('application/json')
			->set_output(json_encode($response))
			->_display();
		exit();

	}

	//return some basic information about this user	
	public function get_user_info() {

		$this->load->model('login/register_model');
		$this->load->model('user_data/student_data_model');
        $this->load->model('constant/grade_model');
        $this->load->model('question/question_subject_model');
		// Check the access token's owner is a user
		if ($this->_server->getOwnerType() === 'user')
		{
			$userId = $this->_server->getOwnerId();

			$openId = $this->input->get('openid');
			if (!empty($openId)) {
			
				if ($userId != alpha_id($this->authserver->getUidByOpenId($openId), true)) {
					goto openId_error;
				}

			}else{
				
				openId_error:{
					
					$this->output
						->set_content_type('application/json')
						->set_output(json_encode(array(
							'error' => 'openId error'
						)));
					$this->output->_display();
					exit();				
				}
				
			}

			$user = (array)$this->register_model->get_user_info($userId)['user'];
			// If the user can't be found return 404
			if ( ! $user)
			{
				$this->output
					 ->set_status_header('404')
					 ->set_content_type('application/json')
					 ->set_output(json_encode(array(
						'error' => 'Resource owner not found'
					)));
				$this->output->_display();
				exit();
			}
			else
			{
				// Basic response
				$response = array(
					'openId' => $openId,
					'nick'  => $user['name'],
                    'email'=>$user['email'],
				);
                $grade_name = '';
                $grade_id = $user['register_grade'];
                if ($grade_id) {
                    $grades = $this->grade_model->get_grade();
                    foreach ($grades as $key=>$grade) {
                        if($key == $grade_id) {
                            $grade_name = $grade;
                        }
                    }               
                }
                $response['grade'] = $grade_name;
                $response['username'] = $user['uname'];
                $gender = '';
                $student_data=$this->student_data_model->get_student_data($userId);
                if(!empty($student_data))                          
                {               
                    $gender = $student_data->sex;
                }        
                $response['gender'] = $gender;
                $subject_name = '';
                if (!empty($user['register_subject'])) {
                    $subject_name = $this->question_subject_model->get_subject_name($user['register_subject']);
                }
                $response['subject_name'] = $subject_name;
                $this->load->helper("img_helper");
                $response['avatar'] = $user['avatar'] ? path2avatar($userId):'';
                $phone_info = $this->register_model->get_phone($userId);
                $response['phone'] = $phone_info['errorcode'] == 1 ? $phone_info['phone'] : '';
				if ($this->_server->hasScope('user.contact'))
				{
					$response['result']['email'] = $user['email'];
					$response['result']['phone'] = $user['phone'];
				}
			
				// Respond
				$this->output
					->set_content_type('application/json; charset=utf-8')
					->set_output(json_encode($response))
					->_display();
				exit();

			}
		}
		else
		{
			$this->output
				->set_status_header('403')
				->set_content_type('application/json')
				->set_output(json_encode(array(
				'error' => 'Only access tokens representing users can use this endpoint'
			)))
				->_display();
			exit();

		}

	}

	private function _check_access_token(){

		try {

			$this->authserver = new League\OAuth2\Server\Authorization(
				new ClientModel,
				new SessionModel,
				new ScopeModel
			);
			$request = new League\OAuth2\Server\Util\Request();
			$this->_server = new League\OAuth2\Server\Resource(
				new SessionModel()
			);
			$this->_server->isValid();
		}
		catch (League\OAuth2\Server\Exception\InvalidAccessTokenException $e)
		{
			$this->output
				->set_status_header('403')
				->set_content_type('application/json')
				->set_output(json_encode(array(
				'error' =>  $e->getMessage()
			)))
				->_display();
			exit();

		}

	}




}
