<?php



/**
 * Мениджър на Задания за производство
 *
 *
 * @category  bgerp
 * @package   planning
 * @author    Ivelin Dimov <ivelin_pdimov@abv.bg>
 * @copyright 2006 - 2015 Experta OOD
 * @license   GPL 3
 * @since     v 0.1
 * @title     Задания за производство
 */
class planning_Jobs extends core_Master
{
    
    
	/**
	 * За конвертиране на съществуващи MySQL таблици от предишни версии
	 */
	public $oldClassName = 'mp_Jobs';
	
	
	/**
     * Интерфейси, поддържани от този мениджър
     */
    public $interfaces = 'doc_DocumentIntf';
    
    
    /**
     * Заглавие
     */
    public $title = 'Задания за производство';
    
    
    /**
     * Единично заглавие
     */
    public $singleTitle = 'Задание за производство';
    
    
    /**
     * Абревиатура
     */
    public $abbr = 'Job';
    
    
    /**
     * Плъгини за зареждане
     */
    public $loadList = 'plg_RowTools, doc_DocumentPlg, planning_Wrapper, doc_ActivatePlg, acc_plg_DocumentSummary, plg_Search, doc_SharablePlg';
    
    
    /**
     * Кой има право да чете?
     */
    public $canRead = 'ceo, planning';
    
    
    /**
     * Кой има право да променя?
     */
    public $canEdit = 'ceo, planning';
    
    
    /**
     * Кой има право да добавя?
     */
    public $canAdd = 'ceo, planning';
    
    
    /**
     * Кой може да променя състоянието?
     */
    public $canChangestate = 'ceo, planning';
    
    
    /**
	 * Кой може да го разглежда?
	 */
	public $canList = 'ceo, planning';
	
	
	/**
	 * Кой може да разглежда сингъла на документите?
	 */
	public $canSingle = 'ceo, planning';
    
    
	/**
	 * Полета за търсене
	 */
	public $searchFields = 'folderId, productId, notes, saleId, deliveryPlace, storeId';
	
	
	/**
     * Икона на единичния изглед
     */
    public $singleIcon = 'img/16/clipboard_text.png';
    
    
	/**
     * Дали може да бъде само в началото на нишка
     */
    public $onlyFirstInThread = TRUE;
    
    
    /**
     * Полета, които ще се показват в листов изглед
     */
    public $listFields = 'id=№, productId=Артикул, dueDate, quantity, state, createdOn, createdBy';
    
    
    /**
     * Шаблон за единичен изглед
     */
    public $singleLayoutFile = 'planning/tpl/SingleLayoutJob.shtml';
    
    
    /**
     * Поле за дата по което ще филтрираме
     */
    public $filterDateField = 'dueDate';
    
    
	/**
     * Описание на модела (таблицата)
     */
    function description()
    {
    	$this->FLD('productId', 'key(mvc=cat_Products,select=name)', 'silent,mandatory,caption=Артикул');
    	$this->FLD('dueDate', 'date(smartTime)', 'caption=Падеж,mandatory');
    	$this->FLD('quantity', 'double(decimals=2)', 'caption=Количество,mandatory,silent');
    	$this->FLD('notes', 'richtext(rows=3)', 'caption=Забележки');
    	$this->FLD('tolerance', 'percent', 'caption=Толеранс');
    	$this->FLD('deliveryTermId', 'key(mvc=cond_DeliveryTerms,select=codeName,allowEmpty)', 'caption=Данни от договора->Условие');
    	$this->FLD('deliveryDate', 'date(smartTime)', 'caption=Данни от договора->Срок');
    	$this->FLD('deliveryPlace', 'key(mvc=crm_Locations,select=title,allowEmpty)', 'caption=Данни от договора->Място');
    	$this->FLD('storeId', 'key(mvc=store_Stores,select=name)', 'caption=Данни от договора->Склад');
    	
    	$this->FLD('weight', 'cat_type_Weight', 'caption=Тегло,input=none');
    	$this->FLD('brutoWeight', 'cat_type_Weight', 'caption=Бруто,input=none');
    	$this->FLD('state',
    			'enum(draft=Чернова, active=Активирано, rejected=Отказано, closed=Приключено, stopped=Спряно)',
    			'caption=Състояние, input=none'
    	);
    	$this->FLD('saleId', 'key(mvc=sales_Sales)', 'input=hidden,silent');
    	
    	$this->FLD('sharedUsers', 'userList(roles=planning|ceo)', 'caption=Споделяне->Потребители,mandatory');
    	$this->FLD('history', 'blob(serialize, compress)', 'caption=Данни,input=none');
    	
    	$this->setDbIndex('productId');
    }
    
    
    /**
     * Преди показване на форма за добавяне/промяна.
     *
     * @param core_Manager $mvc
     * @param stdClass $data
     */
    public static function on_AfterPrepareEditForm($mvc, &$data)
    {
    	$form = &$data->form;
    	$form->setReadOnly('productId');
    	$pInfo = cat_Products::getProductInfo($form->rec->productId);
    	$uomName = cat_UoM::getShortName($pInfo->productRec->measureId);
    	
    	$form->setField('quantity', "unit={$uomName}");
    	
    	if($form->rec->saleId){
    		$saleRec = sales_Sales::fetch($form->rec->saleId);
    		
    		$form->setDefault('deliveryTermId', $saleRec->deliveryTermId);
    		$form->setDefault('deliveryDate', $saleRec->deliveryTime);
    		$form->setDefault('deliveryPlace', $saleRec->deliveryLocationId);
    		$form->setDefault('storeId', $saleRec->shipmentStoreId);
    		$caption = "|Данни от|* <b>" . sales_Sales::getRecTitle($form->rec->saleId) . "</b>";
    		
    		$form->setField('deliveryTermId', "caption={$caption}->Условие");
    		$form->setField('deliveryDate', "caption={$caption}->Срок");
    		$form->setField('deliveryPlace', "caption={$caption}->Място");
    		$form->setField('storeId', "caption={$caption}->Склад");
    	} else {
    		$form->setField('deliveryTermId', 'input=none');
    		$form->setField('deliveryDate', 'input=none');
    		$form->setField('deliveryPlace', 'input=none');
    		$form->setField('storeId', 'input=none');
    	}
    }
    
    
    /**
     *  Подготовка на филтър формата
     */
    protected static function on_AfterPrepareListFilter($mvc, $data)
    {
    	$data->listFilter->setOptions('state', array('' => '') + arr::make('draft=Чернова, active=Активирано, closed=Приключено, stopped=Спряно', TRUE));
    	$data->listFilter->setField('state', 'placeholder=Всички');
    	$data->listFilter->showFields .= ',state';
    	$data->listFilter->input();
    	
    	if($state = $data->listFilter->rec->state){
    		$data->query->where("#state = '{$state}'");
    	}
    }
    
    
    /**
     * След подготовка на сингъла
     */
    public static function on_AfterRenderSingle($mvc, &$tpl, &$data)
    {
    	$tpl->push('planning/tpl/styles.css', "CSS");
    	
    	if(count($data->row->history)){
    		foreach ($data->row->history as $hRow){
    			$clone = clone $tpl->getBlock('HISTORY_ROW');
    			$clone->placeObject($hRow);
    			$clone->removeBlocks();
    			$clone->append2master();
    		}
    	}
    }
    
    
    /**
     * Извиква се след въвеждането на данните от Request във формата ($form->rec)
     *
     * @param core_Mvc $mvc
     * @param core_Form $form
     */
    public static function on_AfterInputEditForm($mvc, &$form)
    {
    	if($form->isSubmitted()){
    		$rec = &$form->rec;
    		
    		// Колко е транспортното тегло
    		if($weight = cls::get('cat_Products')->getWeight($rec->productId)){
    			$rec->brutoWeight = $weight * $rec->quantity;
    		} else {
    			$rec->brutoWeight = NULL;
    		}
    		
    		// Колко е еденичното тегло
    		$params = cls::get('cat_Products')->getParams($rec->productId);
    		if(isset($params['weight'])){
    			$rec->weight = $params['weight'] * $rec->quantity;
    		} else {
    			$rec->weight = NULL;
    		}
    	}
    }
    
    
    /**
     * След преобразуване на записа в четим за хора вид
     */
    public static function on_AfterRecToVerbal($mvc, &$row, $rec, $fields = array())
    {
    	if($fields['-list']){
    		$row->folderId = doc_Folders::recToVerbal(doc_Folders::fetch($rec->folderId))->title;
    		$row->productId = cat_Products::getHyperlink($rec->productId, TRUE);
    	}
    	 
    	if($rec->saleId){
    		$row->saleId = sales_Sales::getHyperlink($rec->saleId, TRUE);
    	}
    	
    	if($fields['-single']){
    		
    		if($rec->storeId){
    			$row->storeId = store_Stores::getHyperLink($rec->storeId, TRUE);
    		}
    		
    		$pInfo = cat_Products::getProductInfo($rec->productId);
    		$row->quantity .= " " . cat_UoM::getShortName($pInfo->productRec->measureId);
    		$row->origin = cls::get('cat_Products')->renderJobView($rec->productId, $rec->modifiedOn);
    		
    		if($rec->state == 'stopped' || $rec->state == 'closed') {
    			$tpl = new ET(tr(' от [#user#] на [#date#]'));
    			$row->state .= $tpl->placeArray(array('user' => $row->modifiedBy, 'date' => dt::mysql2Verbal($rec->modifiedOn)));
    		}
    	}
    }
    
    
    /**
     * Връща разбираемо за човека заглавие, отговарящо на записа
     */
    public static function getRecTitle($rec, $escaped = TRUE)
    {
    	$self = cls::get(get_called_class());
    	 
    	return tr($self->singleTitle) . " №{$rec->id}";
    }
    
    
    /**
     * Имплементиране на интерфейсен метод (@see doc_DocumentIntf)
     */
    function getDocumentRow($id)
    {
    	$rec = $this->fetch($id);
    	$row = new stdClass();
    	$row->title = $this->getRecTitle($rec);
    	$row->authorId = $rec->createdBy;
    	$row->author = $this->getVerbal($rec, 'createdBy');
    	$row->state = $rec->state;
    	$row->recTitle = $this->getRecTitle($rec);
    
    	return $row;
    }


