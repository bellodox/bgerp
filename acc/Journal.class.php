<?php



/**
 * Мениджър Журнал
 *
 *
 * @category  bgerp
 * @package   acc
 * @author    Milen Georgiev <milen@download.bg>
 * @copyright 2006 - 2014 Experta OOD
 * @license   GPL 3
 * @since     v 0.1
 */
class acc_Journal extends core_Master
{
    
    
    /**
     * Заглавие
     */
    var $title = "Журнал със счетоводни транзакции";
    
    
    /**
     * Плъгини за зареждане
     */
    var $loadList = 'plg_Created, plg_State, plg_RowTools, plg_Printing, plg_Search,
                     acc_Wrapper, plg_Sorting';
    
    
    /**
     * Полета, които ще се показват в листов изглед
     */
    var $listFields = "id, valior, docType, totalAmount";
    
    
    /**
     * Детайла, на модела
     */
    var $details = 'acc_JournalDetails';
    
    
    /**
     * Заглавие в единствено число
     */
    var $singleTitle = 'Счетоводна статия';
    
    
    /**
     * Кой има право да чете?
     */
    var $canRead = 'ceo,acc';
    
    
    /**
     * Кой може да го разглежда?
     */
    var $canList = 'ceo,acc';
    
    
    /**
     * Кой може да разглежда сингъла на документите?
     */
    var $canSingle = 'ceo,acc';
    
    
    /**
     * Кой има право да добавя?
     */
    var $canAdd = 'no_one';
    
    
    /**
     * Кой има право да променя?
     */
    var $canEdit = 'no_one';
    
    
    /**
     * Кой може да го изтрие?
     */
    var $canDelete = 'no_one';
    
    
    /**
     * Кой може да го отхвърли?
     */
    var $canReject = 'no_one';
    
    
    /**
     * Шаблон за единичния изглед
     */
    var $singleLayoutFile = 'acc/tpl/SingleLayoutJournal.shtml';
    
    
    /**
     * Полета за търсене
     */
    var $searchFields = 'reason';
    
    
    /**
     * Полето в което автоматично се показват иконките за редакция и изтриване на реда от таблицата
     */
    var $rowToolsSingleField = 'docType';
    
    
    /**
     * Кеш на афектираните пера
     */
    public $affectedItems = array();
    
    
    /**
     * Кеш на афектираните пера
     */
    protected $updated = array();
    
    
    /**
     * Описание на модела
     */
    function description()
    {
        // Ефективна дата
        $this->FLD('valior', 'date', 'caption=Вальор,mandatory');
        
        // Пораждащ документ
        $this->FLD('docType', 'class(interface=acc_TransactionSourceIntf)', 'caption=Документ,input=none');
        $this->FLD('docId', 'int', 'input=none,column=none');
        
        // Обща сума
        $this->FLD('totalAmount', 'double(decimals=2)', 'caption=Оборот,input=none');
        
        // Основание за транзакцията
        $this->FLD('reason', 'varchar', 'caption=Основание,input=none');
        
        // Състояние
        $this->FLD('state', 'enum(draft=Чернова,active=Активна,revert=Сторнирана)', 'caption=Състояние,input=none');
        
        $this->setDbUnique('docType,docId,state');
    }
    
    
    /**
     * Малко манипулации след подготвянето на формата за филтриране
     */
    static function on_AfterPrepareListFilter($mvc, $data)
    {
        $data->listFilter->class = 'simpleForm';
        $data->listFilter->FNC('dateFrom', 'date', 'input,caption=От');
        $data->listFilter->FNC('dateTo', 'date', 'input,caption=До');
        
        $data->listFilter->setDefault('dateFrom', date('Y-m-01'));
        $data->listFilter->setDefault('dateTo', date("Y-m-t", strtotime(dt::now())));
        
        $data->listFilter->showFields = 'search,dateFrom,dateTo';
        $data->listFilter->toolbar->addSbBtn('Филтрирай', array($mvc, 'list', 'show' => Request::get('show')), 'id=filter', 'ef_icon = img/16/funnel.png');
        
        // Активиране на филтъра
        $data->listFilter->input(NULL, 'silent');
        
        $data->query->orderBy('id', 'DESC');
        
        if($rec = $data->listFilter->rec){
            
            // Ако се търси по стринг
            if($rec->search){
                
                // и този стринг отговаря на хендлър на документ в системата
                $doc = doc_Containers::getDocumentByHandle($rec->search);
                
                if(is_object($doc)){
                    
                    // Показваме документа и другите документи в нишката му
                    $data->query->orWhere("#docType = '{$doc->getClassId()}' AND #docId = '{$doc->that}'");
                    
                    $chain = $doc->getDescendants();
                    
                    if(count($chain)){
                        foreach ($chain as $desc){
                            $data->query->orWhere("#docType = '{$desc->getClassId()}' AND #docId = '{$desc->that}'");
                        }
                    }
                }
            }
            
            // Филтер по начална дата
            if($rec->dateFrom){
                $data->query->where(array("#valior >= '[#1#]'", $rec->dateFrom));
            }
            
            // Филтър по крайна дата
            if($rec->dateTo){
                $data->query->where(array("#valior <= '[#1#] 23:59:59'", $rec->dateTo));
            }
        }
    }
    
    
    /**
     * След всеки запис в журнала
     *
     * @param core_Mvc $mvc
     * @param int $id
     * @param stdClass $rec
     */
    public static function on_AfterSave(core_Mvc $mvc, &$id, $rec, $fields = NULL, $mode = NULL)
    {
        if ($rec->state != 'draft') {
        	
        	$fields = arr::make($fields, TRUE);
        	
        	// Инвалидираме балансите, които се променят от този вальор
        	acc_Balances::alternate($rec->valior);
        }
        
        // След активиране, извличаме всички записи от журнала и запомняме кои пера са вкарани
        if($rec->state == 'active'){
            $dQuery = acc_JournalDetails::getQuery();
            $dQuery->where("#journalId = {$rec->id}");
            
            while($dRec = $dQuery->fetch()){
                foreach (array('debitItem1', 'debitItem2', 'debitItem3', 'creditItem1', 'creditItem2', 'creditItem3') as $item){
                    if(isset($dRec->$item)){
                        $mvc->affectedItems[$dRec->$item] = $dRec->$item;
                    }
                }
            }
        }
    }
    
    
    /**
     * Извиква се след конвертирането на реда ($rec) към вербални стойности ($row)
     */
    static function on_AfterRecToVerbal($mvc, $row, $rec, $fields = array())
    {
        $row->totalAmount = '<strong>' . $row->totalAmount . '</strong>';
        $origMvc = $mvc;
        
        if($rec->docType && cls::load($rec->docType, TRUE)) {
            $mvc = cls::get($rec->docType);
            $doc = new core_ObjectReference($rec->docType, $rec->docId);
            
            if($doc) {
                try {
                    $row->docType = $doc->getLink();
                } catch(core_exception_Expect $e) {
                    $row->docType = "{$rec->docType}:{$rec->docId}";
                }
            }
        }
        
        if($fields['-list']){
            $dQuery = acc_JournalDetails::getQuery();
            $dQuery->where("#journalId = {$rec->id}");
            $details = $dQuery->fetchAll();
            
            $row->docType = $row->docType . " <a href=\"javascript:toggleDisplay('{$rec->id}inf')\"  style=\"background-image:url(" . sbf('img/16/plus.png', "'") . ");\" class=\" plus-icon more-btn\"> </a>";
            
            $row->docType .= "<ol style='margin-top:2px;margin-top:2px;margin-bottom:2px;color:#888;display:none' id='{$rec->id}inf'>";
            
            foreach ($details as $decRec){
                $dAcc = acc_Accounts::getNumById($decRec->debitAccId);
                $cAcc = acc_Accounts::getNumById($decRec->creditAccId);
                $row->docType .= "<li>" . tr('Дебит') . ": <b>{$dAcc}</b> <span style='margin-left:20px'>" . tr('Кредит') . ": <b>{$cAcc}</b></span></li>";
            }
            $row->docType .= "</ol>";
        }
    }
    
    
    /**
     * Изпълнява се след подготовката на титлата в единичния изглед
     */
    static function on_AfterPrepareSingleTitle($mvc, &$res, $data)
    {
        $data->title .= " (" . $mvc->getVerbal($data->rec, 'state') . ")";
    }
    
    
    /**
     * Контиране на счетоводен документ.
     *
     * Документа се задава чрез двойката параметри в URL `docId` и `docType`. Класът, зададен
     * в `docType` трябва да поддържа интерфейса `acc_TransactionSourceIntf`
     *
     * @param int $docId (от URL)
     * @param mixed $docType (от URL) ид или име на клас поддържащ интерфейса
     * `acc_TransactionSourceIntf`
     */
    function act_Conto()
    {
        expect($docId = Request::get('docId', 'int'));
        expect($docClassId = Request::get('docType', 'class(interface=acc_TransactionSourceIntf)'));
        
        $mvc = cls::get($docClassId);
        
        // Дали имаме права за контиране
        $mvc->requireRightFor('conto', $docId);
        
        // Контиране на документа
        $mvc->conto($docId);
        
        // Редирект към сингъла
        return redirect(array($mvc, 'single', $docId));
    }
    
    
    /**
     * Сторниране на счетоводен документ.
     *
     * Документа се задава чрез двойката параметри в URL `docId` и `docType`. Класът, зададен
     * в `docType` трябва да поддържа интерфейса `acc_TransactionSourceIntf`
     *
     * @param int $docId (от URL)
     * @param mixed $docType (от URL) ид или име на клас поддържащ интерфейса
     * `acc_TransactionSourceIntf`
     */
    function act_Revert()
    {
        expect($docId = Request::get('docId', 'int'));
        expect($docClassId = Request::get('docType', 'class(interface=acc_TransactionSourceIntf)'));
        
        $mvc = cls::get($docClassId);
        
        $mvc->requireRightFor('revert', $docId);
        
        if (!$result = self::rejectTransaction($docClassId, $docId)) {
            core_Message::redirect(
                "Невъзможно сторниране",
                'page_Error',
                NULL,
                getRetUrl()
            );
        }
        
        list($docClassId, $docId) = $result;
        
        return new Redirect(array($docClassId, 'single', $docId));
    }
    
    
    /**
     * Записва счетоводната транзакция, породена от документ
     *
     * Документът ($docClassId, $docId) ТРЯБВА да поддържа интерфейс acc_TransactionSourceIntf
     *
     * @param mixed $docClassId - класа на документа
     * @param int $docId - ид на документа
     * @param boolean $notifyDocument - да нотифицира ли документа, че транзакцията е приключена
     */
    public static function saveTransaction($docClassId, $docId, $notifyDocument = TRUE)
    {
        $mvc      = cls::get($docClassId);
        $docClass = cls::getInterface('acc_TransactionSourceIntf', $mvc);
        $docRec   = $mvc->fetchRec($docId);
        
        try {
        	
        	Mode::push("saveTransaction", TRUE);
            $transaction = $mvc->getValidatedTransaction($docRec);
            Mode::pop("saveTransaction");
        } catch (acc_journal_Exception $ex) {
            $tr = $docClass->getTransaction($docRec->id);
            bp($ex->getMessage(), $tr);
        }
        
        $transaction->rec->docType = $mvc->getClassId();
        $transaction->rec->docId   = $docRec->id;
        
        if ($success = $transaction->save()) {
        	
        	// Нотифицира мениджъра на документа за успешно приключилата транзакция, ако сме задали
        	if($notifyDocument === TRUE){
        		$docClass->finalizeTransaction($docRec);
        	}
        }
        
        return $success;
    }
    
