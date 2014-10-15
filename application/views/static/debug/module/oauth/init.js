define(function(require,exports){

	var _oauthLogin = require('module/oauth/oauthLogin');
	_oauthLogin.login();
	_oauthLogin.logout();
	// 加载公共登陆验证
	require("tizi_valid").indexLogin();

})