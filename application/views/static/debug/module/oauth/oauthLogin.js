define(function (require,exports){
	require('tizi_ajax');
	require('md5');

	exports.login = function(){
	
		$("#submit").click(function(e){
			e.preventDefault();

			var username = $("input[name='username']").val();
			var password = tizi_md5($("input[name='password']").val());

			$.tizi_ajax({

				url:baseUrlName+'oauth/show',
				data:{'username':username, 'password':password},
				type:'POST',
				success:function(data){
					if(data.status == 99){
						window.location.href = baseUrlName+'oauth/authorize';
					}else{
						alert(data.msg);
					}
				}

			})

		})
	}
	//i切换用户
	exports.logout = function(){
	
		$(".userOther").click(function(){
			$.tizi_ajax({
				url:baseUrlName+'oauth/show',
				data:{'logout':'1'},
				type:'POST',
				success:function(data){
					window.location.reload();
				}
			})	
		})

	}

})
