<?php

/*zujuan login*/
//$route['logout']="login/login/logout";
$route['login/submit/tizi']="login/login/tizi_submit";
$route['login/user_login']="login/login/user_login";
$route['login/submit/jxt']="login/login/jxt_submit";

/*zujuan register*/
$route['register/submit/tizi']="login/register/user_register";
$route['register/phone_register']="login/register/phone_register";

/* oauth */
$route['oauth/callback']="login/oauth_login/callback";
$route['oauth/login']="login/oauth_login/login";
$route['oauth/register']="login/oauth_login/register";

/* 3party_login */
$route['sso/login'] = "login/sso_login/login";
//qrcode login
$route['sso/qrcode'] = "login/qrcode_login/login";

/*zujuan send*/
$route['send_phone_code']="login/verify/send_phone_code";
$route['check_code']="login/verify/check_code";
$route['login/check_phone']="login/verify/check_phone";
$route['login/reset_password']="login/login/reset_password";

/*zujuan user info*/
$route['user/change_phone']="user/user_info/change_phone";
$route['user/change_name']="user/user_info/change_name";
$route['user/change_password']="user/user_info/change_password";
$route['user/change_child_name']="user/user_info/change_child_name";
$route['user/change_avatar']="user/user_info/change_avatar";
$route['student/sign_class']="user/user_info/sign_class";
$route['student/dropout_class']="user/user_info/dropout_class";

/*zujuan oral_daliy*/
$route['oral_daily/stu_video_list']="oral_daily/stu_video/get_video_list";

/*for bbs  by zhangxiaoming*/
$route['bbs/login.html']= "bbs/login/index";
$route['bbs/checkuidreg.html']= "bbs/login/returnuid";
