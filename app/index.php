<?php

define("BASEPATH",__file__);

require_once('config.php');
require_once('common.php');
require_once('class.iiko_params_test.php');


$iiko_params = new iiko_params_test(100,$_ENV["API_KEY"]);

echo "<pre>";
print_r($iiko_params->get());



?>