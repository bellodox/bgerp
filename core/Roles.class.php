<?php



/**
 * С каква роля да получават новите потребители по подразбиране?
 */
defIfNot('EF_ROLES_DEFAULT', 'user');


/**
 * Клас 'core_Roles' - Мениджър за ролите на потребителите
 *
 *
 * @category  ef
 * @package   core
 * @author    Milen Georgiev <milen@download.bg>
 * @copyright 2006 - 2012 Experta OOD
 * @license   GPL 3
 * @since     v 0.1
 * @link
 */
class core_Roles extends core_Manager
{

    /**
     * Заглавие на модела
     */
    var $title = 'Роли';
    

    /**
     * Статична променлива за съхранение на съществуващите роли в системата
     * (id -> Role, Role -> id)
     */
    static $rolesArr;
    
    
    /**
     * Променлива - флаг, че изчислените роли за наследяване 
     * и потребителските роли трябва да се преизчислят
     */
    var $recalcRoles = FALSE;


    /**
     * Наследените роли, преди да редактираме формата
     */
    var $oldInheritRecs;
    

    /**
     * Колонки в списъчния изглед
     */
    var $listFields = 'id,role, inheritInput, type';
    

    /**
     * Описание на модела (таблицата)
     */
    function description()
    {
        $this->FLD('role', 'varchar(64)', 'caption=Роля,mandatory');
        $this->FLD('inheritInput', 'keylist(mvc=core_Roles,select=role,groupBy=type)', 'caption=Наследяване,notNull');
        $this->FLD('inherit', 'keylist(mvc=core_Roles,select=role,groupBy=type)', 'caption=Калкулирано наследяване,input=none,notNull');
        $this->FLD('type', 'enum(job=Модул,team=Екип,rang=Ранг,system=Системна,position=Длъжност)', 'caption=Тип,notNull');
        
        $this->setDbUnique('role');
        
        $this->load('plg_Created,plg_SystemWrapper,plg_RowTools');
    }
    
    
    /**
     * Добавя посочената роля, ако я няма
     */
    static function addRole($role, $inherit = NULL, $type = 'job')
    {
        expect($role);
        $rec = new stdClass();
        $rec->role = $role;
        $rec->type = $type;
        $rec->createdBy = -1;
        
        $Roles = cls::get('core_Roles');
        
        if(isset($inherit)) {
            $rec->inheritInput = $Roles->getRolesAsKeylist($inherit);
        }
        
        $rec->id = $Roles->fetchField("#role = '{$rec->role}'", 'id');
        
        $id = $rec->id;
        
        $Roles->save($rec);
        
        return !isset($id);
    }
    
    
    /**
     * Зарежда ролите, ако все още не са заредени
     */
    static function loadRoles()
    {
        if(!count(self::$rolesArr)) {
            
            self::$rolesArr = core_Cache::get('core_Roles', 'allRoles', 1440, array('core_Roles'));
            
            if(!self::$rolesArr) {
                
                $query = static::getQuery();
                
                while($rec = $query->fetch()) {
                    if($rec->role) {
                        self::$rolesArr[$rec->role] = $rec->id;
                        self::$rolesArr[$rec->id] = $rec->role;
                    }
                }
                
                core_Cache::set('core_Roles', 'allRoles', self::$rolesArr, 1440, array('core_Roles'));
            }
        }
    }
    
    
    /**
     * Връща id-то на ролята според името и
     */
    static function fetchByName($role)
    {
        self::loadRoles();
        
        return self::$rolesArr[$role];
    }
    
    
    /**
     * Създава рекурсивно списък с всички роли, които наследява посочената роля
     *
     * @param mixed $roles keylist или масив от роли, където елементите са id-тата, наименованията или записите на ролите
     * @return array масив от първични ключове на роли
     */
    static function expand($roles, &$current = array())
    {
        if (!is_array($roles)) {
            $roles = keylist::toArray($roles, TRUE);
        }
       
        foreach ($roles as $role) {
            if (is_object($role)) {
                $rec = $role;
            } elseif (is_numeric($role)) {
                $rec = static::fetch($role); 
            } else {
                $rec = static::fetch("#role = '{$role}'");
            }

            // Прескачаме насъсществуващите роли
            if(!$rec) continue;
            
            if ($rec && !isset($current[$rec->id])) {
                $current[$rec->id] = $rec->id;
                $current += static::expand($rec->inherit, $current);
            }
        }
        
        return $current;
    }
    
    
    /**
     * Връща keylist с всички роли от посочения тип
     */
    static function getRolesByType($type)
    {
        $roleQuery = core_Roles::getQuery();
        
        while($roleRec = $roleQuery->fetch("#type = '{$type}'")) {
            $res[$roleRec->id] = $roleRec->id;
        }
        
        return keylist::fromArray($res);
    }


    /**
     * Връща keylist с роли от вербален списък
     */
    static function getRolesAsKeylist($roles)
    {
        // Ако входния аргумент е keylist - директно го връщаме
        if(keylist::isKeylist($roles)) {

            return $roles;
        }

        $rolesArr = arr::make($roles);
        
        $Roles = cls::get('core_Roles');
        
        foreach($rolesArr as $role) {
            $id = $Roles->fetchByName($role);
            expect($id, $role);
            $keylistArr[$id] = $id;
        }
        
        $keylist = keylist::fromArray($keylistArr);
        
        return $keylist;
    }
    
    
    /**
     * Връща масив с броя на всички типове, които се срещат
     * 
     * @paramt keyList, array или list $roles - id' тата на ролите
     * 
     * @return array $rolesArr - Масив с всички типове и броя срещания
     */
    static function countRolesByType($roles) 
    {
        $res = array();

        if(is_string($roles) && $roles) {
            if(!keylist::isKeylist($roles)) {
                //Вземаме всики типове роли
                $roles = self::getRolesAsKeylist($roles);
            }
            $roles = keylist::toArray($roles);
        } elseif(is_int($roles)) {
            $roles = array($roles => $roles);
        } else {
            expect(is_array($roles));
        }
        
        if(count($roles)) {
            foreach ($roles as $id => $dummy) {
                
                $type = self::fetchField($id, 'type');

                if ($type) {
                    
                    //За всяко срещане на роля добавяме единица
                    $res[$type] += 1 ;
                }
            }
        }

        return $res;
    }


