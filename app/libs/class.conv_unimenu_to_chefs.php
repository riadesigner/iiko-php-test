<?php
/**
 * ПРЕОБРАЗУЕМ ФОРМАТ UNIMENU В CHEFSMENU
 * 
 * */

class Conv_unimenu_to_chefs {
    
    private array $UNIMENU;    
    private array $DATA;    


	function __construct(array $unimenu){				
        $this->UNIMENU = $unimenu;
		return $this;
	}

    public function convert(): Conv_unimenu_to_chefs {
        $this->DATA = $this->UNIMENU;
        return $this;
    }

    public function get_data(): array {
        return $this->DATA;
    }

}




?>