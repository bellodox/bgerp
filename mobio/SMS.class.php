<?php


/**
 * Пращане на SMS' и от mobio
 *
 * @category  vendors
 * @package   mobio
 * @author    Yusein Yuseinov <yyuseinov@gmail.com>
 * @copyright 2006 - 2013 Experta OOD
 * @license   GPL 3
 * @since     v 0.1
 */
class mobio_SMS extends core_Manager
{
    
	
    /**
     * Кой има право да чете?
     */
    var $canRead = 'no_one';
    
    
    /**
     * Кой има право да променя?
     */
    var $canEdit = 'no_one';
    
    
    /**
     * Кой има право да добавя?
     */
    var $canAdd = 'no_one';
    
    
    /**
     * Кой има право да го види?
     */
    var $canView = 'no_one';
    
    
    /**
     * Кой може да го разглежда?
     */
    var $canList = 'no_one';
    
    /**
     * Кой има право да го изтрие?
     */
    var $canDelete = 'no_one';
	
    
	/**
	 * Интерфейсния клас за изпращане на SMS
	 */
	var $interfaces = 'callcenter_SentSMSIntf';
    
    
    /**
     * Плъгини за зареждане
     * 
     * var string|array
     */
    public $loadList = 'callcenter_SMSPlg';
	
	
	/**
	 * 
	 */
	var $title = 'Мобио';
		
	
	/**
     * Интерфейсен метод за изпращане на SMS' и
     * 
     * @param string $number - Номера на получателя
     * @param string $message - Текста на съобщението
     * @param string $sender - От кого се изпраща съобщението
     * 
     * @return array $nRes - Mасив с информация, дали е получено
     * $res['sendStatus'] string - Статус на изпращането - received, sended, receiveError, sendError, pending
     * $nRes['uid'] string - Уникалното id на съобщението
     * $nRes['msg'] - Статуса
     */
    function sendSMS($number, $message, $sender)
    {
        // Конфигурацията на модула
    	$conf = core_Packs::getConfig('mobio');
    	
    	// Масива, който ще връщаме
        $nRes = array();
        
        // Ако константата за УРЛ-то е зададена
        if ($conf->MOBIO_URL != '') {
            
            // Вземаме шаблона
            $tpl = new ET($conf->MOBIO_URL);
            
            // Заместваме данните
            $tpl->placeArray(array('FROM' => urlencode($sender), 'PHONE' => urlencode($number), 'MESSAGE' => urlencode($message)));
            
            // Вземаме съдържанието
            $url = $tpl->getContent();
            
            // Опитваме се да изпратим
            $ctx = stream_context_create(array('http' => array('timeout' => 5)));
            
            // Вземаме резултата
            $resStr = file_get_contents($url, 0, $ctx);
            
            // Ако има грешка - веднага маркираме в SMS Мениджъра
            $resArr = explode(':', $resStr);
            
            // Ако няма грешки
            if ($resArr[0] == 'OK') {
                
                // Сетваме променливите
                $nRes['sendStatus'] = 'sended';
                $nRes['uid'] = $resArr[1];
                $nRes['msg'] = "|Успешно изпратен SMS";
            } else {
                
                // Сетваме променливите
                $nRes['sendStatus'] = 'sendError';
                $nRes['msg'] = "|Не може да се изпрати";
                
                if (isDebug()) {
                    $nRes['msg'] .= "|*\n" . $resStr;
                }
                
                self::log("Грешка при изпращане на SMS: " . $resStr);
            }
        } else {
            
            // Ако не е дефиниран шаблона
            
            // Сетваме грешките
            $nRes['sendStatus'] = 'sendError';
            $nRes['msg'] = "|Липсва константа за URL' то";
            
            // Записваме в лога
            static::log("Липсва константа за URL' то");
        }
    	
        return $nRes;
    }
    
    
    /**
     * Интерфейсен метод, който връща масив с настройките за услугата
     * 
     * @return array $paramsArr
     * enum $paramsArr['utf8'] - no|yes - Дали поддържа UTF-8
     * integer $paramsArr['maxStrLen'] - Максималната дължина на стринга
     * string $paramsArr['allowedUserNames'] - Масив с позволените имена за изпращач
     */
    function getParams()
    {
        $conf = core_Packs::getConfig('mobio');
        $paramsArr = array();
        $paramsArr['utf8'] = $conf->MOBIO_SUPPORT_UTF8;
        $paramsArr['maxStrLen'] = $conf->MOBIO_MAX_STRING_LEN;
        $paramsArr['allowedUserNames'] = arr::make($conf->MOBIO_ALLOWED_USER_NAMES, TRUE);
        
        return $paramsArr;
    }
    
    
    /**
     * Инрерфейсен метод
     * Подготвя номера на получателя
     * @see callcenter_SentSMSIntf
     * 
     * @param string $number
     * 
     * @return string
     */
    public function prepareNumberStr($number)
    {
        $number = drdata_PhoneType::getNumberStr($number, 0, '');
        
        return $number;
    }
    
    
    /**
     * Инрерфейсен метод
     * Връща статуса на съобщението от съоветната услуга
     * @see callcenter_SentSMSIntf
     * 
     * @param string $uid
     * 
     * @return 
     */
    public function getStatus($uid)
    {
        
        return ;
    }
    
    
    /**
     * Отбелязване на статуса на съобщенито
     * Извиква се от външната програма след промяна на статуса на SMS'а
     */
    function act_Delivery()
    {
        // Вземаме променливите
        $uid = request::get('msgid', 'varchar');
        $oldStatus = request::get('oldstats', 'varchar');
        $number = request::get('tonum', 'varchar');
        $code = request::get('newstatus', 'varchar');
        
        // Ако не е получен успешно
        if ((int)$code !== 1) {
            $status = 'receiveError';
        } else {
            $status = 'received';
        }
        
        try {
            $classId = $this->getClassId();
            
            // Обновяваме статуса на съобщението
            callcenter_SMS::update($classId, $uid, $status);
        } catch (core_exception_Expect $e) {
            self::log("Възникна грешка при обновяване на състоянието с msgid: " . $uid);
        }
    }
}
