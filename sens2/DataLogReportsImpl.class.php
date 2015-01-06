<?php


/**
 * Драйвър за отчет за записи от сензори
 *
 *
 * @category  bgerp
 * @package   sens2
 * @author    Ivelin Dimov <ivelin_pdimov@abv.bg>
 * @copyright 2006 - 2014 Experta OOD
 * @license   GPL 3
 * @since     v 0.1
 */
class sens2_DataLogReportsImpl extends frame_BaseDriver
{
	
	
	/**
	 * Кой може да избира драйвъра
	 */
	public $canSelectSource = 'ceo, sens, admin';
	
	
	
	/**
	 * Интерфейси, които имплементира класа
	 */
	public $interfaces = 'frame_ReportSourceIntf';
	
	
	/**
     * Заглавие
     */
    public  $title = 'Записи на индикаторите';
    
    
    /**
     * Брой редове от сензорите на страница
     */
    public  $listDataRecPerPage = 50;
    
    
    /**
     * Добавя полетата на вътрешния обект
	 * 
	 * @param core_Fieldset $fieldset
     */
    public function addEmbeddedFields(core_Form $form)
    {
    	$form->FLD('from', 'datetime', 'caption=От,mandatory');
    	$form->FLD('to', 'datetime', 'caption=До,mandatory');
    	$form->FLD('indicators', 'keylist(mvc=sens2_Indicators,select=title)', 'caption=Сензори,mandatory');
    }


    /**
     * Коя е най-ранната дата на която може да се активира документа
     */
    public function getEarlyActivation()
    {
    	return $this->innerForm->to;
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
     */
    public function prepareInnerState()
    {
    	$data = new stdClass();
    	$filter = $this->innerForm;
    	
    	$DateTime = cls::get('type_Datetime');
    	$KeyList = cls::get('type_KeyList', array('params' => array('mvc' => 'sens2_Indicators', 'select' => 'title')));
    	 
    	if(!strpos($filter->to, ' ')) {
    		$filter->to .= ' 23:59:59';
    	}
    	 
    	$query = sens2_DataLogs::getQuery();
    	 
    	$query->where(array("#time >= '[#1#]' AND #time <= '[#2#]'", $filter->from, $filter->to));
    	 
    	$query->in("indicatorId", keylist::toArray($filter->indicators));
    	 
    	$data->pager = cls::get('core_Pager', array('itemsPerPage' => $this->listDataRecPerPage));
    	$data->pager->setLimit($query);
    	
    	while($rec = $query->fetch()) {
    		$data->recs[$rec->id] = $rec;
    	}
    	 
    	return $data;
    }


    /**
     * Рендира вградения обект
	 * 
	 * @param stdClass $data
     */
    public function renderEmbeddedData($data)
    {
    	$layout = new ET(getFileContent('sens2/tpl/ReportLayout.shtml'));
    
    	$this->prependStaticForm($layout, 'FORM');
    
    	if(count($data->recs)) {
    		foreach($data->recs as $id => $rec) {
    			$data->rows[$id] = sens2_DataLogs::recToVerbal($rec);
    			$data->rows[$id]->time = str_replace(' ', '&nbsp;', $data->rows[$id]->time);
    		}
    		
    		$this->invoke('AfterPrepareListRows', array($data, $data));
    	}
    		
    	$table = cls::get('core_TableView');
    
    	$layout->append($table->get($data->rows, 'time=Време,indicatorId=Индикатор,value=Стойност'), 'data');
    		
    	if($data->pager){
    		$layout->append($data->pager->getHtml(), 'data');
    	}
    	
    	return $layout;
    }


    /**
     * Можели вградения обект да се избере
     */
    public function canSelectInnerObject($userId = NULL)
    {
    	return core_Users::haveRole($this->canSelectSource, $userId);
    }
    
    
    /**
     * Подготвя формата за въвеждане на данни за вътрешния обект
     *
     * @param core_Form $form
     */
    public function prepareEmbeddedForm(core_Form &$form)
    {
    	
    }
}