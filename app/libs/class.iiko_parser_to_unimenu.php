<?php
/**
 * ДВА ВАРИАНТА ПАРСИНГА МЕНЮ (v-2.0.0)
 * 
 * добавлена возможность выбора, что парсить
 * - json_file 
 * - iiko_response
 * 
 * 1. Строим структуру меню как копию структуры импортированных вложенных папок
 * 2. Строим структуру меню с учетом указанных категорий товаров
 * 
 * особенность формата UNIMENU в том (в том числе), что 
 * Menus, groups, products - хранятся как ассоциативный массив (с id ключами ),
 * а itemSizes, modifiers и groupModifiers - как обычные массивы (с индексами 0, 1, 2, ...);
 * 
 * */

class Iiko_parser_to_unimenu {
    private array $IIKO_RESPONSE;
    private string $JSON_FILE_PATH="";
    private array $DATA;    
    private array $menuGroupsFlatten;
    private array $productsById;
    private array $modifiersById;
    private array $groupsModifiersById;
    private array $categoriesById;          
    private bool $GROUPS_AS_CATEGORY;

	function __construct(string $json_file_path = "", array $iiko_response = []){				
        $this->JSON_FILE_PATH = $json_file_path;
        $this->IIKO_RESPONSE = $iiko_response;
		return $this;
	}

    public function parse(bool $groups_as_category = false): void {
        $this->GROUPS_AS_CATEGORY = $groups_as_category;
        if(!empty($this->JSON_FILE_PATH)){
            $res = $this->load_json_file($this->JSON_FILE_PATH);
            $this->DATA = $this->build_all_menus($res);
        }else{
            $this->DATA = $this->build_all_menus($this->IIKO_RESPONSE);        
        }
    }

    public function get_data(): array {
        return $this->DATA;
    }

    private function load_json_file(string $json_file_path): array {
		// Step 1: Read the file
		$jsonString = file_get_contents($json_file_path);
		
		if ($jsonString === false) {			
			throw new RuntimeException("Error: Unable to read the JSON file.");
		}
		
		// Step 2: Decode the JSON
		$data = json_decode($jsonString, true);

		// Check for JSON decoding errors
		if (json_last_error() !== JSON_ERROR_NONE) {
			throw new RuntimeException("Error decoding JSON: " . json_last_error_msg());
		}
        return $data;
    }

    private function build_all_menus($data): array {        

        // Собираем категории
        $categoriesById = [];
        foreach ($data['productCategories'] as $cat) {
            $categoriesById[$cat['id']] = $cat;
        }

        // Собираем группы модификаторов 
        $groupsModifiersById = [];
        foreach ($data['groups'] as $group) {
            if ($group['isGroupModifier']) {
                $groupsModifiersById[$group['id']] = $group;
            }
        }
    
        // Фильтрация групп, которые не являются модификаторами
        $onlyMenuGroups = array_filter($data['groups'], fn($e) =>(!$e["isGroupModifier"]));		        		

        // Строим дерево групп (тольк Папок меню )
        $menuGoupsTree = $this->build_groups_tree($onlyMenuGroups);

        // Делаем дерево плоским
        $menuGroupsFlatten = $this->flatten_groups_tree($menuGoupsTree);

        // Собираем товары, услуги и модификаторы
        $productsById = [];
        $servicesById = [];
        $modifiersById = [];
        foreach ($data['products'] as $product) {
            if($product["type"]==="Dish"){
                $productsById[$product['id']] = $product;
            }elseif($product["type"]==="Service"){
                $servicesById[$product['id']] = $product;
            }elseif($product["type"]==="Modifier"){
                $modifiersById[$product['id']] = $product;
            }            
        }

        $this->menuGroupsFlatten = $menuGroupsFlatten;
        $this->productsById = $productsById;
        $this->modifiersById = $modifiersById;
        $this->groupsModifiersById = $groupsModifiersById;
        $this->categoriesById = $categoriesById;                        

        if($this->GROUPS_AS_CATEGORY){
            $menus = $this->build_menus_from_groups();
        }else{
            $menus = $this->build_menus_from_categories();
        }        
        return [
            "SourceMenus" => "NOMENCLATURE",
            "TypeMenus" => "UNIMENU",
            "TotalMenus" => count($menus),
            "Menus" => $menus,            
        ];
    }

