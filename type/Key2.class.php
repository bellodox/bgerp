<?php


/**
 * Клас  'type_Key' - Ключ към ред от MVC модел
 *
 *
 * @category  ef
 * @package   type
 * @author    Milen Georgiev <milen@download.bg> и Yusein Yuseinov <yyuseinov@gmail.com>
 * @copyright 2006 - 2015 Experta OOD
 * @license   GPL 3
 * @since     v 0.1
 *
 *
 *
 * source=Class:Method($params - Параметрите на типa, 
 *                     $limit = 0 - колко резултата да върнем, 
 *                     $q = '' - пълнотекстова заявка, 
 *                     $sectionWith = array() - id-та, само сред които се прави търсенето, 
 *                     $includeHiddens = дали да се показват и скритите опции?)
 * 
 * Тази функция трябва да връща масив в възможни опции
 */
class type_Key2 extends type_Int
{
    
    
    /**
     * Клас за <td> елемент, който показва данни от този тип
     */
    public $tdClass = '';
    
    
    /**
     * Хендлър на класа
     * 
     * @var string
     */
    public $handler;
    
    
    /**
     * Параметър определящ максималната широчина на полето
     */
    public $maxFieldSize = 0;

    
    /**
     * Конвертира стойността от вербална към (int) - ключ към core_Interfaces
     */
    function toVerbal_($value)
    {
        if ($value === NULL || $value === '') return NULL;
        
        $resArr = $this->getOptions(1, '', $value);
        
        if(count($resArr)) {
            $res = reset($resArr);
        }

        return $res;
    }
    
    
    /**
     * Връща вътрешното представяне на вербалната стойност
     */
    function fromVerbal_($value)
    {
        if(empty($value)) return NULL;
        

        // Вербалната стойност може да бъде:
        // 1. Число - тогава се третира като ключ към модела
        // 2. Стринг, съдържащ число в скоби най-накрая. Тогава чизслото най-накрая се третира като ид
        // 3. Друг стринг - тогава той точно трябва да отговаря на title в модела

        if(ctype_digit($value)) {
            $key = $value;
        } else {
            $key = self::getKeyFromTitle($value);
        }
 
        if($key) {
            $resArr = $this->getOptions(1, '', $key, TRUE);
        } else {
            $resArr = $this->getOptions(1, "\"{$value}", TRUE);
        }
            
        if(count($resArr) == 1) {

            return key($resArr);
        } elseif(count($resArr) > 1) {
            $this->error = 'Нееднозначно определяне';
        } else {
            $this->error = 'Несъществуващ обект';
        }

        return FALSE;
    }


    /**
     * Връща опците, съответсващи на избраните параметри
     */
    public function getOptions($limit = NULL, $query = '', $ids = NULL, $includeHiddens = FALSE)
    { 
        if(!$this->params['selectSourceArr']) {
            if($this->params['selectSource']) {
                $this->params['selectSourceArr'] = explode('::', $this->params['selectSource']);
            } else {
                $this->params['selectSourceArr'] = array($this->params['mvc'], 'getSelectArr');
            }
        }

        if(!$this->params['titleFld']) {
            $mvc = cls::get($this->params['mvc']);
            if($mvc->getField('name', FALSE)) {
                $this->params['titleFld'] = 'name';
            }
            if($mvc->getField('title', FALSE)) {
                $this->params['titleFld'] = 'title';
            }
        }
        
        expect($this->params['titleFld']);

        $resArr = call_user_func($this->params['selectSourceArr'], $this->params, $limit,  $query, $ids, $includeHiddens);

        return $resArr;
    }

     
    
