<?php

define("BASEPATH",__file__);

require_once('config.php');
require_once('common.php');
require_once('libs/class.iiko_params_test.php');
require_once('libs/class.iiko_nomenclature_loader.php');
require_once('libs/class.iiko_nomenclature_parse.php');
require_once('libs/class.iiko_nomenclature_parse2.php');
require_once('libs/class.iiko_parser_to_unimenu.php');
require_once('libs/class.iiko_extmenu_loader.php');
require_once('libs/class.conv_unimenu_to_chefs.php');
require_once('libs/class.iiko_nomenclature_divider.php');
require_once('libs/class.iiko_extmenu_to_chefs.php');


?>
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
        <li><a href="/convert-extmenu-to-chefs">convert extmenu->chefs</a></li>
        <li><a href="/params">Загр. параметры iiko</a></li>
        <li><a href="/new-load-numenc">Новая загруз. номенкл.</a></li></li>        
    </ul>

<?php

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

    '/reload-dev-extmenu' => function () {
        global $CFG;
        echo "<h2>загрузка внешнего меню из тестового сервера</h2>";
        // echo "<p>пауза...</p>";        
        $id_org = "3336e8d3-85c7-4ded-8c3e-28f0640c467b"; // Мой ресторан
        $id_dev_extmenu = "11215"; // Тестовое меню 2        
        reload_dev_menu($id_org, $id_dev_extmenu, $CFG->api_test_key);
    }, 

    '/params' => function () {
        global $CFG;
        echo "<h2>загрузка параметров iiko</h2>";        
        echo "<p>пауза...</p>";
        // get_and_save_iiko_params(100, $CFG->api_key);        
    },  
   
    '/convert-extmenu-to-chefs' => function () {
        global $CFG;
        echo "<h2>Конверт Внешнего меню в форма CHEFS</h2>";        
        // echo "<p>пауза...</p>";
        convert_extmenu_to_chefs();
    },  
       
    

    // /parse/1 or /parse/2  ...
    // '#^/parse/(\d+)$#' => function ($id) {
    //     $id = htmlspecialchars($id);
    //     echo "<h2>парсинг меню. Версия {$id}</h2>"; 
    //     parse_nomenclature($file_name, $id);
    // },

    '/new-load-numenc' => function () {
        global $CFG;
        echo "<h2>Новая загрузка номенклатуры</h2>";
        
        $id_org = "0c6f6201-c526-4096-a096-d7602e3f2cfd"; // pizza
        $api_key = $CFG->api_key;

        // $id_org = "3336e8d3-85c7-4ded-8c3e-28f0640c467b"; // test
        // $api_key = $CFG->api_test_key;        

        new_full_nomencl_parser($id_org, $api_key);
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


function convert_extmenu_to_chefs(): void {

    $file_name= "json-dev-extmenu.json";
    $json_file_path = __dir__."/files/$file_name";
    $extmenu = loadJsonFile($json_file_path);

    $data = Iiko_extmenu_to_chefs::parse($extmenu);
    
    try {        
        $savedFile = saveArrayToUniqueJson($data);
        echo "<br>File saved: " . $savedFile;
    } catch (RuntimeException $e) {
        echo "<br>Error: " . $e->getMessage();
    }  
    
    echo "<p>ok</p>";
}



// function parse_nomenclature($file_name, $var = 1){
//     $json_file_path = __dir__."/files/$file_name";
//     if($var==1){        
//         $n = new Iiko_nomenclature_parse($json_file_path);    
//     }elseif($var==2){
//         $n = new Iiko_nomenclature_parse2($json_file_path);    
//     }else{
//         throw new Exception("Invalid var");
//     }
// }



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

function new_full_nomencl_parser($id_org, $api_key){
    
    $NOMCL_LOADER = new Iiko_nomenclature_loader($id_org, $api_key);    
    $NOMCL_LOADER->reload(true, true);
    $json_file_path = $NOMCL_LOADER->get_file_path();        

    echo "<p>Загружен файл: $json_file_path</p>";

    $NOMENCL_DIVIDER = new Iiko_nomenclature_divider($json_file_path);
    $temp_file_names = $NOMENCL_DIVIDER->get();

    echo "<p>Разделенные файлы:</p>";
    echo "<pre>";
    print_r($temp_file_names);
    echo "</pre>";

    $groups_as_category = false;
    $PARSER_TO_UNIMENU = new Iiko_parser_to_unimenu($temp_file_names);    
    $PARSER_TO_UNIMENU->parse($groups_as_category);
    $data = $PARSER_TO_UNIMENU->get_data();

    echo "<h2>Парсинг и перевод в UNIMENU выполнен, всего меню: ".$data['TotalMenus']."</h2>";
    foreach($data['Menus'] as $menu){
        echo sprintf("<p>Меню: %s, id=%s </p>", $menu['name'], $menu['menuId']);
    }

    $id_menu = "9da77ff8-862d-45e4-a7f2-a5117910fa66"; // pizza-vl
    $selected_unimenu = $data["Menus"][$id_menu];
    $CHEFS_CONVERTER = new Conv_unimenu_to_chefs($selected_unimenu);
    $chefsdata = $CHEFS_CONVERTER->get_data();
    
    echo sprintf("<p>Конвертирование меню <strong>%s</strong> в CHEFS выполнено</p>", $data["Menus"][$id_menu]['name']);
    echo "<pre>";
    print_r($chefsdata);
    echo "</pre>";    

    $NOMENCL_DIVIDER->clean();
    $NOMCL_LOADER->clean(); 
    
    
}



function render_index_page(){
    ?>
    
    <pre>

        TOPMENU

        1. [Главная страница]

        2. [reload dev extmenu] – загрузка внешнего (тестового) меню iiko в файл json

        3. [conv extmenu->chefs] - аналог js функции

        4. [Загрузить параметры iiko] - загружает параметры iiko в файл json и выводит название файла
           (ORGANIZATIONS, EXTERNALMENUS, TERMINALS, TABLES ...)
                
        5. [Релоад номенкл.] - 
            - Загружает номенклатуру iiko в файл json
            - Делит ее на файлы. 
            - [Парсит в -> UNIMENU] 
            - [UNIMENU -> CMEFS]        
                

    </pre>

    <?php
}


?>


</body>
</html>