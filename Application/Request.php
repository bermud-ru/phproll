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

#[AllowDynamicProperties]
abstract class Request
{
    public $cfg = null;
    public $header = [];
    public $response_header = [];
    public $params = [];
    public $uri = null;

    const DEFAULT = 1;
    const BASE64 = 2;
    const OBJECT = 4;

    /**
     * Конструктор
     *
     * @param array $config данные из файла конфигурации
     * @param array|null $header внешний заголовок
     */
    public function __construct(array $params, ?array $header = null)
    {
        $this->cfg = new \Application\Jsonb($params, ['owner' => $this]);
        $this->uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $this->header = $header ?? self::getallheaders();
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
//            case 'cfg':
//                $value = $this->cfg;
//                break;
            case (strpos($name, 'db') === 0 ? true : false):
                $value = isset($this->{$name}) ? $this->{$name} : ($this->{$name} = new \Application\PDA($this->cfg->{$name}));
                break;
        }

        return $value;
    }

    /**
     * COOKIE
     *
     * @param string $param
     * @param int $opt
     * @return mixed|null
     */
    public function cookie(string $param, int $opt = \Application\Request::DEFAULT)
    {
        $p = null;
        if (isset($_COOKIE[$param])) {
            if ($opt & \Application\Request::BASE64) $p = base64_decode($_COOKIE[$param]);
            if ($opt & \Application\Request::OBJECT) {
                $p = json_decode($p ?? $_COOKIE[$param], false, 512, JSON_INVALID_UTF8_IGNORE);
                if (json_last_error() !== JSON_ERROR_NONE) return null; //new \stdClass();
            }
            if ($opt === \Application\Request::DEFAULT) $p = \Application\Parameter::ize($_COOKIE[$param]);
        }
        return $p;
    }

    /**
     * Если нет getallheaders()
     * @param $params
     * @return array
     */
    public static function getallheaders(): array
    {
        if (function_exists('getallheaders')) return getallheaders();

        $headers = [];
        foreach ($_SERVER as $name => $value)
            if ((substr($name, 0, 5) == 'HTTP_') || ($name == 'CONTENT_TYPE') || ($name == 'CONTENT_LENGTH'))
                $headers[str_replace([' ', 'Http'], ['-', 'HTTP'],
                    ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;

        return $headers;
    }

    /**
     * @function RAWRequet
     *
     * @return array|false|int|string|null
     */
    protected function RAWRequet()
    {
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'PUT': case "POST":
                return file_get_contents('php://input');
            case 'GET': case 'DELETE': default:
                return parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
        }
    }

    /**
     * @function initParams
     *
     * Получаем значение параменных в запросе
     * @return array
     */
    protected function initParams()
    {
        $pairs = explode( '&', $this->$this->RAWRequet());
        $params = [];
        if ($pairs) for ($i=0, $c=count($pairs); $i < $c; $i++) {
            $kv = explode('=', $pairs[$i]);
            if (!array_key_exists($kv[0], $params)) {
                $params[$kv[0]] = $kv[1];
            } else if (is_array($params[$kv[0]])) {
                $params[$kv[0]][] = $kv[1];
            } else {
                $params[$kv[0]] = [$params[$kv[0]], $kv[1]];
            }
        }

        return $this->params = $params;
    }

    /**
     * @function http_build_query — Генерирует строку запроса
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
     * @function set_response_header
     *
     * Установка параметров заголовка ответа
     * @param $extra array - injection items for response headers
     */
    final function set_response_header(array $extra = [])
    {
        $a = array_merge($extra, $this->response_header);
        array_walk($a, function ($v, $k) {
            if (is_scalar($v)) {
                $o = trim(preg_replace('/\s+/', ' ', addslashes($v)));
                header("$k: $o");
            } elseif (is_array($v) && count($v)) {
                $o = $v[0];
                $replace = isset($v[1]) ? boolval($v[1]) : TRUE;
                $http_response_code = isset($v[2]) ? intval($v[2]) : 200;
                header("$k: $o", $replace, $http_response_code);
            }
        });
    }

    /**
     * Crash handler
     *
     * @param \Exception $e
     * @return mixed
     */
    abstract function crash(\Exception $e);

    /**
     * run Abstract method
     *
     * @return mixed
     */
    abstract function run();
}