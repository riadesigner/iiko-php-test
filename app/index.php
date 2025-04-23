<?php

define("BASEPATH",__file__);

require_once('config.php');
require_once('common.php');
require_once('class.iiko_params_test.php');

/**
 * --------------------------
 * 
 *           ROUTER
 * 
 * --------------------------
 */

$routes = [
    '/' => function () {
        echo "Главная страница";
    },
    '/params' => function () {
        global $CFG;
        echo "загрузка параметров iiko";        
        get_and_save_iiko_params(100, $CFG->api_key);
    },
    '/parse' => function () {
        echo "парсинг меню";
    },
];

// Получаем текущий URI без параметров и слэша в конце
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestUri = rtrim($requestUri, '/');

// Если URI пустой (например, "/"), установим его в "/"
if ($requestUri === '') {
    $requestUri = '/';
}

// Проверяем, есть ли маршрут
if (array_key_exists($requestUri, $routes)) {
    $routes[$requestUri](); // Вызываем соответствующую функцию
} else {
    // 404 страница
    http_response_code(404);
    echo "Страница не найдена";
}

// ------------------------------------------------------------


function get_and_save_iiko_params($id_cafe, $api_key): void {
    $iiko_params = new iiko_params_test($id_cafe, $api_key);
    $iiko_params->reload();
    // $data = $iiko_params->get();    
    $data2 = $iiko_params->get_rough();        
    
    // try {        
    //     $savedFile = saveArrayToUniqueJson($data);
    //     echo "<br>File saved: " . $savedFile;
    // } catch (RuntimeException $e) {
    //     echo "<br>Error: " . $e->getMessage();
    // }

    try {        
        $savedFile = saveArrayToUniqueJson($data2);
        echo "<br>File saved: " . $savedFile;
    } catch (RuntimeException $e) {
        echo "<br>Error: " . $e->getMessage();
    }    


}

// print_r(glob('storage/*.json'));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iiko test</title>
</head>
<body>

    <ul>
        <li><a href="/">Главная</a></li>
        <li><a href="/params">Загрузить параметры iiko</a></li>
        <li><a href="/parse">Парсинг меню</a></li>
    </ul>

    
</body>
</html>