<?php 


/**
 * Харесвания на документите
 *
 * @category  bgerp
 * @package   doc
 * @author    Yusein Yuseinov <yyuseinov@gmail.com>
 * @copyright 2006 - 2015 Experta OOD
 * @license   GPL 3
 * @since     v 0.1
 */
class doc_Likes extends core_Manager
{
    
    
    /**
     * 
     */
    protected static $isLikedArr = array();
    
    
    /**
     * 
     */
    protected static $likedArr = array();
    
    
    /**
     * Заглавие
     */
    public $title = "Харесвания";
    
    
    /**
     * Кой има право да го променя?
     */
    public $canEdit = 'no_one';
    
    
    /**
     * Кой има право да добавя?
     */
    public $canAdd = 'no_one';
    
    
    /**
     * Кой има право да го види?
     */
    public $canView = 'debug';
    
    
    /**
     * Кой може да го разглежда?
     */
    public $canList = 'debug';
    
    
    /**
     * Кой има право да изтрива?
     */
    public $canDelete = 'no_one';
    
    
    /**
     * Плъгини за зареждане
     */
    public $loadList = 'doc_Wrapper, plg_Created';
    
    
    /**
     * Описание на модела
     */
    function description()
    {
        $this->FLD('containerId', 'key(mvc=doc_Containers)', 'caption = Контейнер');
        $this->FLD('threadId', 'key(mvc=doc_Threads)', 'caption = Нишка');
        
        $this->setDbIndex('threadId');
        $this->setDbUnique('containerId, createdBy');
    }
    
    
    /**
     * Отбелязва докумена, като харесан
     * 
     * @param integer $cid
     * @param NULL|integer $threadId
     * @param NULL|integer $userId
     * 
     * @return integer
     */
    public static function like($cid, $threadId = NULL, $userId = NULL)
    {
        if (!$userId) {
            $userId = core_Users::getCurrent();
        }
        
        if (!isset($threadId)) {
            $threadId = doc_Containers::fetchField($cid, 'threadId');
        }
        
        $rec = new stdClass();
        $rec->containerId = $cid;
        $rec->createdBy = $userId;
        $rec->threadId = $threadId;
        
        $savedId = self::save($rec, NULL, 'IGNORE');
        
        self::resetCache();
        
        return $savedId;
    }
    
    
    /**
     * Премахва харесването
     * 
     * @param integer $cid
     * @param NULL|integer $userId
     * 
     * @return integer
     */
    public static function dislike($cid, $userId = NULL)
    {
        if (!$userId) {
            $userId = core_Users::getCurrent();
        }
        
        $delCnt = self::delete(array("#containerId = '[#1#]' AND #createdBy = '[#2#]'", $cid, $userId));
        
        self::resetCache();
        
        return $delCnt;
    }
    
    
    /**
     * Проверява дали има харесване за документа
     * 
     * @param integer $cid
     * @param integer $threadId
     * @param integer $userId
     * 
     * @return boolean
     */
    public static function isLiked($cid, $threadId, $userId = NULL)
    {
        $likedArr = self::prepareLikedArr($cid, $threadId);
        
        if (!isset($userId)) {
            
            $isEmpty = empty($likedArr);
            
            return !$isEmpty;
        }
        
        if (!isset(self::$isLikedArr[$cid][$userId])) {
            self::$isLikedArr[$cid][$userId] = FALSE;
            
            foreach ($likedArr as $lRec) {
                if ($lRec->createdBy == $userId) {
                    
                    self::$isLikedArr[$cid][$userId] = TRUE;
                    
                    break;
                }
            }
        }
        
        return self::$isLikedArr[$cid][$userId];
    }
    
    
    /**
     * Връща броя на харесванията на документа
     * 
     * @param integer $cid
     * @param integer $threadId
     * 
     * @return integer
     */
    public static function getLikesCnt($cid, $threadId)
    {
        $likedArr = self::prepareLikedArr($cid, $threadId);
        
        return (int) count($likedArr);
    }
    
    
    /**
     * Връща всички харесвания
     * 
     * @param integer $cid
     * @param string $order
     * 
     * @return array
     */
    public static function getLikedArr($cid, $threadId, $order = 'DESC')
    {
        
        return self::prepareLikedArr($cid, $threadId, $order);
    }
    
    
    /**
     * 
     * @param integer $cid
     * @param integer $threadId
     * @param string $order
     * 
     * @return array
     */
    protected static function prepareLikedArr($cid, $threadId, $order = 'DESC')
    {
        $key = $order . '|' . $threadId;
        
        if (!isset(self::$likedArr[$key])) {
            self::$likedArr[$key] = array();
            
            $query = self::getQuery();
            $query->where(array("#threadId = [#1#]", $threadId));
            $query->orderBy('createdOn', $order);
            
            while ($rec = $query->fetch()) {
                self::$likedArr[$key][$rec->containerId][] = $rec;
            }
        }
        
        return (array) self::$likedArr[$key][$cid];
    }
    
    
    /**
     * Ресетва масив с кешовете
     */
    protected static function resetCache()
    {
        self::$isLikedArr = array();
        self::$likedArr = array();
    }
}
