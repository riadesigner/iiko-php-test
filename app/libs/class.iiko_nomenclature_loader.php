<?php

if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 	ПОЛУЧАЕМ НОМЕНКЛАТУРУ ИЗ IIKO v-1.0.0
 * 
 *  @param <string> $id_organization
 *  @param <string> $iiko_api_key
 * 
*/
class Iiko_nomenclature_loader{

	private string $ID_ORG;
	private string $IIKO_API_KEY;
	private array $DATA;
	private string $TOKEN;
	private string $PATH_TO_TEMP_FILE;
	
	/**
	 * @param <string> $id_org
	 * @param <string> $iiko_api_key / optional
	 * @param <string> $token / optional
	 * @return <Iiko_nomenclature>
	 * 
	 * опционально можно передать токен, если есть
	 * или iiko_api_key, если токен еще не получен
	*/
	function __construct(string $id_org, string $iiko_api_key="", string $token=""){
		$this->ID_ORG = $id_org;
		$this->IIKO_API_KEY = $iiko_api_key;
		$this->TOKEN = $token;		
		return $this;
	}

	// @param <bool> $create_temp_file // если true, то создаем временный файл
	// @param <bool> $auto_clear_data // если true, то очищаем данные сразу после сохранения файла
	public function reload(bool $create_temp_file = false, bool $auto_clear_data = false): void{		
		if(empty($this->TOKEN)){ $this->TOKEN = $this->reload_token(); }
		$this->DATA = $this->load_nomenclature();
		if($create_temp_file){
			// SAVING TO TEMP FILE
			$this->PATH_TO_TEMP_FILE = saveArrayToUniqueJson($this->DATA);
		};
		if($auto_clear_data){
			unset($this->DATA);
		}
	}

	public function get_data(): array{
		return $this->DATA;
	}
	
	public function get_file_path(): string{
		return $this->PATH_TO_TEMP_FILE;
	}
	
	public function clean(): never{
		// очищаем данные
		unset($this->DATA);
		//удаляем временный файл
		unlink($this->PATH_TO_TEMP_FILE);
		exit();
	}

	// ---------------
	// PRIVATE METHODS
	// ---------------
	private function reload_token(): string {
		// GETTING TOKEN FROM IIKO 
		$url     = 'api/1/access_token';
		$headers = ["Content-Type"=>"application/json"];
		$params  = ["apiLogin" => $this->IIKO_API_KEY];
		$res = iiko_get_info($url,$headers,$params);		
		if(!isset($res['token'])) die("Неправильный IIKO_API_KEY");
		return $res['token'];
	}

	private function load_nomenclature(): array{
		// GETTING NOMENCLATURE FROM IIKO
		$url     = 'api/1/nomenclature';
		$headers = [
			"Content-Type"=>"application/json",
			"Authorization" => 'Bearer '.$this->TOKEN
		]; 
		$params  = [
			"organizationId"=> $this->ID_ORG,
			"startRevision"=> "0",    
		];
		$res = iiko_get_info($url,$headers,$params);		
		return $res;
	}

}

?>