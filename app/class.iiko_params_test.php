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
class Iiko_params_test{
	private int $id_cafe;
	private string $API_KEY;
	private array $iiko_params;		
	private string $token; 
	private array $ROUGH_DATA;
	private string $switch_to_current_organization_id;
	private string $current_organization_id;
	private string $current_extmenu_id;
	private string $current_terminal_group_id;
	private string $current_oldway_menu_id;	
	private string $current_terminal_group_status;	
	private array $arr_organizations;
	private array $arr_extmenus;
	private array $all_terminal_groups;
	private array $arr_terminals;
	private array $arr_tables;
	private array $arr_order_types;

	function __construct(int $id_cafe, string $iiko_api_key){
		if( !$id_cafe || !$this->check_iiko_key($iiko_api_key) ){
			throw new Exception("not valid iiko api key"); 
		}	
		$this->id_cafe = $id_cafe;	
		$this->API_KEY = $iiko_api_key;
		// read iiko params from db
		$this->iiko_params = [];
		return $this;
	}

	public function reload(string $new_current_organization_id = ""): bool{	
		$this->switch_to_current_organization_id = $new_current_organization_id;
		$this->ROUGH_DATA = [];
		$this->read_organizations_info();
		$this->read_extmenus_info();
		$this->read_terminals_info();
		$this->read_iiko_tables_info();
		$this->read_order_types();	
		$this->read_status_current_terminal_group();	
		glog("-- IIKO LOADED ROUGH_DATA -- \n".print_r($this->ROUGH_DATA,1));
						
		return $this->update_db();		
	}

	public function get(): array{
		return $this->iiko_params;
	}

	public function get_token(): string{
		return $this->token;
	}
	
	// return info about current organization 
	// works only after $this->reload()!
	// because $current_organization_id not init in __construct
	public function get_current_organization(): array{
		$currentOrganization = null;
		$id = $this->iiko_params["current_organization_id"];
		foreach($this->arr_organizations as $org){
			if($org["id"]===$id){
				$currentOrganization = $org;
				break;
			}
		}
		return $currentOrganization;
	}

	public function set_current_oldway_menu_id(string $id): void{
		$this->current_oldway_menu_id = $id;
		$this->iiko_params["current_oldway_menu_id"] = $id;	
	}
	public function set_current_terminal_group_id(string $id): void{
		$this->current_terminal_group_id = $id;
		$this->iiko_params["current_terminal_group_id"] = $id;
	}
	public function set_current_extmenu_id(string $id): void{
		$this->current_extmenu_id = $id;
		$this->iiko_params["current_extmenu_id"] = $id;	
	}	
	public function read_status_current_terminal_group():void {					

		$url     = 'api/1/terminal_groups/is_alive';
		$headers = [
			"Content-Type"=>"application/json",
			"Authorization" => 'Bearer '.$this->token
		]; 
		$params  = [
			"organizationIds" => [$this->current_organization_id],
			"terminalGroupIds" => [$this->current_terminal_group_id],
		];
		$res = iiko_get_info($url,$headers,$params);
		
		if(!isset($res["isAliveStatus"]) || !count($res["isAliveStatus"])) {
			glogError(print_r($res,1));	
			$this->current_terminal_group_status = "Unknown";
		}else{
			$this->current_terminal_group_status = $res["isAliveStatus"][0]["isAlive"];
		}
		
		$this->ROUGH_DATA["STATUS_CURRENT_TERMINAL_GROUP"] = $res;

	}