    /**
     *   ВАРИАНТ 1. СОБИРАЕМ МЕНЮ ИЗ ГРУПП        
     * - каждая корневая группа – это отдельное меню 
     * - группы (они же папки) – используются в качестве категорий
     * - а iiko-категории – не учитываются
    */     
    private function build_menus_from_groups(): array{
        $menus = [];
        foreach ($this->menuGroupsFlatten as $rootGroup) {
            // создаем меню
            $menu = [
                "menuId"=>$rootGroup['id'], 
                "name" => $rootGroup["name"],                 
                "description"=>$rootGroup["description"],                
                "groups"=>[],
                "products"=>[]
            ];
            // заполняем категориями
            foreach ($rootGroup['sub_groups'] as $cat) {
                $category = [
                    "groupId"=>$cat['id'], 
                    "type"=> "CATEGORY",
                    "name" => $cat["name"]
                ];                
                // отбираем товары для категории
                $prods = array_filter($this->productsById, fn($e) => $e["parentGroup"] === $category["groupId"]);
                // добавляем товары в меню с указанием категории (parentGroup)
                foreach ($prods as $prod) {
                    $prodId = $prod['id'];
                    $prod = $this->parse_prod($this->productsById[$prodId], $cat['id'], $menu);
                    $menu["products"][$prodId] = $prod;
                }
                // добавляем категорию в меню
                $menu["groups"][$category["groupId"]] = $category;
            }
            $menus[$menu["menuId"]] = $menu;
        }
        return $menus;
    }

    /**
     *   ВАРИАНТ 2. СОБИРАЕМ МЕНЮ ИЗ КАТЕГОРИЙ        
     * - каждая корневая группа – это отдельное меню 
     * - группы используются для определения какие товары к какому меню относятся
     * - iiko-категории – используются в качестве категорий 
    */ 
    private function build_menus_from_categories(): array{
        // Вычисляем какие товары к какому меню относятся:
        // - каждая корневая папка – это отдельное меню 
        // - распределяем индексы всех товаров по этим меню  
        $productsIdsByMenu = [];
        foreach ($this->menuGroupsFlatten as $menu) {
            $arr = [];
            foreach ($menu['sub_groups'] as $cat) {                
                // отбираем товары для каждой категории
                $prods = array_filter($this->productsById, fn($e) => $e["parentGroup"] === $cat["id"]);
                // вычисляем индексы товаров для каждой категории
                $indexes = array_map(fn($e) => $e["id"], $prods);
                $arr = [...$arr, ...$indexes];
            }            
            $productsIdsByMenu[$menu['id']] = $arr;            
        } 
        // создаем меню с категориями 
        $menus = [];
        foreach ($this->menuGroupsFlatten as $rootGroup) {
            $menu = [
                "menuId"=>$rootGroup["id"],
                "name"=>$rootGroup["name"],
                "description"=>$rootGroup["description"],
                "groups"=>[],
                "products"=>[]
            ];
            // берем все товары этого меню
            $menuProdsIds = $productsIdsByMenu[$rootGroup["id"]];
            foreach($menuProdsIds as $prodId){
                $prod = $this->productsById[$prodId];
                $cat = $this->categoriesById[$prod["productCategoryId"]];                
                $category = [
                    "groupId" => $cat["id"],
                    "type"=> "CATEGORY",
                    "name"=>$cat["name"]                    
                ];                           
                // добавляем категорию меню, если такой категории еще нет 
                if(!isset($menu["groups"][$cat["id"]])){         
                    $menu["groups"][$cat["id"]] = $category;
                };
                //добавляем товар в меню с указанием категории (parentGroup)
                $prod = $this->parse_prod($prod, $cat["id"], $menu);                
                $menu["products"][$prodId] = $prod;
            }
            $menus[$menu["menuId"]] = $menu;
        }
        return $menus;
    }    

    private function parse_prod($prod, $parentGroupId, &$menu): array{
        
        // парсим групповые модификаторы текущего товара
        $prodGroupModifiers = $this->parse_modifiers($prod, $menu);
        
        // парсим размеры текущего товара
        $itemSizes = [];
        if(count($prod['sizePrices']) > 0){
            foreach($prod['sizePrices'] as $sizePrice){                
                $itemSizes[] = [
                    "sizeId" => $sizePrice['sizeId'],
                    "sizeName"=>"",
                    "price" => $sizePrice['price']['currentPrice'],
                    "isDefault"=>false,
                    "weightGrams"=> 0,
                    "measureUnitType"=>mb_strtoupper($prod["measureUnit"], 'UTF-8'),
                ];
            }
            $weightGrams = (float) $prod['weight'] * 1000;
            $itemSizes[0]["weightGrams"] = (int) $weightGrams;
        }else{
            // если товар не имеет размеров, 
            // то добавляем один размер по умолчанию
            $itemSizes[] = [
                "sizeId" => null,
                "sizeName"=>"",
                "price" => 0,
                "isDefault"=>false,
                "weightGrams"=> 0,
                "measureUnitType"=>"GRAM",
            ];        
        }
        
        $product = [                        
            "itemId"=>$prod['id'],                         
            "name" => $prod["name"],
            "description"=>$prod["description"],    
            "imageUrl" => "",
            "type" => "PRODUCT", 
            "parentGroup" => $parentGroupId,
            "itemSizes"=>$itemSizes,
            "modifiers" => [], // одиночные модификаторы (в этой версии не используются)
            "groupModifiers"=> $prodGroupModifiers, // группы модификаторов
            "isAvailable" => true,
            "pos" => $prod["order"]            
        ];        
        return $product;
    }

