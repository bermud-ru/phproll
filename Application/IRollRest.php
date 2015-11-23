<?php
/**
 *  IRollRest.php
 *
 * @category Application
 * @author Андрей Новиков <bermud@nm.ru>
 * @data 11/11/2015
 */
namespace Application;

interface IRollRest
{
    public function get(array $params);
    public function put(array $params);
    public function post(array $params);
    public function delete(array $params);
}