    /**
     * Извиква се след подготовката на toolbar-а за табличния изглед
     */
    protected static function on_AfterPrepareListToolbar($mvc, &$data)
    {
    	if(!empty($data->toolbar->buttons['btnAdd'])){
    		$data->toolbar->removeBtn('btnAdd');
    	}
    }
    
    
    /**
     * Изпълнява се след подготовката на ролите, които могат да изпълняват това действие
     */
    public static function on_AfterGetRequiredRoles($mvc, &$res, $action, $rec = NULL, $userId = NULL)
    {
    	if($action == 'write' || $action == 'add' || $action == 'edit'){
    		
    		if(isset($rec)){
    			// Може да се добавя само ако има ориджин
    			if(empty($rec->productId)){
    				$res = 'no_one';
    			} else {
    				$productRec = cat_Products::fetch($rec->productId);
    				 
    				// Трябва да е активиран
    				if($productRec->state != 'active'){
    					$res = 'no_one';
    				}
    				 
    				// Трябва и да е производим
    				if($res != 'no_one'){
    					 
    					if($productRec->canManifacture == 'no'){
    						$res = 'no_one';
    					}
    				}
    			}
    			
    			// Ако се създава към оферта, тя трябва да е активна
    			if(!empty($rec->saleId)){
    				if(sales_Sales::fetchField($rec->saleId, "state") != 'active'){
    					$res = 'no_one';
    				}
    			}
    		}
    			
    		if($action == 'add' && empty($rec)){
	    		$res = 'no_one';
    		}
    	}
    	 
    	if(($action == 'activate' || $action == 'restore' || $action == 'conto' || $action == 'write' || $action == 'add') && isset($rec->productId) && $res != 'no_one'){
    
    		// Ако има активно задание, да не може друга да се възстановява,контира,създава или активира
    		if($mvc->fetch("#productId = {$rec->productId} AND (#state = 'active' || #state = 'stopped')")){
    			$res = 'no_one';
    		}
    	}
    	 
    	// Ако няма ид, не може да се активира
    	if($action == 'activate' && empty($rec->id)){
    		$res = 'no_one';
    	}
    }
    
    
    /**
     * Връща масив от използваните документи в даден документ (като цитат или
     * са включени в детайлите му)
     * @param int $data - сериализираната дата от документа
     * @return param $res - масив с използваните документи
     * 					[class] - инстанция на документа
     * 					[id] - ид на документа
     */
    function getUsedDocs_($id)
    {
    	$rec = $this->fetchRec($id);
    	$res[] = (object)array('class' => cls::get('cat_Products'), 'id' => $rec->productId);
    
    	return $res;
    }
    
    
    /**
     * Функция, която се извиква след активирането на документа
     */
    public static function on_AfterActivation($mvc, &$rec)
    {
    	// След активиране на заданието, добавяме артикула като перо
    	cat_Products::forceItem($rec->productId, 'catProducts');
    	
    	$rec->history[] = array('action' => 'active', 'date' => $rec->modifiedOn, 'user' => $rec->modifiedBy);
    	$mvc->save($rec, 'history');
    }
    
    
    /**
     * След подготовка на сингъла
     */
    public static function on_AfterPrepareSingle($mvc, &$res, $data)
    {
    	$rec = $data->rec;
    	$row = $data->row;
    	
    	if(count($rec->history)){
    		array_unshift($rec->history, array('action' => 'create', 'date' => $rec->createdOn, 'user' => $rec->createdBy));
    	} else {
    		$rec->history = array();
    		$rec->history[] = array('action' => 'create', 'date' => $rec->createdOn, 'user' => $rec->createdBy);
    	}
    
    	$row->history = array();
    	$actionNames = array('create' => 'Създаване', 'active' => 'Активиране', 'stopped' => 'Спиране', 'closed' => 'Приключване', 'wakeup' => 'Събуждане');
    	foreach ($rec->history as $historyRec){
    		$row->history[] = (object)array('date' => cls::get('type_DateTime')->toVerbal($historyRec['date']),
    										'user' => crm_Profiles::createLink($historyRec['user']),
    										'action' => $actionNames[$historyRec['action']],
    		);
    	}
    }
    
    
    /**
     * След подготовка на тулбара на единичен изглед.
     *
     * @param core_Mvc $mvc
     * @param stdClass $data
     */
    public static function on_AfterPrepareSingleToolbar($mvc, &$data)
    {
    	if($mvc->haveRightFor('changestate', $data->rec)){
    		if($data->rec->state == 'closed'){
    			$data->toolbar->addBtn("Събуждане", array($mvc, 'changeState', $data->rec->id, 'type' => 'close', 'ret_url' => TRUE), 'ef_icon = img/16/lightbulb.png,title=Събуждане на заданието,warning=Сигурнили сте че искате да събудите заданието');
    		} elseif($data->rec->state == 'active'){
    			$data->toolbar->addBtn("Приключване", array($mvc, 'changeState', $data->rec->id, 'type' => 'close', 'ret_url' => TRUE), 'ef_icon = img/16/lightbulb_off.png,title=Приключване на заданието,warning=Сигурнили сте че искате да приключите заданието');
    		}
    		
    		if($data->rec->state == 'stopped'){
    			$data->toolbar->addBtn("Актириране", array($mvc, 'changeState', $data->rec->id, 'type' => 'stop', 'ret_url' => TRUE, ), 'ef_icon = img/16/control_play.png,title=Активиране на заданието,warning=Сигурнили сте че искате да активирате заданието');
    		} elseif($data->rec->state == 'active'){
    			$data->toolbar->addBtn("Спиране", array($mvc, 'changeState', $data->rec->id, 'type' => 'stop', 'ret_url' => TRUE), 'ef_icon = img/16/control_pause.png,title=Спиране на заданието,warning=Сигурнили сте че искате да спрете заданието');
    		}
    	}
    }
    
    
    /**
     * Затваря/отваря или спира/пуска заданието
     */
    public function act_changeState()
    {
    	$this->requireRightFor('changestate');
    	expect($id = Request::get('id', 'int'));
    	expect($rec = $this->fetch($id));
    	expect($type = Request::get('type', 'enum(close,stop)'));
    	$this->requireRightFor('changestate', $rec);
    	 
    	if($type == 'stop'){
    		expect($rec->state == 'stopped' || 'active');
    		$state = ($rec->state == 'stopped') ? 'active' : 'stopped';
    		$action = $state;
    	} else {
    		expect($rec->state == 'closed' || 'active');
    		$state = ($rec->state == 'closed') ? 'active' : 'closed';
    		$action = ($state == 'closed') ? 'closed' : 'wakeup';
    	}
    	
    	$rec->exState = $rec->state;
    	$rec->state = $state;
    	 
    	// Записваме в историята действието
    	$rec->history[] = array('action' => $action, 'date' => dt::now(), 'user' => core_Users::getCurrent());
    	
    	$this->save($rec, 'state,history');
    	 
    	return followRetUrl();
    }
    
    
    /**
     * Подготовка на заданията за артикула
     * 
     * @param stdClass $data
     */
    public function prepareJobs($data)
    {
    	$data->rows = array();
    	$data->hideSaleCol = TRUE;
    	
    	// Намираме неоттеглените задания
    	$query = $this->getQuery();
    	$query->where("#productId = {$data->masterId}");
    	$query->where("#state != 'rejected'");
    	$query->orderBy("id", 'DESC');
    	while($rec = $query->fetch()){
    		$data->rows[$rec->id] = $this->recToVerbal($rec);
    		if(isset($rec->saleId)){
    			$data->hideSaleCol = FALSE;
    		}
    	}
    	
    	$masterInfo = $data->masterMvc->getProductInfo($data->masterId);
    	
    	// Показваме ги ако има записи или е производим артикула
    	if(count($data->rows) || isset($masterInfo->meta['canManifacture'])){
    		$data->TabCaption = 'Задания';
    		$data->Tab = 'top';
    		
    		// Проверяваме можем ли да добавяме нови задания
    		if($this->haveRightFor('add', (object)array('productId' => $data->masterId, 'folderId' => $folderId))){
    			$folderId = $data->masterMvc->fetchField($data->masterId, 'folderId');
    			$data->addUrl = array($this, 'add', 'productId' => $data->masterId, 'folderId' => $folderId, 'ret_url' => TRUE);
    		}
    	}
    }
    
    
    /**
     * Рендиране на заданията към артикул
     * 
     * @param stdClass $data
     * @return core_ET $tpl - шаблон на детайла
     */
    public function renderJobs($data)
    {
    	 if(!$data->TabCaption) return;
    	 
    	 $tpl = getTplFromFile('crm/tpl/ContragentDetail.shtml');
    	 $tpl->append(tr('Задания'), 'title');
    	 
    	 if(isset($data->addUrl)){
    	 	$addBtn = ht::createLink('', $data->addUrl, FALSE, 'ef_icon=img/16/add.png');
    	 	$tpl->append($addBtn, 'title');
    	 }
    	 
    	 $listFields = arr::make('id=Пулт,dueDate=Падеж,saleId=Към продажба,quantity=Количество,createdBy=Oт,createdOn=На');
    	 if($data->hideSaleCol){
    	 	unset($listFields['saleId']);
    	 }
    	 
    	 $table = cls::get('core_TableView', array('mvc' => $this));
    	 $details = $table->get($data->rows, $listFields);
    	 $tpl->replace($details, 'content');
    	 
    	 return $tpl;
    }
    
    
    /**
     * Може ли документа да се добави в посочената папка?
     *
     * @param $folderId int ид на папката
     * @return boolean
     */
    public static function canAddToFolder($folderId)
    {
    	$coverClass = doc_Folders::fetchCoverClassName($folderId);
    
    	return cls::haveInterface('cat_ProductFolderCoverIntf', $coverClass);
    }
}