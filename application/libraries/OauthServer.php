<?php

class OauthServer{

    public function __construct(){
    
        //auto load lib
        require(__DIR__.DIRECTORY_SEPARATOR.'Oauth_Server'.DIRECTORY_SEPARATOR.'ClassLoader.php');
        $class_loader = new ClassLoader(null, __DIR__.DIRECTORY_SEPARATOR.'Oauth_Server');
        $class_loader->register();

    }

	/*
	public function __call($method,$args)
	{
		foreach($this->_objs as $obj)
		{
			if(method_exists($obj,$method))
				return call_user_method_array($method,$obj,$args);
		}

	}


	public function addObj($object)
	{
		$this->_objs[]=$object;
	}
	 */



    


    
    
    





}
