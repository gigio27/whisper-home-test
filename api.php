<?php
	
	define("a328763fe27bba","TRUE");
	
	#region start
	require_once("config.php");

	#require_once __DIR__ . "/config.php";

	function api_json($code,$data){ http_response_code($code); header('Content-Type: application/json'); echo json_encode($data, JSON_UNESCAPED_SLASHES); exit; }
	function api_db(){ static $m=null; if(!$m){ $m=@new mysqli(MYSQL_DEFAULT_SERVERNAME,MYSQL_DEFAULT_USERNAME,MYSQL_DEFAULT_DB_PASSWORD,MYSQL_DEFAULT_DB_NAME); if($m->connect_errno) api_json(500,["error"=>"database connection failed"]); $m->set_charset("utf8mb4"); } return $m; }
	function require_token(){
	$t = $_POST['token'] ?? $_GET['token'] ?? '';
	if($t==='') api_json(401,["error"=>"token is required"]);
	$s=api_db()->prepare("SELECT username, expires_at FROM auth_tokens WHERE token=?");
	$s->bind_param("s",$t); $s->execute(); $s->bind_result($u,$e);
	if(!$s->fetch()) { $s->close(); api_json(401,["error"=>"invalid token"]); }
	$s->close();
	if(strtotime($e) < time()) api_json(410,["error"=>"session expired"]);
	return $u; // username (email)
	}


						
	header("Content-Type: application/json; charset=utf-8");

	$data = $_GET["data"] ?? null;
	$globals["_GET_DATA"] = $data;

	#endregion start
	
	switch($data){

	case "get_chats":
		$username = require_token();
		$limit = isset($_POST["limit"]) ? (int)$_POST["limit"] : 6;
		if($limit <= 0) $limit = 6;
		if($limit > 100) $limit = 100;

		$query = "
		SELECT
			m.contact_id,
			m.msg_type,
			m.msg_body,
			m.msg_datetime,
			c.contact_name,
			c.profile_picture_url
		FROM messages m
		INNER JOIN (
			SELECT contact_id, MAX(msg_datetime) AS latest_msg
			FROM messages
			WHERE belongs_to_username = ?
			GROUP BY contact_id
		) latest
			ON m.contact_id = latest.contact_id AND m.msg_datetime = latest.latest_msg
		LEFT JOIN contacts c
			ON c.belongs_to_username = ? AND c.contact_id = m.contact_id
		WHERE m.belongs_to_username = ?
		ORDER BY m.msg_datetime DESC
		LIMIT $limit;
		";
		$results = mysql_fetch_array($query,[$username,$username,$username]);
		echo json_encode($results); die();
	break;

	case "get_msgs":
		$username   = require_token();
		$contact_id = isset($_POST["contact_id"]) ? (int)$_POST["contact_id"] : 0;
		if($contact_id <= 0){ api_json(400,["error"=>"contact_id is required"]); }

		$limit = isset($_POST["limit"]) ? (int)$_POST["limit"] : 50;
		if($limit <= 0) $limit = 50;
		if($limit > 500) $limit = 500;

		$query   = "SELECT * FROM messages WHERE `belongs_to_username` = ? AND `contact_id` = ? ORDER BY `msg_datetime` DESC LIMIT $limit;";
		$results = mysql_fetch_array($query,[$username,$contact_id]);
		echo json_encode($results); die();
	break;

	case "get_new_msgs":
		$username   = require_token();
		$contact_id = isset($_POST["contact_id"]) ? (int)$_POST["contact_id"] : 0;
		$last_id    = isset($_POST["last_id"]) ? (int)$_POST["last_id"] : 0;
		if($last_id <= 0){ api_json(400,["error"=>"last_id is required"]); }
		if($contact_id <= 0){ api_json(400,["error"=>"contact_id is required"]); }

		$query   = "SELECT * FROM messages WHERE `row_id` > ? AND `belongs_to_username` = ? AND `contact_id` = ? ORDER BY `msg_datetime` DESC;";
		$results = mysql_fetch_array($query,[$last_id,$username,$contact_id]);
		echo json_encode($results); die();
	break;

	case "get_contact_name_by_contact_id":
		$username   = require_token();
		$contact_id = isset($_POST["contact_id"]) ? (int)$_POST["contact_id"] : 0;
		if($contact_id <= 0){ api_json(400,["error"=>"contact_id is required"]); }

		$query   = "SELECT `contact_name` FROM contacts WHERE `belongs_to_username` = ? AND `contact_id` = ? LIMIT 1;";
		$results = mysql_fetch_array($query,[$username,$contact_id]);
		echo json_encode($results); die();
	break;

	case "get_profile_pic_by_contact_id":
		$username   = require_token();
		$contact_id = isset($_POST["contact_id"]) ? (int)$_POST["contact_id"] : 0;
		if($contact_id <= 0){ api_json(400,["error"=>"contact_id is required"]); }

		$query   = "SELECT profile_picture_url FROM contacts WHERE `belongs_to_username` = ? AND `contact_id` = ? LIMIT 1;";
		$results = mysql_fetch_array($query,[$username,$contact_id]);
		echo json_encode($results); die();
	break;

	case "send_wa_txt_msg":
		$username   = require_token();
		$contact_id = isset($_POST["contact_id"]) ? (int)$_POST["contact_id"] : 0;
		$msg        = $_POST["text"] ?? $_POST["msg"] ?? null; // compat ui

		if(!$msg){ api_json(400,["error"=>"text is required"]); }
		if($contact_id <= 0){ api_json(400,["error"=>"contact_id is required"]); }

		// une seule insertion côté expéditeur (pas de table users dans la base)
		$res = mysql_insert("messages",[
		"belongs_to_username" => $username,
		"contact_id"          => $contact_id,
		"is_from_me"          => 1,
		"msg_type"            => "text",
		"msg_body"            => $msg,
		]);

		echo json_encode(!empty($res["success"])); die();
	break;

	default:
		api_json(400,["error"=>"unknown action"]);
	}


	// a) config for the sound effect
	if(($_GET['data'] ?? '') === 'get_config'){
	$sql="SELECT setting,value FROM config WHERE setting IN ('notification_sound_url','notification_sound_enabled')";
	$r=api_db()->query($sql); $cfg=[]; while($row=$r->fetch_assoc()) $cfg[$row['setting']]=$row['value'];
	api_json(200,["config"=>$cfg]);
	}

	// b) read receipt
	if(($_GET['data'] ?? '') === 'mark_read' && $_SERVER['REQUEST_METHOD']==='POST'){
	$u = require_token();
	$mid = (int)($_POST['message_id'] ?? 0);
	if($mid<=0) api_json(400,["error"=>"message_id is required"]);
	$s=api_db()->prepare("INSERT INTO messages_read(message_id,username,read_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE read_at=VALUES(read_at)");
	$s->bind_param("is",$mid,$u); $s->execute();
	api_json(200,["status"=>"ok"]);
	}

	// c) who read
	if(($_GET['data'] ?? '') === 'get_readers'){
	$u = require_token();
	$mid = (int)($_GET['message_id'] ?? 0);
	if($mid<=0) api_json(400,["error"=>"message_id is required"]);
	$s=api_db()->prepare("SELECT username, read_at FROM messages_read WHERE message_id=? ORDER BY read_at ASC");
	$s->bind_param("i",$mid); $s->execute();
	$res=$s->get_result(); $rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r;
	api_json(200,["readers"=>$rows]);
	}

	// config côté client (son on/off + url)
	if(($_GET['data']??'')==='get_settings'){
	$r=api_db()->query("SELECT setting,value FROM config WHERE setting IN ('notification_sound_url','notification_sound_enabled')");
	$o=[]; while($row=$r->fetch_assoc()) $o[$row['setting']]=$row['value'];
	api_json(200,["settings"=>$o]);
	}

	// marquer comme lu (tous les messages reçus jusqu'à last_msg_id)
	if(($_GET['data']??'')==='mark_read' && $_SERVER['REQUEST_METHOD']==='POST'){
	$u=require_token();
	$last=(int)($_POST['last_msg_id']??0);
	if($last<=0) api_json(400,["error"=>"last_msg_id is required"]);
	// on marque seulement les messages de cette personne
	$s=api_db()->prepare("SELECT row_id FROM messages WHERE belongs_to_username=? AND row_id<=? AND is_from_me=0");
	$s->bind_param("si",$u,$last); $s->execute(); $res=$s->get_result();
	$ins=api_db()->prepare("INSERT INTO messages_read(message_id,username,read_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE read_at=VALUES(read_at)");
	while($r=$res->fetch_assoc()){ $mid=(int)$r['row_id']; $ins->bind_param("is",$mid,$u); $ins->execute(); }
	api_json(200,["status"=>"ok","last"=>$last]);
	}

	// qui a lu (utile pour une alerte rapide)
	if(($_GET['data']??'')==='get_readers'){
	$u=require_token();
	$mid=(int)($_GET['message_id']??0);
	if($mid<=0) api_json(400,["error"=>"message_id is required"]);
	$s=api_db()->prepare("SELECT username, read_at FROM messages_read WHERE message_id=? ORDER BY read_at ASC");
	$s->bind_param("i",$mid); $s->execute();
	$res=$s->get_result(); $rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r;
	api_json(200,["readers"=>$rows]);
	}
	
	include_all_plugins("api.php");
	die();
?>