<?php


/**
 * Драйвер за работа с .kml файлове.
 * 
 * @category  vendors
 * @package   fileman
 * @author    Yusein Yuseinov <yyuseinov@gmail.com>
 * @copyright 2006 - 2016 Experta OOD
 * @license   GPL 3
 * @since     v 0.1
 */
class fileman_webdrv_Kml extends fileman_webdrv_Xml
{
    
    
    /**
     * Кой таб да е избран по подразбиране
     * @Override
     * @see fileman_webdrv_Generic::$defaultTab
     */
    static $defaultTab = 'preview';
    
    
    /**
     * Връща всички табове, които ги има за съответния файл
     * 
     * @param object $fRec - Записите за файла
     * 
     * @return array
     * 
     * @Override
     * @see fileman_webdrv_Generic::getTabs
     */
    static function getTabs($fRec)
    {
        // Вземаме табовете от родителя
        $tabsArr = parent::getTabs($fRec);
        
        // Вземаме съдържанието
        $view = static::renderView($fRec);
        
        // Таб за съдържанието
		$tabsArr['preview'] = (object) 
			array(
				'title'   => 'Изглед',
				'html'    => "<div class='webdrvTabBody' style='white-space:pre-wrap;'><div class='webdrvFieldset'><div class='legend'>" . tr("Преглед") . "</div>{$view}</div></div>",
				'order' => 6,
				'tpl' => $view,
			);
        
        return $tabsArr;
    }
    
    
    /**
     * Преглед на kml файла
     * 
     * @param object $fRec - Записите за файла
     * 
     * @return ET
     */
    public static function renderView($fRec)
    {
        $content = fileman_Files::getContent($fRec->fileHnd);
        
        $content = mb_strcut($content, 0, 1000000);
        
        $content = i18n_Charset::convertToUtf8($content);
        
        $content = trim($content);
        
        $valArr = self::parseKmlString($content);
        
        $attr = self::getPreviewWidthAndHeight();
        $attr['height'] = $attr['width'] / 1.618;
        
        $tpl = location_Paths::renderView($valArr, $attr);
        
        return $tpl;
    }
    
    
    /**
     * Подготвя данните от подадения kml файл
     * 
     * @param string $str
     * 
     * @return array - Масив, който може да се подаде към location_Paths::renderView
     */
    public static function parseKmlString($str)
    {
        $valArr = array();
        
        $xml = @simplexml_load_string($str);
        
        if (!$xml) return $valArr;
        
        $valArr = self::prepareXml($xml);
        
        return $valArr;
    }
    
    
    /**
     * Опитва се да извлече данние от xml обекта и да ги подготви във формата на location_Path
     * 
     * @param SimpleXMLElement $xml
     * @return array
     */
    protected static function prepareXml($xml)
    {
        $placemark = array();
        
        // В зависимост от структурата определяме променливата
        if ($xml->Document) {
            if ($xml->Document->Folder) {
                if ($xml->Document->Folder->Placemark) {
                    $placemark = $xml->Document->Folder->Placemark;
                }
            } else {
                $placemark = $xml->Document->Placemark;
            }
        } else {
            $placemark = $xml->Placemark;
        }
        
        // Информация по-подразбиране
        $info = '';
        $info = (string)$xml->Document->Placemark->name;
        if (!$info) {
            $info = (string)$xml->Placemark->name;
        }
        if (!$info) {
            $info = (string)$xml->Document->name;
        }
        
        $coordinates = $infoArr = array();
        
        foreach ($placemark as $pl) {
            if ($pl->Point) {
                // В този случай се отнасят за един обект
                $coordinates[0] .= "\n" . (string)$pl->Point->coordinates;
                
                // Опитваме се да намерим по-точна информация
                $info2 = '';
                $info2 = (string)$pl->Point->name;
                if (!$info2) {
                    $info2 = (string)$pl->name;
                }
                $infoArr[0] = $info2 ? $info2 : $info;
            } elseif ($pl->MultiGeometry->LineString) {
                foreach ((array)$pl->MultiGeometry as $ls) {
                    foreach ((array)$ls as $lc) {
                        $coordinates[] = (string)$lc->coordinates;
                        
                        // Опитваме се да намерим по-точна информация
                        $info2 = '';
                        $info2 = (string)$lc->comment;
                        if (!$info2) {
                            $info2 = (string)$pl->name;
                        }
                        $infoArr[] = $info2 ? $info2 : $info;
                    }
                }
            } elseif ($pl->Polygon) {
                if (!($boundary = $pl->Polygon->outerBoundaryIs)) {
                    $boundary = $pl->Polygon->innerBoundaryIs;
                }
                $coordinates[] = (string)$boundary->LinearRing->coordinates;
                
                // Опитваме се да намерим по-точна информация
                $info2 = '';
                $info2 = (string)$boundary->name;
                if (!$info2) {
                    $info2 = (string)$pl->Polygon->name;
                }
                if (!$info2) {
                    $info2 = (string)$pl->name;
                }
                $infoArr[] = $info2 ? $info2 : $info;
            }
        }
        
        // Преобразуваме масива с координати и информация във формата на location_Path
        foreach ($coordinates as $i => $c) {
            $c = trim($c);
            $cExplode = explode("\n", $c);
            
            foreach ($cExplode as $cStr) {
                if (!$cStr) continue;
                
                $eArr = explode(',', $cStr);
                
                $cArr[$i]['coords'][] = array($eArr[1], $eArr[0]);
                $cArr[$i]['info'] = $infoArr[$i];
            }
        }
        
        return $cArr;
    }
}
