<?php
/**
 * PHPRoll.php
 *
 * @category RIA (Rich Internet Application) / SPA (Single-page Application)
 * @author Андрей Новиков <andrey@novikov.be>
 * @data 07/12/2015
 *
 */
namespace Application;

/**
 * Simple PHP backend script for RIA (Rich Internet Application) / SPA (Single-page Application) frontend
 */

class PHPRoll
{
    protected $config = [];
    protected $script = null;
    protected $route = null;
    protected $header = [];
    protected $params = [];

    /**
     * @param $config данные из файла конфигурации
     */
    public function __construct(&$config)
    {
        $this->config = $config;
        $this->script = pathinfo(__FILE__,PATHINFO_BASENAME);
        $this->route = $config['route'](array_filter(explode("/", substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),1))));
        $this->header = (function_exists('getallheaders')) ? getallheaders() : $this->__getAllHeaders($_SERVER);
        $this->params = array();
    }

    /**
     * @param $pattern
     * @param array $options
     * @return string
     */
    public function contex($pattern, array $options = array())
    {
        $path = (isset($this->config['view']) ? $this->config['view'] : '') . $pattern;
        if (!file_exists($path)) {
            $options['error'] = array('message'=> "File [$pattern] not found");
            $path = (isset($this->config['view']) ? $this->config['view'] : '') . $this->config['pattern']();
        }
        extract($options); ob_start(); require($path);
        return ob_get_clean();
    }

    /**
     * Если нет getallheaders()
     * @param $params
     * @return array
     */
    protected function __getAllHeaders()
    {
        $headers = array();
        foreach ($_SERVER as $name => $value)
            if ((substr($name, 0, 5) == 'HTTP_') || ($name == 'CONTENT_TYPE') || ($name == 'CONTENT_LENGTH'))
                $headers[str_replace(array(' ', 'Http'), array('-', 'HTTP'), ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
        return $headers;
    }

    public function run(){
        switch ($_SERVER['REQUEST_METHOD'])
        {
            case 'PUT':
            case 'POST':
                parse_str(file_get_contents('php://input'), $this->params);
                $pattern = $this->config['pattern']($name = key($this->params));
                break;
            case 'GET':
            case 'DELETE':
            default:
                parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), $this->params);
                $pattern = $this->config['pattern'](current($this->route));
        }

        if ($pattern) echo $this->contex($pattern, array(
            'params' => $this->params,
            'header' => $this->header,
            'route' => $this->route,
            'config' => $this->config,
            'script' => '/'. (count($this->route) ? implode($this->route, '/') . (strtolower(end($this->route)) != $this->script ? $this->script : '') : $this->script),
            'json' => function (array $params){
                echo json_encode($params);
                exit(1);
            }));
    }
}
?>