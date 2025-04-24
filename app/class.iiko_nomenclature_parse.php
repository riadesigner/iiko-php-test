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
class Iiko_nomenclature_parse{

	private array $ARR_CAFES = [];
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
		
		$this->make_categories_array($productCategories);
		$this->search_cafes_in_groups($groups);
		$this->search_sections_in_groups($groups);
		$this->search_items_in_products($products);

		$this->print_menu();
		
	}

	private function make_categories_array($categories): void{
		if(!count($categories))return;
		echo "<ol>";
		foreach($categories as $cat){
			$this->ARR_CATEGORIES[$cat["id"]] = $cat;			
			echo "<li>{$cat['name']}</li>";
		}		
		echo "</ol>";
	}

	private function search_cafes_in_groups($groups): void{
		$arr = array_filter($groups, fn($e) =>
			(
				$e["parentGroup"] == null 
				&& $e["isGroupModifier"] == false
			)
		);		
		$this->ARR_CAFES = count($arr)?array_map( function($e){ return ["id"=>$e["id"], "name"=>$e["name"]]; }, $arr) : [];
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

	public function print_menu(): void{
		$cafes = &$this->ARR_CAFES;
		if(!count($cafes)){
			echo "не найдено кафе или меню";
			return;
		}	
		echo "<hr>";	
		foreach($cafes as $cafe){			
			printf("<h1>кафе:%s</h1>", $cafe["name"]);
			foreach($cafe["sections"] as $section){				
				printf("<p><strong>- %s</strong></p>", $section["name"]);								
				echo "<ol>";
				foreach($section["items"] as $item){
					// $amountPrices = count($item["sizePrices"]); 
					$productCategory = $item["productCategoryId"];
					$catName = $this->get_catname_by_id($productCategory);
					$price = (int) $item["sizePrices"][0]["price"]["currentPrice"]; 					
					printf("<li> %s (%s руб) <small><i>%s</i> [%s]</small></li>", $item["name"], $price, $catName, $productCategory);
				}			
				echo "</ol>";
				if(count($section["services"])) echo "<p>Услуги</p>";
				foreach($section["services"] as $service){
					// $amountPrices = count($item["sizePrices"]); 
					$productCategory = $item["productCategoryId"];
					$catName = $this->get_catname_by_id($productCategory);
					$price = (int) $item["sizePrices"][0]["price"]["currentPrice"]; 					
					printf("-- %s (%s руб) <small>%s</small><br>", $service["name"], $price, $catName);
				}								
			}
		}
		echo "<hr>";	

	}
}

?>