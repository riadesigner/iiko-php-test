<?php
/**
 * ПРЕОБРАЗУЕМ ФОРМАТ UNIMENU В CHEFSMENU
 * 
 * */

class Conv_unimenu_to_chefs {
    
    private array $UNIMENU;    
    private array $DATA;    


	function __construct(array $unimenu){				
        $this->UNIMENU = $unimenu;
		return $this;
	}

    public function convert(): Conv_unimenu_to_chefs {
        $ready_menus = array(); 

        $menus = $this->UNIMENU['Menus'];

        foreach($menus as $menu){
            $ready_menus[] = $this->convert_menu($menu);
        }

        $this->DATA = $ready_menus;
        return $this;
    }

    public function get_data(): array {
        return $this->DATA;
    }

    private function convert_menu(array $menu): array {
        $data = array();
        $data['id'] = $menu['menuId'];
        $data['name'] = $menu['name'];
        $data['description'] = $menu['description'];
        $data['categories'] = $this->get_categoties($menu['groups']);        
        $counter = 0;
        foreach($data['categories'] as &$category){
            $counter++;
            $category['items'] = $this->get_items($menu, $category["id"]);
        }
        return $data;
    }

    private function get_categoties(array $groups): array {        
        $categories = array_filter($groups, fn($e) => $e["type"] === "CATEGORY");
        $categories = array_map(fn($e) => [
            "id" => $e["groupId"],
            "name" => $e["name"],
        ], $categories);
        return $categories;
    }

    private function get_items(array $menu, string $category_id): array {
        $items = array_filter($menu['products'], fn($e) => $e["parentGroup"] === $category_id);
        $items_parsed = array_map(fn($e) => [
            "id" => $e["itemId"],
            "name" => $e["name"],
            "description" => $e["description"],
            "imageUrl"=> $e["imageUrl"],
            "sizes"=>[],
            "modifiers"=> $this->get_modifiers_goups($menu, $e["groupModifiers"]),
            "orderItemType"=>"",
            "sku"=>"",        
        ], $items);
        return $items_parsed;
    }

    private function get_modifiers_goups(array $menu, array $groupModifiers): array {

        $gModifiers = array_map(fn($e) => [
            "modifierGroupId" => $e["groupId"],            
            "name" =>  $menu["groups"][$e["groupId"]]["name"]??"",
            "restrictions"=>[
                "minQuantity"=> $e["restrictions"]["minQuantity"],
                "maxQuantity"=> $e["restrictions"]["maxQuantity"],
                "required"=>$e["restrictions"]["required"],
                "freeQuantity"=> $e["restrictions"]["freeQuantity"],
                "byDefault"=> $e["restrictions"]["byDefault"],                
            ],
            "items"=> $this->get_modifiers_items($menu, $e["groupId"]),
            ],$groupModifiers);

        // ФИШКА PIZZAIOLO:
        // делим на группы обычных модификаторов
        // и на группы модификаторов размера        
        // $gNormalModifiers = array_filter($gModifiers, fn($e) => !str_contains(mb_strtolower($e["name"]), "размер"));
        // $gSizeModifiers = array_filter($gModifiers, fn($e) => str_contains(mb_strtolower($e["name"]), "размер"));

        return $gModifiers;

    }

    private function get_modifiers_items(array $menu, string $modifierGroupId): array {
        $items = array_filter($menu['products'], fn($e) => ($e["parentGroup"] === $modifierGroupId));
        $items_parsed = array_map(fn($e) => [
            "modifierId" => $e["itemId"],
            "name" => $e["name"],
            "description" => $e["description"],
            "portionWeightGrams"=>"",
            "imageUrl"=> $e["imageUrl"],
            "price"=>$e["itemSizes"][0]["price"]??0,
        ], $items);
        return $items_parsed;
    }


}




?>