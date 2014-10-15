// 配置sea的路径，别名等参数
// 获取当前时间
var myDate = new Date();
var timestamp = myDate.getFullYear() +'' + myDate.getMonth()+1 + '' + myDate.getDate() + '' + myDate.getHours() + '' + myDate.getMinutes();
// seajs配置开始
seajs.config({
	// 基础目录
    base: staticPath,
    // 别名
    alias: aliasContent,
	// 映射
	'map': [
	    [ /^(.*\.(?:css|js))(.*)$/i, '$1?version=' + timestamp ]
	  ]
})


// 调用方法
seajs.use('jquery',function(){
	
	// 得到当前页面是哪个
	var _page = $('#tiziModule').attr('module');
	// 如果page不存在则返回
	if(!_page){return;}
	switch(_page){
		// 模块是首页
		case 'oauth':
		seajs.use("module/oauth/init");
		break;
	}

})
