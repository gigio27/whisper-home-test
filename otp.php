<?php
define('a328763fe27bba', true);
require_once __DIR__ . "/config.php";

/* helpers */
function out($code, $data){
  if (ob_get_level()) { @ob_clean(); }
  http_response_code($code);
  header('Content-Type: application/json');
  echo json_encode($data, JSON_UNESCAPED_SLASHES);
  exit;
}

function db(){ static $m=null; if(!$m){ $m=@new mysqli(MYSQL_DEFAULT_SERVERNAME,MYSQL_DEFAULT_USERNAME,MYSQL_DEFAULT_DB_PASSWORD,MYSQL_DEFAULT_DB_NAME); if($m->connect_errno) out(500,["error"=>"database connection failed"]); $m->set_charset("utf8mb4"); } return $m; }
function cfg($k,$d=null){ $s=db()->prepare("SELECT value FROM config WHERE setting=?"); $s->bind_param("s",$k); $s->execute(); $s->bind_result($v); if($s->fetch()){ $s->close(); return $v; } $s->close(); return $d; }
function rand_digits($n){ $s=""; for($i=0;$i<$n;$i++) $s.=random_int(0,9); return $s; }
function rand_token(){ return bin2hex(random_bytes(32)); }

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if($action === 'request_otp'){
  $email = trim($_POST['email'] ?? '');
  $honeypot = trim($_POST['website'] ?? '');
  if($honeypot!=='') out(400,["error"=>"invalid request"]);
  if($email==='') out(400,["error"=>"email is required"]);

  $len = (int)cfg('otp_length','6');
  $otp = rand_digits($len);
  $validMin = (int)cfg('otp_valid_minutes','10');

  // temporary token based on  email+otp (hashed for security)
  $tmp = hash('sha256', strtolower($email).'|'.$otp);
  $exp = (new DateTime("+$validMin minutes"))->format("Y-m-d H:i:s");
  $ip  = $_SERVER['REMOTE_ADDR'] ?? null;

  // we use auth_tokens as temporary storage 
  $q="INSERT INTO auth_tokens(username, token, expires_at, ip) VALUES(?,?,?,?)
      ON DUPLICATE KEY UPDATE username=VALUES(username), expires_at=VALUES(expires_at), ip=VALUES(ip)";
  $s=db()->prepare($q); $s->bind_param("ssss",$email,$tmp,$exp,$ip); $s->execute();

  error_log("[OTP] email=$email otp=$otp valid=$validMin min"); // To see logs in errors.log
  $payload=["message"=>"otp sent"];
  if(defined('ENV') && ENV==='dev') $payload["dev_hint_otp"]=$otp;
  out(200,$payload);
}

if($action === 'verify_otp'){
  $email = trim($_POST['email'] ?? '');
  $otp   = trim($_POST['otp'] ?? '');
  if($email==='' || $otp==='') out(400,["error"=>"email and otp are required"]);

  $tmp = hash('sha256', strtolower($email).'|'.$otp);
  $s=db()->prepare("SELECT expires_at FROM auth_tokens WHERE token=?");
  $s->bind_param("s",$tmp); $s->execute(); $s->bind_result($exp);
  if(!$s->fetch()) { $s->close(); out(401,["error"=>"invalid or expired otp"]); }
  $s->close();
  if(strtotime($exp) < time()) out(410,["error"=>"otp expired"]);

  $session = rand_token();
  $hours = (int)cfg('token_expiry_hours','24');
  $exp2 = (new DateTime("+$hours hours"))->format("Y-m-d H:i:s");
  $ip   = $_SERVER['REMOTE_ADDR'] ?? null;

  $s=db()->prepare("INSERT INTO auth_tokens(username, token, expires_at, ip) VALUES(?,?,?,?)");
  $s->bind_param("ssss",$email,$session,$exp2,$ip); $s->execute();

  out(200,["token"=>$session,"message"=>"login successful"]);
}

out(400,["error"=>"unknown action"]);
