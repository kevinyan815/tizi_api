<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

<style type="text/css">
html, body, h1, h2, h3, h4, h5, h6, div, ol, ul, li, dl, dt, dd, p, textarea, input, select, option, form, tr, th, td, img, em, i, var, strong {
margin: 0;padding: 0;font-size: 12px;font-family: '微软雅黑',Tahoma,Arial,sans-serif;}
body,html{height:100%}
h1,h2,h3,h4,h5,h6{ font-size:100%;}
a {text-decoration:none}
a:hover {text-decoration:none;}
em {font-style:normal}
li {list-style:none}
img {border:0;vertical-align:middle}
table {border-collapse:collapse;border-spacing:0}
p {word-wrap:break-word}
.undis {display:none}
.dis {display:block}
a,textarea,input{outline:none}
textarea {overflow:auto;resize:none;}
img {border:none;display: block;}
em,i{ font-style:normal;}
.fl{ float:left; display:inline;}
.fr{ float:right; display:inline;}
.yh{ font-family:"微软雅黑"; font-weight:normal;}
.posr{ position:relative; *zoom:1;}
.layout{width:1000px; margin:0 auto;}
.layout:after,.hd:after,.bd:after,.ft:after,.cf:after,.header:after,.wrap:after,.footer:after,.fn-clear:after{content:"";display:table;clear:both}
.layout,.hd,.bd,.ft,.cf,.header,.wrap,.footer,.fn-clear{*zoom:1}
/*head**/
/*非三端头部开始*/
.commonHeader{ background:#179c7e; height:70px;line-height:70px;}
/*.commonHeader .layout{background:#333;}*/
.commonHeader h1 a{ background:url(<?php echo $site_url; ?>application/views/static/image/common/logo.gif) no-repeat; width:200px; height:50px; text-indent:-999em; overflow:hidden; display:block;margin-top:8px;}
.commonHeader ul.fl{ margin:16px 0px 0px 10px;}
.commonHeader ul.fl li{ float:left;_display:inline; margin-right:10px;}
.commonHeader ul.fl li a{ display:block; background:url(<?php echo $site_url; ?>application/views/static/image/teacher/headerNavBg.gif) no-repeat left top; padding:0px 0 0 17px; line-height:34px; color:#fff; font-family:"黑体"; font-size:14px;}
.commonHeader ul.fl li a span{ background:url(<?php echo $site_url; ?>application/views/static/image/teacher/headerNavBg.gif) no-repeat right 0; padding:0 17px 0 0; display:block;}
.commonHeader ul.fl li a:hover,.commonHeader ul.fl li.on a{ background:url(<?php echo $site_url; ?>application/views/static/image/teacher/headerNavBgOn.gif) no-repeat left 0; color:#2A8D6A; text-decoration:none;}
.commonHeader ul.fl li a:hover span,.commonHeader ul.fl li.on a span{ background:url(<?php echo $site_url; ?>application/views/static/image/teacher/headerNavBgOn.gif) no-repeat right 0;}
.commonHeader .sLink{ margin-top:13px;}
.commonHeader .sLink a{color:#fff;background:url(<?php echo $site_url; ?>application/views/static/image/student/sLink_a_bg.gif) no-repeat; display:block; text-align:center; width:89px;height:25px; line-height:25px;}
.commonHeader .sLink a:hover{ text-decoration:none;}
/*.commonHeader .nav{line-height: 50px;}*/
.commonHeader .nav a{ color:#fff;margin-left:15px; float:left;font-size:14px;}
.commonHeader .nav a:hover{ color:#ccc; text-decoration:none;}
.cBtnFeedback{color:#fff;}
.header_feedback{/*background: url(<?php echo $site_url; ?>application/views/static/image/student/bg.png) top left;_background: url(<?php echo $site_url; ?>application/views/static/image/student/bg.gif) top left;*//*display:inline-block;width:90px;*//*height:25px;line-height:25px;*/color:#fff;text-align:center; margin-right:20px; line-height: 50px;}
/*非三端头部结束*/
/*content**/
/*404页面样式开始*/
.noFind{text-align:center;}
.noFind h2{line-height: 150px;color:#179C7E;font-size:26px;font-weight: normal}
.noFind .bd{background:url(<?php echo $site_url; ?>application/views/static/image/nofound/tizi_nf_tx.gif) no-repeat center 40px;height:200px;border-radius: 10px;margin-bottom:240px;}
.noFind .bd p{padding-top:180px;font-size:18px;line-height:50px;}
/*404页面样式结束*/
/*footer**/
/*底部开始*/
.footer{ position:fixed; z-index:300;_position:static; bottom:0; background:#666; color:#fff; text-align:center; line-height:30px; width:100%;}
.footer a{ color:#ddd; margin:0 5px;}
.footer span{ padding:0 5px;}
.staticDiv{position:static}
/*非三端底部样式开始*/
.commonFooter{background:#fff;color:#333;}
.commonFooter .layout{border-top:1px solid #ccc;padding:10px 0;}
.commonFooter a{color:#333;}
/*非三端底部样式结束*/
</style>
<title>页面不存在－梯子网</title>
</head>

<body>
<!--头部start-->
<div class="commonHeader">
  <div class="layout">
        <h1 class="fl">
            <a href="<?php echo $site_url; ?>">梯子</a>
        </h1>
        <div class="nav fr">
            <?php if($uname): ?>
                <a href="<?php echo $site_url; ?>logout">退出</a>
            <?php else: ?>
                <a href="<?php echo $site_url; ?>">登录</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<!--头部end-->


<!--内容start-->
<div class="noFind layout">
  <h2>出错啦</h2>
  <div class="bd">
      <p>很抱歉，你访问的页面不存在，请检查您访问的网址是否正确。<br />系统将在 <span class="oTime" id="oTime">5</span> 秒后为您跳转到个人主页。</p>
  </div>
</div>
<!--内容end-->


<!--尾部start-->
<div class="footer commonFooter staticDiv" id="footer">
  <div class="layout">
    <p class="fr">  <span>&copy;2013 tizi</span>
  <span>京ICP备12050551号</span>
  <span>京公安网备11010802012731号</span></p>
    <p class="fl">
        <a href="<?php echo $site_url; ?>about/us" target="_blank">关于我们</a> | <a href="<?php echo $site_url; ?>about/report" target="_blank">媒体报道</a> | <a href="<?php echo $site_url; ?>about/school" target="_blank">团体帐户申请</a> | <a href="<?php echo $site_url; ?>about/contact" target="_blank">合作</a> | <a href="<?php echo $site_url; ?>about/join" target="_blank">求职</a> |
    </p>
    </div>
</div>
<!--尾部end-->

<script>
//倒计时跳转页面
var start = 5;
var step = -1;
function count(){
	var oTime = document.getElementById("oTime");
	oTime.innerHTML = start;
	start += step;
	if(start <1 ){
		window.location.href = '<?php echo $site_url; ?>';
	}
	setTimeout("count()",1000);
}
window.onload = count;
</script>
</body>
</html>
