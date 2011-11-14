<?php 


/**
 * Блокирани е-мейли
 */
class blast_Blocked extends core_Manager
{
	

    /**
     *  Заглавие на таблицата
     */
    var $title = "Блокирани е-мейли";
    
    
    /**
     * Права
     */
    var $canRead = 'admin, blast';
    
    
    /**
     *  
     */
    var $canEdit = 'admin, blast';
    
    
    /**
     *  
     */
    var $canAdd = 'admin, blast';
    
    
    /**
     *  
     */
    var $canView = 'admin, blast';
    
    
    /**
     *  
     */
    var $canList = 'admin, blast';
    
    /**
     *  
     */
    var $canDelete = 'admin, blast';
    
	
	/**
	 * 
	 */
	var $canBlast = 'admin, blast';
	
    
    /**
     * 
     */
	var $loadList = 'blast_Wrapper';
       	
	
	/**
	 * Описание на модела
	 */
	function description()
	{
		$this->FLD('mail', 'email', 'caption=Е-майл');
		//$this->FLD('list', 'key(mvc=blast_Lists, select=title)', 'caption=Лист');
		
		$this->setDbUnique('mail');
	}
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
}
?>