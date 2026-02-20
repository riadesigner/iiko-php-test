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
require_once('libs/class.iiko_category_processor.php');

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
        <li><a href="/">1. Главная</a></li>
        <li><a href="/reload-extmenu">2. reload external menu</a></li>
        <li><a href="/convert-extmenu-to-chefs">3. convert extmenu->chefs</a></li>
        <li><a href="/params">4. Загр. параметры iiko</a></li>
        <li><a href="/new-load-numenc">5. Новая загруз. номенкл.</a></li></li>        
        <li><a href="/new-load-external-menu">6. Новая загруз. внешнего меню</a></li></li>                
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

    '/reload-extmenu' => function () {
        global $CFG;
        echo "<h2>загрузка внешнего меню из тестового сервера</h2>";
        // echo "<p>пауза...</p>";        
        $id_org = "0c6f6201-c526-4096-a096-d7602e3f2cfd"; // Pizzaiolo
        $current_menu_id = "64136"; // Меню онлайн Ладыгина                
        reload_external_menu($id_org, $CFG->api_key, $current_menu_id);
    }, 
   
    '/convert-extmenu-to-chefs' => function () {
        global $CFG;
        echo "<h2>Конверт Внешнего меню в форма CHEFS</h2>";        
        // echo "<p>пауза...</p>";
        convert_extmenu_to_chefs();
    },  

    '/params' => function () {
        global $CFG;
        echo "<h2>загрузка параметров iiko</h2>";        
        // echo "<p>пауза...</p>";
        get_and_save_iiko_params(100, $CFG->api_key);        
    }, 
        
    '/new-load-numenc' => function () {
        global $CFG;
        echo "<h2>Новая загрузка номенклатуры</h2>";        
        $id_org = "0c6f6201-c526-4096-a096-d7602e3f2cfd"; // pizza
        new_full_nomencl_parser($id_org, $CFG->api_key);
    },

    '/new-load-external-menu' => function () {
        global $CFG;
        echo "<h2>Новая загрузка внешнего меню</h2>";        
        $id_org = "0c6f6201-c526-4096-a096-d7602e3f2cfd"; // Pizzaiolo
        $current_menu_id = "64136"; // Меню онлайн Ладыгина                
        new_load_external_menu($id_org, $CFG->api_key, $current_menu_id);

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


/**
 *  -----------------------
 *  ALL ABOUT EXTERNAL MENU
 *  -----------------------
 */

function convert_extmenu_to_chefs(): void {

    // $file_name= "json-info-formated-full-new.json";
    $file_name= "2026-02-16_05-49-52_f577c9e9-external.json";
    
    $json_file_path = __dir__."/exports/$file_name";
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


function reload_external_menu($id_org, $api_key, $current_menu_id): void{
    global $CFG;
    $EXTM_LOADER = new Iiko_extmenu_loader($id_org, $api_key, $current_menu_id, true);    
    $EXTM_LOADER->reload();
    $data = $EXTM_LOADER->get_data();
    $meta_info = $EXTM_LOADER->get_info();
    try {        
        $savedFile = saveArrayToUniqueJson($data);
        echo "<br>File saved: " . $savedFile;
        echo "<hr>";
        echo "<pre>";
        print_r($meta_info);
        echo "</pre>";
    } catch (RuntimeException $e) {
        echo "<br>Error: " . $e->getMessage();
    }      
}

function new_load_external_menu($id_org, $api_key, $current_menu_id): void{
    global $CFG;

    // Создаем экземпляр процессора
    $processor = new IikoCategoryProcessor();

    try {

        // -----------------------         
        // GETTING TOKEN FROM IIKO         		
        // -----------------------
		$url     = 'api/1/access_token';
		$headers = ["Content-Type"=>"application/json"];
		$params  = ["apiLogin" => $api_key];
		$res = iiko_get_info($url,$headers,$params);		
		if(!isset($res['token'])){
			glogError(print_r($res,1));
			$errMessage = $res['errorDescription']??"";
			die("неправильный API KEY! ".$api_key.",<br> ".$errMessage);		
		}	
		$TOKEN = $res['token'];

		// ----------------------------------------------------
		// получаем Меню по его Id с Базовой ценовой категорией
		// ----------------------------------------------------
		$url     = 'api/2/menu/by_id';
		$headers = [
			"Content-Type"=>"application/json",
			"Authorization" => 'Bearer '.$TOKEN
		]; 
		$params  = [
			'externalMenuId' => $current_menu_id,
			'organizationIds' => [$id_org], 
			"version" => 2,
			"startRevision"=>0,
		];	
        
        // Запускаем извлечение и обработку
        $files = $processor->extractCategories($url, $headers, $params);
        
        echo "Всего обработано файлов: " . count($files) . "\n";
        
    } catch (Exception $e) {
        echo "Ошибка: " . $e->getMessage() . "\n";
    }    
}




/**
 *  -----------------------
 *  ALL ABOUT NOMENCLATURES
 *  -----------------------
 */

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

    die('TEST STOP!'); // ------------------------------------------ дальше что-то идет не так....

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
    $infodata = $CHEFS_CONVERTER->get_info();    

    echo sprintf("<p>Конвертирование меню <strong>%s</strong> в CHEFS выполнено</p>", $data["Menus"][$id_menu]['name']);

    echo "<pre>";
    print_r($infodata);
    echo "</pre>";

    echo "<pre>";
    print_r($chefsdata);
    echo "</pre>";    

    $NOMENCL_DIVIDER->clean();
    $NOMCL_LOADER->clean(); 
    
}


/**
 *  -----------------
 *  RENDER INDEX PAGE
 *  -----------------
 */


function render_index_page(){
    ?>
    
    <pre>

        TOPMENU

        1. [Главная страница]

        2. [reload external menu] – загрузка внешнего меню iiko в файл json
        PRODUCTION

        3. [conv extmenu->chefs] - аналог js функции
        PRODUCTION

        4. [Загрузить параметры iiko] - загружает параметры iiko в файл json и выводит название файла
           (ORGANIZATIONS, EXTERNALMENUS, TERMINALS, TABLES ...)
                
        5. [Релоад номенкл.] - 
            - Загружает номенклатуру iiko в файл json
            - Делит ее на файлы. 
            // дальше пока пауза .. что-то сломалось 
            - [Парсит в -> UNIMENU] 
            - [UNIMENU -> CHEFS]        

        6. [new load external menu] 
            Претендент на замену варианту 2 ->
            Ключевые особенности:
            – Загрузка внешнего меню потоком.            
            - Загрузка json минуя PHP сразу в файлы
            .. остальное пока в работе
            TODO
            собрать из файлов оптимизированный json
            в формате CHEFS 
            

            


        
        

    </pre>

    <?php
}


?>


</body>
</html>