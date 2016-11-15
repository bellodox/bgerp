<?php


/**
 *  Клас  'unit_MinkPColab' - PHP тестове за колаборатор
 *
 * @category  bgerp
 * @package   tests
 * @author    Milen Georgiev <milen@download.bg>
 * @copyright 2006 - 2012 Experta OOD
 * @license   GPL 3
 * @since     v 0.1
 * @link
 */

class unit_MinkPColab extends core_Manager {
     
    /**
     * Стартира последователно тестовете от MinkPColab 
     */
    //http://localhost/unit_MinkPColab/Run/
    public function act_Run()
    {
        if (!TEST_MODE) {
            return;
        }
        
        $res = '';
        $res .= "<br>".'MinkPColab';
        $res .= "  1.".$this->act_InstallColab();
        $res .= "  2.".$this->act_CreateColab();
        $res .= "  3.".$this->act_AddRights();
        $res .= "  4.".$this->act_LogColab();
        return $res;
    }
    
    /**
     * Логване
     */
    public function SetUp()
    {
        $browser = cls::get('unit_Browser');
        $browser->start('http://localhost/');
        
        //Потребител DEFAULT_USER (bgerp)
        $browser->click('Вход');
        $browser->setValue('nick', unit_Setup::get('DEFAULT_USER'));
        $browser->setValue('pass', unit_Setup::get('DEFAULT_USER_PASS'));
        $browser->press('Вход');
        return $browser;
    }
  
    /**
     * Избор на фирма
     */
    public function SetFirm()
    {
        $browser = $this->SetUp();
        $browser->click('Визитник');
        $browser->click('F');
        $Company = 'Фирма bgErp';
        $browser->click($Company);
        $browser->press('Папка');
        return $browser;
    }
    /**
     * 1. Инсталиране на пакета colab
     */
    //http://localhost/unit_MinkPColab/InstallColab/
    function act_InstallColab()
    {
        // Логване
        $browser = $this->SetUp();
    
        $browser->click('Админ');
        $browser->setValue('search', 'colab');
        $browser->press('Филтрирай');
        //$browser->click('Активирай');
        $browser->open('http://localhost/core_Packs/install/?pack=colab');
        //echo $browser->getHtml();
    }
    
    /**
     * 2. Създаване на колаборатор и потребител към него
     */
    //http://localhost/unit_MinkPColab/CreateColab/
    function act_CreateColab()
    {
        // Логване
        $browser = $this->SetUp();
    
        // Създаване на лице
        $browser->click('Визитник');
        $browser->click('Лица');
        $browser->press('Ново лице');
        $browser->setValue('name', 'Представител на Фирма bgErp');
        $browser->setValue('buzCompanyId', '3');
        $browser->setValue('groupList[9]', True);
        $browser->press('Запис');
        // Добавяне на потребител
        $browser->press('Потребител');
        $browser->setValue('nick', 'colab1');
        $browser->setValue('passNew', '123456');
        $browser->setValue('passRe', '123456');
        $browser->setValue('email', 'colab1@abv.bg');
        $browser->setValue('Headquarter', False);
        $browser->setValue('collaborator', True);
        $browser->press('Запис');
        //return $browser->getHtml();
        
    }
    
    /**
     * 3. Даване на права до папката на фирмата и изход
     */
    //http://localhost/unit_MinkPColab/AddRights/
    function act_AddRights()
    {
        // Логване
        $browser = $this->SetUp();
        $browser->click('Визитник');
        $browser->click('F');
        $Company = 'Фирма bgErp';
        $browser->click($Company);
        $browser->click('Права');
        $browser->click('Свързване на партньор към папката на обекта');
        $browser->setValue('contractorId', 'Представител на Фирма bgErp');
        $browser->press('Запис');
        $browser->click('Изход');
        //Да се затвори браузъра
        
    }  
    
    /**
     * 4. Логване на колаборатора, запитване и продажба
     */
    //http://localhost/unit_MinkPColab/LogColab/
    function act_LogColab()
    {
        // Логване
        $browser = cls::get('unit_Browser');
        $browser->start('http://localhost/');
        
        //Потребител colab1
        $browser->click('Вход');
        $browser->setValue('nick', 'colab1');
        $browser->setValue('pass', '123456');
        $browser->press('Вход');
        //Запитване
        $browser->click('Теми');
        $browser->press('Запитване');
        $browser->setValue('title', 'Запитване от колаборатор');
        $browser->setValue('inqDescription', 'Чували');
        $browser->setValue('measureId', 'брой');
        $browser->press('Запис');
        //Продажба 
        $browser->click('Теми');
        $browser->press('Продажба');
        $browser->setValue('reff', 'от колаборатор');
        $browser->press('Запис');
        
        // Добавяне на артикул
        $browser->press('Артикул');
        $browser->setValue('productId', 'Чувал голям 50 L');
        $browser->refresh('Запис');
        $browser->setValue('packQuantity', '100');
        $browser->press('Запис');
        $browser->press('Заявка');
        //return $browser->getHtml();
       
    }
    
}