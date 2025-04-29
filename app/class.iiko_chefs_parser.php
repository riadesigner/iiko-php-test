<?php


class Iiko_chefs_parser {
    // private string $JSON_FILE_PATH="";
    // private array $DATA = [];    

	function __construct(string $json_file_path){				
        // $this->JSON_FILE_PATH = $json_file_path;
		return $this;
	}

    public function parse(): void {
        // $arr = $this->load_json_file($this->JSON_FILE_PATH);
        // $this->DATA = $this->build_menu_from_iiko_json($arr);
    }

    public function print(): void {
        $arr = $this->DATA["menu"][0]["categories"];
        $this->print_tree($arr);
    }	
    
    private function print_tree(array $categories, int $level = 0): void{		
		
		// Рекурсивно выводим дерево групп
		foreach ($categories as $cat) {
			// Формируем отступы (количество дефисов = уровень вложенности + 1)
			$indent = str_repeat('---&nbsp;&nbsp;', $level );
			if(!$level){
				echo "<br><h3>$indent {$cat['name']} <small>{$cat['id']}</small></h3>";
			}else{
				echo "<br>$indent {$cat['name']} <small>{$cat['id']}</small>";
			}
	
			// Рекурсивно обрабатываем подкатегории, увеличивая уровень вложенности
			if (!empty($cat['items'])) {
				$this->print_tree($cat['items'], $level + 1);
			}
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
    private function build_menu_from_iiko_json($data): array {

        // Индексы
        $groupsById = [];
        foreach ($data['groups'] as $group) {
            if (!$group['isGroupModifier']) {
                $groupsById[$group['id']] = $group;
            }
        }
    
        // Индекс модификаторов по группам
        $modifiersByGroupId = [];
        if (!empty($data['modifierGroups'])) {
            foreach ($data['modifierGroups'] as $group) {
                $modifiersByGroupId[$group['id']] = [
                    'modifiersGroupId' => $group['id'],
                    'name' => $group['name'],
                    'items' => []
                ];
            }
            foreach ($data['modifiers'] as $mod) {
                $groupId = $mod['modifierGroupId'];
                if (!isset($modifiersByGroupId[$groupId])) continue;
                $modifiersByGroupId[$groupId]['items'][] = [
                    'modifierId' => $mod['id'],
                    'name' => $mod['name'],
                    'description' => $mod['description'] ?? '',
                    'price' => $mod['price'] ?? 0,
                    'portionWeightGrams' => $mod['weight'] ?? 0,
                    'restrictions' => [[
                        'byDefault' => $mod['defaultAmount'] ?? 0,
                        'freeQuantity' => $mod['freeAmount'] ?? 0,
                        'maxQuantity' => $mod['maxAmount'] ?? 0,
                        'minQuantity' => $mod['minAmount'] ?? 0,
                    ]]
                ];
            }
        }
    
        // Индекс размеров
        $sizesById = [];
        foreach ($data['sizes'] ?? [] as $size) {
            $sizesById[$size['id']] = $size['name'];
        }
    
        // Индекс цен по productId
        $priceByProductId = [];
        foreach ($data['sizePrices'] ?? [] as $entry) {
            $priceByProductId[$entry['productId']][] = [
                'price' => $entry['price']['currentPrice'] ?? 0,
                'sizeName' => $sizesById[$entry['sizeId']] ?? 'Стандарт',
            ];
        }
    
        // Продукты по группам
        $productsByGroup = [];
        foreach ($data['products'] ?? [] as $product) {
            if (!isset($product['parentGroup'])) continue;
            $productsByGroup[$product['parentGroup']][] = $product;
        }
    
        // Категории
        $categories = [];
        foreach ($groupsById as $groupId => $group) {
            $groupItems = [];
            foreach ($productsByGroup[$groupId] ?? [] as $product) {
                // Модификаторы
                $productModifiers = [];
                foreach ($product['modifierSchema']['groupModifiers'] ?? [] as $modGroup) {
                    $groupId = $modGroup['modifierGroupId'];
                    if (isset($modifiersByGroupId[$groupId])) {
                        $productModifiers[] = $modifiersByGroupId[$groupId];
                    }
                }
    
                // Размеры
                $sizes = $priceByProductId[$product['id']] ?? [];
    
                $groupItems[] = [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'sizes' => $sizes,
                    'description' => $product['description'] ?? '',
                    'imageUrl' => $product['imageLinks'][0] ?? '',
                    'orderItemType' => $product['type'] ?? 'Product',
                    'modifiers' => $productModifiers
                ];
            }
    
            $categories[] = [
                'id' => $groupId,
                'name' => $group['name'],
                'items' => $groupItems
            ];
        }
    
        // Финальный формат
        return [
            'menu' => [[
                'name' => 'Меню',
                'categories' => $categories
            ]],
            'id_menu' => 'uniq_000000100'
        ];
        
    }

}




?>