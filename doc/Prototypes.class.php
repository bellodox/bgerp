<?php



/**
 * Клас 'doc_Prototypes' - Модел за шаблонни документи
 *
 *
 * @category  bgerp
 * @package   doc
 * @author    Ivelin Dimov <ivelin_pdimov@abv.bg>
 * @copyright 2006 - 2016 Experta OOD
 * @license   GPL 3
 * @since     v 0.1
 */
class doc_Prototypes extends core_Manager
{
    
    
    /**
     * Плъгини за зареждане
     */
    public $loadList = 'plg_RowTools2,plg_Created,plg_Modified,doc_Wrapper,plg_Rejected';


    /**
     * Заглавие
     */
    public $title = "Шаблонни документи";
    
    
    /**
     * Наименование на единичния обект
     */
    public $singleTitle = "Шаблонен документ";
    
    
    /**
     * Полета, които ще се показват в листов изглед
     */
    public $listFields = "docId,title,driverClassId,sharedWithRoles,sharedWithUsers,state,createdOn,createdBy,modifiedOn,modifiedBy";
    
    
    /**
     * Кой може да разглежда
     */
    public $canList = 'admin';
    
    
    /**
     * Кой може да добавя
     */
    public $canAdd  = 'officer';
    
    
    /**
     * Кой може да редактира
     */
    public $canEdit  = 'officer';
    
    
    /**
     * Кой може да редактира
     */
    public $canDelete  = 'no_one';
    
    
    /**
     * Кой може да оттегля
     */
    public $canReject  = 'no_one';
    
    
    /**
     * Кой може да възстановява
     */
    public $canRestore  = 'no_one';
    
    
    /**
     * Описание на модела (таблицата)
     */
    function description()
    {
    	$this->FLD('title', 'varchar', 'caption=Заглавие,mandatory');
    	$this->FLD('originId', 'key(mvc=doc_Containers)', 'caption=Документ,mandatory,input=hidden,silent');
    	$this->FLD('classId', 'class(interface=doc_PrototypeSourceIntf)', 'caption=Документ,mandatory,input=hidden,silent');
    	$this->FLD('docId', 'int', 'caption=Документ,mandatory,input=hidden,silent');
    	$this->FLD('driverClassId', 'class', 'caption=Документ,input=hidden');
    	$this->FLD('sharedWithRoles', 'keylist(mvc=core_Roles,select=role,groupBy=type,orderBy=orderByRole)', 'caption=Споделяне->Роли');
    	$this->FLD('sharedWithUsers', 'userList', 'caption=Споделяне->Потребители');
    	$this->FLD('fields', 'blob(serialize, compress)', 'input=none');
    	$this->FLD('state', 'enum(active=Активирано,rejected=Оттеглено)','caption=Състояние,column=none,input=none,notNull,value=active');
    	
    	$this->setDbUnique('classId,docId,title');
    	$this->setDbUnique('originId');
    	$this->setDbUnique('classId,docId');
    	$this->setDbIndex('classId,docId,driverClassId');
    }
    
    
    /**
     * Изпълнява се след подготовката на ролите, които могат да изпълняват това действие
     */
    public static function on_AfterGetRequiredRoles($mvc, &$requiredRoles, $action, $rec = NULL, $userId = NULL)
    {
    	// Кога може да се добавя
    	if($action == 'add' && isset($rec)){
    		if(isset($rec->originId)){
    			$doc = doc_Containers::getDocument($rec->originId);
    			
    			// Документа трябва да има нужния интерфейс
    			if(!$doc->haveInterface('doc_PrototypeSourceIntf')){
    				$requiredRoles = 'no_one';
    			} else {
    				
    				// Да няма шаблон и да не е направил запис в журнала
    				if($mvc->fetch("#originId = {$rec->originId}")){
    					$requiredRoles = 'no_one';
    				} elseif(acc_Journal::fetchByDoc($doc->getClassId(), $doc->that)){
    					$requiredRoles = 'no_one';
    				}
    			}
    		} else {
    			
    			// Ако няма ориджин не може
    			$requiredRoles = 'no_one';
    		}
    	}
    	
    	// Кога може да се добавя и редактира
    	if(($action == 'add' || $action == 'edit') && isset($rec->originId)){
    		if($requiredRoles != 'no_one'){
    			$doc = doc_Containers::getDocument($rec->originId);
    			
    			// Трябва потребителя да има достъп до документа
    			if(!$doc->haveRightFor('single')){
    				$requiredRoles = 'no_one';
    				
    				// И документа да не е оттеглен
    			} elseif($doc->fetchField('state') == 'rejected'){
    				$requiredRoles = 'no_one';
    			}
    		}
    	}
    }
    
    
    /**
     * Извиква се след подготовката на toolbar-а за табличния изглед
     */
    public static function on_AfterPrepareListToolbar($mvc, &$data)
    {
    	if(!empty($data->toolbar->buttons['btnAdd'])){
    		$data->toolbar->removeBtn('btnAdd');
    	}
    }
    
    
    /**
     * Преди показване на форма за добавяне/промяна
     */
    public static function on_AfterPrepareEditForm($mvc, &$data)
    {
    	$form = $data->form;
    	expect($origin = doc_Containers::getDocument($form->rec->originId));
    	
    	$form->setDefault('title', $origin->getTitleById());
    	$form->setDefault('classId', $origin->getClassId());
    	$form->setDefault('docId', $origin->that);
    	
    	// Попълване на полето за драйвер за по-бързо търсене
    	if($origin->getInstance() instanceof embed_Manager){
    		$form->setDefault('driverClassId', $origin->fetchField($origin->driverClassField));
    	} elseif($origin->getInstance() instanceof core_Embedder){
    		$form->setDefault('driverClassId', $origin->fetchField($origin->innerClassField));
    	}
    }
    
    
    /**
     * Изпълнява се след създаване на нов запис
     */
    public static function on_AfterCreate($mvc, $rec)
    {
    	// След като се създаде шаблон, оригиналния документ минава в състояние шаблон
    	$nRec = (object)array('id' => $rec->docId, 'state' => 'template');
    	cls::get($rec->classId)->save($nRec, 'state');
    }
    
    
    /**
     * След преобразуване на записа в четим за хора вид
     */
    public static function on_AfterRecToVerbal($mvc, &$row, $rec, $fields = array())
    {
    	if(isset($fields['-list'])){
    		$row->docId = doc_Containers::getDocument($rec->originId)->getLink(0);
    		$row->ROW_ATTR['class'] = "state-{$rec->state}";
    	}
    }
    
    
    /**
     * Синхронизиране на шаблона с оригиналния документ
     * 
     * @param int $containerId - ид на контейнер
     */
    public static function sync($containerId)
    {
    	if(!$rec = self::fetch(array("#originId = [#1#]", $containerId))) return;
    	
    	$origin = doc_Containers::getDocument($containerId);
    	
    	// Ако оригиналния документ се оттегли, оттегля се и шаблона
    	$newState = ($origin->fetchField('state') == 'rejected') ? 'rejected' : 'active';
    	self::save((object)array('id' => $rec->id, 'state' => $newState), 'state');
    }
    
    
    /**
     * Намира наличните шаблони за документа
     * 
     * @param mixed $class  - документ
     * @param mixed $driver - драйвер, ако има
     * @return array $arr   - намерените шаблони
     */
    public static function getPrototypes($class, $driver = NULL)
    {
    	$arr = array();
    	$Class = cls::get($class);
    	
    	// Намират се всички активни шаблони за този клас/драйвер
    	$query = self::getQuery();
    	$condition = "#classId = {$Class->getClassId()} AND #state != 'rejected'";
    	if(isset($driver)){
    		$Driver = cls::get($driver);
    		$condition .= " AND #driverClassId = '{$Driver->getClassId()}'";
    	}
    	
    	$query->where($condition);
    	
    	$cu = core_Users::getCurrent();
    	
    	// Ако потребителя не е 'ceo'
    	if(!haveRole('ceo', $cu)){
    		
    		// Търсят се само шаблоните, които не са споделени с никой
    		$where = "(#sharedWithRoles IS NULL AND #sharedWithUsers IS NULL)";
    		
    		// или са споделени с текущия потребител
    		$where .= " OR LOCATE('|{$cu}|', #sharedWithUsers)";
    		
    		// или са споделени с някоя от ролите му
    		$userRoles = core_Users::fetchField($cu, 'roles');
    		$userRoles = keylist::toArray($userRoles);
    		foreach ($userRoles as $roleId){
    			$where .= " OR LOCATE('|{$roleId}|', #sharedWithRoles)";
    		}
    		
    		// Добавяне на ограниченията към заявката
    		$query->where($where);
    	}
    	
    	// Ако има записи, се връщат ид-та на документите
    	while($rec = $query->fetch()){
    		$arr[$rec->docId] = $rec->title;
    	}
    	
    	// Връщане на намерените шаблони
    	return $arr;
    }
}