    private function parse_modifiers($prod, &$menu): array{
       
        // пропускаем одиночные модификаторы, 
        // не используем в этой версии                    
        // $prod['modifiers']
                
        // парсим групповые модификаторы текущего товара
        $prodGroupModifiers = [];              

        foreach ($prod['groupModifiers'] as $gModifier) {
            
            $gm = $this->groupsModifiersById[$gModifier["id"]];

            $readyGroupModifiers = [
                "groupId" => $gModifier["id"],
                "type"=> "MODIFIERS_GROUP",
                "name"=>$gm["name"]
            ];
            
            // сохраняем групповой модификатор в конечный json
            if(!isset($menu["groups"][$gModifier["id"]])){
                $menu["groups"][$gModifier["id"]] = $readyGroupModifiers;
            }

            // находим модификаторы в данной группе 
            $modifiers = $gModifier["childModifiers"];                        
            $mById = $this->modifiersById;
            
            // собираем модификаторы,
            // делаем их подобными обычным товарам, 
            // но с пометкой type=MODIFIER  
            $readyModifiers = array_map(function($e) use($mById) { 
                $m = $mById[$e["id"]];
                $weightGrams = (float) $m["weight"] * 1000;
                $price = $m["sizePrices"][0]["price"]["currentPrice"];
                $itemSizes = [
                    [
                    "sizeId" => "",
                    "sizeName" => "",
                    "price" =>  $price,
                    "isDefault" =>  false,
                    "weightGrams" => (int) $weightGrams,
                    "measureUnitType" => mb_strtoupper($m["measureUnit"], 'UTF-8'),
                    ]
                ];           

                $modifier = [
                    "itemId"=>$e["id"],
                    "name"=>$m["name"],
                    "description"=>$m["description"],
                    "imageUrl"=> "",
                    "type" => "MODIFIER",
                    "parentGroup" => $m["parentGroup"],
                    "itemSizes"=>$itemSizes,
                    "modifiers" => [],
                    "groupModifiers" => [],                    
                    "isAvailable" => true,                    
                    "pos" => $m["order"],
                ];
                return $modifier;
            }, $modifiers);
            
            // сохраняем группу модификаторов в конечный json
            foreach($readyModifiers as $mo){
                $modifierId = $mo["itemId"];
                if(!isset($menu["products"][$modifierId])){
                    $menu["products"][$modifierId] = $mo;
                }
            }

            // меняем названия переменных 
            // у вложенных модификаторов группы
            $childModifiers = [];
            foreach($modifiers as $mo){
                $childModifiers[] = [                        
                    "modifierId"=>$mo["id"],
                    "restrictions"=>[
                        "minQuantity"=>$mo["minAmount"],
                        "maxQuantity"=>$mo["maxAmount"],
                        "required"=>$mo["required"],
                        "byDefault"=>$mo["defaultAmount"], 
                        "freeQuantity"=>$mo["freeOfChargeAmount"], 
                    ]                     
                ];
            }
            // собираем групповой модификатор 
            // со своими названиями переменных     
            $prodGroupModifiers[] = [
                "modifierGroupId"=>$gModifier["id"],
                "restrictions"=>[
                    "minQuantity"=>$gModifier["minAmount"],
                    "maxQuantity"=>$gModifier["maxAmount"],
                    "required"=>$gModifier["required"],
                    "byDefault"=>$gModifier["defaultAmount"], 
                    "freeQuantity"=>$gModifier["freeOfChargeAmount"],                    
                ],
                "childModifiers"=>$childModifiers,                
            ];                        
        }
        
        return $prodGroupModifiers;
    }

	private function build_groups_tree(array $groups): array {

        $groupsById = [];

        // Инициализация всех групп и преобразование sub_groups в ассоциативный массив        
		foreach ($groups as $group) {
			$groupsById[$group['id']] = $group;
			$groupsById[$group['id']]['sub_groups'] = [];
		}
	
		// Связывание подгрупп с родителями
		foreach ($groupsById as $id => &$group) {
			$parentId = $group['parentGroup'];
			if ($parentId !== null && $parentId !== '') {
				if (isset($groupsById[$parentId])) {
					$groupsById[$parentId]['sub_groups'][$id] = &$group;
				}
			}
		}
		unset($group); // Удаление ссылки после цикла
	
		// Сбор корневых групп
		$result = [];
		foreach ($groupsById as $id => $group) {
			$parentId = $group['parentGroup'];
			if (empty($parentId) || $parentId === null) {
				$result[$id] = $group;
			}
		}
		return $result;
	}    

	private function flatten_groups_tree(array $tree): array {
		$result = [];
		foreach ($tree as $group) {					
			$all_subs = [];
			$this->flatten_groups_tree_helper($group['sub_groups'], $all_subs);
			$group['sub_groups'] = $all_subs;
			$result[] = $group;
		}
		return $result;
	}

	private function flatten_groups_tree_helper(array $tree, array &$result): void {
		foreach ($tree as $group) {
			$subs = $group['sub_groups'];
			$group['sub_groups'] = [];
			$result[] = $group;
			$this->flatten_groups_tree_helper($subs, $result);		
		}		
	}

   
}




?>