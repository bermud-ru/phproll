<?php
/**
 *  RollHelper.php
 *
 * @category Application
 * @author Андрей Новиков <bermud@nm.ru>
 * @data 11/11/2015
 */
namespace Application;

class RollHelper {
    protected $owner;

    function __construct(&$owner){
        $this->owner = $owner;
    }

    public function __invoke()
    {
        $arguments = func_get_args();
        if (isset($this->owner->_config['method'])) {
            return $this->owner->execMethod($this, $this->owner->_config['method'], $arguments);
        } else {
            return null;
        }
    }

}