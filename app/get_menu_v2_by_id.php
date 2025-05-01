<?php

header('content-type: application/json; charset=utf-8');
$callback = $_REQUEST['callback'] ?? 'alert';
if (!preg_match('/^[a-z0-9_-]+$/i', (string) $callback)) {  $callback = 'alert'; }   

define("BASEPATH",__file__);

require_once getenv('WORKDIR').'/config.php';

require_once WORK_DIR.APP_DIR.'core/common.php';    

require_once WORK_DIR.APP_DIR.'core/class.sql.php';
 
require_once WORK_DIR.APP_DIR.'core/class.smart_object.php';
require_once WORK_DIR.APP_DIR.'core/class.smart_collect.php';
require_once WORK_DIR.APP_DIR.'core/class.user.php';
require_once WORK_DIR.APP_DIR.'core/class.iiko_params.php';

session_start();
SQL::connect();


// GETTING EXTERNAL MENU BY ID / API 2 

if(!isset($_POST['token'])){
	__errorjsonp(["error"=>"unknown token"]);
	exit();
}
if(!isset($_POST['externalMenuId'])){
    __errorjsonp(["error"=>"unknown external menu id"]);
    exit();
}
if(!isset($_POST['id_cafe']) || empty($_POST['id_cafe']) ){
    __errorjsonp(["error"=>"--its need to know id_cafe"]);
    exit();
}


$token = $_POST['token'];

$currentExtmenuHash = $_POST['currentExtmenuHash'];
$id_cafe = post_clean($_POST['id_cafe']);

$id_cafe = (int) $_POST['id_cafe'];
$cafe = new Smart_object("cafe",$id_cafe);
if(!$cafe->valid())__errorjsonp("Unknown cafe, ".__LINE__);

$iiko_params_collect = new Smart_collect("iiko_params", "where id_cafe='".$cafe->id."'");
if(!$iiko_params_collect || !$iiko_params_collect->full()) __errorjsonp("--cant find iiko params for cafe ".$cafe->id);
$iiko_params = $iiko_params_collect->get(0);

$token = $_POST['token'];
$externalMenuId = $_POST['externalMenuId'];
$organization_id = $iiko_params->current_organization_id;

$url     = 'api/2/menu/by_id';
$headers = [
    "Content-Type"=>"application/json",
    "Authorization" => 'Bearer '.$token
]; 

$params  = [
    'externalMenuId' => $externalMenuId,
    'organizationIds' => [$organization_id], 
    'priceCategoryId' => null, 
    'version' => 2
];

$res = iiko_get_info($url,$headers,$params);
$newExtmenuHash = md5(json_encode($res, JSON_UNESCAPED_UNICODE));
$need2update = $currentExtmenuHash!==$newExtmenuHash;

$answer = [
        "menu"=>$res,
        "menu-hash"=>$newExtmenuHash,
        "need-to-update"=>$need2update
    ];

__answerjsonp($answer);


?>