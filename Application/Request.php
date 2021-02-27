<?php
/**
 * Request.php
 *
 * @category Simple Request handeler for REST json data
 * @author Андрей Новиков <andrey (at) novikov (dot) be>
 * @data 11/06/2020
 * @status beta
 * @version 0.1.2
 * @revision $Id: Request.php 0004 2020-06-11 23:44:01Z $
 *
 */

namespace Application;

abstract class Request
{
    public $cfg = null;
    public $header = [];
    public $params = [];
    public $request = null;

    /**
     * Конструктор
     *
     * @param $config данные из файла конфигурации
     */
    public function __construct($params)
    {
        $this->cfg = new \Application\Jsonb($params, ['owner' => $this]);
        $this->header = (function_exists('getallheaders')) ? getallheaders() : $this->__getAllHeaders($_SERVER);
        $this->request = $this->RAWRequet();
        $this->initParams();
    }

    /**
     * Request Native property
     *
     * @param $name
     * @return mixed
     * @throws \Exception
     */
    public function __get($name)
    {
        $value = null;
        switch (strtolower($name)) {
            case 'cfg':
                $value = $this->cfg;
                break;
            case (strpos($name, 'db') === 0 ? true : false):
                $value = isset($this->{$name}) ? $this->{$name} : ($this->{$name} = new \Application\PDA($this->cfg->{$name}));
                break;
        }
        return $value;
    }

    /**
     * Если нет getallheaders()
     * @param $params
     * @return array
     */
    protected function __getAllHeaders(): array
    {
        $headers = array();
        foreach ($_SERVER as $name => $value)
            if ((substr($name, 0, 5) == 'HTTP_') || ($name == 'CONTENT_TYPE') || ($name == 'CONTENT_LENGTH'))
                $headers[str_replace(array(' ', 'Http'), array('-', 'HTTP'),
                    ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
        return $headers;
    }

    /**
     * @return array|false|int|string|null
     */
    protected function RAWRequet()
    {
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'PUT':
            case "POST":
                return file_get_contents('php://input');
            case 'GET':
            case 'DELETE':
            default:
                return parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
        }
    }

    /**
     * Получаем значение параменных в запросе
     *
     */
    protected function initParams()
    {

        if (strpos($_SERVER['CONTENT_TYPE'], 'json') !== FALSE) {
            $this->params = json_decode($this->request, true);
        } else {
            mb_parse_str($this->request, $this->params);
        }
        return $this->params;
    }

    /**
     * http_build_query — Генерирует строку запроса
     *
     * @param $a
     * @param string $prefix
     * @param string $separator
     * @return string
     */
    public static function http_build_query($a, $prefix = '', $separator = '&'): string
    {
        if (is_array($a)) {
            $res = []; foreach($a as $key => $value) $res[] = "$prefix$key=$value";
            return implode($separator, $res);
        }
        return (string) $a;
    }

    /**
     * run Abstract method
     *
     * @return mixed
     */
    abstract function run();
}