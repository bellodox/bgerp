<?php 


/**
 * Модул за записване на всички номера
 *
 * @category  bgerp
 * @package   callcenter
 * @author    Yusein Yuseinov <yyuseinov@gmail.com>
 * @copyright 2006 - 2013 Experta OOD
 * @license   GPL 3
 * @since     v 0.1
 */
class callcenter_Numbers extends core_Manager
{
    
    
    /**
     * Заглавие на модела
     */
    var $title = 'Номера';
    
    
    /**
     * Кой има право да чете?
     */
    var $canRead = 'user';
    
    
    /**
     * Кой има право да променя?
     */
    var $canEdit = 'admin, ceo';
    
    
    /**
     * Кой има право да добавя?
     */
    var $canAdd = 'admin, ceo';
    
    
    /**
     * Кой има право да го види?
     */
    var $canView = 'user';
    
    
    /**
     * Кой може да го разглежда?
     */
    var $canList = 'user';
    
    
    /**
     * Кой има право да го изтрие?
     */
    var $canDelete = 'admin, ceo';
    
    
    /**
     * Кои полета да се извличат при изтриване
     */
    var $fetchFieldsBeforeDelete = 'id,number';
    
    
    /**
     * Плъгини за зареждане
     */
    var $loadList = 'callcenter_Wrapper, plg_RowTools, plg_Printing, plg_Search, plg_Sorting, plg_saveAndNew, plg_Created';

    
    /**
     * Поле за търсене
     */
    var $searchFields = 'number, type';
    
    
    /**
     * Полета, които ще се показват в листов изглед
     */
    public $listFields = 'id, number, type, contragent=Визитка';
    
    
	/**
     * Описание на модела (таблицата)
     */
    function description()
    {
        
        $this->FLD('number', 'drdata_PhoneType', 'caption=Номер, mandatory, width=100%, silent');
        $this->FLD('type', 'enum(tel=Телефон, mobile=Мобилен, fax=Факс, internal=Вътрешен)', 'caption=Тип');
        $this->FLD('classId', 'key(mvc=core_Classes, select=name)', 'caption=Визитка->Клас');
        $this->FLD('contragentId', 'int', 'caption=Визитка->Номер');
        
        $this->FNC('contragent', 'varchar', 'caption=Контрагент');
        
        $this->setDbUnique('number, type, classId, contragentId');
    }
    
    
    /**
     * След преобразуване на записа в четим за хора вид.
     *
     * @param core_Mvc $mvc
     * @param stdClass $row Това ще се покаже
     * @param stdClass $rec Това е записа в машинно представяне
     */
    static function on_AfterRecToVerbal($mvc, &$row, $rec)
    {
        // Ако има клас
        if ($rec->classId) {
            
            // Инстанция на класа
            $class = cls::get($rec->classId);
            
            // Вземаме записите
            $cardRec = $class->fetch($rec->contragentId);
            
            // Ако класа е инстанция на профилите
            if (($class instanceof crm_Profiles)) {
                
                // Вземаме профила
                $userId = crm_Profiles::fetchField($rec->contragentId, 'userId');
                
                // Вземам линк към профила на отговорника
                $card = crm_Profiles::createLink($userId);
            } else {
                
                // Ако имаме права за сингъла на записа
                if ($class->haveRightFor('single', $cardRec)) {
                    
                    // Линк към сингъла
                    $card = ht::createLink($cardRec->name, array($class, 'single', $rec->contragentId)) ;
                } else {
                    
                    // Вземам линк към профила на отговорника
                    $inChargeLink = crm_Profiles::createLink($cardRec->inCharge);
                    
                    $card = $class->getVerbal($cardRec, 'name') . " « {$inChargeLink}";
                }
            }
        }
        
        // Добавяме линка в контрагента
        $row->contragent = $card;
    }
    
    
    /**
     * Добавяме посочения номер в модела
     * 
     * @param array $numbersArr - Масив с номерата, които ще добавяме - tel, fax, mobile
     * @param int $classId - id на класа
     * @param int $docId - id на документа
     * 
     * @return array $retArr - Масив с броя на изтрите и добавените резултати
     */
    public static function addNumbers($numbersArr, $classId, $docId)
    {
        // Резултата, който ще връщаме
        $retArr = array();
        
        // Инстанция на текущия клас
        $me = cls::get(get_called_class());
        
        // Вземаме всички записи за документа и класа
        $existRecsArr = static::getRecsForDoc($classId, $docId);
        
        // Обхождаме записите
        foreach ((array)$numbersArr as $type => $numberArr) {
            
            // Обхождаме номерата
            foreach ((array)$numberArr as $number) {
                
                // Вземаме детайлна информация за номерата
                $numberDetArr = drdata_PhoneType::toArray($number);
                
                // Обхождаме масива с номера
                foreach ($numberDetArr as $numberDetObj) {
                    
                    // Вземаме номера, като стринг
                    $numStr = static::getNumStrFromObj($numberDetObj);
                    
                    // Ако е бил записан
                    if ($numRec = $existRecsArr[$numStr]) {
                     
                        // Обновяваме записите
                        $me->savedItems[$numRec->id] = $numRec->id;
                        
                        // Премахваме от масива
                        unset($existRecsArr[$numStr]);
                    } else {
                     
                        // Ако е нов
                         
                        // Ако е факс
                        if ($type == 'fax') {
                            $fType = 'fax';
                        } else {
                            // Ако е мобилине
                            if ($numberDetObj->mobile) {
                                $fType = 'mobile';
                            } else {
                                $fType = 'tel';
                            }
                        }
                        
                        // Създаваме записа
                        $nRec = new stdClass();
                        $nRec->number = $numStr;
                        $nRec->type = $fType;
                        $nRec->classId = $classId;
                        $nRec->contragentId = $docId;
                        
                        // Записваме
                        $saved = static::save($nRec, NULL, 'IGNORE');
                        
                        // Ако записа е бил успешен
                        if ($saved) {
                            
                            // Увеличаваме с единица
                            $retArr['saved']++;
                        }
                    }
                }
            }
        }

        // Ако номера е бил изтрит, премахваме
        foreach ((array)$existRecsArr as $num => $rec) {
            
            // Изтриваме
            $deleted = static::delete(array("#classId = '[#1#]' AND #contragentId = '[#2#]' AND #number = [#3#]", $classId, $docId, $num));
            
            // Ако е бил изтрит успешно
            if ($deleted) {
                
                // Увеличаваме броя на изтритите с едница
                $retArr['deleted']++;
            }
        }
        
        return $retArr;
    }
    
    
    /**
     * Връща записите за съответния документ от класа
     * 
     * @param int $classId - id на класа
     * @param int $docId - id на документ
     * 
     * @return array - Масив с номерата и записите
     */
    static function getRecsForDoc($classId, $docId)
    {
        // Масива, който ще връщаме
        $resArr = array();
        
        // Ако няма подаден клас или документ връщаме
        if (!$classId || !$docId) return $resArr;
        
        // Вземаме записитеи за класа и документа
        $query = static::getQuery();
        $query->where(array("#classId = '[#1#]'", $classId));
        $query->where(array("#contragentId = '[#1#]'", $docId));
        
        // Обхождаме всички резултати
        while ($rec = $query->fetch()) {
            
            // Добавяме номера и записа в масива
            $resArr[$rec->number] = $rec;
        }
        
        return $resArr;
    }
    
    
    /**
     * Добавяме посочения номер в модела като вътрешен
     * 
     * @param array $numbersArr - Масив с номерата, които ще добавяме - tel, fax, mobile
     * @param int $classId - id на класа
     * @param int $docId - id на документа
     */
    public static function addInternalNumbers($numbers, $classId, $docId)
    {
        // Масив с номерата
        $numberArr = arr::make($numbers);
        
        // Обхождаме номерата
        foreach ((array)$numberArr as $num) {
                    
            // Създаваме записа
            $nRec = new stdClass();
            $nRec->number = $num;
            $nRec->type = 'internal';
            $nRec->classId = $classId;
            $nRec->contragentId = $docId;
            
            // Записваме, ако няма такъв запис
            static::save($nRec, NULL, 'IGNORE');
        }
    }
    
    
    /**
     * 
     */
    static function on_AfterPrepareListFilter($mvc, $data)
    {
        // В хоризонтален вид
        $data->listFilter->view = 'horizontal';
        
        // Добавяме бутон
        $data->listFilter->toolbar->addSbBtn('Филтрирай', 'default', 'id=filter', 'ef_icon = img/16/funnel.png');
        
        // Показваме само това поле. Иначе и другите полета 
        // на модела ще се появят
        $data->listFilter->showFields = 'search';
        
        $data->listFilter->input('search', 'silent');
    }
    
    
	/**
     * 
     */
    function on_AfterSave($mvc, $id, $rec)
    {
        // Добавяме id' тата на записаните данни
        $mvc->savedItems[$rec->id] = $rec->id;
    }
    
    
    /**
     * 
     */
    static function on_AfterDelete($mvc, &$res, $query)
    {
        foreach ($query->getDeletedRecs() as $rec) {
            $mvc->deletedItems[$rec->id] = $rec;
        }
    }
    
