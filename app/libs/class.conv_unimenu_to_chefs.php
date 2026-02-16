<?php
/**
 * ПРЕОБРАЗУЕМ ФОРМАТ UNIMENU В CHEFSMENU (v-2.5.0)
 *  
 * добавлен calc_meta_info
 * 
 * убрали конвертирование сразу нескольких меню 
 * теперь на вход конструктор принимает только одно меню
 * 
 * для виртуального размерного ряда:
 * - добавлено поле originalPrice (из modifier->sizes[0]->price) 
 * - добавлено поле sizeGroupId (из modifierGroupId)
 * (для возможности перед отправкой заказа в iiko  
 * распарсить размерный ряд обратно в модификатор размера, 
 * из которого он был собран)
 * 
 * - добавлены названия для размерного ряда, если он есть, а имена пустые
 * (такое может быть, если используется настоящий размерный ряд, 
 * но загружается меню как Номенклатура, а не как Внешнее меню)
 * 
 * особенность формата chefsmenu в том (в том числе), что 
 * CATEGORIES и ITEMS (ТОВАРЫ) - хранятся как ассоциативный массив (с id ключами ),
 * а MODIFIERS и ITEMS (ГРУППЫ МОДИФИКАТОРОВ и МОДИФИКАТОРЫ) - как обычные массивы (с индексами 0, 1, 2, ...);
 * 
 * */

class Conv_unimenu_to_chefs {
    
    private array $DATA;
    private string $INFO;

	function __construct(array $unimenu){				        
        $this->DATA = $this->convert_menu($unimenu);
        $this->INFO = $this->calc_meta_info($this->DATA);
		return $this;
	}

    public function get_data(): array {
          return [
            "SourceMenu" => "NOMENCLATURE",
            "TypeMenu" => "CHEFSMENU",
            "Menu" => $this->DATA,            
        ];
    }

    public function get_info(): string {
        return $this->INFO;
    }

    // ----------------
    // PRIVATE METHODS
    // ----------------
    private function convert_menu(array $menu): array {
        $data = array();
        $data['id'] = $menu['menuId'];
        $data['name'] = $menu['name'];        
        $data['categories'] = $this->get_categoties($menu['groups']);        
        $counter = 0;
        foreach($data['categories'] as &$category){
            $counter++;
            $category['items'] = $this->get_items($menu, $category["id"]);
        }
        return $data;
    }

    // собираем категории
    private function get_categoties(array $groups): array {        
        $categories = array_filter($groups, fn($e) => $e["type"] === "CATEGORY");
        $categories = array_map(fn($e) => [
            "id" => $e["groupId"],
            "name" => $e["name"],
        ], $categories);
        return $categories;
    }

    // собираем товары
    private function get_items(array $menu, string $category_id): array {
       
        $items = array_filter($menu['products'], fn($e) => $e["parentGroup"] === $category_id);
        
        $items_parsed = array_map(function($e) use($menu) { 
                        
            [$gNormalModifiers, $gSizesModifiers] = $this->get_modifiers_goups($menu, $e["groupModifiers"]);

            $gNormalModifiers = $this->assoc_to_array($gNormalModifiers);
            
            $sizes = $this->get_item_sizes($e["itemSizes"], $gSizesModifiers);

            return [
            "id" => $e["itemId"],
            "name" => $e["name"],
            "description" => $e["description"],
            "imageUrl"=> $e["imageUrl"],
            "sizes"=> $sizes,
            "modifiers"=> $gNormalModifiers,
            "orderItemType"=>"",
            "sku"=>"",        
        ];}, $items);
        return $items_parsed;
    }

    // собираем группы модификаторов
    private function get_modifiers_goups(array $menu, array $groupModifiers): array {        

        $gModifiers = array_map(fn($e) => [
            "modifierGroupId" => $e["modifierGroupId"],            
            "name" =>  $menu["groups"][$e["modifierGroupId"]]["name"]??"",
            "restrictions"=>[
                "minQuantity"=> $e["restrictions"]["minQuantity"],
                "maxQuantity"=> $e["restrictions"]["maxQuantity"],
                "required"=>$e["restrictions"]["required"],
                "freeQuantity"=> $e["restrictions"]["freeQuantity"],
                "byDefault"=> $e["restrictions"]["byDefault"],                
            ],
            "items"=> $this->get_modifiers_items($menu, $e["modifierGroupId"], $e["childModifiers"]),
            ],$groupModifiers);

        // ФИШКА PIZZAIOLO:
        // делим группы модификаторов
        // на ОБЫЧНЫЕ и на МОДИФИКАТОРЫ РАЗМЕРА
        // (если в названии группы модификаторов есть слово "размер")
        $gNormalModifiers = array_filter($gModifiers, fn($e) => !str_contains(mb_strtolower($e["name"]), "размер"));
        $gSizesModifiers = array_filter($gModifiers, fn($e) => str_contains(mb_strtolower($e["name"]), "размер"));

        return [$gNormalModifiers, $gSizesModifiers];

    }

