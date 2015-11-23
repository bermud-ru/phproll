<?php
/**
 * index.php
 *
 * @category   Application
 * @author Андрей Новиков <bermud@nm.ru>
 * @data 06/10/2015
 *
 */

namespace Application;

spl_autoload_register(function($className)
{
    //$namespace=str_replace("\\","/",__NAMESPACE__);
    $className=str_replace("\\","/",$className);
    $class=__DIR__ . "/../{$className}.php";
    if (is_readable($class)) {
        require_once $class;
    }
});

(new \Application\PHPRoll(require(__DIR__ . '/../Application/config.php')))->run();

?>