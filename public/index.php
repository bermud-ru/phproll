<?php
/**
 * index.php
 *
 * @category RIA (Rich Internet Application) / SPA (Single-page Application)
 * @author Андрей Новиков <andrey@novikov.be>
 * @data 07/12/2015
 *
 */
namespace Application;

/**
 * Simple PHP backend script for RIA (Rich Internet Application) / SPA (Single-page Application) frontend
 * @param $pattern
 * @param array $options
 * @return string
 */

$loader = require_once __DIR__.'/../vendor/autoload.php';
spl_autoload_register(function($className)
{
    //$namespace=str_replace("\\","/",__NAMESPACE__);
    $className=str_replace("\\","/",$className);
    $class=__DIR__ . "/../{$className}.php";
    if (is_readable($class)) require_once $class;
});

echo (new \Application\PHPRoll(require('../config.php')))->run();

exit(1);
?>