    /**
     * Опитва се да извлече ключа от текста
     * 
     * @param string $title
     * 
     * return integer|NULL
     */
    protected static function getKeyFromTitle($title)
    {
        
        $len = mb_strlen($title);
        
        $lastCloseBracketPos = mb_strrpos($title, ')');
        
        if (!$lastCloseBracketPos) return $title;
        
        if ($len != ($lastCloseBracketPos+1)) return $title;
        
        $lastOpenBracketPos = mb_strrpos($title, ' (');
        
        if (!$lastOpenBracketPos) return $title;
        
        $lastOpenBracketPos += 2;
        
        $key = mb_substr($title, $lastOpenBracketPos, $lastCloseBracketPos-$lastOpenBracketPos);

        return $key;
    }
    
    
     
    
    /**
     * Рендира HTML поле за въвеждане на данни чрез форма
     */
    function renderInput_($name, $value = "", &$attr = array())
    {

        // Варианти за генериране на селекта
        // 1. Ако има Select2 - винаги използваме неговото рендиане
        // 2. Ако опциите са под MaxSuggestions - показваме обикновен селект
        // 2. Комбобокс с id вградени в титлата, ако нямаме селект 2
        

        if(!$this->params['maxSuggestions']) {
            $maxSuggestions = $this->params['maxSuggestions'] = core_Setup::get('TYPE_KEY_MAX_SUGGESTIONS', TRUE);
        }
 
        $options = $this->getOptions($maxSuggestions);
        
        if(ctype_digit($value)) {
            $currentOpt = $this->getOptions(1, '', $value, TRUE);
            $key = reset($currentOpt);
            if($key) {
                if(!isset($options[$key])) {
                    $options = arr::combine($currentOpt, $options);
                }
            }
        }

        $this->setFieldWidth($attr, NULL, $options);

        if(core_Packs::isInstalled('select2') && !Mode::is('javascript', 'no')) {
            
            // Показваме Select2
            ht::setUniqId($attr);
            $tpl = ht::createSelect($name, $options, $value, $attr);
            
            $ajaxUrl = '';
            $handler = $this->getHandler();
            if (count($options) >= $maxSuggestions) {
                $ajaxUrl = toUrl(array($this, 'getOptions', 'hnd' => $handler, 'maxSugg' => $maxSuggestions, 'ajax_mode' => 1), 'absolute');
            }
            
            // Добавяме необходимите файлове и стартирам select2
            select2_Adapter::appendAndRun($tpl, $attr['id'], $attr['placeholder'], TRUE, NULL, $ajaxUrl);

        } elseif(count($options) >= $maxSuggestions && !Mode::is('javascript', 'no')) {
            // Показваме Combobox
            
            $this->params['inputType'] = 'combo';
            $handler = $this->getHandler();

            foreach($options as $key => $title) {
                $title = $title . ' (' . $key . ')';
                $comboOpt[$title] = $title;
                if($key == $value) {
                    $value = $title;
                }
            }

            $attr['ajaxAutoRefreshOptions'] = "{Ctr:\"type_Key2\"" .
                ", Act:\"GetOptions\", hnd:\"{$handler}\", maxSugg:\"{$maxSuggestions}\", ajax_mode:1}";
                
             
            $tpl = ht::createCombo($name, $value, $attr, $comboOpt);

        } else {
            // Показваме обикновен Select
            $tpl = ht::createSelect($name, $options, $value, $attr);
        }

        
        return $tpl;
    }

    
    /**
     *
     */
    private function getHandler()
    {
        $hnd = core_Crypt::encodeVar($this->params, EF_CRYPT_CODE . 'Key2');

        return $hnd;

    }
    
    
    /**
     * Връща списък е елементи <option> при ajax заявка
     */
    function act_GetOptions()
    {
        // Приключваме, ако няма заявка за търсене
        $hnd = Request::get('hnd');
 
        $hnd = core_Crypt::decodeVar($hnd, EF_CRYPT_CODE . 'Key2');
        if (!$hnd) { 
            $res = array(
                'error' => 'Липсва данни за елемента за избор'
            );
        } else {
            
            $res = array();
            
            $this->params = $hnd;

            $q = trim(Request::get('q'));
                      
            $select = new ET('<option value="">&nbsp;</option>');
            
            $options = $this->getOptions($this->params['maxSuggestions'], $q);
            
            if(is_array($options)) {
               
                foreach ($options as $key => $title) {
                    
                    if($this->params['inputType'] == 'combo') {
                        $key = $title . ' (' . $key . ')';
                        $arrt = array('value' => $key);
                        $select->append(ht::createElement("option", $attr, $key));
                    } else {
                        $obj = (object) array('id' => $key, 'text' => $title);
                        $res[] = $obj;
                    }
                }
            }
            if($this->params['inputType'] == 'combo') {
                $res = array('content' => $select->getContent());
               
            }
        }
                           

        core_App::getJson($res);
    }
    
}