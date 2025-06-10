<?php

if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 	ПОЛУЧАЕМ НОМЕНКЛАТУРУ ИЗ IIKO
 * 
 *  @param <string> $id_organization
 *  @param <string> $iiko_api_key
 * 
*/
class Iiko_nomenclature{

	private string $ID_ORG;
	private string $IIKO_API_KEY;
	private array $DATA;
	private string $TOKEN;
	
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

	public function reload(): void{
		if(empty($this->TOKEN)){ $this->TOKEN = $this->reload_token(); }
		$this->DATA = $this->load_nomenclature();
	}

	public function get_data(): array{
		return $this->DATA;
	}
	private function reload_token(): string {
		// GETTING TOKEN FROM IIKO 
		$url     = 'api/1/access_token';
		$headers = ["Content-Type"=>"application/json"];
		$params  = ["apiLogin" => $this->IIKO_API_KEY];
		$res = iiko_get_info($url,$headers,$params);		
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