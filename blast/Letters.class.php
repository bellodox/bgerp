<?php 


/**
 * Циркулярни писма
 *
 *
 * @category  bgerp
 * @package   blast
 * @author    Yusein Yuseinov <yyuseinov@gmail.com>
 * @copyright 2006 - 2013 Experta OOD
 * @license   GPL 3
 * @since     v 0.1
 * @see       https://github.com/bgerp/bgerp/issues/148
 */
class blast_Letters extends core_Master
{
    
    
   /**
     * Име на папката по подразбиране при създаване на нови документи от този тип.
     * Ако стойноста е 'FALSE', нови документи от този тип се създават в основната папка на потребителя
     */
    var $defaultFolder = 'Циркулярни писма';
	
    
    /**
     * Наименование на единичния обект
     */
    var $singleTitle = "Циркулярно писмо";
    
    
    /**
     * Икона за единичния изглед
     */
    var $singleIcon = 'img/16/letter.png';
    
    
    /**
     * Абревиатура
     */
    var $abbr = 'Let';
    
    
    /**
     * Полето "Заглавие" да е хипервръзка към единичния изглед
     */
    var $rowToolsSingleField = 'subject';
    
    
    /**
     * Заглавие
     */
    var $title = "Циркулярни писма";
    
    
    /**
     * Кой има право да чете?
     */
    var $canRead = 'admin, blast';
    
    
    /**
     * Кой има право да променя?
     */
    var $canEdit = 'admin, blast';
    
    
    /**
     * Кой има право да добавя?
     */
    var $canAdd = 'admin, blast';
    
    
    /**
     * Кой може да го види?
     */
    var $canView = 'admin, blast';
    
    
    /**
     * Кой може да го разглежда?
     */
    var $canList = 'admin, blast';
    
    
    /**
     * Кой може да го изтрие?
     */
    var $canDelete = 'no_one';
    
    
    /**
     * Кой може да праша информационните съобщения?
     */
    var $canBlast = 'admin, blast';
    
    
    /**
     * Какви интерфейси поддържа този мениджър
     */
    var $interfaces = 'email_DocumentIntf';
    
    
    /**
     * Плъгините и враперите, които ще се използват
     */
    var $loadList = 'blast_Wrapper, plg_State, plg_RowTools, plg_Rejected, plg_Printing, doc_DocumentPlg, bgerp_plg_Blank';
    
    
    /**
     * Дали може да бъде само в началото на нишка
     */
    var $onlyFirstInThread = TRUE;
    
    
    /**
     * Полета, които ще се показват в листов изглед
     */
    var $listFields = 'id, subject, listId, numLetters';
    
    
    /**
     * Детайла, на модела
     */
    var $details = 'blast_LetterDetails';
    
    
    /**
     * Нов темплейт за показване
     */
    var $singleLayoutFile = 'blast/tpl/SingleLayoutLetters.shtml';
    
    
    /**
     * Данните на получателя
     */
    var $userDetails = NULL;
    
    
    /**
     * Шаблона на писмото
     */
    var $letterTemp = NULL;
    
    /**
     * Групиране на документите
     */
    var $newBtnGroup = "2.3|Циркулярни";
    
    
    /**
     * 
     */
    var $cloneFields = 'listId, subject, body, numLetters, template, recipient, attn, country, pcode, place, address,position';

    
    /**
     * Описание на модела
     */
    function description()
    {
        $this->FLD('listId', 'key(mvc=blast_Lists, select=title)', 'caption=Списък, mandatory');
        $this->FLD('subject', 'richtext(rows=3)', 'caption=Заглавие, width=100%, mandatory, width=100%');
        $this->FLD('body', 'richtext', 'caption=Текст, oldFieldName=text, mandatory, width=100%');
        $this->FLD('numLetters', 'int(min=1, max=100)', 'caption=Печат, mandatory, input=none, hint=Колко писма ще се печатат едновременно');
        $this->FLD('template', 'enum(triLeft=3 части - ляво,
            triRight=3 части - дясно, oneRightUp = 1 част горе - дясно)', 'caption=Шаблон, mandatory');
        
        $this->FLD('attn', 'varchar', 'caption=Адресант->Лице, width=100%');
        $this->FLD('position', 'varchar', 'caption=Адресант->Длъжност, width=100%');
        $this->FLD('recipient', 'varchar', 'caption=Адресант->Фирма, width=100%');
        $this->FLD('address', 'varchar', 'caption=Адресант->Адрес, width=100%');
        $this->FLD('pcode', 'varchar', 'caption=Адресант->П. код, width=100%');
        $this->FLD('place', 'varchar', 'caption=Адресант->Град/с, width=100%');
        $this->FLD('country', 'varchar', 'caption=Адресант->Държава, width=100%');
    }

