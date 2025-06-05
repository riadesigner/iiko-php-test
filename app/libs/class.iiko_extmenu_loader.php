<?php

if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 	ПОЛУЧАЕМ ВНЕШНЕЕ МЕНЮ ИЗ IIKO
 * 
 *  @param <string> $id_organization
 *  @param <string> $id_external_menu
 *  @param <string> $iiko_api_key
 * 
*/
class Iiko_extmenu_loader{

	private string $ID_ORG;
	private string $ID_EXTL_MENU;
	private string $IIKO_API_KEY;
	private array $DATA;
	private string $TOKEN;
	
	function __construct(string $id_org, string $id_external_menu, string $iiko_api_key=""){
		$this->ID_ORG = $id_org;
		$this->ID_EXTL_MENU = $id_external_menu;
		$this->IIKO_API_KEY = $iiko_api_key;		
		return $this;
	}

	public function reload(): void{
		$this->TOKEN = $this->reload_token();
		$this->DATA = $this->load_extmenu();
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

	private function load_extmenu(): array{
		// GETTING EXTERNAL MENU FROM IIKO
		$url     = 'api/2/menu/by_id';
		$headers = [
			"Content-Type"=>"application/json",
			"Authorization" => 'Bearer '.$this->TOKEN
		]; 
		$params  = [
			'externalMenuId' => $this->ID_EXTL_MENU,
			'organizationIds' => [$this->ID_ORG], 
			'priceCategoryId' => null, 
			'version' => 2
		];
		$res = iiko_get_info($url,$headers,$params);		
		return $res;
	}

}

?>