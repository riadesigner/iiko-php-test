<?php


class Iiko_chefs_parser {
    private string $JSON_FILE_PATH="";
    private array $DATA;    
    private array $menuGroupsFlatten;
    private array $productsById;
    private array $modifiersById;
    private array $groupsModifiersById;
    private array $categoriesById;          
    private bool $GROUPS_AS_CATEGORY;

	function __construct(string $json_file_path){				
        $this->JSON_FILE_PATH = $json_file_path;
		return $this;
	}

    public function parse(bool $groups_as_category = false): void {
        $this->GROUPS_AS_CATEGORY = $groups_as_category;
        $arr = $this->load_json_file($this->JSON_FILE_PATH);
        $this->DATA = $this->build_all_menus($arr);
        echo "<pre>";
        print_r($this->DATA);
        echo "</pre>";
    }
    // get_menu_v2_by_id
    // res([
    //     vars['menu'],
    //     vars['menu-hash'],
    //     vars['need-to-update']
    // ]);


    // public function print(): void {
    //     $arr = $this->DATA["menu"][0]["categories"];
    //     $this->print_tree($arr);
    // }	
    
    // private function print_tree(array $categories, int $level = 0): void{		
		
	// 	// Рекурсивно выводим дерево групп
	// 	foreach ($categories as $cat) {
	// 		// Формируем отступы (количество дефисов = уровень вложенности + 1)
	// 		$indent = str_repeat('---&nbsp;&nbsp;', $level );
	// 		if(!$level){
	// 			echo "<br><h3>$indent {$cat['name']} <small>{$cat['id']}</small></h3>";
	// 		}else{
	// 			echo "<br>$indent {$cat['name']} <small>{$cat['id']}</small>";
	// 		}
	
	// 		// Рекурсивно обрабатываем подкатегории, увеличивая уровень вложенности
	// 		if (!empty($cat['items'])) {
	// 			$this->print_tree($cat['items'], $level + 1);
	// 		}
	// 	}
	// }    

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
            return $this->build_menus_from_groups();
        }else{
            return $this->build_menus_from_categories();
        }        
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
                "itemCategories"=>[],                
            ];
            // заполняем категориями
            foreach ($rootGroup['sub_groups'] as $cat) {
                $category = [
                    "groupId"=>$cat['id'], 
                    "type"=> "CATEGORY",
                    "name" => $cat["name"],                     
                    "items"=>[]
                ];                
                // отбираем товары для категории
                $prods = array_filter($this->productsById, fn($e) => $e["parentGroup"] === $category["groupId"]);
                // добавляем товары в категорию
                foreach ($prods as $prod) {
                    $prodId = $prod['id'];
                    $prod = $this->parse_prod($this->productsById[$prodId]);
                    $category["items"][$prod["id"]] = $prod;
                }
                // добавляем категорию в меню
                $menu["itemCategories"][$category["groupId"]] = $category;
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
                "itemCategories"=>[],
            ];
            // берем все товары этого меню
            $menuProdsIds = $productsIdsByMenu[$rootGroup["id"]];
            foreach($menuProdsIds as $prodId){
                $prod = $this->productsById[$prodId];
                $cat = $this->categoriesById[$prod["productCategoryId"]];                
                $category = [
                    "groupId" => $cat["id"],
                    "type"=> "CATEGORY",
                    "name"=>$cat["name"],                    
                    "items"=>[],
                ];                           
                // добавляем категорию меню, если такой категории еще нет 
                if(!isset($menu["itemCategories"][$cat["id"]])){         
                    $menu["itemCategories"][$cat["id"]] = $category;
                };
                //добавляем товар в эту категорию
                $prod = $this->parse_prod($prod);                
                $menu["itemCategories"][$cat["id"]]["items"][$prodId] = $prod;
            }
            $menus[$menu["menuId"]] = $menu;
        }
        return $menus;
    }    

    private function parse_prod($prod): array{
        // пропускаем одиночные модификаторы, 
        // не используем в этой версии                    
        // $prod['modifiers']

        // парсим групповые модификаторы текущего товара
        $prodGroupModifiers = [];                
        foreach ($prod['groupModifiers'] as $gModifier) {
            
            // находим модификаторы группы 
            $modifiers = $gModifier["childModifiers"];                        
            $mById = $this->modifiersById;
            // собираем модификаторы                        
            $items = array_map(function($e) use($mById) { 
                $price = $mById[$e["id"]]["sizePrices"][0]["price"]["currentPrice"];
                $itemSizes = [
                    [
                    "sizeId" => "",
                    "sizeName" => "",
                    "price" =>  $price,
                    "isDefault" =>  false,
                    "weightGrams" => 0,
                    "measureUnitType" => "GRAM",
                    ]
                ];
                return [
                    "itemId"=>$e["id"],
                    "name"=>$mById[$e["id"]]["name"],
                    "description"=>$mById[$e["id"]]["description"],
                    "imageUrl"=> "",
                    "type" => "MODIFIER",
                    "itemSizes"=>$itemSizes,
                    "isAvailable" => true,
                ];}, $modifiers);
            
            $prodGroupModifiers[$gModifier["id"]] = [
                "modifierGroupId"=>$gModifier["id"],                            
                "name"=>$this->groupsModifiersById[$gModifier["id"]]["name"]??"без названия",
                "restrictions"=>[
                    "minAmount"=>$gModifier["minAmount"],
                    "maxAmount"=>$gModifier["maxAmount"],
                    "required"=>$gModifier["required"],                                
                ],                
                "items"=>$items,                            
            ];                        
        }
        
        $product = [                        
            "id"=>$prod['id'],                         
            "name" => $prod["name"],
            "description"=>$prod["description"],       
            "modifiers"=>$prodGroupModifiers,
            "price"=>$prod["sizePrices"][0]["price"]["currentPrice"],
        ];        
        return $product;
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