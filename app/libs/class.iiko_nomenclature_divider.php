<?php

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * 	ПАРСИМ НОМЕНКЛАТУРУ ИЗ IIKO v-1.0.0
 *  
 *  1. получаем на вход json-файл (номенклатуру) 
 *  2. разбиваем этот json файл на части 
 *  3. сохраняем результат в временные файлы
 *  4. возвращаем результат в виде массива ссылок на временные файлы
 *  5. удаляем временные файлы
 * 
 *  @param <string> $json_file_nomenclature
 * 
*/
class Iiko_nomenclature_divider{

	private string $JSON_FILE_PATH;
	private array $TEMP_FILES;
	
	function __construct(string $json_file_path){		
		$this->JSON_FILE_PATH = $json_file_path;
		return $this;
	}

	// возвращает массив с ссылками на временные файлы
	// @return array
	public function get(): array{

		// extract dish
		$query = '.products[]? | select(.isDeleted | not) | select((.type | ascii_downcase) == "dish")';
		$dish_file_name = $this->extract_part("extract_dish_", $query);

		// extract modifiers
		$query = '.products[]? | select(.isDeleted | not) | select((.type | ascii_downcase) == "modifier")';
		$modifiers_file_name = $this->extract_part("extract_modifier_", $query);

		// extract service
		$query = '.products[]? | select(.isDeleted | not) | select((.type | ascii_downcase) == "service")';
		$service_file_name = $this->extract_part("extract_service_", $query);

		// extract categories
		$query = '.productCategories[]? | select(.isDeleted | not) | {id, name } ';
		$categories_file_name = $this->extract_part("extract_categories_", $query);
		
		// extract groups		
		$query = '.groups[]? | select(.isDeleted | not) | {parentGroup, isIncludedInMenu, isGroupModifier, id, code, name} ';
		$groups_file_name = $this->extract_part("extract_groups_", $query);

		$this->TEMP_FILES = [
			"dish" => $dish_file_name,
			"modifiers" => $modifiers_file_name,
			"service" => $service_file_name,
			"categories" => $categories_file_name,
			"groups" => $groups_file_name,
		];		

		return $this->TEMP_FILES;
	}

	// удаляем временные файлы
	public function clean(){
		foreach($this->TEMP_FILES as $file){
			unlink($file);
		}
	}

	// ========================
	//         PRIVATE
	// ========================
	
	// извлекаем часть файла
	private function extract_part(string $prefix, string $query){
		
		// Извлекаем базовое имя файла (с расширением)
		$baseName = basename($this->JSON_FILE_PATH);
		$directory = dirname($this->JSON_FILE_PATH);
		
		// Удаляем расширение .json
		$fileName = pathinfo($baseName, PATHINFO_FILENAME);		

		$inputFile = $this->JSON_FILE_PATH;
		$outputFile = sprintf( "%s/%s%s.%s", $directory, $prefix, $fileName, "jsonl" );

		// Формируем команду с экранированием
		$command = sprintf(    
			"jq -c '%s' %s > %s",
			$query,
			escapeshellarg($inputFile),
			escapeshellarg($outputFile)
		);		
		// Выполняем команду
		exec($command, $output, $returnCode);

		if ($returnCode === 0) {			
			glog("Файл $outputFile успешно преобразован!");
			return $outputFile;
		} else {
			glogError("Ошибка (код $returnCode): " . implode("\n", $output));
			return false;
		}		

	}


}

?>