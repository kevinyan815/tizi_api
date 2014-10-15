<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Homepage extends MY_Controller {

    function __construct()
    {
        parent::__construct();
    }


    function index()
    {
        echo 'Tizi Api';
    }
}