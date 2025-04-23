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
	
	function __construct(string $json_file_path=""){		
		// read the json file
		$arr = $this->load_json_file($json_file_path);

		// parse the array
		$this->parse($arr);

		return $this;
	}

	public function parse($arr){						
		echo "<br>TOTAL = ".count($arr);
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