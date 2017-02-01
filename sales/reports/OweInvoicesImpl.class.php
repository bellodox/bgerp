<?php



/**
 * Имплементация на 'frame_ReportSourceIntf' за направата на справка
 * по отклоняващи се цени в продажбите
 *
 * @category  bgerp
 * @package   sales
 * @author    Gabriela Petrova <gab4eto@gmail.com>
 * @copyright 2006 - 2015 Experta OOD
 * @license   GPL 3
 * @since     v 0.1
 */
class sales_reports_OweInvoicesImpl extends frame_BaseDriver
{
	
	
	/**
	 * Кой може да избира драйвъра
	 */
	public $canSelectSource = 'ceo,sales';
	
	
	/**
	 * Кои интерфейси имплементира
	 */
	public $interfaces = 'frame_ReportSourceIntf';
	
	
	/**
	 * Заглавие
	 */
	public $title = 'Продажби » Задължения по фактури';
	
	
	/**
	 * Брой записи на страница
	 */
	public $listItemsPerPage = 50;

	
	/**
	 * Добавя полетата на вътрешния обект
	 *
	 * @param core_Fieldset $fieldset
	 */
	public function addEmbeddedFields(core_FieldSet &$form)
	{
		
		$form->FNC('contragentFolderId', 'key(mvc=doc_Folders,select=title)', 'caption=Контрагент,silent,input,mandatory');
		$form->FNC('from', 'date', 'caption=Към дата,silent,input');
		$form->FNC('notInv', 'enum(yes=Да, no=Не)', 'caption=Без нефактурирано,silent,input');
		
		$this->invoke('AfterAddEmbeddedFields', array($form));
	}
	
	
	/**
	 * Подготвя формата за въвеждане на данни за вътрешния обект
	 *
	 * @param core_Form $form
	 */
	public function prepareEmbeddedForm(core_Form &$form)
	{
		$form->setOptions('contragentFolderId', array('' => '') + doc_Folders::getOptionsByCoverInterface('crm_ContragentAccRegIntf'));
		$form->setDefault('from',date('Y-m-01', strtotime("-1 months", dt::mysql2timestamp(dt::now()))));
		
		$this->invoke('AfterPrepareEmbeddedForm', array($form));
	}


	/**
	 * Проверява въведените данни
	 *
	 * @param core_Form $form
	 */
	public function checkEmbeddedForm(core_Form &$form)
	{

	}