    /**
     * Проверка за зацикляне след субмитване на формата. Разпъване на всички наследени роли
     */
    static function on_AfterInputEditForm($mvc, $form)
    {
        $rec = $form->rec;
        
        // Ако формата е изпратена успешно
        if ($form->isSubmitted()) {
            
            // Шаблона за проверка на валидна роля
            // Да започва с буква(кирилица или латиница) или долна черта
            // Може да съдържа само: букви(кирилица или латиница), цифри, '_' и '-'
            $pattern = "/^[a-zА-Я\\_]{1}[a-z0-9А-Я\\_\\-]*$/iu";
            
            // Ако не е валидна роля
            if (!preg_match($pattern, $rec->role)) {
                
                // Сетваме грешка
                $form->setError('role', 'Некоректно име на роля|*: ' . $mvc->getVerbal($form->rec, 'role').' - |допустими са само: букви, цифри|*, "&nbsp_&nbsp", "&nbsp-&nbsp".');    
            }
        }
        
        // Ако формата е субмитната и редактираме запис
        if ($form->isSubmitted() && ($rec->id)) {
            
            if($rec->inheritInput || $rec->inherit) {
                
                $expandedRoles = self::expand($form->rec->inheritInput);
                
                // Ако има грешки
                if ($expandedRoles[$rec->id]) {
                    $form->setError('inherit', "|Не може да се наследи роля, която е или наследява текущата роля");  
                } else {
                    $rec->inherit = keylist::fromArray($expandedRoles);
                }

            }
         }
    }


    /**
     * Изпълнява се при преобразуване на реда към вербални стойности
     */
    function on_AfterRecToVerbal($mvc, $row, $rec, $fields = array())
    {
        $rolesInputArr = keylist::toArray($rec->inheritInput);
        $rolesArr      = keylist::toArray($rec->inherit);

        foreach($rolesArr as $roleId) {

            if(!$rolesInputArr[$roleId]) {
                $addRoles .= ($addRoles ? ', ' : '') . core_Roles::fetchByName($roleId);
            }
        }

        if($addRoles) {

            $row->inheritInput .= "<div style='color:#666;'>" . tr("индиректно") . ": " . $addRoles . "</div>";
        }

        $row->inheritInput = "<div style='max-width:400px;'>{$row->inheritInput}</div>";

    }


    /**
     * Преизчислява за всяка роля, всички наследени индеректно роли
     */
    static function rebuildRoles()
    {
        $i = 0;

        $maxI = self::count() + 1;

        $Roles = cls::get('core_Roles');
        
        do {
            
            $haveChanges = FALSE;
            
            expect($i++ <= $maxI);
            
            $query = self::getQuery();

            while($rec = $query->fetch()) {
                
                $calcRolesArr = self::expand($rec->inheritInput);
                
                $calcRolesKeylist = keylist::fromArray($calcRolesArr);

                if(($calcRolesKeylist || $rec->inherit) && ($calcRolesKeylist != $rec->inherit)) {
                    $rec->inherit = $calcRolesKeylist;
                    $haveChanges = TRUE;
                    $Roles->save_($rec, 'inherit');
                    $ind++;
                }
            }

        } while($haveChanges);
        
        return "<li> Преизчислени са $ind индиректни роли</li>";
    }
    
    function act_Test()
    {   
        self::rebuildRoles();
        core_Users::rebuildRoles();

        return self::on_Shutdown($this);

    }


    /**
     * Получава управлението, когато в модела има промени
     */
    static function haveChanges()
    {
        $Roles = cls::get('core_Roles');

        $Roles->recalcRoles = TRUE;

        // Нулираме статичната променлива
        self::$rolesArr = NULL;
        
        // Изтриваме кеша
        core_Cache::remove('core_Roles', 'allRoles');

    }


    /**
     * При шътдаун на скрипта преизчислява наследените роли и ролите на потребителите
     */
    static function on_Shutdown($mvc)
    {
        if($mvc->recalcRoles) {
            self::rebuildRoles();
            core_Users::rebuildRoles();
        }

        $mvc->recalcRoles = FALSE;
    }


    /**
     * Изпълнява се след запис/промяна на роля
     */
    static function on_AfterSave($mvc, &$id, $rec, $saveFileds = NULL)
    {
        $mvc->haveChanges();
    }
    

    /**
     * Изпълнява се след запис/промяна на роля
     */
    static function on_AfterDelete($mvc, &$id)
    {
        $mvc->haveChanges();
    }
    
    
    /**
     * Само за преход между старата версия
     */
    function on_AfterSetupMVC($mvc, &$res)
    {
        
        if (!$this->fetch("#role = 'admin'")) {
            $rec = new stdClass();
            $rec->role = 'admin';
            $rec->type = 'system';
            $this->save($rec);
            $res .= "<li> Добавена роля 'admin'";
        }
        
        if (!$this->fetch("#role = '" . EF_ROLES_DEFAULT . "'")) {
            $rec = new stdClass();
            $rec->role = EF_ROLES_DEFAULT;
            $rec->type = 'system';
            $this->save($rec);
            $res .= "<li> Добавена роля '" . EF_ROLES_DEFAULT . "'";
        }
        
    }

 }