	// PRIVATE FUNCS
	private function update_db(): bool{
		
		$this->iiko_params["organizations"] = json_encode($this->arr_organizations, JSON_UNESCAPED_UNICODE);			
		$this->iiko_params["terminal_groups"] = json_encode($this->arr_terminals, JSON_UNESCAPED_UNICODE);	
		$this->iiko_params["tables"] = json_encode($this->arr_tables, JSON_UNESCAPED_UNICODE);	
		$this->iiko_params["order_types"] = json_encode($this->arr_order_types, JSON_UNESCAPED_UNICODE);	
		if($this->arr_extmenus && count($this->arr_extmenus)){
			$this->iiko_params["extmenus"] = json_encode($this->arr_extmenus, JSON_UNESCAPED_UNICODE);	
			$this->iiko_params["current_extmenu_id"] = $this->current_extmenu_id;
		}		
		$this->iiko_params["current_organization_id"] = $this->current_organization_id;
		$this->iiko_params["current_terminal_group_id"] = $this->current_terminal_group_id;	
		$this->iiko_params["current_terminal_group_status"] = $this->current_terminal_group_status;	
			
		$this->iiko_params["rough_data"] = json_encode($this->ROUGH_DATA, JSON_UNESCAPED_UNICODE);	
		
		$this->iiko_params["oldway_menus"] = "";
		$this->iiko_params["current_oldway_menu_id"]="";
		$this->iiko_params["nomenclature_mode"] = 0;

		return $this->save();
	}

	// public function save(): bool {
	// 	$this->iiko_params["updated_date"] = 'now()';
	// 	return $this->iiko_params->save();
	// }
	
	private function check_iiko_key(string $api_key):bool{
		glog("check_iiko_key: ".$api_key);
		if(empty($api_key)) {
			glogError("empty api key");	
			return false;
		}
		$url     = 'api/1/access_token';
		$headers = ["Content-Type"=>"application/json"];
		$params  = ["apiLogin" => $api_key];
		$res = iiko_get_info($url,$headers,$params);
		if( isset($res["errorDescription"]) ) {
			if(str_contains((string) $res["errorDescription"], "is not authorized")){				
				glogError("--unknown login");	
				return false;
			}else{
				glogError(print_r($res,1));
				return false;
			}		
		}
	
		if(!isset($res["token"])){
			glogError(print_r($res,1));
			return false;
		}else{
			$this->token = $res["token"];			
		}
	
		return true;
	}

	// private function read_params_for_cafe(int $id_cafe): Smart_object{		
	// 	$iiko_params_collect = new Smart_collect("iiko_params", "where id_cafe='".$id_cafe."'");
	// 	if($iiko_params_collect->full()){
	// 		return $iiko_params_collect->get(0);			
	// 	}else{
	// 		$iiko_params = new Smart_object("iiko_params");
	// 		$iiko_params->id_cafe = $id_cafe;		
	// 		if(!$iiko_params->save()) throw new Exception("Something wrong. Can`t saving iiko params");			
	// 		return $iiko_params;
	// 	}		
	// }

	private function read_organizations_info(): void{
		$this->arr_organizations = $this->iiko_get_organizations_info($this->token);
		if(!count($this->arr_organizations))throw new Exception("--unknown organization");
		if(!$this->switch_to_current_organization_id){
			$this->current_organization_id = $this->arr_organizations[0]["id"];	
		}else{
			foreach($this->arr_organizations as $organization){
				if($organization["id"] == $this->switch_to_current_organization_id){
					$this->current_organization_id = $organization["id"];
					break;
				}
			}
		}
		$this->ROUGH_DATA["ORGANIZATIONS"] = $this->arr_organizations;			
	}	

	private function read_extmenus_info(): void{
		$this->arr_extmenus = $this->iiko_get_extmenus_info($this->token);	
		if(count($this->arr_extmenus)){
			$this->ROUGH_DATA["EXTERNALMENUS"] = $this->arr_extmenus;	
			$this->current_extmenu_id = $this->calc_new_current_extmenu_id($this->arr_extmenus, $this->iiko_params["current_extmenu_id"]);
		}		
	}	

	private function read_iiko_tables_info(): void{
		$arrTerminalGroupsIds = [];
		foreach($this->arr_terminals as $terminalGroup){
			array_push($arrTerminalGroupsIds, $terminalGroup["id"]);
		}
		$restaurantSections = $this->iiko_get_table_sections_info($this->token, $arrTerminalGroupsIds);				
		$this->ROUGH_DATA["TABLES"] = $restaurantSections;	
		$this->arr_tables = $this->iiko_parse_tables_res($restaurantSections);		
	}