    // собираем модификаторы
    private function get_modifiers_items(array $menu, string $modifierGroupId, array $childModifiers): array {
        // отбираем модификаторы группы
        $items = array_filter($menu['products'], fn($e) => ($e["parentGroup"] === $modifierGroupId));
        // пересобираем их в chefs структуру
        $items_parsed = array_map(function($e) use($childModifiers) { 
            // собираем параметры restrictions у модификатора
            $modifierId = $e["itemId"];
            $mo = array_filter($childModifiers, function($cm) use($modifierId) { return $cm["modifierId"] === $modifierId; });
            $norestrictions = ["minQuantity" => 0, "maxQuantity" => 0, "required" => false, "byDefault" => 0, "freeQuantity" => 0];
            $restrictions = $mo[array_key_first($mo)]["restrictions"]??$norestrictions;
            return [
            "modifierId" => $modifierId,
            "modifierGroupId"=> $e["parentGroup"],
            "name" => $e["name"],
            "description" => $e["description"],
            "imageUrl"=> $e["imageUrl"],
            "portionWeightGrams"=>$e["itemSizes"][0]["weightGrams"]??0,
            "price"=>$e["itemSizes"][0]["price"]??0,
            "restrictions"=>$restrictions,
            ];
    }, $items);
        return $this->assoc_to_array($items_parsed);
    }

    // собираем размеры товара
    private function get_item_sizes(array $sizes, array $gSizesModifiers=null): array {
        
        // фишка PIZZAIOLO:
        // если есть специальный модификатор для размеров, 
        // то строим размерный ряд из него        
        if($gSizesModifiers!==null && count($gSizesModifiers)>0){

            $gSizesModifier = $gSizesModifiers[array_key_first($gSizesModifiers)];            
            $mainPrice = (int) $sizes[0]["price"];
            $measureUnitType = $sizes[0]["measureUnitType"];
            $weightGrams = (int) $sizes[0]["weightGrams"];

            $sizes_parsed = [];

            $count = 0;
            foreach($gSizesModifier["items"] as $item){             
                // устанавливаем по умолчанию второй по счету размер; 
                $isDefault = $count==1; $count++;                
                $sizes_parsed[] = [
                    "sizeCode"=>"",
                    "sizeId" => $item["modifierId"],
                    "sizeName" => $item["name"],
                    "isDefault"=> $isDefault,
                    "price" => (int) $item["price"] + $mainPrice,
                    "originalPrice" => (int) $item["price"],
                    "sizeGroupId"=> $item["modifierGroupId"],
                    "measureUnitType"=> $measureUnitType,
                    "portionWeightGrams" => (int) $item["portionWeightGrams"] + $weightGrams,
                ];
            }

        // иначе, применяем обычные размеры
        }else{            
            $sizes_parsed = array_map(fn($e)=>[
                "sizeCode"=>"",
                "sizeId" => $e["sizeId"],
                "sizeName" => $e["sizeName"],
                "isDefault"=> false,
                "price" => $e["price"],
                "originalPrice" => null,
                "sizeGroupId"=> "",
                "measureUnitType"=>$e["measureUnitType"],
                "portionWeightGrams" => $e["weightGrams"],
            ], $sizes);
            // устанавливаем первый по счету размер по умолчанию
            $sizes_parsed[0]["isDefault"] = true;
            // ------------------------------------------------------------------
            // если названия хотябы одного размера не укзано, 
            // то присваеваем всем размерам свои названия S, M, L, XL, XXL, XXXL;
            // ------------------------------------------------------------------
            // ищем пустые названия размеров            
            if((in_array('', array_column($sizes_parsed, 'sizeName'), true))){
                // если есть хоть одно пустое название:
                // сортируем по цене
                usort($sizes_parsed, function($a, $b) {
                    return $a['price'] <=> $b['price'];
                });
                // Добавляем размеры
                foreach ($sizes_parsed as $index => &$item) {
                    if ($index == 0) {
                        $item['sizeName'] = 'S';
                    } elseif ($index == 1) {
                        $item['sizeName'] = 'M';
                    } elseif ($index == 2) {
                        $item['sizeName'] = 'L';
                    } else {
                        $xCount = $index - 2; // Количество "X" = позиция - 2
                        $item['sizeName'] = str_repeat('X', $xCount) . 'L';
                    }
                }
                unset($item); // Разрываем ссылку после цикла
            }
        }

        return $sizes_parsed;
    }

    private function assoc_to_array($assoc) {
        $new_array = [];
        foreach ($assoc as $key => $value) {
            $new_array[] = $value;
        }
        return $new_array;
    }
    

    private function calc_meta_info($chefsdata): string {
        // Размер финальных данных:
        $size_chefs = strlen(json_encode($chefsdata));
        $size_chefs = round($size_chefs / 1048576, 2) . " MB";
        $vars_chefs =  $this->count_vars($chefsdata);
        $infoMsg = "Converted menu from UNIMENU to CHEFSMENU: ";
        $infoMsg .="Размер chefsdata: ~" . $size_chefs.", ";
        $infoMsg .= 'Переменных в chefsdata: ' . $vars_chefs;
        return $infoMsg;
    }

    // Подсчет переменных:
    private function count_vars($data) {
        $count = 0;
        foreach ($data as $key => $value) {
            $count++;
            if (is_array($value)) {
                $count += $this->count_vars($value);
            }
        }
        return $count;
    }    

}




?>