<?php
/**
 * Помощен клас-имплементация на интерфейса acc_TransactionSourceIntf за класа deals_AdvanceReports
 *
 * @category  bgerp
 * @package   deals
 * @author    Ivelin Dimov <ivelin_pdimov@abv.com>
 * @copyright 2006 - 2014 Experta OOD
 * @license   GPL 3
 * @since     v 0.1
 * 
 * @see acc_TransactionSourceIntf
 *
 */
class deals_transaction_AdvanceReport
{
    /**
     * 
     * @var deals_AdvanceReports
     */
    public $class;
    
    
    /**
     * @param int $id
     * @return stdClass
     * @see acc_TransactionSourceIntf::getTransaction
     */
    public function finalizeTransaction($id)
    {
    	$rec = $this->class->fetchRec($id);
    	$rec->state = 'active';
    
    	$this->class->save($rec);
    }
    
    
    /**
     *  Имплементиране на интерфейсен метод (@see acc_TransactionSourceIntf)
     *  Създава транзакция която се записва в Журнала, при контирането
     */
    public function getTransaction($id)
    {
    	// Извличаме записа
    	expect($rec = $this->class->fetchRec($id));
    	expect($origin = $this->class->getOrigin($rec));
    	$originRec = $origin->fetch();
    	 
    	$entries = array();
    	$creditArr = array($rec->creditAccount, array($originRec->contragentClassId, $originRec->contragentId), array($origin->className, $origin->that), array('currency_Currencies', $rec->currencyId));
    	 
    	/*
    	 * Дебитираме разходната сметка, кредитираме сметката от фин. сделката
    	*/
    	$vatAmount = 0;
    	$dQuery = deals_AdvanceReportDetails::getQuery();
    	$dQuery->where("#reportId = '{$rec->id}'");
    	while($dRec = $dQuery->fetch()){
    		$arr = array();
    
    		$vatAmount += $dRec->amount * $dRec->vat;
    		$arr['amount'] = round($dRec->amount, 2);
    		$pInfo = cat_Products::getProductInfo($dRec->productId);
    
    		$debitArr = array();
    		$debitArr[] = ($pInfo->meta['canStore']) ? '601' : '602';
    		$debitArr[] = array('cat_Products', $dRec->productId);
    		$debitArr['quantity'] = $dRec->quantity;
    		$arr['debit'] = $debitArr;
    
    		$creditArr['quantity'] = round($dRec->amount / $rec->rate, 2);
    
    		$arr['credit'] = $creditArr;
    
    		$entries[] = $arr;
    	}
    	
    	$vatAmount = round($vatAmount, 2);
    	$entries[] = array(
    			'amount' => $vatAmount, // В основна валута
    			'credit' => array(
    					$rec->creditAccount,
    					array($originRec->contragentClassId, $originRec->contragentId),   // Перо 1 - Клиент
    					array($origin->className, $origin->that), // Перо 2 - Фин. сделка
    					array('currency_Currencies', acc_Periods::getBaseCurrencyId($rec->valior)), // Перо 3 - Валута
    					'quantity' => $vatAmount,
    			),
    			 
    			'debit' => array('4530', array($origin->className, $origin->that),),
    	);
    	 
    	// Подготвяме информацията която ще записваме в Журнала
    	$result = (object)array(
    			'reason' => $this->class->getRecTitle($rec), // основанието за ордера
    			'valior' => $rec->valior,   // датата на ордера
    			'entries' => $entries);
    	 
    	return $result;
    }
}