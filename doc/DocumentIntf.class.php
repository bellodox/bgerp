<?php



/**
 * Клас 'doc_DocumentIntf' - Интерфейс за мениджърите на документи
 *
 *
 * @category  bgerp
 * @package   doc
 * @author    Milen Georgiev <milen@download.bg>
 * @copyright 2006 - 2012 Experta OOD
 * @license   GPL 3
 * @since     v 0.1
 */
class doc_DocumentIntf
{
    
    
    /**
     * Намира най-подходящите $rec->folderId (папка)
     * и $rec->threadId за дадения документ
     */
    function route($rec)
    {
        $this->class->route($rec);
    }
    
    
    /**
     * Връща манипулатор на документа
     */
    function getHandle($id)
    {
        return $this->class->getHandle($id);
    }
    
    
    /**
     * Връща обект,следните вербални стойности
     * - $docRow->title - Заглавие на документа
     * - $docRow->authorId - id на автора на документа, ако той е потребител на системата
     * - $docRow->author - името на автора на документа
     * - $docRow->authorEmail - името на автора на документа
     * - $docRow->state - състояние на документа
     */
    function getDocumentRow($id)
    {
        return $this->class->getDocumentRow($id);
    }
    
    
    /**
     * Връща заглавието на документа, като хипервръзка, сочеща към самия документ
     */
    function getLink($id)
    {
        return $this->class->getLink($id);
    }
    
    
    /**
     * @todo Чака за документация...
     */
    function getIconStyle($id)
    {
        return $this->class->getIconStyle($id);
    }
    
    
    /**
     * Връща визуалното представяне на документа
     */
    function getDocumentBody($id, $mode = 'html')
    {
        return $this->class->getDocumentBody($id, $mode);
    }
    
    
    /**
     * Определя състоянието на нишката от документи
     * Външните документи правят нишката в състояние "отворено",
     * а всички останали - в "затворено"
     */
    function getThreadState($id)
    {
        return $this->class->getThreadState($id);
    }
    
    
    /**
     * Потребителите, с които е споделен този документ
     *
     * @return string keylist(mvc=core_Users)
     */
    function getShared($id)
    {
        return $this->class->getShared($id);
    }
    
    
    /**
     * Проверка дали нов документ може да бъде добавен в
     * посочената папка като начало на нишка
     *
     * @param $folderId int ид на папката
     * @param $firstClass string класът на корицата на папката
     */
    static function canAddToFolder($folderId, $folderClass)
    {
        return $this->class->canAddToFolder($folderId, $folderClass);
    }
    
    
    /**
     * Проверка дали нов документ може да бъде
     * добавен в посочената ник-а
     *
     * @param $threadId int ид на нишката
     * @param $firstClass string класът на първия документ в нишката
     */
    function canAddToThread($threadId, $firstClass)
    {
        return $this->class->canAddToThread($threadId, $firstClass);
    }
    
    
    /**
     * Връща възможните типове за файлови формати, към които може да се конвертира дадения документ
     *
     * @return array $res - Масив с типа (разширението) на файла и стойност указваща дали е избрана 
     *                      по подразбиране
     */
    function getPossibleTypeConvertings()
    {
        return $this->class->getPossibleTypeConvertings();
    }
    
    
    /**
     * Конвертира документа към файл от указания тип и връща манипулатора му
     *
     * @param string $fileName - Името на файла, без разширението
     * @param string $type     - Разширението на файла
     *
     * return array $res - Масив с fileHandler' и на документите
     */
    function convertDocumentAsFile($fileName, $type)
    {
        return $this->class->convertDocumentAsFile($fileName, $type);
    }
}
