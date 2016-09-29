<?php



/**
 * Списък с листвани артикули за клиента/доставчика
 *
 * @category  bgerp
 * @package   crm
 * @author    Ivelin Dimov <ivelin_pdimov@abv.bg>
 * @copyright 2006 - 2016 Experta OOD
 * @license   GPL 3
 * @since     v 0.1
 */
class crm_ext_ProductListToContragents extends core_Manager
{
	
	
	/**
	 * Кой  може да пише?
	 */
	public $canWrite = 'ceo, crm';
	
	
	/**
	 * Кой  може да изтрива?
	 */
	public $canDelete = 'ceo, crm';
	
	
	/**
	 * Кой  може да добавя?
	 */
	public $canAdd = 'ceo, crm';
	
	
	/**
	 * Кой  може да листва?
	 */
	public $canList = 'no_one';
	
	
	/**
	 * Кой  може да редактира?
	 */
	public $canEdit = 'ceo, crm';
	
	
	/**
     * Полета, които ще се показват в листов изглед
     */
    public $listFields = 'productId=Артикул,packagingId=Опаковка,code=Техен код,modified=Модифициране';
			

    /**
     * Плъгини за зареждане
     */
    public $loadList = 'plg_Modified, crm_Wrapper, plg_RowTools2, plg_SaveAndNew, plg_Search';
    
    
    /**
     * Полета от които се генерират ключови думи за търсене (@see plg_Search)
     */
    public $searchFields = 'productId,packagingId,code';
    
    
    /**
     * Еденично заглавие
     */
    public $singleTitle = 'Артикул за листване';
    
    
    /**
     * Заглавие
     */
    public $title = 'Артикули за листване';
    
    
    /**
     * Работен кеш
     */
    protected static $cache = array();
    
    
    /**
     * Брой на страница
     */
    public $listItemsPerPage = 20;
    
    
	/**
	 * Описание на модела (таблицата)
	 */
	function description()
	{
		$this->FLD('contragentClassId', 'class(interface=crm_ContragentAccRegIntf)', 'caption=Собственик->Клас,input=hidden,silent');
		$this->FLD('contragentId', 'int', 'caption=Собственик->Id,input=hidden,silent');
		$this->FLD('productId', 'key(mvc=cat_Products,select=name)', 'caption=Артикул,notNull,mandatory', 'tdClass=productCell leftCol wrap,silent,removeAndRefreshForm=packagingId,caption=Артикул');
    	$this->FLD('packagingId', 'key(mvc=cat_UoM, select=shortName, select2MinItems=0)', 'caption=Мярка', 'smartCenter,tdClass=small-field nowrap,silent,caption=Опаковка,input=hidden,mandatory');
    	$this->FLD('type', 'enum(sellable,buyable)', 'caption=За,input=hidden,silent');
    	$this->FLD('code', 'varchar(32)', 'caption=Техен код');
	
    	$this->setDbUnique('contragentClassId,contragentId,productId,packagingId,type');
    	$this->setDbIndex('code');
	}
	
	
	/**
	 * Преди показване на форма за добавяне/промяна
	 */
	protected static function on_AfterPrepareEditForm($mvc, &$data)
	{
		$form = &$data->form;
		$rec = $form->rec;
		$mvc->currentTab = ($rec->contragentClassId == crm_Companies::getClassId()) ? 'Фирми' : 'Лица';
		$meta = ($rec->type == 'sellable') ? 'canSell' : 'canBuy';
		
		if(empty($rec->id)){
			
			// Извличане на всички допустими артикули, които още не са листвани към контрагента
			$cached = self::getAll($rec->contragentClassId, $rec->contragentId);
			$cached = $cached[$rec->type];
			$ignoreIds = arr::extractValuesFromArray($cached, 'productId');
			
			$products = cat_Products::getProducts($rec->contragentClassId, $rec->contragentId, NULL, $meta, NULL, NULL, $ignoreIds);
			$products = array('' => '') + $products;
		} else {
			$products = array($rec->productId => cat_Products::getRecTitle(cat_Products::fetch($rec->productId), FALSE));
		}
		$form->productOptions = $products;
		$form->setOptions('productId', $products);
		
		// Ако е избран артикул, показва се и опаковката му
		if(isset($rec->productId)){
			$packs = cat_Products::getPacks($rec->productId);
			$form->setField('packagingId', 'input');
			$form->setOptions('packagingId', $packs);
			$form->setDefault('packagingId', key($packs));
		}
	}
	
	
	/**
	 * Извиква се след въвеждането на данните от Request във формата ($form->rec)
	 */
	protected static function on_AfterInputEditForm($mvc, &$form)
	{
		$rec = $form->rec;
		if($form->isSubmitted()){
			
			// Ако няма код
			if(empty($rec->code)){
				
				// И има такава опаковка, взима се ЕАН кода
				if($pack = cat_products_Packagings::getPack($rec->productId, $rec->packagingId)){
					$rec->code = (!empty($pack->eanCode)) ? $pack->eanCode : NULL;
				}
				
				// Ако още не е намерен код, взима се кода на артикула
				if(empty($rec->code)){
					$rec->code = cat_Products::fetchField($rec->productId, 'code');
				}
				
				if(empty($rec->code)){
					$rec->code = NULL;
				}
			}
		}
	}
	
	
	/**
	 * След подготовката на заглавието на формата
	 */
	protected static function on_AfterPrepareEditTitle($mvc, &$res, &$data)
	{
		$rec = $data->form->rec;
		$singleTitle = $mvc->singleTitle;
		$singleTitle .= ($rec->type == 'sellable') ? " " . tr('в продажба') : " " . tr('в покупка');
		$data->form->title = core_Detail::getEditTitle($rec->contragentClassId, $rec->contragentId, $singleTitle, $rec->id, 'в');
	
		// Махане на бутона запис и нов, ако няма достатъчно записи
		if(count($data->form->productOptions) <= 1){
			$data->form->toolbar->removeBtn('saveAndNew');
		}
	}
	
	
	/**
	 * Подготовка на листваните артикули за един контрагент
	 * 
	 * @param stdClass $data
	 * @return void
	 */
	public function prepareProductList($data)
	{
		// Намират се ид-та на групите за клиенти и доставчици
		$clientGroupId = crm_Groups::getIdFromSysId('customers');
		$supplierGroupId = crm_Groups::getIdFromSysId('suppliers');
		
		$data->contragentClassId = $data->masterMvc->getClassId();
		$data->isClient = keylist::isIn($clientGroupId, $data->masterData->rec->groupList);
		$data->isSupplier = keylist::isIn($supplierGroupId, $data->masterData->rec->groupList);
		$Tab = core_Request::get('Tab', 'varchar');
		
		// Ако контагента не е доставчик или клиент и няма листвани артикули или не е отворен таба, не се подготвя нищо
		if(($data->isClient === FALSE && $data->isSupplier === FALSE && !self::fetch("#contragentClassId = {$data->contragentClassId} AND #contragentId = {$data->masterId}")) || $Tab !== 'CommerceDetails'){
			$data->render = FALSE;
			return;
		}
		
		// Подготовка на данните
		$this->prepareData($data);
		
		// Добавяне на бутони
		if($this->haveRightFor('add', (object)array('contragentClassId' => $data->contragentClassId, 'contragentId' => $data->masterId))){
			$data->addSellableUrl = array($this, 'add', 'contragentClassId' => $data->contragentClassId, 'contragentId' => $data->masterId, 'type' => 'sellable', 'ret_url' => TRUE);
			$data->addBuyableUrl = array($this, 'add', 'contragentClassId' => $data->contragentClassId, 'contragentId' => $data->masterId, 'type' => 'buyable', 'ret_url' => TRUE);
		}
	}
	
	
	/**
	 * Подготовка на формата
	 * 
	 * @param stdClass $data
	 * @return void
	 */
	private function prepareForm($data)
	{
		// Подготвяме формата за филтър по склад
        $form = cls::get('core_Form');
        
        $form->FLD("search", 'varchar', 'placeholder=Търсене,silent');
        $form->view = 'horizontal';
        $form->setAction(getCurrentUrl());
        $form->toolbar->addSbBtn('', 'default', 'id=filter', 'ef_icon=img/16/funnel.png');
        
        // Инпутване на формата
        $form->input();
        $data->form = $form;
	}
	
	
	/**
	 * Подготовка на данни
	 * 
	 * @param stdClass $data
	 * @return void
	 */
	private function prepareData(&$data)
	{
		$this->prepareListFields($data);
		$data->sellable = new stdClass();
		$data->sellable->recs = $data->sellable->rows = array();
		$data->buyable = new stdClass();
		$data->buyable->recs = $data->buyable->rows = array();
		$data->sellable->listFields = $data->buyable->listFields = $data->listFields;
		
		// Подготовка на форма за филтриране
		$this->prepareForm($data);
		
		// Намиране на всички листвани артикули за контрагента
		$sellableQuery = self::getQuery();
		$sellableQuery->where("#contragentClassId = {$data->contragentClassId} AND #contragentId = {$data->masterId}");
		
		// Ако има филтър по ключови думи, добавя се и той
		if(!empty($data->form->rec->search)){
			plg_Search::applySearch($data->form->rec->search, $sellableQuery);
		}
		
		// Подготовка на заявката
		$buyableQuery = clone $sellableQuery;
		$buyableQuery->where("#type = 'buyable'");
		$sellableQuery->where("#type = 'sellable'");
		
		// Подготовка на листваните артикули за продажба
		$data->sellable->recs = $sellableQuery->fetchAll();
		$data->sellable->pager = cls::get('core_Pager',  array('itemsPerPage' => $this->listItemsPerPage));
		$data->sellable->pager->itemsCount = count($data->sellable->recs);
		$data->sellable->pager->setPageVar('s');
		
		// За всеки запис, вербализира се, ако трябва да се показва
		foreach ($data->sellable->recs as $sId => $sRec){
			if(!$data->sellable->pager->isOnPage()) continue;
			$data->sellable->rows[$sId] = $this->recToVerbal($sRec);
		}
		
		// Подготовка на листваните артикули за покупка
		$data->buyable->recs = $buyableQuery->fetchAll();
		$data->buyable->pager = cls::get('core_Pager',  array('itemsPerPage' => $this->listItemsPerPage));
		$data->buyable->pager->itemsCount = count($data->buyable->recs);
		$data->buyable->pager->setPageVar('b');
		
		// За всеки запис, вербализира се, ако трябва да се показва
		foreach ($data->buyable->recs as $bId => $bRec){
			if(!$data->buyable->pager->isOnPage()) continue;
			$data->buyable->rows[$bId] = $this->recToVerbal($bRec);
		}
	}
	
	
	/**
	 * Рендиране на листваните артикули за продажба
	 * 
	 * @param core_ET $tpl
	 * @param stdClass $data
	 */
	private function renderSellableBlock(&$tpl, $data)
	{
		// Рендиране на таблицата с артикулите
		$table = cls::get('core_TableView', array('mvc' => $this));
		$this->invoke('BeforeRenderListTable', array($tpl, &$data->sellable));
		$tableTpl = $table->get($data->sellable->rows, $data->sellable->listFields);
		$tpl->replace($tableTpl, 'SELLABLE');
		
		// Редниране на бутона за добавяне
		if(isset($data->addSellableUrl)){
			$btn = ht::createBtn('Артикул', $data->addSellableUrl, NULL, NULL, 'ef_icon=img/16/shopping.png,title=Добавяне на нов артикул за листване в продажба');
			$tpl->replace($btn, 'SELLABLE_BTN');
		}
		
		// Рендиране на пейджъра
		if(isset($data->sellable->pager)){
			$tpl->append($data->sellable->pager->getHtml(), 'SELLABLE_PAGER');
		}
	}
	
	
	/**
	 * Рендиране на листваните артикули за покупка
	 * 
	 * @param core_ET $tpl
	 * @param stdClass $data
	 */
	private function renderBuyableBlock(&$tpl, $data)
	{
		// Рендиране на таблицата с артикулите
		$table = cls::get('core_TableView', array('mvc' => $this));
		$this->invoke('BeforeRenderListTable', array($tpl, &$data->buyable));
		$tableTpl = $table->get($data->buyable->rows, $data->buyable->listFields);
		$tpl->replace($tableTpl, 'BUYABLE');
		
		// Редниране на бутона за добавяне
		if(isset($data->addBuyableUrl)){
			$btn = ht::createBtn('Артикул', $data->addBuyableUrl, NULL, NULL, 'ef_icon=img/16/shopping.png,title=Добавяне на нов артикул за листване в покупка');
			$tpl->replace($btn, 'BUYABLE_BTN');
		}
		
		// Рендиране на пейджъра
		if(isset($data->buyable->pager)){
			$tpl->append($data->buyable->pager->getHtml(), 'BUYABLE_PAGER');
		}
	}
	
	
	/**
	 * Рендиране на листваните артикули за клиента
	 * 
	 * @param stdClass $data
	 * @return core_ET $tpl
	 */
	public function renderProductList($data)
	{
		// Взимане на шаблона
		$tpl = getTplFromFile("crm/tpl/ProductListToContragents.shtml");
		
		// Ако няма да се рендира нищо, връща се празен шаблон
		if($data->render === FALSE) return $tpl;
		$tpl->replace(tr('Листвани артикули'), 'listTitle');
		
		// Ако има филтър форма, рендира се
		if(isset($data->form)){
			$tpl->append($data->form->renderHtml(), 'FILTER');
		}
		
		// Рендиране на двете таблици за листвани артикули
		self::renderSellableBlock($tpl, $data);
		self::renderBuyableBlock($tpl, $data);
		
		// Връщане на шаблона
		return $tpl;
	}
	
	
	/**
	 * Изпълнява се след подготовката на ролите, които могат да изпълняват това действие
	 */
	protected static function on_AfterGetRequiredRoles($mvc, &$requiredRoles, $action, $rec = NULL, $userId = NULL)
	{
		if(($action == 'add' || $action == 'edit' || $action == 'delete') && isset($rec)){
			
			// Ако няма контрагент, записа не може да бъде променян
			if(empty($rec->contragentClassId) || empty($rec->contragentId)){
				$requiredRoles = 'no_one';
			} else {
				
				// Ако потребителя не може да редактира визитката, не може да променя листваните артикули
				if(!cls::get($rec->contragentClassId)->haveRightFor('edit', $rec->contragentId)){
					$requiredRoles = 'no_one';
				}
			}
		}
	}
	
	
	/**
	 * След преобразуване на записа в четим за хора вид
	 */
	protected static function on_AfterRecToVerbal($mvc, &$row, $rec)
	{
		$row->modified = "<div class='nowrap'>" . $mvc->getFieldType('modifiedOn')->toVerbal($rec->modifiedOn);
		$row->modified .= " " . tr('от||by') . " " . crm_Profiles::createLink($rec->modifiedBy) . "</div>";
		$row->productId = cat_Products::getShortHyperlink($rec->productId);
	    $row->code = "<b>{$row->code}</b>";
	}
	
	
	/**
	 * Кешира и връща всички листвани артикули за клиента
	 * 
	 * @param int $contragentClassId
	 * @param int $contragentId
	 */
	private static function getAll($contragentClassId, $contragentId)
	{
		$contragentClassId = cls::get($contragentClassId)->getClassId();
		
		// Ако няма наличен кеш за контрагента, извлича се наново
		if(!isset(self::$cache[$contragentClassId][$contragentId])){
			self::$cache[$contragentClassId][$contragentId] = array();
			
			// Кои са листваните артикули за контрагента
			$query = self::getQuery();
			$query->where("#contragentClassId = {$contragentClassId} AND #contragentId = {$contragentId}");
			self::$cache[$contragentClassId][$contragentId]['sellable'] = array();
			self::$cache[$contragentClassId][$contragentId]['buyable'] = array();
			
			// Добавя се всеки запис, групиран според типа
			while($rec = $query->fetch()){
				self::$cache[$contragentClassId][$contragentId][$rec->type][$rec->id] = $rec;
			}
		}
		
		// Връщане на кешираните данни
		return self::$cache[$contragentClassId][$contragentId];
	}
}