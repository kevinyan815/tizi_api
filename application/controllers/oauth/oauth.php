<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require(dirname(dirname(__DIR__)).'/'.'core/Api_Controller.php');
class Oauth extends Api_Controller {

    public function __construct(){

        parent::__construct();

		//
		$this->load->helper('form');
		//
        $this->load->library('OauthServer');
        $request = new League\OAuth2\Server\Util\Request();

        // Create the auth server, the three parameters passed are references
        //  to the storage models
        $this->authserver = new League\OAuth2\Server\Authorization(
            new ClientModel,
            new SessionModel,
            new ScopeModel
        );
        // Enable the authorization code grant type
        $this->authserver->addGrantType(new League\OAuth2\Server\Grant\AuthCode());
		$this->authserver->setAccessTokenTTL(7776000);//90 days
		$this->authserver->requireStateParam();
		$this->authserver->setDefaultScope('get_user_info');
		//$this->authserver->requireScopeParam();

    }

    public function index(){

        try {

            // Tell the auth server to check the required parameters are in the
            //  query string
            $params = $this->authserver->getGrantType('authorization_code')->checkAuthoriseParams();

            $this->session->set_userdata('client_id', $params['client_id']);
            $this->session->set_userdata('client_details', $params['client_details']);
            $this->session->set_userdata('redirect_uri', $params['redirect_uri']);
            $this->session->set_userdata('response_type', $params['response_type']);
            $this->session->set_userdata('scopes', $params['scopes']);
            $this->session->set_userdata('state', $params['state']);

            redirect('oauth/show');//oauth/oauth/signin

        } catch (Oauth2\Exception\ClientException $e) {
             exit("error");
        } catch (Exception $e) {
            exit($e->getMessage());
            // Throw an error here which has caught a non-library specific error
        }
    }

    public function signin() {

		$this->load->model('login/login_model');
		$this->load->model('login/session_model');
        // Process the sign-in form submission
        //已废弃
  //       if ($this->input->post('username') != null) {
  //           try {

		// 		$response = array();
	
		// 		$username = $this->input->post('username', true);
		// 		$password = $this->input->post('password', true);

		// 		$submit=array('errorcode'=>false, 'response_status' => 'error', 'error'=>'');
		// 		$user_id=$this->login_model->login($username,$password);

		// 		if($user_id['errorcode']==Constant::LOGIN_SUCCESS)
		// 		{
		// 			//create app session
		// 			$session_id=$this->session_model->generate_session($user_id['user_id']);
		// 			$this->session_model->generate_cookie($username,$user_id['user_id']);
		// 			$this->session_model->clear_mscookie();
		// 			//$this->session->set_userdata('user_id', $user_id['user_id']);
		// 			$response = ['status'=> 99, 'msg'=>'login success'];

		// 		}
		// 		else
		// 		{
		// 			$response['status'] = 1;
		// 			$response['msg'] = $this->lang->line('error_'.strtolower($user_id['error']));
		// 		}

		// 		exit(json_encode($response));

  //           } catch (Exception $e) {
  //               $params['error_message'] = $e->getMessage();
  //           }
		// }
        //切换用户
        if($this->input->post('logout') != NULL){

			if($this->session->userdata('user_id')){

				$this->session_model->clear_session();
				$this->session_model->clear_cookie();
				$this->session_model->clear_current_dir_cookie();

			}
			exit(json_encode(['status'=>99, 'msg'=>'success']));

		}
        //授权登录首次进入
        else{
		
			try {

				$params = $this->authserver->getGrantType('authorization_code')->checkAuthoriseParams();

				$this->session->set_userdata('client_id', $params['client_id']);
				$this->session->set_userdata('client_details', $params['client_details']);
				$this->session->set_userdata('redirect_uri', $params['redirect_uri']);
				$this->session->set_userdata('response_type', $params['response_type']);
				$this->session->set_userdata('scopes', $params['scopes']);
				$this->session->set_userdata('state', $params['state']);

			} catch (Oauth2\Exception\ClientException $e) {

				exit($e->getMessage);

			} catch (Exception $e) {
				exit($e->getMessage());
				// Throw an error here which has caught a non-library specific error
			}
		}
        //未登录
        if (($user_id = $this->session->userdata('user_id')) != null) {

			$this->load->model('login/register_model');
			$user_info = $this->register_model->get_user_info($user_id);
			$this->session->userdata('scopes', '');

			$this->smarty->assign('user_id', true);
			$this->smarty->assign('name', $user_info['user']->name);
			$this->smarty->display('oauth/signin.html');
            //redirect('oauth/authorize');

        }
        //已登录，可切换用户
        else {
			$this->smarty->display('oauth/signin.html');
        }
    }

