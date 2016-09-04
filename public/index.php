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

(require_once __DIR__.'/../vendor/autoload.php')->add('Application\\', __DIR__ . "/../");

echo (new \Application\PHPRoll(require('../config.php')))->run();

exit(1);
?>