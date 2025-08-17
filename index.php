<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8" />
		<meta http-equiv="X-UA-Compatible" content="IE=edge" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />

		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.7/css/bootstrap.min.css" />
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.4/jquery-confirm.min.css" />
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/plyr/3.7.8/plyr.min.css" />

		<link rel="stylesheet" href="./assets/css/main.css?v=<?php echo time(); ?>" />
		<link rel="stylesheet" href="./assets/css/mobile.css?v=<?php echo time(); ?>" />

		<title>היי מה נשמע?</title>
	</head>
	<body>

		<div id="main" class="main-container row">
			<div id="chats_list" class="left-container col-md-4">
				<!--header -->
				<div class="header row">
					<div class="col-12 row">
						<div class="user_avatar_container col-2">
							<img src="./profile_pics/assaf.jpg" alt="User's Avatar"/>
						</div>
						<div class="user_info_container col-6">
							<div class="user_full_name_comes_here">Assaf Levy</div>
							<div class="user_status_comes_here hide_on_mobile">Online</div>
						</div>
						<div class="logout_btn_container col-4">
							<button class="logout btn btn-dark">Logout</button>
						</div>
					</div>
				</div>
				<!--search-container -->
				<div class="search-container">
					<div class="input">
						<i class="fa-solid fa-magnifying-glass"></i>
						<input type="text" placeholder="Search or start new chat   " />
					</div>
					<i class="fa-sharp fa-solid fa-bars-filter"></i>
				</div>
				<!--chats -->
				<div id="chats" class="chat-list"></div>
				<audio id="notify" preload="auto"></audio>
			</div>

			<div id="chat_window" class="right-container col-md-8">
				<!--header -->
				<div class="header row">
				
					<div class="row col-10">
						<div class="show_chats_list col-2">
							<i class="fa-solid fa-chevron-left"></i>
						</div>
						
						<div class="contact_profile_img col-3">
							<img class="dp" src="" alt="" />
						</div>
						
						<div class="contact_name_container col-7">
							<span class="contact_name"></span>
							<span class="contact_id"></span>
						</div>
					</div>
					
					<div class="contact_more_options col-2">
						<ul class="row">
							<li class="col-6 show_more_option_menu">
								<i class="fa-solid fa-ellipsis-vertical"></i>
							</li>
						</ul>
					</div>
					
				</div>
				<!--chat-container -->
				<div id="msgs" class="chat-container"></div>
				<!--input-bottom -->
				<form id="send_msg" class="send_msg_form chatbox-input">
					<i class="fa-sharp fa-solid fa-paperclip"></i>
					<input id="msg" type="text" placeholder="Type a message" required />
					<button class="submit_msg">
						<i class="fa-solid fa-paper-plane"></i>
					</button>
				</form>
			</div>		
		</div>

		<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
		
		<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.4/jquery-confirm.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/plyr/3.7.8/plyr.min.js"></script>

		<script>
			(function(){
			// 1) token mandatory
			const token = localStorage.getItem('token');
			if(!token){ location.href = 'login.html'; return; }

			// 2) play sound on new notification
			const snd = document.getElementById('notify');
			let soundEnabled = false;

			// 3) get the config (URL sound + on/off)
			fetch('api.php?data=get_config')
				.then(r => r.json())
				.then(j => {
				if(j && j.config){
					snd.src = j.config.notification_sound_url || '';
					soundEnabled = (j.config.notification_sound_enabled === '1');
				}
				})
				.catch(()=>{});

			// 4) Add automatically token for all requests jQuery to api.php
			$.ajaxPrefilter(function(options, original, jqXHR){
				if(!options.url) return;
				if(options.url.indexOf('api.php') === -1) return; // only for api

				// GET: aaddjoute ?token=...
				if((options.type || 'GET').toUpperCase() === 'GET'){
				options.url += (options.url.indexOf('?') === -1 ? '?' : '&') + 'token=' + encodeURIComponent(token);
				}else{
				// POST: append or FormData
				if(original.data instanceof FormData){
					original.data.append('token', token);
					options.data = original.data;
					options.processData = false;
					options.contentType = false;
				}else{
					options.data = (original.data ? original.data + '&' : '') + 'token=' + encodeURIComponent(token);
				}
				}
			});

			// 5) if servor respond is 401/410 => back to login
			$(document).ajaxError(function(_e, xhr){
				if(xhr && (xhr.status === 401 || xhr.status === 410)){
				localStorage.removeItem('token');
				location.href = 'login.html';
				}
			});

			window.APP_AUTH = { token, soundEnabled, snd };
			})();
			</script>
		<script src="./assets/js/main.js?v=<?php echo time(); ?>"></script>
		
	</body>
</html>