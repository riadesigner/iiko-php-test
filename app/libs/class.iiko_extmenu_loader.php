<?php

if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 	ПОЛУЧАЕМ ВНЕШНЕЕ МЕНЮ ИЗ IIKO v-1.0.1
 * 
*/
class Iiko_extmenu_loader{

	private string $ID_ORG;
	private string $IIKO_API_KEY;
	private string $EXTERNAL_MENU_ID;
	private array $DATA;
	private string $TOKEN;
	private string $INFO;
	
	/**
	 * @param <string> $id_org / required
	 * @param <string> $iiko_api_key / required
	 * @param <string> $externalMenuId / required
	 * @return <Iiko_extmenu_loader>
	 * 
	*/
	function __construct(string $id_org, string $iiko_api_key="", $externalMenuId = ""){
		$this->ID_ORG = $id_org;
		$this->IIKO_API_KEY = $iiko_api_key;	
		$this->EXTERNAL_MENU_ID = $externalMenuId;
		return $this;
	}

	public function reload(): void{		

		if(empty($this->TOKEN)){ $this->TOKEN = $this->reload_token(); }
		$this->DATA = $this->load_extmenu();
		// collecting a meta information about the datamenu
		$this->INFO = $this->calc_meta_info($this->DATA);
	}

	public function get_data(): array{
		return $this->DATA;
	}

	public function get_info(): string{
		return $this->INFO;
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
		if(!isset($res['token'])){
			glogError(print_r($res,1));
			$errMessage = $res['errorDescription']??"";
			die("неправильный API KEY! ".$this->IIKO_API_KEY.",<br> ".$errMessage);		
		}	
		return $res['token'];
	}

	private function load_extmenu(): array{
		// --------------------------------------------
		// получаем все меню с ценовыми категориями
		// TODO: надо сделать выбор в админке на случай, 
		// если есть ценовые категории
		// --------------------------------------------
		$url     = 'api/2/menu';
		$headers = [
			"Content-Type"=>"application/json",
			"Authorization" => 'Bearer '.$this->TOKEN
		]; 
		$params  = [];
		$res = iiko_get_info($url,$headers,$params);
		$priceCategories = $res['priceCategories']??[];
		$currentPriceCategory = count($priceCategories)>0 ? $priceCategories[0]['id'] : null;		
		
		glog('получаем все меню с ценовыми категориями'.print_r($res,1)); 
		glog('currentPriceCategory = '.$currentPriceCategory);

		// ----------------------------------------------------
		// получаем Меню по его Id с Базовой ценовой категорией
		// ----------------------------------------------------
		$url     = 'api/2/menu/by_id';
		$headers = [
			"Content-Type"=>"application/json",
			"Authorization" => 'Bearer '.$this->TOKEN
		]; 
		$params  = [
			'externalMenuId' => $this->EXTERNAL_MENU_ID,
			'organizationIds' => [$this->ID_ORG], 
			"version" => 2,
			"startRevision"=>0,
		];	

		if($currentPriceCategory!==null){
			$params['priceCategoryId'] = $currentPriceCategory;
		}

		$res = iiko_get_info($url,$headers,$params);
		
		if(empty($res)){						
			$errMessage = "не удалось загрузить меню. не хватило памяти";
			glogError($errMessage);
			die($errMessage);
		}
		return $res;
	}

	private function calc_meta_info(array $iiko_response): string{		
		// размер исходных данных от iiko:
		$size_iiko = strlen(serialize($iiko_response)); 
		$size_iiko = round($size_iiko / 1024 / 1024 ) . " MB";
		$vars_iiko = $this->count_recursive($iiko_response);
		$infoMsg = "Размер исходных данных от iiko: ~" . $size_iiko . ", ";
		$infoMsg .= 'Переменных в исходных данных от iiko: ' . $vars_iiko;
		return $infoMsg;
	}

	// Для глубокого подсчёта переменных в res from iiko:
	private function count_recursive(array $arr) {
		$count = 0;
		array_walk_recursive($arr, function() use (&$count) {
			$count++;
		});
		return $count;
	}

}

?>