	private function read_terminals_info(): void{
		$this->all_terminal_groups = $this->iiko_get_terminal_groups_info($this->token, $this->current_organization_id);	
		if(!count($this->all_terminal_groups))throw new Exception("--has not terminal groups");
		$this->ROUGH_DATA["TERMINALS"] = $this->all_terminal_groups;	
	
		// GET TERMINAL GROUPS FOR CURRENT ORGANIZATION
		$this->arr_terminals = $this->all_terminal_groups[0]['items'];
		if(!count($this->arr_terminals)) throw new Exception("--has not actual terminals");
		$this->current_terminal_group_id = $this->arr_terminals[0]['id'];

	}	

	private function iiko_parse_tables_res(array $restaurantSections): array{
		$arr = [];	
		if(is_array($restaurantSections) && count($restaurantSections)){
			foreach($restaurantSections as $section){
				array_push($arr,[
					'section_name'=>$section['name'],
					'section_id'=>$section['id'],
					'terminalGroupId'=>$section['terminalGroupId'],
					'tables'=>$section['tables']
				]);			
			}
		}
		return $arr;
	}

	private function read_order_types(): void{
		$the_order_types = $this->iiko_get_order_types_info($this->token, $this->current_organization_id);
		if(!count($the_order_types)) throw  new Exception("--cant receive order types");		
		$this->arr_order_types = $the_order_types[0]['items'];	
		$ROUGH_DATA["ORDER_TYPES"] = $the_order_types;		
	}

	private function iiko_get_organizations_info($token): array {
		$url     = 'api/1/organizations';
		$headers = [
			"Content-Type"=>"application/json",
			"Authorization" => 'Bearer '.$token
		]; 
		$params  = ['organizationIds' => null, 'returnAdditionalInfo' => true, 'includeDisabled' => true];
		$res = iiko_get_info($url,$headers,$params);
		return $res["organizations"] ?? [];
	}

	private function iiko_get_order_types_info(string $token, string $orgId): array {		
		$url     = 'api/1/deliveries/order_types';
		$headers = [
			"Content-Type"=>"application/json",
			"Authorization" => 'Bearer '.$token
		]; 
		$params  = ['organizationIds' => [$orgId]];
		$res = iiko_get_info($url,$headers,$params);
		return $res['orderTypes'] ?? [];
	}	

	private function iiko_get_extmenus_info($token): array {
		$url     = 'api/2/menu';
		$headers = [
			"Content-Type"=>"application/json",
			"Authorization" => 'Bearer '.$token
		]; 
		$params  = [];
		$res = iiko_get_info($url,$headers,$params);
		return $res["externalMenus"] ?? [];
	}	

	private function iiko_get_table_sections_info(string $token, array $arrTerminalGroupIds): array {		
		$url     = 'api/1/reserve/available_restaurant_sections';
		$headers = [
			"Content-Type"=>"application/json",
			"Authorization" => 'Bearer '.$token
		]; 
		$params  = ['terminalGroupIds' => [...$arrTerminalGroupIds], 'returnSchema' => true];				
		$res = iiko_get_info($url,$headers,$params);					
		return $res['restaurantSections'] ?? [];
	}

	private function iiko_get_terminal_groups_info($token, $orgId): array {
		$url     = 'api/1/terminal_groups';
		$headers = [
			"Content-Type"=>"application/json",
			"Authorization" => 'Bearer '.$token
		]; 
		$params  = [
			'organizationIds' => [$orgId], 
			'includeDisabled' => true
		];
		$res = iiko_get_info($url,$headers,$params);
		return $res["terminalGroups"] ?? [];
	}

	private function calc_new_current_extmenu_id(array $arr_extmenus, string $saved_current_extmenu_id): string{
		
		$new_current_extmenu_id = "";

		if(count($arr_extmenus)>0){		
			if(!empty($saved_current_extmenu_id)){			
				$filteredArray = array_filter($arr_extmenus, function ($m) {	
					global $saved_current_extmenu_id;				
					return $m['id'] === $saved_current_extmenu_id;
				});
				if(count($filteredArray)>0){
					// оставляем как есть 
					$new_current_extmenu_id = $saved_current_extmenu_id;
				}else{
					// берем первое меню из списка
					$new_current_extmenu_id = $arr_extmenus[0]["id"];
				}			
			}else{
				// берем первое меню из списка
				$new_current_extmenu_id = $arr_extmenus[0]["id"];
			}
		}

		return $new_current_extmenu_id;
	}

}

?>