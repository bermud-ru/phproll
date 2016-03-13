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
    public $config = [];
    public $header = [];
    public $params = [];
    public $path = [];

    protected $parent = null;
    protected $script = null;

    /**
     * @param $config данные из файла конфигурации
     */
    public function __construct($params)
    {
        $this->script = pathinfo(__FILE__, PATHINFO_BASENAME);
        if (is_array($params)) {
            $this->config = $params;
            $this->header = (function_exists('getallheaders')) ? getallheaders() : $this->__getAllHeaders($_SERVER);
            $this->initParams();
            $this->path = array_filter(explode("/", substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), 1)));
        }
        elseif ($params instanceof \Application\PHPRoll)
        {
            $this->setParent($params);
        }
    }

    /**
     * Маршрутизатор
     * @param $params
     * @return mixed
     */
    protected function route($params)
    {
        return isset($this->config['route']) && is_callable($this->config['route']) ? $this->config['route']($this, $params) : null;
    }

    /**
     * Наследуем родителя
     * @param $parent
     * @return mixed
     */
    protected function setParent(&$parent)
    {
        return $this->parent = $parent;
    }

    /**
     * Получаем значение параменных в запросе
     *
     */
    protected function initParams()
    {
        switch ($_SERVER['REQUEST_METHOD'])
        {
            case 'PUT':
            case 'POST':
                parse_str(file_get_contents('php://input'), $this->params );
                break;
            case 'GET':
            case 'DELETE':
            default:
                parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), $this->params);
        }
    }

    /**
     * Получаем название шаблона
     * @return string
     */
    protected function getPattern()
    {
        $pattern = isset($params['pattern']) && is_callable($params['pattern'])  ? $params['pattern'] :
            function($param=null, $value = 'index.phtml') { return ($param) ? $param . '.phtml' : $value; };

        $result = '';
        switch ($_SERVER['REQUEST_METHOD'])
        {
            case 'PUT':
            case 'POST':
                $result = $pattern(key($this->params));
                break;
            case 'GET':
            case 'DELETE':
            default:
                $result = $pattern(current($this->path));
        }
        return $result;
    }

    /**
     * @param $pattern
     * @param array $options
     * @return string
     */
    public function contex($pattern, array $options = array())
    {
        $path = (isset($this->config['view']) ? $this->config['view'] : '') . $pattern;
        if (!file_exists($path))
        {
            $options['error'] = array('message'=> "File [$pattern] not found");
            $path = (isset($this->config['view']) ? $this->config['view'] : '') . ($this->config['pattern']());
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

    /**
     * Генерация заголовка ответа и форматирование кода ответа
     * @param $type
     * @param $params
     * @return int
     */
    public function responce($type, $params)
    {
        if (strstr($_SERVER["HTTP_USER_AGENT"], "MSIE") == false)
        {
            header("Cache-Control: no-cache");
            header("Pragma: no-cache");
        }
        else
        {
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Pragma: public");
        }

        header('HTTP/1.1 206 Partial content');
        header('Content-Encoding: utf-8');
        header('Content-Transfer-Encoding: binary');

        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
        header("Access-Control-Allow-Headers: Content-Type");
        header('Expires: 0');

        switch ($type)
        {
            case 'json':
                header('Content-Description: json response');
                header('Content-Type: Application/json; charset=utf-8');
                header('Content-Disposition: attachment; filename=responce.json');
                return json_encode($params);
            case 'file':
                header('Content-Description: downloading file');
                header('Content-Transfer-Encoding: binary');
                header('Content-Disposition: attachment; filename=upload.ext');
                //DOTO: upload file code
                break;
            case 'view':
                break;
            case 'error':
                http_response_code(intval($params));
                return json_encode(array('result'=>'error','code'=>$params));
                break;
            default:
                header('Content-Description: html view');
                header('Content-Type: Application/xml; charset=utf-8');
                header('Content-Disposition: attachment; filename=responce.html');
                return $params;
        }
    }

    public function run(&$method=null, &$params=[])
    {
        if ($method && method_exists($this, $method)) return call_user_func_array($this->{$method}, $params);

        $content = $this->route(isset($this->path[0]) && $this->path[0] ? $this->path[0] : 'default');
        if ($content && is_string($content)) return $content;

        $pattern = $this->getPattern();
        if ($pattern) return $this->contex($pattern, array(
                'params' => $params || $this->params,
                'header' => $this->header,
                'route' => $this->path,
                'config' => $this->config,
                'script' => '/' . (count($this->path) ? implode($this->path, '/') . (strtolower(end($this->path)) != $this->script ? $this->script : '') : $this->script),
                'json' => function (array $params){
                    echo $this->responce('json', $params);
                    exit(1);
                }
            )
        );
    }
}
?>