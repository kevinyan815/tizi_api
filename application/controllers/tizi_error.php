<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Tizi_Error extends CI_Controller {

    function __construct()
    {
        parent::__construct();
    }

    function index()
    {
        set_status_header('404');
        echo '404 Error';
    }
}