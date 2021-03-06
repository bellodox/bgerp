<?php



/**
 * Помощен клас-имплементация на интерфейса import_DriverIntf
 *
 * @category  bgerp
 * @package   planning
 * @author    Ivelin Dimov <ivelin_pdimov@abv.com>
 * @copyright 2006 - 2017 Experta OOD
 * @license   GPL 3
 * @since     v 0.1
 * @title     Импорт на артикули от задачи
 */
class planning_interface_ImportTaskProducts extends import_drivers_Proto 
{
    
 
	/**
	 * Към кои класове може да се добавя драйвера
	 *
	 * @var string - изброените класове или празен клас за всички
	 */
    protected $allowedClasses = 'planning_ReturnNoteDetails,planning_ConsumptionNoteDetails';
    
    
    /**
     * Кой може да избира драйвъра
     */
    protected $canSelectDriver = 'ceo,planning,store';
    
    
    /**
     * Интерфейси, поддържани от този мениджър
     */
    public $interfaces = 'import_DriverIntf';
    
    
    /**
     * Заглавие
     */
    public $title = "Импорт на артикули от задачи";
    
    
    /**
     * Добавя специфични полета към формата за импорт на драйвера
     *
     * @param core_Manager $mvc
     * @param core_FieldSet $form
     * @return void
     */
    public function addImportFields($mvc, core_FieldSet $form)
    {
    	$rec = &$form->rec;
    	$masterRec = $mvc->Master->fetch($rec->{$mvc->masterKey});
    	$form->info = tr('Засклаждане в') . " " . store_Stores::getHyperlink($masterRec->storeId, TRUE);
    	$details = self::getProductsFromTasks($masterRec->threadId, $masterRec->storeId, $mvc->taskActionLoad);
    	
    	// Всички документи в нишката, които са активни
    	$cQuery = doc_Containers::getQuery();
    	$cQuery->where("#threadId = {$masterRec->threadId} AND #state = 'active'");
    	$containers = arr::extractValuesFromArray($cQuery->fetchAll(), 'id');
    	
    	$combinedDetails = array();
    	
    	// Събиране на к-та на артикулите
    	foreach ($details as $dRec){
    		$dRec->caption = cat_Products::getTitleById($dRec->productId);
    		$dRec->caption = str_replace(',', ' ', $dRec->caption);
    		$batch = '';
    		
    		$key = "{$dRec->productId}+{$dRec->packagingId}";
    		
    		$dRec->selectedByNow = 0;
    		if(core_Packs::isInstalled('batch')){
    			$Def = batch_Defs::getBatchDef($dRec->productId);
    			
    			// Ако има партидност и тя е от определен тип
    			if(is_object($Def) && $Def->getClassId() == batch_definitions_Varchar::getClassId()){
    			
    				// Стойноста на партидата ще е задачата
    				$taskId = (!empty($dRec->taskId)) ? $dRec->taskId : $dRec->id;
    				$dRec->batch = planning_Tasks::getBatchName($taskId);
    				$key .= "+{$dRec->batch}";
    				$dRec->caption .= " / |*<i>{$dRec->batch}</i>";
    				 
    				// Колко е изпълнено досега
    				if(count($containers)){
    					$bQuery = batch_BatchesInDocuments::getQuery();
    					$bQuery->XPR('sumQuantity', 'double', 'SUM(#quantity)');
    					$bQuery->in("containerId", $containers);
    					$bQuery->where("#productId = {$dRec->productId} AND #batch = '{$dRec->batch}' AND #storeId = {$masterRec->storeId} AND #operation = '{$mvc->batchMovementDocument}'");
    					$bQuery->show('sumQuantity');
    					$dRec->selectedByNow = $bQuery->fetch()->sumQuantity;
    				}
    			}
    		}
    	
    		// Ако няма партиди гледа се колко е изпълнено досега
    		if(empty($dRec->selectedByNow) && count($containers)){
    			$dQuery = $mvc->getQuery();
    			$dQuery->XPR('sumQuantity', 'double', 'SUM(#quantity)');
    			$dQuery->EXT('storeId', $mvc->Master->className, "externalName=storeId,externalKey={$mvc->masterKey}");
    			$dQuery->EXT('state', $mvc->Master->className, "externalName=state,externalKey={$mvc->masterKey}");
    			$dQuery->EXT('containerId', $mvc->Master->className, "externalName=containerId,externalKey={$mvc->masterKey}");
    			$dQuery->in("containerId", $containers);
    			$dQuery->where("#productId = {$dRec->productId} AND #storeId = {$masterRec->storeId}");
    			$dQuery->show('sumQuantity');
    			$dRec->selectedByNow = $dQuery->fetch()->sumQuantity;
    		}
    		
    		if(array_key_exists($key, $combinedDetails)){
    			$combinedDetails[$key]->quantity += $dRec->quantity;
    		} else {
    			$combinedDetails[$key] = $dRec;
    		}
    	}
    	
    	// Показване на обединените полета
    	foreach ($combinedDetails as $key => $cRec){
    		$defaultQuantity = ($cRec->quantity - $cRec->selectedByNow) / $cRec->quantityInPack;
    		
    		$shortUom = cat_UoM::getShortName($cRec->packagingId);
    		$form->FLD($key, "double(Min=0)","input,caption={$cRec->caption}->К-во,unit={$shortUom}");
    		if($defaultQuantity > 0){
    			$form->setDefault($key, $defaultQuantity);
    		}
    		 
    		$rec->detailsDef[$key] = $cRec;
    	}
    }
    
    
    /**
     * Връща записите, подходящи за импорт в детайла.
     * Съответстващия 'importRecs' метод, трябва да очаква
     * същите данни (@see import_DestinationIntf)
     * 
     * @see import_DriverIntf
	 * @param array $recs
	 * 		o productId        - ид на артикула
     * 		o quantity         - к-во в основна мярка
     * 		o quantityInPack   - к-во в опаковка
     * 		o packagingId      - ид на опаковка
     * 		o batch            - дефолтна партида, ако може
     * 		o notes            - забележки
     * 		o $this->masterKey - ид на мастър ключа
	 * 
	 * @return void
     */
    public function getImportRecs(core_Manager $mvc, $rec)
    {
    	$recs = array();
    	if(!is_array($rec->detailsDef)) return $recs;
    	foreach ($rec->detailsDef as $key => $dRec){

    		// Ако има въведено количество записва се
    		if(!empty($rec->{$key})){
    			unset($dRec->id);
    			$dRec->quantity = $rec->{$key} * $dRec->quantityInPack;
    			$dRec->noteId = $rec->{$mvc->masterKey};
    			$dRec->isEdited = TRUE;
    			$recs[] = $dRec;
    		}
    	}
    	
    	return $recs;
    }
    
    
    /**
     * Връща артикулите, които са вложени/произведени по задачи към документа
     * 
     * @param int $threadId   - ид на тред
     * @param int $storeId    - ид на склад
     * @param string $type    - тип на операцията
     * @param int|NULL $limit - лимит
     * @return void            
     */
    private static function getProductsFromTasks($threadId, $storeId, $type, $limit = NULL)
    {
    	$originId = doc_Threads::getFirstContainerId($threadId);
    	$dQuery = planning_ProductionTaskProducts::getQuery();
    	$dQuery->EXT('originId', 'planning_Tasks', 'externalName=originId,externalKey=taskId');
    	$dQuery->EXT('canStore', 'cat_Products', 'externalName=canStore,externalKey=productId');
    	$dQuery->EXT('state', 'planning_Tasks', 'externalName=state,externalKey=taskId');
    	$dQuery->XPR('quantity', 'double', '#totalQuantity');
    	$dQuery->where("#originId = {$originId} AND #canStore = 'yes' AND #storeId = {$storeId} AND #totalQuantity != 0 AND #type = '{$type}' AND (#state = 'active' || #state = 'closed' || #state = 'wakeup')");
    	$dQuery->show('productId,quantityInPack,packagingId,taskId,quantity,taskId');
  		if(isset($limit)){
    		$dQuery->limit($limit);
    	}	
    	
    	if($type != 'input'){
    		$tQuery = planning_Tasks::getQuery();
    		$tQuery->EXT('canStore', 'cat_Products', 'externalName=canStore,externalKey=productId');
    		$tQuery->XPR('quantity', 'double', '#totalQuantity');
    		$tQuery->where("#originId = {$originId} AND #canStore = 'yes' AND #storeId = {$storeId} AND #totalQuantity != 0 AND (#state = 'active' || #state = 'closed' || #state = 'wakeup')");
    		$tQuery->show('productId,quantityInPack,packagingId,quantity,id,storeId');
    		 
    		if(isset($limit)){
    			$tQuery->limit($limit);
    		}
    		 
    		$res = array_merge($dQuery->fetchAll(), $tQuery->fetchAll());
    	} else {
    		$res = $dQuery->fetchAll();
    	}
    	
    	return $res;
    }
    
    
    /**
     * Може ли драйвера за импорт да бъде избран
     *
     * @param core_Manager $mvc - клас в който ще се импортира
     * @param int|NULL $userId  - ид на потребител
     * @return boolean          - може ли драйвера да бъде избран
     */
    public function canSelectDriver(core_Manager $mvc, $rec, $userId = NULL)
    {
    	$result = parent::canSelectDriver($mvc, $rec, $userId);
    	if($result === TRUE){
    		$masterRec = $mvc->Master->fetchRec($rec);
    		$foundRecs = self::getProductsFromTasks($masterRec->threadId, $masterRec->storeId, $mvc->taskActionLoad, 1);
    		if(!count($foundRecs)) return FALSE;
    	}
    	
    	return $result;
    }
}