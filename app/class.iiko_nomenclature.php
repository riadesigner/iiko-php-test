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
	
	function __construct(string $id_org, string $iiko_api_key=""){
		$this->ID_ORG = $id_org;
		$this->IIKO_API_KEY = $iiko_api_key;
		return $this;
	}

	public function reload(): void{
		
	}
}

?>