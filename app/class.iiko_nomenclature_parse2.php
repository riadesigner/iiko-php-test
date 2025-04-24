<?php

if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 	СОБИРАЕМ И ОБНОВЛЯЕМ ВСЕ ПАРАМЕТРЫ IIKO ДЛЯ КАФЕ
 * 
 *  @param <int> $id_cafe
 *  @param <string> $iiko_api_key
 * 
 *  @return <Iiko_params> $this
*/
class Iiko_nomenclature_parse2{

	private array $ARR_CAFES = [];
	private array $ARR_GROUPS_TREE = [];
	private array $ARR_CATEGORIES = [];
	
	function __construct(string $json_file_path=""){		
		// read the json file
		$arr = $this->load_json_file($json_file_path);

		// parse the array
		$this->parse($arr);

		return $this;
	}

	public function parse($arr){						
		
		$groups = $arr["groups"];
		$productCategories = $arr["productCategories"];
		$products = $arr["products"];
		
		$this->ARR_CATEGORIES = $this->collect_categories_array($productCategories);
		$this->ARR_CAFES = $this->search_cafes_in_groups($groups);
		$this->ARR_GROUPS_TREE = $this->build_groups_tree($groups);

		// $this->print($this->ARR_GROUPS_TREE);
		$this->print_groups_tree($this->ARR_GROUPS_TREE);
		
		// $this->search_sections_in_groups($groups);
		// $this->search_items_in_products($products);
		// $this->print_menu();
		
	}

	public function print(array $arr){
		echo "<pre>";
		print_r($arr);
		echo "</pre>";
	}	

	public function print_groups_tree(array $groups, int $level = 0): void{		
		foreach ($groups as $group) {
			// Формируем отступы (количество дефисов = уровень вложенности + 1)
			$indent = str_repeat('-&nbsp;&nbsp;', $level + 1);
			echo "<br>$indent {$group['name']} <small>{$group['id']}</small>";
	
			// Рекурсивно обрабатываем подкатегории, увеличивая уровень вложенности
			if (!empty($group['sub_groups'])) {
				$this->print_groups_tree($group['sub_groups'], $level + 1);
			}
		}
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

	private function collect_categories_array($categories): array{
		$arr = [];
		if(count($categories)){
			foreach($categories as $cat){
				$arr[$cat["id"]] = $cat;						
			}
		};
		return $arr;
	}

	private function search_cafes_in_groups($groups): array{		
		$arr = array_filter($groups, fn($e) =>
			(
				$e["parentGroup"] == null 
				&& $e["isGroupModifier"] == false
			)
		);		
		
		if(count($arr)){
			$cafes = array_map( function($e){ 				 
				return ["id"=>$e["id"], "name"=>$e["name"]];
			}, $arr);

			foreach($cafes as $cafe){
				$this->ARR_CAFES[$cafe["id"]] = $cafe;
			}						
		};

		return $cafes;
	}

	private function search_sections_in_groups($groups): void{
		$cafes = &$this->ARR_CAFES;
		if(!count($cafes)) return;		
		foreach($cafes as &$cafe){			
			$id_cafe = $cafe["id"];
			$sections = array_filter($groups, function($e) use($id_cafe) {				
				return (
					$e["parentGroup"] == $id_cafe 
					&& $e["isGroupModifier"] == false
					);
				}
			);			
			$cafe["sections"]=$sections;			
		}
	}	

	private function search_items_in_products($products): void{
		$cafes = &$this->ARR_CAFES;		
		if(!count($cafes)) return;
		foreach($cafes as &$cafe){
			$sections = &$cafe["sections"];
			if(count($sections)){
				foreach($sections as &$s){					
					$items = array_filter($products, fn($e)=>
						(
							$e["parentGroup"]==$s["id"]
							&& $e["type"] == "Dish"
						)
					);
					$s["items"] = $items;					
					// $services = array_filter($products, fn($e)=>
					// 	(
					// 		$e["parentGroup"]==$s["id"]
					// 		// && 
					// 		// $e["type"] == "Service"
					// 	)
					// );

					// $s["services"] = $services;
					$s["services"] = [];
				}
			}
		}
	}

	private function get_catname_by_id($id_category): string{
		return isset($this->ARR_CATEGORIES[$id_category])? $this->ARR_CATEGORIES[$id_category]["name"]: "Untitled";
	}

	private function load_json_file(string $json_file_path=""): array{	
		
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


}

?>