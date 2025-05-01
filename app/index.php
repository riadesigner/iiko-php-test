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
        <li><a href="/reload-dev-extmenu">reload dev extmenu</a></li>
        <li><a href="/params">Загр. параметры iiko</a></li>
        <li><a href="/parse/1">Парсинг 1</a></li>
        <li><a href="/parse/2">Парсинг 2</a></li>
        <li><a href="/reload">Релоад номенкл.</a></li>
        <li><a href="/parse-nmcl">Парс. номенкл.</a></li>
        <li><a href="/parse-to-chefsmenu">Парс. в -> chefs</a></li></li>        
        
    </ul>


    <?php

define("BASEPATH",__file__);

require_once('config.php');
require_once('common.php');
require_once('class.iiko_params_test.php');
require_once('class.Iiko_nmcl_parser.php');
require_once('class.Iiko_nomenclature.php');
require_once('class.iiko_nomenclature_parse.php');
require_once('class.iiko_nomenclature_parse2.php');
require_once('class.iiko_chefs_parser.php');
require_once('class.iiko_extmenu_loader.php');


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
        render_index_page();
    },
    '/params' => function () {
        global $CFG;
        echo "<h2>загрузка параметров iiko</h2>";        
        echo "<p>пауза...</p>";
        // get_and_save_iiko_params(100, $CFG->api_key);        
    },  
    '/reload-dev-extmenu' => function () {
        global $CFG;
        echo "<h2>загрузка внешнего меню и тестового сервера</h2>";
        // echo "<p>пауза...</p>";        
        $id_org = "3336e8d3-85c7-4ded-8c3e-28f0640c467b"; // Мой ресторан
        $id_dev_extmenu = "11215"; // Тестовое меню 2        
        reload_dev_menu($id_org, $id_dev_extmenu, $CFG->api_dev_key);
    },       
    '/reload' => function () {
        global $CFG;
        echo "<h2>загрузка номенклатуры</h2>";
        echo "<p>пауза...</p>";
        // $id_org = "0c6f6201-c526-4096-a096-d7602e3f2cfd"; // pizza
        // $id_org = "3336e8d3-85c7-4ded-8c3e-28f0640c467b"; // test
        // reload_nomenclature($id_org, $CFG->api_key);
    },    
    // /parse/1 or /parse/2  ...
    '#^/parse/(\d+)$#' => function ($id) {
        $id = htmlspecialchars($id);
        echo "<h2>парсинг меню. Версия {$id}</h2>";                       
        $file_name = "json-info-formated-full-new.json"; // pizza
        // $file_name = "nomenc-my-full-3.json"; // мой        
        parse_nomenclature($file_name, $id);
    },
    // gpt
    '/parse-nmcl' => function () {
        global $CFG;
        echo "<h2>парсинг (gpt) номенклатуры iiko full из файла</h2>";
        echo "<p>пауза...</p>";
        // $file_name = "2025-04-26_08-50-37_0f7f4440.json";        
        // parse_nmcl($file_name);
    },
    '/parse-to-chefsmenu' => function () {
        global $CFG;
        echo "<h2>парсинг номенклатуры для chefsmenu</h2>";
        // echo "<p>пауза...</p>";
        $file_name = "json-info-formated-full-new.json";
        parse_to_chefsmenu($file_name);
    },
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
    // http_response_code(404);
    echo "Страница не найдена";
}

// --------------------------- SERVICES ---------------------------------


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

function parse_nmcl($file_name){
    $json_file_path = __dir__."/files/$file_name";
    $PARSER_NOMCL = new Iiko_nmcl_parser($json_file_path);    
    $PARSER_NOMCL->parse();    
    $data = $PARSER_NOMCL->get_data();
    try {        
        $savedFile = saveArrayToUniqueJson($data);
        echo "<br>File saved: " . $savedFile;
    } catch (RuntimeException $e) {
        echo "<br>Error: " . $e->getMessage();
    }    
    $PARSER_NOMCL->print();
}