	/**
	 * Подготвя вътрешното състояние, на база въведените данни
	 *
	 * @param core_Form $innerForm
	 */
	public function prepareInnerState()
	{
		// Подготвяне на данните
		$data = new stdClass();
		$data->recs = array();
		$data->sum = array();
		$data->contragent = new stdClass();
		
		$data->rec = $this->innerForm;
		$this->prepareListFields($data);
		
		// ако имаме избран книент
		if ($data->rec->contragentFolderId) {
			$contragentCls = doc_Folders::fetchField("#id = {$data->rec->contragentFolderId}", 'coverClass');
			$contragentId = doc_Folders::fetchField("#id = {$data->rec->contragentFolderId}", 'coverId');
			// всичко за контрагента
			$contragentRec = cls::get($contragentCls)->fetch($contragentId);
			// записваме го в датата
			$data->contragent = $contragentRec;
			$data->contragent->titleLink = cls::get($contragentCls)->getShortHyperLink($contragentId);
		}
		
		// търсим всички продажби, които са на този книент и са активни
		$querySales = sales_Sales::getQuery();
	
		// коя е текущата ни валута
		$currencyNow = currency_Currencies::fetchField(acc_Periods::getBaseCurrencyId($data->rec->from),'code');
		$querySales->where("(#contragentClassId = '{$contragentCls}' AND #contragentId = '{$contragentId}') AND (#state = 'active' AND #valior <= '{$data->rec->from}')");		
	
		while ($recSale = $querySales->fetch()) {
	
			$toPaid = 0;
			if ($recSale->amountDelivered !== NULL && $recSale->amountInvoiced !== NULL) {
    			// нефакторираното е разлика на доставеното и фактурираното
    			$data->notInv += $recSale->amountDelivered - $recSale->amountInvoiced;
			}

			// плащаме в датат валутата на сделката
			$data->currencyId = $recSale->currencyId;
		
			// ако имаме едно ниво на толеранс от задължение > на 0,5
			if ($recSale->amountDelivered - $recSale->amountPaid >= '0.5') {
	
				// то ще търсим всички фактури
				// които са в нишката на тази продажба
				// и са активни
				$queryInvoices = sales_Invoices::getQuery();
				$queryInvoices->where("#threadId = '{$recSale->threadId}' AND #state = 'active' AND #date <= '{$data->rec->from}'");
				$queryInvoices->orderBy("#date", "DESC");

				$saleItem = acc_Items::fetchItem('sales_Sales', $recSale->id);
				$contragentItem = acc_Items::fetchItem($contragentCls, $contragentId);
				$currencyItem = acc_Items::fetchItem('currency_Currencies', currency_Currencies::getIdByCode($recSale->currencyId));
				
				$Balance = new acc_ActiveShortBalance(array('from' => $data->rec->from,
				    'to' => $data->rec->from,
				    'accs' => '411',
				    'item1' => $contragentItem->id,
				    'item2' => $saleItem->id,
				    'item3' => $currencyItem->id,
				    'strict' => TRUE,
				    'cacheBalance' => FALSE));
				 
				// Изчлисляваме в момента, какъв би бил крания баланс по сметката в края на деня
				$Balance = $Balance->getBalanceBefore('411');
				$balHistory = acc_ActiveShortBalance::getBalanceHystory(411, $data->rec->from, $data->rec->from, $contragentItem->id, $saleItem->id, $currencyItem->id);

				while ($invRec = $queryInvoices->fetch()){
	
				    // платеното е разлика на достовеното и салдото
				    $paid =  $recSale->amountDelivered - $recSale->amountBl;
				    // сумата на фактурата с ДДС е суматана на факурата и ДДС стойността
				    $amountVat =  $invRec->dealValue + $invRec->vatAmount; 

				    $index = "92|{$contragentItem->id}|{$saleItem->id}|{$currencyItem->id}";
				    
				    $toPaid = $balHistory['summary']['blAmount'];
	
				    if($data->rec->notInv == "yes") {
				        $toPaid = $balHistory['summary']['creditAmount'] - $data->notInv;
				        
				        if($toPaid < 0) {
				            $toPaid = $balHistory['summary']['baseAmount'] - $data->notInv;
				        }
				    }   
		
					// правим рековете
					$data->recs[] = (object) array ("contragentCls" => $contragentCls,
													'contragentId' => $contragentId,
													'eic'=> $contragentRec->vatId,
							                        'currencyId' => $recSale->currencyId,
													'invId'=>$invRec->id,
													'date'=>$invRec->date,
													'number'=>$invRec->number,
					                                'displayRate'=>$invRec->displayRate,
					                                'rate'=>$invRec->rate,
													'amountVat'=> $amountVat,
													'amountRest'=> $toPaid ,
													'paymentState'=>$recSale->paymentState,
							                        'dueDate'=>$invRec->dueDate
					);
				}
			}
		}
		
        foreach ($data->recs as $rec) { 
        	
        	if ($rec->dueDate == NULL || $rec->dueDate < $data->rec->from) { 
        		$rec->amount = $rec->amountRest;
        	} else {
        	   $rec->amount = 0;
        	  
        	}
        
        	if ($rec->currencyId != $currencyNow) { 
                $rec->amountVat /= ($rec->displayRate) ? $rec->displayRate : $rec->rate;
        		$rec->amountVat = round($rec->amountVat, 2);
        		
        		$rec->amountRest /= ($rec->displayRate) ? $rec->displayRate : $rec->rate;
        		$rec->amountRest = round($rec->amountRest, 2);
        		
        		$rec->amount /= ($rec->displayRate) ? $rec->displayRate : $rec->rate;
        		$rec->amount = round($rec->amount, 2);
        		
        	} 

        }
        
        if (isset ($data->notInv)) { 
        	if ($data->currencyId != $currencyNow) {
        		$data->notInv = currency_CurrencyRates::convertAmount($data->notInv, $data->rec->from, $currencyNow, $data->currencyId);
        	}
        }
        
        $data->sum = new stdClass();
        foreach ($data->recs as $currRec) { 
        	
        	$data->sum->amountVat += $currRec->amountVat;
        	$data->sum->toPaid += $currRec->amountRest;
        	$data->sum->currencyId = $currRec->currencyId;

        	if ($currRec->dueDate == NULL || $currRec->dueDate < dt::now()) { 
        		$data->sum->arrears += $currRec->amount;
        	}
        }
    
		return $data;
	}
	
	
	/**
	 * След подготовката на показването на информацията
	 */
	public static function on_AfterPrepareEmbeddedData($mvc, &$res)
	{
		// Подготвяме страницирането
		$data = $res;
		 
		$pager = cls::get('core_Pager',  array('pageVar' => 'P_' .  $mvc->EmbedderRec->that,'itemsPerPage' => $mvc->listItemsPerPage));
		 
		$pager->itemsCount = count($data->recs, COUNT_RECURSIVE);
		$data->pager = $pager;
		
		$data->summary = new stdClass();
		
		$Double = cls::get('type_Double');
		$Double->params['decimals'] = 2; 
		
		if(count($data->recs)){

			foreach ($data->recs as $rec) {
				if(!$pager->isOnPage()) continue;
		
				$row = $mvc->getVerbal($rec);
				
				// добавяме на редовете със сума и съответната валута
				$row->amountVat =
				"<div>
					<span class='cCode'>$data->currencyId</span> 
                    <span>$row->amountVat</span>
				</div>";
				
				$row->amountRest =
				"<div>
					<span class='cCode'>$data->currencyId</span>
				 	<span>$row->amountRest</span>
				</div>";
		
				$dueDate = sales_Invoices::fetchField($rec->invId,dueDate);
				
				if ($dueDate == NULL || $dueDate < dt::now()) { 
					$row->amount = 
					"<div>
					<span class='cCode'>$data->currencyId</span>
					<span style='color:red'>$row->amount</span></b>
					</div>";
				} else {
					unset ($row->amount);
					$data->summary->amountArrears -= $row->amount;
				}
		
				$data->rows[] = $row;
			}
		}

		// правим един служебен ред за нефактурирано
		if($data->notInv && $mvc->innerForm->notInv == "no"){

				$row = new stdClass();
				$row->contragent = "--------";
				$row->eic = "--------";
				$row->date = "--------";
				$row->number = "Нефактурирано";
				$amountVat = $Double->toVerbal($data->notInv);
				$row->amountVat = 
				"<div>
						<span class='cCode'>$data->currencyId</span>
						<span>$amountVat</span></b>
				</div>";
				
				$data->rows[] = $row;

			// ако нямаме обобщен ред
			if (!$data->summary) {
				// си правим един, които да съдържа нефактурираното
				// и валуюитата на сделката
				$data->summary  = (object) array('amountInv' => $Double->toVerbal($data->notInv),
												 'currencyId'=>$data->currencyId
				);
            // ако вече има
			} else {

				// добавяме нафактурираното към сумата на вече намерените 
				$data->summary->amountInv += $data->notInv;
				//$data->summary->amountInv = $Double->toVerbal($data->summary->amountInv);
				$data->summary->currencyId = $data->currencyId;
			}
		}
		
		// правим обобщения ред в разбираем за човека вид
		$data->summary  = (object) array('currencyId' => $data->currencyId,
		    'amountInv' =>$Double->toVerbal($data->sum->amountVat),
		    'amountToPaid' => $Double->toVerbal($data->sum->toPaid),
		    'amountArrears' => $Double->toVerbal($data->sum->arrears)
		);
		

		$res = $data;
	}
	
	
	/**
	 * Връща шаблона на репорта
	 *
	 * @return core_ET $tpl - шаблона
	 */
	public function getReportLayout_()
	{
		$tpl = getTplFromFile('sales/tpl/OweInvoiceLayout.shtml');
		 
		return $tpl;
	}
	
	
	/**
	 * Рендира вградения обект
	 *
	 * @param stdClass $data
	 */
	public function renderEmbeddedData(&$embedderTpl, $data)
	{

		if(empty($data)) return;
  
    	$tpl = $this->getReportLayout();
    	
    	$tpl->replace($this->getReportTitle(), 'TITLE');

    	$tpl->replace($data->contragent->titleLink, 'contragent');
    	$tpl->replace($data->contragent->vatId, 'eic');
    	
    	$from = dt::mysql2verbal($data->rec->from, 'd.m.Y');
    	$tpl->replace($from, 'from');
    	
    	$tpl->placeObject($data->rec);

    	$f = $this->getFields();
    	
    	$table = cls::get('core_TableView', array('mvc' => $f));
    	$tpl->append($table->get($data->rows, $data->listFields), 'CONTENT');

        if (count($data->summary) ) {

	       $data->summary->colspan = count($data->listFields)-3;
	       $afterRow = new core_ET("<tr  style = 'background-color: #eee'><td colspan=[#colspan#]><b>" . tr('ОБЩО') . "</b></td><td style='text-align:right'><span class='cCode'>[#currencyId#]</span>&nbsp;<b>[#amountInv#]</b></td><td style='text-align:right'><span class='cCode'>[#currencyId#]</span>&nbsp;<b>[#amountToPaid#]</b></td><!--ET_BEGIN contragent--><td style='text-align:right;color:red'><span class='cCode'>[#currencyId#]</span>&nbsp;<b>[#amountArrears#]</b></td></tr>");
	    		
	       $afterRow->placeObject($data->summary);

        }

    	if (count($data->rows)){
    		$tpl->append($afterRow, 'ROW_AFTER');
    	}
    	
    	if($data->pager){
    		$tpl->append($data->pager->getHtml(), 'PAGER');
    	}
		
		$embedderTpl->append($tpl, 'data');
	}
	
	
	/**
	 * Подготвя хедърите на заглавията на таблицата
	 */
	protected function prepareListFields_(&$data)
	{
		$data->listFields = array(
				'date' => 'Дата',
		        'dueDate' => 'Падеж',
				'number' => 'Номер',
				'amountVat' => 'Сума',
				'amountRest' => 'Остатък',
				'amount' => 'Просрочие',
				//'paymentState' => 'Състояние',
		);
	}
	
	
	/**
	 * Вербалното представяне на ред от таблицата
	 */
	private function getVerbal($rec)
	{
		$RichtextType = cls::get('type_Richtext');
		$Double = cls::get('type_Double');
		$Double->params['decimals'] = 2;
		$VatType = cls::get('drdata_VatType');
	
		$row = new stdClass();

		$row->contragent = cls::get($rec->contragentCls)->getShortHyperLink($rec->contragentId);
		$row->eic = $VatType->toVerbal($rec->eic) ;
		
		if ($rec->date) {
	    	$row->date = dt::mysql2verbal($rec->date, 'd.m.Y');
		}
		
		if ($rec->dueDate) {
		    $row->dueDate = dt::mysql2verbal($rec->dueDate, 'd.m.Y');
		}
	    
		if ($rec->number) {
			$number = str_pad($rec->number, '10', '0', STR_PAD_LEFT);
			$url = toUrl(array('sales_Invoices','single', $rec->invId),'absolute');
			$row->number = ht::createLink($number,$url,FALSE, array('ef_icon' => 'img/16/invoice.png'));
		}

		$row->amountVat = $Double->toVerbal($rec->amountVat);
		$row->amountRest = $Double->toVerbal($rec->amountRest);
	    $row->amount = $Double->toVerbal($rec->amount); 

	    $state = array('pending' => "Чакащо", 'overdue' => "Просроченo", 'paid' => "Платенo", 'repaid' => "Издължено");
	 
	    $row->paymentState = $state[$rec->paymentState];
	
		return $row;
	}
	
	
	/**
	 * Скрива полетата, които потребител с ниски права не може да вижда
	 *
	 * @param stdClass $data
	 */
	public function hidePriceFields()
	{
		$innerState = &$this->innerState;

		unset($innerState->recs);
	}
	
	
	/**
	 * Коя е най-ранната дата на която може да се активира документа
	 */
	public function getEarlyActivation()
	{
		$activateOn = "{$this->innerForm->to} 23:59:59";
		 
		return $activateOn;
	}
	
	
	/**
	 * Връща дефолт заглавието на репорта
	 */
	public function getReportTitle()
	{
		$explodeTitle = explode(" » ", $this->title);
		 
		$title = tr("|{$explodeTitle[1]}|*");
	
		return $title;
	}


