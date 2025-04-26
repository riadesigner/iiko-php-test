<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iiko test</title>
    <style>
        body{
            font:.8rem arial;
        }
        small{
            color:gray;
        }
        small i{
            color:red;
        }
        .top-menu {list-style: none;}
        .top-menu li{display: inline-block;margin:0 0 0 2%;}
    </style>
</head>
<body>

    <ul class="top-menu">
        <li><a href="/">Главная</a></li>
        <li><a href="/params">Загрузить параметры iiko</a></li>
        <li><a href="/parse/1">Парсинг / вар. 1</a></li>
        <li><a href="/parse/2">Парсинг / вар. 2</a></li>
        <li><a href="/reload">Релоад номенкл.</a></li>
    </ul>


    <?php

define("BASEPATH",__file__);

require_once('config.php');
require_once('common.php');
require_once('class.iiko_params_test.php');
require_once('class.Iiko_nomenclature.php');
require_once('class.iiko_nomenclature_parse.php');
require_once('class.iiko_nomenclature_parse2.php');

/**
 * --------------------------
 * 
 *           ROUTER
 * 
 * --------------------------
 */

$routes = [
    '/' => function () {
        echo "<h2>Главная страница</h2>";
    },
    '/params' => function () {
        global $CFG;
        echo "<h2>загрузка параметров iiko</h2>";        
        echo "<p>пауза...</p>";
        // get_and_save_iiko_params(100, $CFG->api_key);        
    },    
    '/reload' => function () {
        global $CFG;
        echo "<h2>загрузка номенклатуры</h2>";
        echo "<p>now reloading nomenclature</p>";
        $id_org = "0c6f6201-c526-4096-a096-d7602e3f2cfd";
        reload_nomenclature($id_org, $CFG->api_key);
    },    
    // /parse/1 or /parse/2  ...
    '#^/parse/(\d+)$#' => function ($id) {
        $id = htmlspecialchars($id);
        echo "<h2>парсинг меню. Версия {$id}</h2>";
        parse_nomenclature("json-info-formated-full-original.json", $id);
    }
];

// ---------------------- private -------------------------------

// Получаем URI
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestUri = rtrim($requestUri, '/');
if ($requestUri === '') {
    $requestUri = '/';
}

$matched = false;

foreach ($routes as $pattern => $handler) {
    // Если маршрут — это точное совпадение
    if ($pattern[0] !== '#') {
        if ($requestUri === $pattern) {
            $handler();
            $matched = true;
            break;
        }
    } else {
        // Если маршрут — это регулярное выражение
        if (preg_match($pattern, $requestUri, $matches)) {
            array_shift($matches); // Удаляем полное совпадение
            $handler(...$matches); // Передаём параметры в функцию
            $matched = true;
            break;
        }
    }
}

if (!$matched) {
    http_response_code(404);
    echo "Страница не найдена";
}

// ------------------------------------------------------------


function get_and_save_iiko_params($id_cafe, $api_key): void {
    $iiko_params = new Iiko_params_test($id_cafe, $api_key);
    $iiko_params->reload();    
    $data = $iiko_params->get_rough();

    try {        
        $savedFile = saveArrayToUniqueJson($data);
        echo "<br>File saved: " . $savedFile;
    } catch (RuntimeException $e) {
        echo "<br>Error: " . $e->getMessage();
    }    
}


function parse_nomenclature($file_name, $var = 1){
    $json_file_path = __dir__."/files/$file_name";
    if($var==1){        
        $n = new Iiko_nomenclature_parse($json_file_path);    
    }elseif($var==2){
        $n = new Iiko_nomenclature_parse2($json_file_path);    
    }else{
        throw new Exception("Invalid var");
    }
}

function reload_nomenclature($id_org, $api_key){
    global $CFG;
    $NOMCL = new Iiko_nomenclature($id_org, $api_key);    
    $NOMCL->reload();
    $data = $NOMCL->get_data();
    try {        
        $savedFile = saveArrayToUniqueJson($data);
        echo "<br>File saved: " . $savedFile;
    } catch (RuntimeException $e) {
        echo "<br>Error: " . $e->getMessage();
    }      
}
// print_r(glob('storage/*.json'));



?>


</body>
</html>