    public function authorize() {

        // init auto_approve for default value
        $params['client_details']['auto_approve'] = 0;

        // Retrieve the auth params from the user's session
        $params['client_id'] = $this->session->userdata('client_id');
        $params['client_details'] = $this->session->userdata('client_details');
        $params['redirect_uri'] = $this->session->userdata('redirect_uri');
        $params['response_type'] = $this->session->userdata('response_type');
        $params['scopes'] = $this->session->userdata('scopes');
        $params['state'] = $this->session->userdata('state');

        // Check that the auth params are all present
        foreach ($params as $key=>$value) {
            if ($value === null) {
				 exit("missing parameter ->" . $key);
            }
        }

        // Get the user ID
        $params['user_id'] = $this->session->userdata('user_id');

        // User is not signed in so redirect them to the sign-in route (/oauth/signin)
        if ($params['user_id'] == null) {
            redirect('oauth/oauth/signin?'.$_SERVER['QUERY_STRING']);
        }

        // init autoApprove if in database, value is 0
        $params['client_details']['auto_approve'] = isset($params['client_details']['auto_approve']) ? $params['client_details']['auto_approve'] : 0;

        // Check if the client should be automatically approved
        $autoApprove = ($params['client_details']['auto_approve'] == '1') ? true : false;

		// Process the authorise request if the user's has clicked 'approve' or the client
        if ($this->input->post('approve') == 'yes' || $autoApprove === true) {

			if ($this->input->post('approve') == 'yes') 
				$params['scopes'] = "get_user_info";//test

            // Generate an authorization code
            $code = $this->authserver->getGrantType('authorization_code')->newAuthoriseRequest('user',   $params['user_id'], $params);

            // Redirect the user back to the client with an authorization code
            $redirect_uri = League\OAuth2\Server\Util\RedirectUri::make(
                $params['redirect_uri'],
                array(
                    'code'  =>  $code,
                    'state' =>  isset($params['state']) ? $params['state'] : ''
                )
            );
            redirect($redirect_uri);
        }

        // If the user has denied the client so redirect them back without an authorization code
        if($this->input->get('deny') != null) {
            $redirect_uri = League\OAuth2\Server\Util\RedirectUri::make(
                $params['redirect_uri'],
                array(
                    'error' =>  'access_denied',
                    'error_message' =>  $this->authserver->getExceptionMessage('access_denied'),
                    'state' =>  isset($params['state']) ? $params['state'] : ''
                )
            );
            redirect($redirect_uri);
        }

        echo form_open('oauth/authorize');
        echo form_submit('approve', 'yes');
        echo form_close();
    }

    public function access_token() {

        try {

			if(isset($_POST['code']) && !empty($_POST['code'])){

				$code = $_POST['code'];
				$user_id = $this->authserver->getUserId($code);
				if(!$user_id) goto code_error;
				$this->load->model('login/register_model');
				$user = (array)$this->register_model->get_user_info($user_id)['user'];
				if(empty($user)) goto code_error;
				
			}else{
				code_error:{
					throw new Exception('code error');
				}
			}
            // Tell the auth server to issue an access token
            $response = $this->authserver->issueAccessToken(array('user_id'=>$user['id']));

        } catch (League\OAuth2\Server\Exception\ClientException $e) {

            // Throw an exception because there was a problem with the client's request
            $response = array(
                'error' =>  $this->authserver->getExceptionType($e->getCode()),
                'error_description' => $e->getMessage()
            );

			$this->output->set_header($this->authserver->getExceptionHttpHeaders($this->authserver->getExceptionType($e->getCode()))[0]);

        } catch (Exception $e) {

            // Throw an error when a non-library specific exception has been thrown
            $response = array(
                'error' =>  'undefined_error',
                'error_description' => $e->getMessage()
            );
        }

        header('Content-type: application/json');
		if(isset($response['error']) && empty($response['error'])){
			unset($response['error']);
		}
        exit(json_encode($response));

    }
	


}
