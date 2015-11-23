<?php
/**
 * config.php
 *
 * @category Application
 * @author Андрей Новиков <bermud@nm.ru>
 * @data 06/10/2015
 *
 */
namespace Application;

$config = array(
    'db' => array('dsn' => 'sqlite://srv/app-roll.db'),
    'root'=> array('index', 'index.tmpl'),
    'index'=>array(
        'type'=>'file',
        'path'=> "/srv/roll/Application/templates/app",
        'format'=>'php'
    ),
);

return $config;
?>