	/**
	 * Ако имаме в url-то export създаваме csv файл с данните
	 *
	 * @param core_Mvc $mvc
	 * @param stdClass $rec
	 */
	public function exportCsv()
	{

         $conf = core_Packs::getConfig('core');

         if (count($this->innerState->recs) > $conf->EF_MAX_EXPORT_CNT) {
             redirect(array($this), FALSE, "|Броят на заявените записи за експорт надвишава максимално разрешения|* - " . $conf->EF_MAX_EXPORT_CNT, 'error');
         }

         $csv = "";

         $rowContragent = "Клиент: " . $this->innerState->contragent->name ."\n";
         $rowContragent .=  "ЗДДС № / EIC: " . $this->innerState->contragent->vatId."\n";
         $rowContragent .=  "Към дата: " . $this->innerForm->from;

         $fields = $this->getFields();
         $exportFields = $this->innerState->listFields;
         
         if(count($this->innerState->recs)) {
             foreach ($this->innerState->recs as $rec) {
                 $state = array('pending' => "Чакащо", 'overdue' => "Просроченo", 'paid' => "Платенo", 'repaid' => "Издължено");
                 
                 $rec->paymentState = $state[$rec->paymentState];
             }
             
             $csv = csv_Lib::createCsv($this->innerState->recs, $fields, $exportFields);
			 $csv = $rowContragent. "\n" . $csv;
	    } 

        return $csv;
	}
	
	
	/**
	 * Ще се експортирват полетата, които се
	 * показват в табличния изглед
	 *
	 * @return array
	 * @todo да се замести в кода по-горе
	 */
	protected function getFields_()
	{
	    // Кои полета ще се показват
	    $f = new core_FieldSet;
   
    	$f->FLD('date', 'date');
    	$f->FLD('dueDate', 'date');
    	$f->FLD('number', 'int');
    	$f->FLD('amountVat', 'double');
    	$f->FLD('amountRest', 'double');
    	$f->FLD('amount', 'double');
    	$f->FLD('paymentState', 'varchar');
	
	    return $f;
	}
}