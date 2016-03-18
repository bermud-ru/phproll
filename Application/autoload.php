<?php
/**
 * autoload.php
 *
 * @category Autoload schema
 * @category RIA (Rich Internet Application) / SPA (Single-page Application) Backend
 * @author Андрей Новиков <andrey@novikov.be>
 * @data 07/12/2015
 *
 */
namespace Application;

spl_autoload_register(function($className)
{
    //$namespace=str_replace("\\","/",__NAMESPACE__);
    $className=str_replace("\\","/",$className);
    $class=__DIR__ . "/../{$className}.php";

    if (is_readable($class)) require_once $class;
});

?>