function parse_to_chefsmenu($file_name){
    $json_file_path = __dir__."/files/$file_name";
    $PARSER_TO_CHEFS = new Iiko_chefs_parser($json_file_path);    
    $PARSER_TO_CHEFS->parse();    
    // $data = $PARSER_TO_CHEFS->get_data();
    // try {        
    //     $savedFile = saveArrayToUniqueJson($data);
    //     echo "<br>File saved: " . $savedFile;
    // } catch (RuntimeException $e) {
    //     echo "<br>Error: " . $e->getMessage();
    // }        
} 

function reload_dev_menu($id_org, $id_dev_extmenu, $api_key): void{
    global $CFG;
    $EXTM_LOADER = new Iiko_extmenu_loader($id_org, $id_dev_extmenu, $api_key);    
    $EXTM_LOADER->reload();
    $data = $EXTM_LOADER->get_data();
    try {        
        $savedFile = saveArrayToUniqueJson($data);
        echo "<br>File saved: " . $savedFile;
    } catch (RuntimeException $e) {
        echo "<br>Error: " . $e->getMessage();
    }      
}

// print_r(glob('exports/*.json'));

function render_index_page(){
    ?>
    <h3>Описание структуры:</h3>
    <pre>        
        Iiko_nomenclature - класс для получения данных из iiko.
        Iiko_nmcl_parser - класс для парсинга данных (от GPT).
        Iiko_params_test - класс для получения параметров iiko. 
        Iiko_nomenclature_parse - класс для изучения структуры nomenclature.
        Iiko_nomenclature_parse2 - класс для изучения структуры nomenclature. 

        ---
        /files - папка для хранения исходников
        /exports - папка для экспорта json файлов
        ---

        // json-dev-extmenu.json ( ответ от iiko - внешнее меню "Тестовое меню 2" )
        // json-info-formated-full-new.json (ответ от iiko - номенклатура "pizzaiolo" )

        TOPMENU

        1. [Главная страница]

        2. [Загрузить параметры iiko] - загружает параметры iiko в файл json и выводит название файла
           (ORGANIZATIONS, EXTERNALMENUS, TERMINALS, TABLES ...)
        
        3. [Парсинг 1] - Показывает некоторые данные из файла nomeclature.json 
        
        4. [Парсинг 2] - Показывает некоторые данные из файла nomeclature.json         

        5. [Релоад номенкл.] - Загружает номенклатуру iiko в файл json и выводит название файла 
           (groups, productCategories, products)

        6. [Парс. номенкл.] - Парсинг номенклатуры iiko (вариант GPT)
                
        -----

        
        ВЫВОДЫ

        В ответе json от iiko в номенклатуре есть:
        
        - Groups
        - ProductCategories
        - Products

        ------ 
        
        Groups это: 
            
            - ПАПКИ С ТОВАРАМИ (в т.ч. вложенные) {"isGroupModifier": false}
            – ГРУППЫ МОДИФИКАТОРОВ {"isGroupModifier": true}

        ProductCategories это:
            
            - КАТЕГОРИИ ТОВАРОВ

            Нужно посмотреть как их создавать в iiko. 
            Меню можно построить двумя способами 
            - через категории 
            - через обычные папки
        
        Products это:
        
            – ТОВАРЫ {type: Dish}
            – СЕРВИС {type: Service}
            – МОДИФИКАТОРЫ {type: Modifier}

            При этом:

            Модификаторы – это обычные товары
            
            – просто списком "modifiers": []
            – в группах модификаторов "groupModifiers": []            

            в товаре Модификаторы могут быть указаны
            
            – просто списком "modifiers": []
            – или в группах модификаторов "groupModifiers": []

            Сервис - это товар-услуга 

            - например Доставка



    </pre>

    <?php
}


?>


</body>
</html>