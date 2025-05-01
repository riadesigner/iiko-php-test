<?php


class Iiko_chefs_parser {
    private string $JSON_FILE_PATH="";
    private array $DATA = [];    

	function __construct(string $json_file_path){				
        $this->JSON_FILE_PATH = $json_file_path;
		return $this;
	}

    public function parse(): void {
        $arr = $this->load_json_file($this->JSON_FILE_PATH);
        $this->DATA = $this->build_menu_from_iiko_json($arr);
    }

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

    private function build_menu_from_iiko_json($data): array {

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

        // Выводим дерево групп
        echo "<ol>";
        foreach ($menuGroupsFlatten as $group) {
            printf("<li><h3>%s</h3></li>", $group['name'], $group['parentGroup']);
            foreach ($group['sub_groups'] as $sub_group) {                
                printf("<li>%s</li>", $sub_group['name']);
            }
        }
        echo "</ol>";

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
        
        // Собираем меню из групп
        // Вариант 1, где 
        // - каждая корневая группа – это отдельное меню 
        // - группы (они же папки) – используются в качестве категорий
        // - а iiko-категории – не учитываются
        $menus = [];
        foreach ($menuGroupsFlatten as $rootGroup) {
            // создаем меню
            $menu = ["name" => $rootGroup["name"], "id"=>$rootGroup['id'], "categories"=>[]];
            // заполняем категориями
            foreach ($rootGroup['sub_groups'] as $cat) {
                $category = ["name" => $cat["name"], "id"=>$cat['id'], "items"=>[]];                
                // отбираем товары для категории
                $prods = array_filter($productsById, fn($e) => $e["parentGroup"] === $category["id"]);
                // добавляем товары в категорию
                foreach ($prods as $prod) {
                    
                    // пропускаем одиночные модификаторы, 
                    // не используем в этой версии                    
                    // $prod['modifiers']

                    // парсим групповые модификаторы товара
                    $prodGroupModifiers = [];                
                    foreach ($prod['groupModifiers'] as $gModifier) {
                        
                        // находим модификаторы группы 
                        $modifiers = $gModifier["childModifiers"];                        
                        
                        // собираем модификатор                        
                        $items = array_map(fn($e) => [
                                "id"=>$e["id"],
                                "name"=>$modifiersById[$e["id"]]["name"],
                                "price"=>$modifiersById[$e["id"]]["sizePrices"][0]["price"]["currentPrice"],
                            ], $modifiers);
                        
                        $prodGroupModifiers[$gModifier["id"]] = [
                            "modifierGroupId"=>$gModifier["id"],                            
                            "name"=>$groupsModifiersById[$gModifier["id"]]["name"]??"без названия",
                            "items"=>$items,
                            "restrictions"=>[
                                "minAmount"=>$gModifier["minAmount"],
                                "maxAmount"=>$gModifier["maxAmount"],
                                "required"=>$gModifier["required"],                                
                            ],                            
                        ];                        
                    }
                    
                    $product = [                        
                        "id"=>$prod['id'],                         
                        "name" => $prod["name"],
                        "description"=>$prod["description"],       
                        "modifiers"=>$prodGroupModifiers,
                        "price"=>$prod["sizePrices"][0]["price"]["currentPrice"],
                    ];
                    $category["items"][$prod["id"]] = $product;
                }
                // добавляем категорию в меню
                $menu["categories"][$category["id"]] = $category;
            }
            $menus[$menu["id"]] = $menu;
        }

        echo "<pre>";
        print_r($menus);
        echo "</pre>";

        // return [
        //     'menu' => [[
        //         'name' => 'Меню',
        //         'categories' => $categories
        //     ]],
        //     'id_menu' => 'uniq_000000100'
        // ];

        return [];
        
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