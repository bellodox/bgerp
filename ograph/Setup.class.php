<?php


/**
 * Клас 'ograph_Setup'
 *
 * Исталиране/деинсталиране на Apachetika
 *
 *
 * @category  bgerp
 * @package   ograph
 * @author    Gabriela Petrova <gab4eto@gmail.com>
 * @copyright 2006 - 2015 Experta OOD
 * @license   GPL 3
 * @since     v 0.1
 * @link
 */
class ograph_Setup extends core_ProtoSetup
{

	
	/**
	 * Версия на пакета
	 */
	public $version = '0.1';
	
	
	/**
	 * Описание на модула
	 */
	public $info = "Пакет за работа с Open Graph Protocol елементи";
	
	
	/**
	 * Пакет без инсталация
	 */
	public $noInstall = TRUE;
	
}

