<?php
/**
 * PHPRoll.php (RESTfull SPA)
 *
 * @category Application
 * @author Андрей Новиков <bermud@nm.ru>
 * @data 19/10/2015
 */
namespace Application;

class PHPRoll
{
    protected $config = array();
    protected $settings = array();
    protected $header = null;
    protected $method = null;
    protected $contentType = null;
    protected $route = array();
    protected $params = array();

    /**
     * @param $config данные из файла конфигурации
     */
    public function __construct(&$config)
    {
        $this->config = $config;
        $this->header = (function_exists('getallheaders')) ? getallheaders() : self::getAllHeaders($_SERVER);
        // $this->header = self::getallheaders($params);
        $this->method = strtolower($_SERVER['REQUEST_METHOD']);
        $this->contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : null;
        $this->params = self::getParams($_SERVER);
        $this->route = array_filter(explode("/", substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),1)));

        //TODO: create not only hash
        if (!count($this->route) || (count($this->route) ==1 && $this->route[0] == 'index.php')){
            $this->route = $this->config['root'];
        }
        $this->settings = $this->routeSettings();
    }

    /**
     * Готовим параметры
     * @param $params
     * @return array
     */
    static public function getParams($params)
    {
        $result =  array();
        switch ($params['REQUEST_METHOD']){
            case 'PUT':
            case 'POST':
                parse_str(file_get_contents('php://input'), $result);
                break;
            case 'GET':
            case 'DELETE':
            default:
                parse_str(parse_url($params['REQUEST_URI'], PHP_URL_QUERY), $result);
        }
        //return urldecode($result);
        return $result;
    }

    /**
     * Если нет getallheaders()
     * @param $params
     * @return array
     */
    static public function getAllHeaders($params)
    {
        $headers = array();
        foreach ($_SERVER as $name => $value) {
            if ((substr($name, 0, 5) == 'HTTP_') || ($name == 'CONTENT_TYPE') || ($name == 'CONTENT_LENGTH')) {
                $headers[str_replace(array(' ', 'Http'), array('-', 'HTTP'), ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }

        return $headers;
    }

    /**
     * Формируем установки непсредственно для каждго типа REST
     * @param int $index - по $index получаем название REST API
     * @return array
     */
    protected function routeSettings($index=0){
        $settings =  isset($this->config[$this->route[$index]]) ? $this->config[$this->route[$index]] : array();
        $file = __DIR__ . "/routes/{$this->route[$index]}.json";

        return (file_exists($file)) ? array_merge_recursive($settings, json_decode(file_get_contents($file, FILE_USE_INCLUDE_PATH), true)) : $settings;
    }


    /**
     * Обрабрабатываем REST запрос и генерируем результат
     */
    public function run()
    {
        if (isset($this->settings['type'])) {
            $type = strtolower($this->settings['type']);
            $class = '\Application\Extensions\\' . ucfirst($type);
            $request = (new $class(array_merge_recursive(
                    isset($this->config[$type]) ? $this->config[$type] : array(),
                    $this->routeSettings(array_search($type, $this->route),
                    array('header'=>$this->header)))
            ))->instance($this->route);
            if ($request) return $request($this->params);
        }

        return false;
    }
}
?>