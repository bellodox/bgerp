<?php


/**
 * 
 *
 * @category  bgerp
 * @package   bgerp
 * @author    Yusein Yuseinov <yyuseinov@gmail.com>
 * @copyright 2006 - 2016 Experta OOD
 * @license   GPL 3
 * @since     v 0.1
 */
class escpos_Print extends core_Manager
{
    
    
    /**
     * Разделител на хеша с id-то и класа
     */
    protected static $agentHashDelimiter = '-';
    
    
    /**
     * Разделител между id-то и класа
     */
    protected static $agentIdDelimiter = '_';
    
    
    /**
     * Заглавие
     */
    public $title = 'Отпечатване на документи в мобилен принтер';


    /**
     * Дали id-тата на този модел да са защитени?
     */
    var $protectId = FALSE;
    
    
    /**
     * 
     */
    public $canAdd = 'no_one';
    
    
    /**
     * 
     */
    public $canDelete = 'no_one';
    
    
    /**
     * 
     */
    public $canEdit = 'no_one';
    
    
    /**
     *
     */
    public $canList = 'no_one';
    
    
    /**
     * Масив с `id` от приложението и драйвер, на който отговарят
     */
    public static $drvMapArr = array(1 => 'escpos_driver_Ddp250');
    
    
    /**
     * 
     * 
     * @param bgerp_Print $mvc
     * @param NULL|core_Et $res
     * @param string $action
     */
    function on_BeforeAction($mvc, &$res, $action)
    {
        if ($action != 'print') return ;
        
        $idFullStr = Request::get('id');
        
        $paramsArr = $mvc->parseParamStr($idFullStr);
        
        $id = $paramsArr['id'];
        $clsInst = $paramsArr['clsInst'];
        
        expect($id && $clsInst);
        
        $drvId = Request::get('drv');
        
        $drvName = self::$drvMapArr[$drvId];
        
        if (!$drvName) {
//             $drvName = 'escpos_driver_Html';
            $drvName = 'escpos_driver_Ddp250';
        }
        
        // За да не се кешира
        header("Expires: Sun, 19 Nov 1978 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        
        // Указваме, че ще се връща XML
        header('Content-Type: application/xml');
        
        $res = escpos_Helper::getContentXml($clsInst, $id, $drvName);
        
        echo $res;
        
        // Прекратяваме процеса
        shutdown();
    }
    
    
    /**
     * Подготвя URL за печатане чрез
     * 
     * @param core_Master $mvc
     * @param int $id
     * 
     * @return string
     */
    public static function prepareUrlIdForAgent($clsInst, $id)
    {
        $pId = $clsInst->protectId($id);
        $clsId = $clsInst->getClassId();
        
        $res = $pId . self::$agentIdDelimiter . $clsId;
        
        $hash = self::getHash($res);
        
        $res .= self::$agentHashDelimiter . $hash;
        
        return $res;
    }
    
    
    /**
     * Връща хеша за стринг
     * 
     * @param string $str
     * 
     * @return string
     */
    protected static function getHash($str)
    {
        $res = md5($str . '|' . escpos_Setup::get('SALT'));
        
        $res = substr($res, 0, escpos_Setup::get('HASH_LEN'));
        
        return $res;
    }
    
    
    /**
     * Парсира стринга и връща маси в id и инстанция на класа
     * 
     * @param string $str
     * 
     * @return array
     */
    protected static function parseParamStr($str)
    {
        list($idStr, $hash) = explode(self::$agentHashDelimiter, $str);
        
        $hashGen = self::getHash($idStr);
        
        expect($hashGen == $hash);
        
        list($id, $clsId) = explode(self::$agentIdDelimiter, $idStr);
        
        expect($clsId);
        
        $inst = cls::get($clsId);
        
        $id = $inst->unprotectId($id);
        
        expect($id !== FALSE);
        
        $res = array();
        $res['id'] = $id;
        $res['clsInst'] = $inst;
        
        return $res;
    }
}
