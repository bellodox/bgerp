<?php



/**
 * Ценови групи
 *
 *
 * @category  bgerp
 * @package   price
 * @author    Milen Georgiev <milen@experta.bg>
 * @copyright 2006 - 2013 Experta OOD
 * @license   GPL 3
 * @since     v 0.1
 * @title     Ценоразписи
 */
class price_GroupOfProducts extends core_Detail
{
    
    
    /**
     * Заглавие
     */
    var $title = 'Ценови групи';
    
    
    /**
     * Заглавие
     */
    var $singleTitle = 'Ценова група';
    
    
    /**
     * Плъгини за зареждане
     */
    var $loadList = 'plg_Created, plg_RowTools, price_Wrapper';
                    
 
     
    /**
     * Полета, които ще се показват в листов изглед
     */
    var $listFields = 'id, groupId, productId, validFrom';
    
    
    /**
     * Полето в което автоматично се показват иконките за редакция и изтриване на реда от таблицата
     */
    var $rowToolsField = 'id';
    
    
    /**
     * Кой може да го прочете?
     */
    var $canRead = 'user';
    
    
    /**
     * Кой може да го промени?
     */
    var $canEdit = 'user';
    
    
    /**
     * Кой има право да добавя?
     */
    var $canAdd = 'user';
    
        
    /**
     * Кой може да го изтрие?
     */
    var $canDelete = 'user';
    
    
    /**
     * Поле - ключ към мастера
     */
    var $masterKey = 'productId';
   

    /**
     * Променлива за кеширане на актуалната информация, кой продукт в коя група е;
     */
    static $products = array();


    /**
     * Описание на модела (таблицата)
     */
    function description()
    {
        $this->FLD('groupId', 'key(mvc=price_Groups,select=title,allowEmpty)', 'caption=Група');
        $this->FLD('productId', 'key(mvc=cat_Products,select=name)', 'caption=Продукт');
        $this->FLD('validFrom', 'datetime', 'caption=В сила oт');
    }


    /**
     * Връща групата на продукта към посочената дата
     */
    static function getGroup($productId, $datetime)
    {
        $query = self::getQuery();
        $query->orderBy('#validFrom', 'DESC');
        $query->where("#validFrom <= '{$datetime}'");
        $query->where("#productId = {$productId}");
        $query->limit(1);

        if($rec = $query->fetch()) {

            return $rec->groupId;
        }
    }


    /**
     * Връща масив групите на всички всички продукти към определената дата
     * $productId => $groupId
     */
    static function getAllProducts($datetime = NULL)
    {
        if(!$datetime) {
            $datetime = dt::verbal2mysql();
        }

        $datetime = price_History::canonizeTime($datetime);

        $query = self::getQuery();

        $query->where("#validFrom < '{$datetime}'");

        $query->orderBy("#validFrom", "DESC");

        while($rec = $query->fetch()) {
            if(!$used[$rec->productId]) {
                if($rec->groupId) {
                    $res[$rec->productId] = cat_Products::getTitleById($rec->productId);
                }
                $used[$rec->productId] = TRUE;
            }
        }

        return $res;
    }


    function act_Test()
    {
        bp($this->getAllProducts());
    }
    
    
    static function on_AfterPrepareDetailQuery(core_Detail $mvc, $data)
    {
        // Историята на ценовите групи на продукта - в обратно хронологичен ред.
        $data->query->orderBy("validFrom,id", 'DESC');
    }


    function on_AfterGetRequiredRoles($mvc, &$requiredRoles, $action, $rec)
    {
        if($rec->validFrom && ($action == 'edit' || $action == 'delete')) {
            if($rec->validFrom <= dt::verbal2mysql()) {
                $requiredRoles = 'no_one';
            }
        }
    }
    

    /**
     * Подготвя формата за въвеждане на групи на продукти
     */
    public static function on_AfterPrepareEditForm($mvc, $res, $data)
    {
        if(!$rec->id) {
            $data->form->rec->validFrom = Mode::get('PRICE_VALID_FROM');
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
        if($form->isSubmitted()) {
            
            $rec = $form->rec;

            $now = dt::verbal2mysql();
            
            if(!$rec->validFrom) {
                $rec->validFrom = $now;
            }

            if($rec->validFrom < $now) {
                $form->setError('validFrom', 'Групата не може да се сменя с минала дата');
            }
            
            if($rec->validFrom && !$form->gotErrors() && $rec->validFrom > $now) {
                Mode::setPermanent('PRICE_VALID_FROM', $rec->validFrom);
            }
        }
    }


    
    public static function on_AfterPrepareListRows(core_Detail $mvc, $data)
    {
        if (!$data->rows) {
            return;
        }
        
        $now  = dt::now(true); // Текущото време (MySQL формат) с точност до секунда
        $currentGroupId = NULL;// ID на настоящата ценова група на продукта
        
        /**
         * @TODO следващата логика вероятно ще трябва и другаде. Да се рефакторира!
         */
        
        // Цветово кодиране на историята на ценовите групи: добавя CSS клас на TR елементите
        // както следва:
        //
        //  * 'future' за бъдещите ценови групи (невлезли все още в сила)
        //  * 'active' за текущата ценова група
        //  * 'past' за предишните ценови групи (които вече не са в сила)
        foreach ($data->rows as $id=>&$row) {
            $rec = $data->recs[$id];
            
            if ($rec->validFrom > $now) {
                $row->ROW_ATTR['class'] = 'state-draft';
            } else {
                $row->ROW_ATTR['class'] = 'state-closed';

                if (!isset($currentGroupId) || $rec->validFrom > $data->recs[$currentGroupId]->validFrom) {
                    $currentGroupId = $id;
                }
            }

            $row->groupId = ht::createLink($row->groupId, array('price_Groups', 'single', $rec->groupId));
        }
        
        if (isset($currentGroupId)) {
            $data->rows[$currentGroupId]->ROW_ATTR['class'] = 'state-active';
        }
    }


    public static function on_AfterRenderDetail($mvc, &$tpl, $data)
    {
        $wrapTpl = new ET(getFileContent('cat/tpl/ProductDetail.shtml'));
        $wrapTpl->append($mvc->singleTitle, 'TITLE');
        $wrapTpl->append($tpl, 'CONTENT');
        $wrapTpl->replace(get_class($mvc), 'DetailName');
    
        $tpl = $wrapTpl;
    }


    public static function preparePriceGroup($data)
    {
        static::prepareDetail($data);
    }
    
    
    public function renderPriceGroup($data)
    {
        // Премахваме продукта - в случая той е фиксиран и вече е показан 
        unset($data->listFields[$this->masterKey]);
        
        return static::renderDetail($data);
    }

    
    /**
     * Премахва кеша за интервалите от време
     */
    public static function on_AfterSave($mvc, &$id, &$rec, $fields = NULL)
    {
        price_History::removeTimeline();
    }

}