    /**
     * Проверка дали нов документ може да бъде добавен в
     * посочената папка като начало на нишка
     *
     * @param int $folderId - id на папката
     * @return boolean
     */
    public static function canAddToFolder($folderId)
    {
        // Името на класа
        $coverClassName = strtolower(doc_Folders::fetchCoverClassName($folderId));

        // Ако не е папка проект или контрагент, не може да се добави
        if (($coverClassName != 'doc_unsortedfolders') && 
            ($coverClassName != 'crm_persons') &&
            ($coverClassName != 'crm_companies')) return FALSE;
    }
    
    
    /**
     * Изпълнява се след подготвяне на формата за редактиране
     */
    static function on_AfterPrepareEditForm($mvc, &$res, &$data)
    {
        //Добавя в лист само списъци на лица и фирми
        $query = blast_Lists::getQuery();
        $query->where("#keyField = 'names' OR #keyField = 'company' OR #keyField = 'uniqId'");
        while ($rec = $query->fetch()) {
            $files[$rec->id] = blast_Lists::getVerbal($rec, 'title');
        }
        
        //Ако няма нито един запис, тогава редиректва към страницата за добавяне на списъци.
        if (!$files) {
            
            return redirect(array('blast_Lists', 'add'), FALSE, tr("Нямате добавен списък за циркулярни писма. Моля добавете."));
        }

        $form = $data->form;
        
        if (!$form->rec->id) {
            //Слага state = draft по default при нов запис
            $form->setDefault('state', 'draft');
            
            //Ако добавяме нов показваме всички списъци
            $form->setOptions('listId', $files, $form->rec->id);
        } else {
            
            //Ако редактираме, показваме списъка, който го редактираме
            $file[$form->rec->listId] = $files[$form->rec->listId];
            $form->setOptions('listId', $file, $form->rec->id);
        }
    
        //Ако създаваме нов, тогава попълва данните за адресанта по - подразбиране
        $rec = $data->form->rec;
        if ((!$rec->id) && (!Request::get('clone'))) {
            $rec->recipient = '[#company#]';
            $rec->attn = '[#names#]';
            $rec->country = '[#country#]';
            $rec->pcode = '[#postCode#]';
            $rec->place = '[#city#]';
            $rec->address = '[#address#]';
            $rec->position = '[#position#]';
        }
    }
    
    
    /**
     * Интерфейсен метод на doc_DocumentIntf
     */
    function getDocumentRow($id)
    {
        $rec = $this->fetch($id);
        
        // Вземаме стойността
        $subject = $this->getVerbal($rec, 'subject');
        
        // Превръщаме в стринг
        $subject = strip_tags($subject);
        
        // Максимална дължина
        $subject = str::limitLen($subject, 70);
        
        //Ако заглавието е празно, тогава изписва съответния текст
        if(!trim($subject)) {
            $subject = '[' . tr('Липсва заглавие') . ']';
        }
        
        $row = new stdClass();

        //Заглавие
        $row->title = $subject;
        
        //Създателя
        $row->author = $this->getVerbal($rec, 'createdBy');
        
        //Състояние
        $row->state = $rec->state;
        
        //id на създателя
        $row->authorId = $rec->createdBy;
        
        $row->recTitle = $rec->subject;
        
        return $row;
    }
    
    
    /**
     * Екшън за принтиране
     */
    function act_Print()
    {
        //Вземаме id'то на детайла на писмото
        expect($detailId = Request::get('detailId', 'int'));
        
        //Вземаме детайла на писмото
        expect($letterDetail = blast_LetterDetails::fetch($detailId));
        
        // Шаблона, който ще връщаме
        $tpl = new ET();
        
        // Масив с листовете използвани в детайла
        $listDetIdsArr = type_Keylist::toArray($letterDetail->listDetailsId);
        
        // Обхождаме масива
        foreach ($listDetIdsArr as $listDet) {
            
            // Опции за документа
            $options = new stdClass();

            // Вземаме мастера на детайла
            $options->rec = static::fetch($letterDetail->letterId);
            
            // Добавяме listId до кого е
            $options->__toListId = $listDet;
            
            // Пушваме екшъна
            log_Documents::pushAction(array('data' => array('toListId' => $listDet, 'listId' => $options->rec->listId)));
            
            // Вземаме документа в xhtml формат
            $res = $this->getDocumentBody($options->rec->id, 'xhtml', $options);

            // Добавяме към шаблона
            $tpl->append($res);
            
            // Попваме съответния екшън
            log_Documents::popAction();
        }

        // Ако състоянито на детайла не е затворен
        // За да запишем датата на първото отпечтване
        if ($letterDetail->state != 'closed') {
            
            //Променяме статуса на детайла на затворен  и добавяме дата на принтиране
            $newLetterDetail = new stdClass();
            $newLetterDetail->id = $letterDetail->id;
            $newLetterDetail->state = 'closed';
            $newLetterDetail->printedDate = dt::verbal2mysql();
            blast_LetterDetails::save($newLetterDetail);    
            
            //Проверяваме дали има други непринтирани писма, и ако няма сменяме състоянието на затворено
            $this->closeLetter($letterDetail->letterId);
        }
        
        return $tpl;
    }
    
    
    /**
     * 
     */
    function on_BeforeGetDocumentBody($mvc, &$res, $id, $mode = 'html', $options = NULL)
    {
        if ($options->__toListId) {
            
            // Фетчваме детайла за съответния лист
            $detailRec = blast_ListDetails::fetch($options->__toListId);    
        
            // Десериализираме данните
            $data = unserialize($detailRec->data);
        }
        
        // Ако има id
        if ($id) {
            
            // Вземаме данните от базата
            $options->rec = static::fetch($id);    
        }
        
        // Намираме преполагаемия език на писмото
        core_Lg::push(static::getLanguage($options->rec->body));
        
        // Обхождаме масива с данните
        foreach ((array)$data as $key => $value) {
            
            // Какво ще заместваме
            $search = "[#{$key}#]";
            
            //Заместваме данните
            $options->rec->body = str_ireplace($search, $value, $options->rec->body);
            $options->rec->subject = str_ireplace($search, $value, $options->rec->subject);
            $options->rec->recipient = str_ireplace($search, $value, $options->rec->recipient);
            $options->rec->attn = str_ireplace($search, $value, $options->rec->attn);
            $options->rec->country = str_ireplace($search, $value, $options->rec->country);
            $options->rec->pcode = str_ireplace($search, $value, $options->rec->pcode);
            $options->rec->place = str_ireplace($search, $value, $options->rec->place);
            $options->rec->address = str_ireplace($search, $value, $options->rec->address);
            $options->rec->position = str_ireplace($search, $value, $options->rec->position);
        }
        
        // Добавяме, че разглеждаме детайла
        $options->rec->__detail = TRUE;
    }
    
    
    /**
     * 
     */
    function on_AfterGetDocumentBody($mvc, &$res, $id, $mode = 'html', $options = NULL)
    {
        // Връщаме стария език
        core_Lg::pop('lg');
    }
    
    
    /**
     * 
     * 
     */
    function renderSingleLayout_(&$data)
    {
        // Ако разглеждаме детайла
        if ($data->rec->__detail) {
            
            // Името на шаблона
            $templateFile = ucfirst($data->rec->template);
            
            // Пътя до файла от пакета
            $filePath = "blast/tpl/{$templateFile}LettersTemplate.shtml";
            
            // Целия път до шаблона
            $fullPath = getFullPath($filePath);
            
            //Проверява дали е файл
            if (!is_file($fullPath)) {

                // Редиректваме към сингъла
                return redirect(array('blast_Letters', 'single', $data->rec->id), FALSE,tr("Файлът на шаблона не може да се намери. Моля изберете друг шаблон."));
            }
            
            // Вземаме шаблона
            $tpl = getTplFromFile($filePath);
            
            return $tpl;        
        }
        
        // Добавяме линк към листа
        $data->row->ListLink = ht::createLink($data->row->listId, array('blast_Lists', 'single', $data->rec->listId));
        
        // Превръщаме в стринг заглавието
        $data->row->subject = strip_tags($data->row->subject);
        
        // Ако не е детайл рендираме шаблона по подразбиране
        return getTplFromFile($this->singleLayoutFile);
    }
    
    
    /**
     * Ако няма повече записи за принтиране сменяме състоянието на писмото на "затворено"
     */
    function closeLetter($id)
    {
        // Вземаме детайла на писмото което не е принтирано
        $details = blast_LetterDetails::fetch("#letterId = '$id' AND #printedDate IS NULL");
        
        //Ако няма нито един запис
        if ($details === FALSE) {
            
            // Сменяме състоянието на затворено
            $newLetter = new stdClass();
            $newLetter->id = $id;
            $newLetter->state = 'closed';
            blast_Letters::save_($newLetter); // Ако е прекъсваема, отбелязва с 1 повече принтиране в историята
        }
    }
    
    
    /**
     * Добавя съответните бутони в лентата с инструменти, в зависимост от състоянието
     */
    static function on_AfterPrepareSingleToolbar($mvc, &$data)
    {
        $id = $data->rec->id;
        $state = $data->rec->state;
        
        if (($state == 'draft') || ($state == 'stopped')) {
            //Добавяме бутона Активирай, ако състоянието е чернова или спряно
            $data->toolbar->addBtn('Активиране', array($mvc, 'Activation', $id, 'ret_url' => TRUE), 'class=btn-activation');
        } elseif ($state == 'active') {
            //Добавяме бутона Спри, ако състоянието е активно или изчакване
            $data->toolbar->addBtn('Спиране', array($mvc, 'Stop', $id, 'ret_url' => TRUE), 'class=btn-cancel');
        }
    }
    
    
    /**
     * Екшън за активиране
     */
    function act_Activation()
    {
        // Права за работа с екшън-а
        requireRole('blast, admin');
        
        // Очакваме да има такъв запис
        expect($id = Request::get('id', 'int'));
        
        // Вземаме формата към този модел
        $form = $this->getForm();
        
        // Въвеждаме id-то (и евентуално други silent параметри, ако има)
        $form->input(NULL, 'silent');
        
        // Очакваме да имаме такъв запис
        expect($rec = static::fetch($form->rec->id));

        // Очакваме потребителя да има права за активиране
        $this->haveRightFor('activation', $rec);
        
        // Въвеждаме съдържанието на полетата
        $form->input('numLetters');

        // По подразбиране да е избрана стойността от записа
        $form->setDefault('numLetters', $rec->numLetters);
        
        // Вземаме ret_url
        $retUrl = getRetUrl();
    
        // URL' то където ще се редиректва при отказ
        $retUrl = ($retUrl) ? ($retUrl) : (array($this, 'single', $id));
        
        // Ако формата е изпратена без грешки, то активираме, ... и редиректваме
        if($form->isSubmitted()) {
            
            // Ако броя на отпечатваният не отговаря на броя записан в модела
            if ($rec->numLetters && $rec->numLetters != $form->rec->numLetters) {
                
                // Вземаме вербалната стойност
                $numLetters = static::getVerbal($rec, 'numLetters');
                
                // Сетваме предупреждение
                $form->setWarning('numLetters', "Ще важи само за новите записи. За старите е: {$numLetters}");
            }
        }
        
        // Ако формата е изпратена успешно
        if($form->isSubmitted()) {
            
            // Броя на пимсмата, които ще се печатат едновремнно
            $numLetters = $form->rec->numLetters;

            // Очакваме да е зададение
            expect($numLetters);
            
            // Записваме новия брой
            $nRec = new stdClass();
            $nRec->id = $form->rec->id;
            $nRec->numLetters = $numLetters;
            static::save($nRec, 'numLetters');
            
            //Променяме статуса на активен
            $recList = new stdClass();
            $recList->id = $rec->id;
            $recList->state = 'active';
            blast_Letters::save($recList, 'id, state');
            
            // Вземаме всички записи, които са добавени от предишното активиране в детайлите на писмото
            $queryLetterDetail = blast_LetterDetails::getQuery();
            $queryLetterDetail->where("#letterId = '$rec->id'");
            
            while ($recLetterDetail = $queryLetterDetail->fetch()) {
                
                // Добавяме keylist'а към стринга
                $exist .= $recLetterDetail->listDetailsId;
            }
            
            // Вземаме всички детайли на листа, които са към избраното писмо и не са спрени
            $queryListDetails = blast_ListDetails::getQuery();
            $queryListDetails->where("#listId = '$rec->listId'");
            $queryListDetails->where("#state != 'stopped'");
            
            while ($recListDetail = $queryListDetails->fetch()) {

                // Ако нямаме запис с id'то в модела
                if (!keylist::isIn($recListDetail->id, $exist)) {
                    
                    // Добавяме към масива
                    $allNewId[$recListDetail->id] = $recListDetail->id;
                }
            }
            
            // Ако имаме поне един нов запис
            if (count($allNewId)) {
                
                // Сортираме масива, като най - отгоре са записити с най - малко id
                asort($allNewId);

                // Групираме записите по максималния брой, който ще се печатат заедно
                for ($i = 0; $i < count($allNewId); $i = $i + $numLetters) {
                    $slicedNewId = array_slice($allNewId, $i, $numLetters, TRUE);
                    $keylist = keylist::fromArray($slicedNewId);

                    //Добавяме новите записи в модела
                    $newLetterDetail = new stdClass();
                    $newLetterDetail->letterId = $rec->id;
                    $newLetterDetail->listDetailsId = $keylist;
                    blast_LetterDetails::save($newLetterDetail);
                }
            }

            // След като приключи операцията редиректваме към същата страница, където се намирахме
            return redirect($retUrl, FALSE, tr("Успешно активирахте писмото."));
        }
        
        // Задаваме да се показват само полетата, които ни интересуват
        $form->showFields = 'numLetters';
        
        // Добавяме бутоните на формата
        $form->toolbar->addSbBtn('Запис', 'save', array('class' => 'btn-save'));
        $form->toolbar->addBtn('Отказ', $retUrl, array('class' => 'btn-cancel'));
        
        // Добавяме титлата на формата
        $form->title = "Активиране на писмо за печат";
        $subject = $this->getVerbal($rec, 'subject');
        
        // Превръщаме в стринг
        $subject = strip_tags($subject);
        
        // Вземаме датата
        $date = dt::mysql2verbal($rec->createdOn);
        
        // Добавяме във формата информация, за да знаем за кое писмо става дума
        $form->info = new ET ('[#1#]', tr("|*<b>|Писмо|*<i style='color:blue'>: {$subject} / {$date}</i></b>"));
        
        // Вземаме всички детайли, които не са спряни от съответния лист
        $query = blast_ListDetails::getQuery();
        $query->where("#listId = '{$rec->listId}'");
        $query->where("#state != 'stopped'");
        
        // Обхождаме получените резултати
        while ($qRec = $query->fetch()) {
            
            // Ако имаме права за single
            if (blast_ListDetails::haveRightFor('single', $qRec)) {
                
                // Вземаме id' то
                $listDet = $qRec->id;
                
                // Спираме по нататъшното изпълнение
                break;
            }
        }
        
        // Опциите за създаване на тялот
        $options = new stdClass();
        
        // Записите
        $options->rec = $rec;
        
        // Добавяме listId до кого е
        $options->__toListId = $listDet;
        
        // Вземаме документа в xhtml формат
        $res = $this->getDocumentBody($options->rec->id, 'xhtml', $options);
        
        // Получаваме изгледа на формата
        $tpl = $form->renderHtml();
        
        // Добавяме превю на първото писмо, което ще печатаме
        $preview = new ET("<div style='display:table'><div style='margin-top:20px; margin-bottom:-10px; padding:5px;'><b>" . tr("Примерно писмо") . "</b></div>[#BLAST_LET#]</div>");
        
        // Добавяме към шаблона
        $preview->append($res, 'BLAST_LET');
        
        // Добавяме изгледа към главния шаблон
        $tpl->append($preview);
        
        // Рендираме шаблона и връщаме резултата
        return static::renderWrapping($tpl);
    }
    
    
    /**
     * Екшън за спиране
     */
    function act_Stop()
    {
        //Права за работа с екшън-а
        requireRole('blast, admin');
        
        //Очаква да има въведено id
        expect($id = Request::get('id', 'int'));
        
        //Очакваме да има такъв запис
        expect($rec = $this->fetch($id));
        
        // Очакваме потребителя да има права за спиране
        $this->haveRightFor('stop', $rec);
        
        //Променяме статуса на спрян
        $recUpd = new stdClass();
        $recUpd->id = $rec->id;
        
        // Състоянието да е спряно
        $recUpd->state = 'stopped';
        blast_Letters::save($recUpd, 'state');
        
        // Вземаме ret_url
        $retUrl = getRetUrl();
    
        // URL' то където ще се редиректва при отказ
        $retUrl = ($retUrl) ? ($retUrl) : (array($this, 'single', $id));
        
        // След като приключи операцията редиректваме към същата страница, където се намирахме
        return redirect($retUrl, FALSE, tr("Успешно спряхте писмото."));
    }
    
    
    /**
     * Добавяне на филтър
     * Сортиране на записите
     */
    static function on_BeforePrepareListRecs($mvc, &$res, $data)
    {
        //Добавя филтър за търсене по "Тема" и "Време на започване"
        $data->listFilter->FNC('filter', 'varchar', 'caption=Търсене, input, width=100%, 
                hint=Търсене по "Заглавие"');
        
        $data->listFilter->showFields = 'filter';
        
        $data->listFilter->view = 'horizontal';
        
        //Добавяме бутон "Филтрирай"
        $data->listFilter->toolbar->addSbBtn('Филтрирай', 'default', 'id=filter,class=btn-filter');
        
        $filterInput = trim($data->listFilter->input()->filter);
        
        if($filterInput) {
            $data->query->where(array("#subject LIKE '%[#1#]%'", $filterInput));
        }
        
        // Сортиране на записите по състояние и дата на създаване
        $data->query->orderBy('state', 'ASC');
        $data->query->orderBy('createdOn', 'DESC');
    }
    
    
	/**
	* Изпълнява се след въвеждането на даните от формата
	* Проверява дали сме въвели несъществуващ шаблон
	*/
    function on_AfterInputEditForm($mvc, &$form)
    {
        // Ако сме субмитнали формата
        if ($form->isSubmitted()) {
            
            // Масив с всички записи
            $recArr = (array)$form->rec;
            
            // id' то на листа, от който се вземат данните на потребителя
            if (!$listId = $form->rec->listId) {
                
                // Вземаме от записа
                $listId = $mvc->fetchField($form->rec->id, 'listId');
            }
            
            // Вземаме Относно и Съобщение
            $bodyAndSubject = $recArr['body'] . ' ' . $recArr['subject'];
            
            // Масив с данни от плейсхолдера
            $nRecArr['recipient'] = $recArr['recipient'];
            $nRecArr['attn'] = $recArr['attn'];
            $nRecArr['country'] = $recArr['country'];
            $nRecArr['pcode'] = $recArr['pcode'];
            $nRecArr['place'] = $recArr['place'];
            $nRecArr['address'] = $recArr['address'];
            $nRecArr['position'] = $recArr['position'];
            
            // Обикаляме всички останали стойности в масива
            foreach ($nRecArr as $field) {
                
                // Всички данни ги записваме в една променлива
                $allRecsWithPlaceHolders .= ' ' . $field;    
            }

            // Създаваме шаблон
            $tpl = new ET($allRecsWithPlaceHolders);
            
            // Вземаме всички шаблони, които се използват
            $allPlaceHolder = $tpl->getPlaceHolders();
            
            // Шаблон на Относно и Съобщение
            $bodyAndSubTpl = new ET($bodyAndSubject);
            
            // Вземаме всички шаблони, които се използват
            $bodyAndSubPlaceHolder = $bodyAndSubTpl->getPlaceHolders();

            // Вземаме всички полета, които ще се заместват
            $listsRecAllFields = blast_Lists::fetchField($listId, 'allFields');
            
            $allFieldsArr = array();
            
            //Вземаме всички имена на полетата на данните, които ще се заместват
            preg_match_all('/(^)([^=]+)/m', $listsRecAllFields, $allFieldsArr);

            //Създаваме масив с ключ и стойност имената на полетата, които ще се заместват
            foreach ($allFieldsArr[2] as $field) {
                $field = trim($field);
                $fieldsArr[$field] = $field;
            }
            
            // Премахваме дублиращите се плейсхолдери
            $allPlaceHolder = array_unique($allPlaceHolder);
            
            //Търсим всички полета, които сме въвели, но ги няма в полетата за заместване
            foreach ($allPlaceHolder as $placeHolder) {
                
                $placeHolderL = strtolower($placeHolder);
                
                // Ако плейсхолдера го няма във листа
                if (!$fieldsArr[$placeHolderL]) {
                    
                    // Добавяме към съобщението за предупреждение
                    $warning .= ($warning) ? ", {$placeHolder}" : $placeHolder;
                    
                    // Стринг на плейсхолдера
                    $placeHolderStr = "[#" . $placeHolder . "#]";
                    
                    // Добавяме го в масива
                    $warningPlaceHolderArr[$placeHolderStr] = $placeHolderStr;
                }
            }
            
            // Премахваме дублиращите се плейсхолдери
            $bodyAndSubPlaceHolder = array_unique($bodyAndSubPlaceHolder);

            //Търсим всички полета, които сме въвели, но ги няма в полетата за заместване
            foreach ($bodyAndSubPlaceHolder as $placeHolder) {
                
                $placeHolderL = strtolower($placeHolder);
                
                // Ако плейсхолдера го няма във листа
                if (!$fieldsArr[$placeHolderL]) {

                    // Добавяме към съобщението за грешка
                    $error .= ($error) ? ", {$placeHolder}" : $placeHolder;
                }
            }

            // Показваме грешка, ако има шаблони, които сме въвели в повече в Относно и Съощение
            if ($error) {
                $form->setError('*', "|Шаблоните, които сте въвели ги няма в БД|*: {$error}");    
            }
            
            // Показваме предупреждение за останалите шаблони
            if ($warning) {
                
                // Сетваме грешката
                $form->setWarning('*', "|Шаблоните, които сте въвели ги няма в БД|*: {$warning}"); 
                
                // При игнориране на грешката
                if (!$form->gotErrors()) {
                    
                    // Обхождаме масива с стойност
                    foreach ($nRecArr as $field => $val) {
                        
                        // Премахваме всички плейсхолдери, които не се използват
                        $val = str_ireplace((array)$warningPlaceHolderArr, '', $val);    
                        
                        // Добавяме към записа
                        $form->rec->{$field} = $val;
                    }
                }
            }
        }
    }
    
    
	/**
     * Намира предполагаемия език на текста
     * 
     * @param text $body - Текста, в който ще се търси
     * 
     * @return string $lg - Двубуквеното означение на предполагаемия език
     */
    static function getLanguage($body)
    {
        //Масив с всички предполагаеми езици
        $lg = i18n_Language::detect($body);
        
        //Ако езика не е bg, връщаме en
        if ($lg != 'bg') {
            $lg = 'en';
        }
        
        return $lg;
    }
}