<?php
require_once 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();


#[AllowDynamicProperties]
class glb_object{};
$CFG = new glb_object();

$CFG->dirroot = $_ENV['WORKDIR']."/";
$CFG->log_path = "logs/";
$CFG->log_menu_file = "test.log";

$CFG->api_key = $_ENV['API_KEY'];


?>