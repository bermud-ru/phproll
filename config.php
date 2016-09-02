<?php
/**
 * config.php
 *
 * @category SPA
 * @author Андрей Новиков <andrey@novikov.be>
 * @data 07/12/2015
 *
 */
namespace Application;

return array(
    'basedir' => __DIR__,
    'view' => __DIR__.'/Application/view/',

    'route' => function($owner, $path)
    {
        $result = null;

//        if (isset($path[0])) {
//            switch ($path[0]) {
//                case 'login':
//                    $result = $owner->responce('json', ['result'=>'ok', 'data'=>$owner->params['login']]);
//                    break;
//            }
//        }
        return $result;
    }
);
?>