    /**
     * Валидира един по един списък от редове на транзакция
     *
     * @param stdClass $transaction
     * @return boolean
     */
    protected static function validateTransaction($transaction)
    {
        $transaction = new acc_journal_Transaction($transaction);
        
        return $transaction->check();
    }
    
    
    /**
     * Отменя контирането на счетоводен документ
     *
     * Ако периода, в който е бил контиран документа е отворен - транзакцията се изтрива от
     * журнала. Ако периода е приключен (т.е. затворен), то в текущия период се създава нова
     * транзакция, обратна на тази, генерирана при контирането на документа.
     *
     * @param int $docClassId
     * @param int $docId
     * @return boolean
     */
    public static function rejectTransaction($docClassId, $docId)
    {
        if (!($rec = self::fetchByDoc($docClassId, $docId))) {
            return FALSE;
        }
        
        if (!($periodRec = acc_Periods::fetchByDate($rec->valior))) {
            return FALSE;
        }
        
        if ($periodRec->state == 'closed') {
            return acc_Articles::createReverseArticle($rec);
        } else {
            return static::deleteTransaction($docClassId, $docId);
        }
    }
    
    
    /**
     * Връща записа отговарящ на даден документ
     */
    public static function fetchByDoc($docClassId, $docId)
    {
        return self::fetch("#docType = {$docClassId} AND #docId = {$docId}");
    }
    
    
    /**
     * Изтриване на транзакция
     */
    public static function deleteTransaction($docClassId, $docId)
    {
        $query = static::getQuery();
        $query->where("#docType = {$docClassId} AND #docId = {$docId}");
    	
        // Изтриваме всички записи направени в журнала от документа
    	while($rec = $query->fetch()){
    		
    		acc_JournalDetails::delete("#journalId = {$rec->id}");
    		
    		static::delete($rec->id);
    		
    		// Инвалидираме балансите, които се променят от този вальор
    		acc_Balances::alternate($rec->valior);
    	}
    	
    	return array($docClassId, $docId);
    }
    
    
    /**
     * Добавя ключови думи за пълнотекстово търсене
     */
    function on_AfterGetSearchKeywords($mvc, &$res, $rec)
    {
        // Думите за търсене са името на документа-основания
        $object = new core_ObjectReference($rec->docType, $rec->docId);
        
        if($object->haveInterface('doc_DocumentIntf')){
            $title = $object->getDocumentRow()->title;
            $res .= " " . plg_Search::normalizeText($title);
        }
    }
    
    
    /**
     * Метод връщащ урл-то за контиране на документ
     * @param core_Manager $mvc - мениджър
     * @param int $recId - ид на записа, който ще се контира
     */
    public static function getContoUrl(core_Manager $mvc, $recId)
    {
        $contoUrl = array('acc_Journal',
            'conto',
            'docId' => $recId,
            'docType' => $mvc->className,
            'ret_url' => TRUE
        );
        
        return $contoUrl;
    }
    
    
    /**
     * Ф-я извикваща се след генерирането на баланса, чисти всички замърсени данни
     */
    public static function clearDrafts()
    {
        // Избиране на всички чернови в журнала, По стари от 5 минути
        $query = self::getQuery();
        $query->where("#state = 'draft'");
        $query->where('#createdOn < (NOW() - INTERVAL 5 MINUTE)');
        
        while($rec = $query->fetch()){
            try{
                $document = new core_ObjectReference($rec->docType, $rec->docId);
            } catch(core_exception_Expect $e){
                continue;
            }
            
            // Ако състоянието на документа е чернова
            $state = $document->fetchField('state', FALSE);
            
            if($state == 'draft'){
                
                // Изтриване на замърсените данни
                acc_JournalDetails::delete("#journalId = {$rec->id}");
                acc_Journal::delete("#id = {$rec->id}");
                
                // Логваме в журнала
                acc_Articles::log("Изтрит ред '{$rec->id}' от журнала На документ {$document->className}:{$rec->docId}");
            }
        }
    }
    
    
    /**
     * Връща всички записи от журнала където в поне един ред на кредита и дебита на една сметка
     * се среща зададеното перо
     *
     * @param mixed $item - масив с име на мениджър и ид на запис, или ид на перо
     * @param stdClass $itemRec - върнатия запис на перото
     * @param boolean $showAllRecs - дали да се връщат само записите с перата или всички записи от д-те в чиято транзакция
     * участва посоченото перо
     * @return array $res - извлечените движения
     */
    public static function getEntries($item, &$itemRec = NULL, $showAllRecs = FALSE)
    {
        expect($item);
        
        // Ако е подаден масив, опитваме се да намерим кое е перото
        if(is_array($item)){
            $Class = cls::get($item[0]);
            expect($Class->fetch($item[1]));
            $item = acc_Items::fetchItem($Class->getClassId(), $item[1]);
            
            if(!$item) return NULL;
        }
        
        // Извличаме ид-та на журналите, имащи ред с участник това перо
        expect($itemRec = acc_Items::fetchRec($item));
        $jQuery = acc_JournalDetails::getQuery();
        
        acc_JournalDetails::filterQuery($jQuery, NULL, NULL, NULL, $itemRec->id);
        
        if($showAllRecs === FALSE) return $jQuery->fetchAll();
        
        $jIds = array();
        $jQuery->show('journalId');
        
        while($jRec = $jQuery->fetch()){
            $jIds[$jRec->journalId] = $jRec->journalId;
        }
        
        // Извличаме всички транзакции на намерените журнали
        $jQuery = acc_JournalDetails::getQuery();
        $jQuery->EXT('docType', 'acc_Journal', 'externalKey=journalId');
        $jQuery->EXT('docId', 'acc_Journal', 'externalKey=journalId');
        $jQuery->where("#createdOn BETWEEN '{$itemRec->createdOn}' AND '{$now}'");
        $jQuery->orderBy("#id", 'ASC');
        
        if(count($jIds)){
            $jQuery->in('journalId', $jIds);
            
            return $jQuery->fetchAll();
        }
        
        // Връщаме извлечените записи
        return $jIds;
    }
    
    
    /**
     * Афектираните пера, нотифицират мениджърите си
     */
    public static function on_Shutdown($mvc)
    {
        // Всяко афектирано перо, задейства ивент в мениджъра си
        if(count($mvc->affectedItems)){
        	
        	// Увеличаваме времето за изпълнение според броя афектирани пера
        	$timeLimit = count($mvc->affectedItems) * 10;
        	core_App::setTimeLimit($timeLimit);
        	
            foreach ($mvc->affectedItems as $rec) {
                acc_Items::notifyObject($rec);
            }
        }
        
        // Ъпдейтваме информацията за журнала, ако е отбелязан че са му променени детайлите
        if(count($mvc->updated)){
        	
        	// Увеличаваме времето за изпълнение спрямо броя променените записи
        	$timeLimit = count($mvc->updated) * 15;
        	core_App::setTimeLimit($timeLimit);
        	
            foreach ($mvc->updated as $journalId){
                $rec = $mvc->fetchRec($journalId);
                $mvc->updateMaster($rec);
                
                // Нотифицираме документа породил записа в журнала че журнала му е променен
                if(cls::load($rec->docType, TRUE)){
                    cls::get($rec->docType)->invoke('AfterJournalUpdated', array($rec->docId, $rec->id));
                }
            }
        }
    }
    
    
    /**
     * Обновява данните на журнала след промяна в детайлите
     */
    private function updateMaster($id)
    {
        $rec = $this->fetchRec($id);
        $rec->totalAmount = 0;
        
        $dQuery = acc_JournalDetails::getQuery();
        $dQuery->where("#journalId = {$rec->id}");
        $dQuery->show('amount');
        
        while($dRec = $dQuery->fetch()){
            $rec->totalAmount += $dRec->amount;
        }
        
        $this->save_($rec, 'totalAmount');
    }
    
    
    /**
     * Поддържа точна информацията за записите в детайла
     */
    public static function on_AfterUpdateDetail($mvc, $id, $Detail)
    {
        // Ако има промяна в детайлите, маркираме журнала че е променен
        if(!empty($id)){
            $mvc->updated[$id] = $id;
        }
    }
    
    
    /**
     * Метод реконтиращ всички документи в посочените дати съдържащи определени сметки
     * Намира всички документи, които имат записи в журнала. Изтриват им се транзакциите
     * и се записват на ново
     * 
     * @param mixed $accSysIds - списък от систем ид-та на сметки
     * @param date $from - от коя дата
     * @param date $to - до коя дата
     * @return int - колко документа са били реконтирани
     */
    private function reconto($accSysIds, $from = NULL, $to = NULL, $types = array())
    {
    	// Дигаме времето за изпълнение на скрипта
    	set_time_limit(1100);
    	
    	// Филтрираме записите в журнала по подадените параметри
    	$to = (!$to) ? dt::today() : $to;
    	$query = acc_JournalDetails::getQuery();
    	acc_JournalDetails::filterQuery($query, $from, $to);
    	
    	$accSysIds = array_values($accSysIds);
    	foreach ($accSysIds as $index => $sysId){
    		$or = ($index == 0) ? FALSE : TRUE;
    		$acc = acc_Accounts::getRecBySystemId($sysId);
    		$query->where("#debitAccId = {$acc->id} OR #creditAccId = {$acc->id}", $or);
    	}
    	
    	if(count($types)){
    		$query->in("docType", $types);
    	}
    	
    	// Групираме записите по документи
    	$query->show('docId,docType,valior');
    	$query->groupBy('docId,docType');
    	
    	$recs = $query->fetchAll();
    	
    	// За всеки запис ако има
    	if(count($recs)){
    		foreach ($recs as $rec){
    			
    			// Ако е в затворен период, пропускаме го
    			$periodState = acc_Periods::fetchByDate($rec->valior)->state;
    			if($periodState == 'closed') continue;
    			
    			// Изтриваме му транзакцията
    			acc_Journal::deleteTransaction($rec->docType, $rec->docId);
    		}
    		
    		// Преизчисляваме баланса
    		cls::get('acc_Balances')->recalc();
    		
    		foreach ($recs as $rec){
    			 
    			// Ако е в затворен период, пропускаме го
    			$periodState = acc_Periods::fetchByDate($rec->valior)->state;
    			if($periodState == 'closed') continue;
    			 
    			// Преконтираме документа
    			acc_Journal::saveTransaction($rec->docType, $rec->docId, FALSE);
    		}
    	}
    	
    	// Засегнатите документи
    	return count($recs);
    }
    
    
    /**
     * Екшън реконтиращ всички документи където участва дадена сметка
     * в даден интервал
     */
    public function act_Reconto()
    {
    	requireRole('admin,ceo');
    	
    	$form = cls::get('core_Form');
    	$form->title = tr("Реконтиране на документи");
    	$form->FLD('from', 'date', 'caption=От,mandatory');
    	$form->FLD('to', 'date', 'caption=До,mandatory');
    	$form->FLD('accounts', 'acc_type_Accounts', 'caption=Сметки');
    	$form->FLD('types', 'keylist(mvc=core_Classes)', 'caption=Документи');
    	$form->setSuggestions('types', core_Classes::getOptionsByInterface('acc_TransactionSourceIntf', 'title'));
    	
    	$form->input();
    	
    	if($form->isSubmitted()){
    		$rec = &$form->rec;
    		
    		// Трябва баланса да е преизчислен за да продължим
    		if(core_Locks::isLocked('RecalcBalances')){
    			
    			return followRetUrl(NULL, tr("Баланса се преизчислява в момента, моля изчакайте"));
    		}
    		
    		if($rec->from > $rec->to){
    			$form->setError('from', 'Началната дата трябва да е по-малка от крайната');
    		}
    		
    		if(empty($rec->accounts) && empty($rec->types)){
    			$form->setError('accounts,types', 'Трябва да е избрано, поне едно от двете полета');
    		}
    		
    		if(!$form->gotErrors()){
    			$accounts = keylist::toArray($rec->accounts);
    			$types = arr::make($rec->types, TRUE);
    			foreach ($accounts as $id => $accId){
    				$accounts[$id] = acc_Accounts::fetchField($accId, 'systemId');
    			}
    			$res = $this->reconto($accounts, $rec->from, $rec->to, $types);
    			
    			return followRetUrl(NULL, tr("|Реконтирани са|* {$res} |документа|*"));
    		}
    	}
    	
    	$form->toolbar->addSbBtn('Реконтиране', 'save', 'ef_icon = img/16/arrow_refresh.png, title = Реконтиране');
    	$form->toolbar->addBtn('Отказ', getRetUrl(), 'ef_icon = img/16/close16.png, title=Прекратяване на действията');
    	
    	$tpl = $this->renderWrapping($form->renderHtml());
    	
    	return $tpl;
    }
}