    /**
     * Връща подадения номер като стринг като пълен номер
     * 
     * @param string $number - Номера
     * 
     * @return string $numStr - Номера в пълен формат
     */
    static function getNumberStr($number)
    {
        // Вземаме номера
        $numArr = drdata_PhoneType::toArray($number);
        
        // Ако има номер
        if ($numArr[0]) {
            
            // Връща пълния номер от подадени обект
            $numStr = static::getNumStrFromObj($numArr[0]);
        } else {
            
            // Връщаме подадени стринг
            $numStr = $number;
        }
        
        return $numStr;
    }
    
    
    /**
     * Връща пълния номер от подадени обект
     * 
     * @param object $numObj - Обект, генериран от drdata_PhoneType
     * 
     * @return string $callerNumStr - Стринг с пълния номер
     */
    static function getNumStrFromObj($numObj)
    {
        // Ако не е обект, връщаме
        if (!is_object($numObj)) return $numObj;
        
        // Генерираме пълния номер
        $callerNumStr = '+' . $numObj->countryCode . $numObj->areaCode . $numObj->number;
        
        return $callerNumStr;
    }
    
    
	/**
     * При спиране на скрипта
     */
    function on_Shutdown($mvc)
    {
        // Ако имаме променини или добавени номера
        if(count((array)$mvc->savedItems)) {
            
            // Обхождаме масива
            foreach ((array)$mvc->savedItems as $id) {
                
                // Вземаме записа
                $rec = $mvc->fetch($id);
                
                // Ако е вътрешен
                if ($rec->type == 'internal') {
                    
                    // Записваме номера
                    $numStr = $rec->number;
                } else {
                    
                    // Вземаме пълния номер
                    $numStr = static::getNumberStr($rec->number);
                }
                
                // Обновяваме записите в Централата
                callcenter_Talks::updateRecsForNum($numStr);
                callcenter_Fax::updateRecsForNum($numStr);
                callcenter_SMS::updateRecsForNum($numStr);
            }
        }
        
        // Ако имаме изтрити номера
        if(count((array)$mvc->deletedItems)) {
            
            // Обхождаме масива
            foreach ((array)$mvc->deletedItems as $id => $rec) {
                
                // Ако е вътрешен
                if ($rec->type == 'internal') {
                    
                    // Записваме номера
                    $numStr = $rec->number;
                } else {
                    
                    // Вземаме пълния номер
                    $numStr = static::getNumberStr($rec->number);
                }
                
                // Обновяваме записите в Централата
                callcenter_Talks::updateRecsForNum($numStr);
                callcenter_Fax::updateRecsForNum($numStr);
                callcenter_SMS::updateRecsForNum($numStr);
            }
        }
    }
    
    
    /**
     * Връща масив със записите за номерата
     * 
     * @param string $number - Номера
     * @param string $type - Типа на номера - tel, mobile, fax, internal
     * @param boolean $all - Дали да се върнат всичките или само последния
     * 
     * @return array - Масив с запсите
     */
    static function getRecForNum($number, $type=FALSE, $all=FALSE)
    {
        // Резултата, който ще връщаме
        $res = array();
        
        // Вземаме номера, на инициатора
        $numStr = static::getNumberStr($number);
        
        // Вземаме последния номер, който сме регистрирали
        $query = static::getQuery();
        $query->where(array("#number = '[#1#]'", $numStr));
        $query->orderBy('id', 'DESC');
        
        // Ако е зададен типа
        if ($type) {
            
            // Добавяме типа в клаузата
            $query->where(array("#type = '[#1#]'", $type));
        }
        
        // Ако не ни трябват всичките, а последния
        if (!$all) {
            
            // Лимит 1
            $query->limit(1);
        }
        
        // Обхождаме резултатите
        while ($rec = $query->fetch()) {
            
            // Добавяме в масива
            $res[] = $rec;
        }
        
        return $res;
    }
    
    
    /**
     * 
     */
    static function on_AfterPrepareListToolbar($mvc, &$data)
    {
        // Променяме името на бутоно
        $data->toolbar->buttons['btnAdd']->title = 'Нов вътрешен';
    }
    
    
	/**
     * Преди показване на форма за добавяне/промяна.
     *
     * @param core_Manager $mvc
     * @param stdClass $data
     */
    public static function on_AfterPrepareEditForm($mvc, &$data)
    {
        // Добавяне функционално поле и скриваме ненужните
        $form = $data->form;
        $form->FNC('userId', 'user', 'caption=Лице, width=100%, mandatory, input');
        $form->setField('type', 'input=none');
        $form->setField('contragentId', 'input=none');
        $form->setField('classId', 'input=none');
        
        // Ако добавяме нов
        if (!$form->rec->id) {
            
            // Добавяме титлата на формата
            $form->title = "Добавяне на вътрешен номер";
        } else {
            
            // Да е избран потребителя, който редактираме
            $userId = crm_Profiles::fetchField($form->rec->contragentId, 'userId');
            $form->setDefault('userId', $userId);
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
        // Ако формата е изпратена успешно
        if ($form->isSubmitted()) {
            $rec = $form->rec;
            
            // Ако има избран потреибител
            if ($form->rec->userId) {
                
                // Вземаме id' то на профила
                $profileId = crm_Profiles::fetchField("#userId = '{$form->rec->userId}'");
            }
            
            // Очакваме да има такова id
            expect($profileId);
            
            // Задаваме id на контрагент
            $rec->contragentId = $profileId;
            
            // Ако създаваме нов
            if (!$form->rec->id) {
                
                // Типа да е вътрешен
                $rec->type = 'internal';
                
                // Класа да е на профилите
                $rec->classId = core_Classes::fetchIdByName('crm_Profiles');
            }
        }
        
        // Ако записа не е униклен
        if (!$mvc->isUnique($rec)) {
            
            // Сетваме грешка
            $form->setError('number', 'За този потребител е добавен същия номер');
        }
    }
    
    
	/**
     * Изпълнява се след подготовката на ролите, които могат да изпълняват това действие.
     *
     * @param core_Mvc $mvc
     * @param string $requiredRoles
     * @param string $action
     * @param stdClass $rec
     * @param int $userId
     */
    public static function on_AfterGetRequiredRoles($mvc, &$requiredRoles, $action, $rec = NULL, $userId = NULL)
    {
        // Ако ще добаме, променяме или изтриваме запис
        if ($rec && ($action == 'add' || $action == 'edit' || $action == 'delete')) {
            
            // Ако типа не е вътрешен
            if ($rec->type && $rec->type != 'internal') {
                
                // Не може да се променя
                $requiredRoles = 'no_one';
            }
        }
    }
    
    
    /**
     * Връща масив с id' та на потребители, които използват този номер
     * 
     * @param integer $num - Вътрешен номер
     * 
     * @return array $userArr - Масив с id' та на потребители, които използват този номер
     */
    static function getUserForNum($num)
    {
        // id на профилите
        $profileId = core_Classes::getId('crm_Profiles');
        
        // Вземаме всички вътрешни записи, за съответния номер
        $query = static::getQuery();
        $query->where(array("#number = '[#1#]'", $num));
        $query->where("#type = 'internal'");
        $query->where("#classId = '{$profileId}'");
        
        $userArr = array();
        
        // Обхождаме резултата
        while ($rec = $query->fetch()) {
            
            // Ако няма контрагент, продължаваме
            if (!$rec->contragentId) continue ;
            
            // Вземаме id' то на потребителя
            $userId = crm_Profiles::fetchField($rec->contragentId, 'userId');
            
            // Добавяме в масива
            $userArr[$userId] = $userId;
        }
        
        return $userArr;
    }
    
    
    /**
     * Връща вътрешните номера за подадените потребители
     * 
     * @param array $usersArr - Масив с потребители
     * 
     * @return array $numbersArr - Масив с номерата
     */
    static function getInternalNumbersForUsers($usersArr=NULL)
    {
        // Ако не са подадени потребители
        if (!$usersArr) {
            
            // Използваме текущия
            $currUserId = core_Users::getCurrent();
            $usersArr[$currUserId] = $currUserId;
        }
        
        // За всеки случай преобразуваме в масив
        $usersArr = arr::make($usersArr);
        
        // Масив с id' тата на профилите
        $profileIdsArr = array();
        
        // Обхождаме потребителите
        foreach ($usersArr as $user) {
            
            // Вземаме id' тата на профилите
            $profileId = crm_Profiles::fetchField(array("#userId = '[#1#]'", $user), 'id');
            
            // Добавяме в масива
            $profileIdsArr[$profileId] = $profileId;
        }
        
        // Вземаме всички вътрешни номера за съответните профии
        $query = static::getQuery();
        $query->where("#type = 'internal'");
        $profileClassId = core_Classes::getId('crm_Profiles');
        $query->where("#classId = '{$profileClassId}'");
        $query->orWhereArr('contragentId', $profileIdsArr);
        $numbersArr = array();
        
        // Обхождаме резултата
        while($rec = $query->fetch()) {
            
            // Добавяме в масива
            $numbersArr[$rec->id] = $rec->number;
        }
        
        return $numbersArr;
    }
    
    
    /**
     * Обновява номерата за потребителите от указателя
     */
    function act_Update()
    {
        // Изискваме да има роля admin
        requireRole('admin');
        
        // Вземаме всички записи за потребителите
        $Person = cls::get('crm_Persons');
        $pQuery = $Person->getQuery();
        $pQuery->where("1=1");
        
        // Обхождаме резултатите
        while ($pRec = $pQuery->fetch()) {
            
            // Обновяваме
            $pRecArr = (array)$Person->updateNumbers($pRec);
            
            // Броя на записаните номера
            $savedNums += $pRecArr['saved'];
            
            // Броя на изтритите номера
            $delNums += $pRecArr['deleted'];
        }
        
        // Вземаме всички записи за фирмите
        $Company = cls::get('crm_Companies');
        $cQuery = $Company->getQuery();
        $cQuery->where("1=1");
        
        // Обхождаме резултатите
        while ($cRec = $cQuery->fetch()) {
            
            // Обновяваме
            $cRecArr = (array)$Company->updateNumbers($cRec);
            
            // Броя на записаните номера
            $savedNums += $cRecArr['saved'];
            
            // Броя на изтритите номера
            $delNums += $cRecArr['deleted'];
        }
        
        // Ако има записани номера, добавяме съответния текст в резултата
        if ($savedNums) {
            
            if ($savedNums == 1) {
                $res = tr("Добавен e|* {$savedNums} |номер");
            } else {
                $res = tr("Добавени са|* {$savedNums} |номера");
            }
        }
        
        // Ако има изтрити номера, добавяме съответния текст в резултата
        if ($delNums) {
            
            // Ако има записани номера
            if ($res) {
                
                // Добавяме празен ред
                $res .= "\n";
            }
            
            if ($delNums == 1) {
                $res .= tr("Изтрит e|* {$delNums} |номер");
            } else {
                $res .= tr("Изтрити са|* {$delNums} |номера");
            }
        }
        
        // Вземаме URL' то където ще редиректваме
        $retUrl = getRetUrl();
        
        // Ако няма URL, създаваме
        if (!$retUrl) {
            $retUrl = array('callcenter_Numbers', 'list');
        }
        
        // Ако няме резултата
        if (!$res) {
            
            // Добавяме текста
            $res = tr('Няма нови номера');
        }
        
        return Redirect($retUrl, FALSE, $res);
    }
}
