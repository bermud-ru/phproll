<?php
/**
 *  CRollValidator.php
 *
 * @category Application
 * @author Андрей Новиков <bermud@nm.ru>
 * @data 11/11/2015
 */
namespace Application;

class CRollValidator {
    protected $_owner;

    function __construct(&$owner){
        $this->_owner = $owner;
    }
}