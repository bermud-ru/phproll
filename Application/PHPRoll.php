<?php
/**
 * PHPRoll.php
 *
 * @category RIA (Rich Internet Application) / SPA (Single-page Application) Backend
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
    protected $file = null;

    /**
     * @param $config данные из файла конфигурации
     */
    public function __construct($params)
    {
        $this->file = pathinfo(__FILE__, PATHINFO_BASENAME);
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
     * Наследуем родителя для использования его свойств в сложных
     * ветвлениях при выполнении сценария
     *
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
     * Алгоритм формирования последовательносит шаблонов,
     * использование скрипта мастера $script
     * или если передана строка использовать как название скрипта
     *
     * @param array|string $param
     * @param array $opt
     * @return array|string
     */
    protected function tpl($param, array $opt) {
        $ext = $opt['ext'] ?? '.phtml';
        $script = $opt['script'] ?? null;
        $prefix = ''; $name = null;

        if (is_array($param) || ($script && $script != $param)) {
            if (($script === false) || count($param) == 1 && (($script === false) || ($script && in_array($script, $param)))) {
                $name = array_pop($param);
            } else {
                $param = is_array($param) ? $param : [$param];
                foreach ($param as $k => $v) {
                    $name[] = $prefix . $v . $ext;
                    $prefix .= $v . DIRECTORY_SEPARATOR;
                }
                if (count($name) && $script && !in_array($script, $param)) array_unshift($name, $script . $ext);

                return $name;
            }
            if (count($param)) $prefix = implode(DIRECTORY_SEPARATOR, $param) . DIRECTORY_SEPARATOR;
        }

        return empty($name) ? null : $prefix . $name . $ext;
    }

    /**
     * Получаем название шаблона | последовательности вложенных вдруг в друга шаблонов
     *
     * @param $inc boolean шаблон в шаблоне
     * @return string
     */
    public function getPattern($opt)
    {
        $result = '';
        switch ($_SERVER['REQUEST_METHOD'])
        {
            case 'PUT':
            case 'POST':
                $result = $this->tpl(key($this->params), $opt);
                break;
            case 'GET':
            case 'DELETE':
            default:
                $result = $this->tpl($this->path, $opt);
        }

        return $result;
    }

    /**
     * Герерация html котента
     *
     * @param $pattern
     * @param array $options
     * @return string
     */
    public function context($pattern, array $options = array())
    {
        $path = (isset($this->config['view']) ? $this->config['view'] : __DIR__ . DIRECTORY_SEPARATOR);
        $is_set = is_array($pattern);
        $p = array_reverse($is_set ? $pattern : [$pattern]);
        $count = count($p) - 1;

        foreach ($p as $k => $f) {
            $file = (!preg_match('/^\\' . DIRECTORY_SEPARATOR . '.+$/i', $f)) ? $path . $f : $f;
            if (!file_exists($file)) {
                $file = ($is_set) && ($k != $count) ? ((!$k) ? $path . $this->config['404'] ?? null : null) : $path . ($this->config['404'] ?? 'index.phtml');
            }
            $context = null;
            if ($file) {
                extract($options); ob_start(); require($file);
                $context = ob_get_clean();
            }

            if ($is_set &&  $k < $count) {
                if (!isset($options['include'])) $options['include'] = [];
                $options['include'][$f] = $context;
            } else {
                return $context;
            }
        }
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
                $headers[str_replace(array(' ', 'Http'), array('-', 'HTTP'),
                ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;

        return $headers;
    }

    /**
     * Генерация заголовка ответа и форматирование кода ответа
     * @param $type
     * @param $params
     * @return int
     */
    public function responce($type, $params)
    {//TODO: Разобраться с заголовками каждого типа ответа
        if (isset($_SERVER["HTTP_USER_AGENT"]) && strstr($_SERVER["HTTP_USER_AGENT"], "MSIE") == false) {
            header("Cache-Control: no-cache");
            header("Pragma: no-cache");
        } else {
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Pragma: public");
        }
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
        header("Access-Control-Allow-Headers: Content-Type");
        header('Content-Encoding: utf-8');
        header('Content-Transfer-Encoding: binary');
        switch ($type) {
            case 'json':
                header('HTTP/1.1 206 Partial content');
                header('Expires: 0');
                header('Content-Description: json response');
                header('Content-Type: Application/json; charset=utf-8');
                header('Content-Disposition: attachment; filename=responce.json');
                return json_encode($params);
            case 'error':
                header('HTTP/1.1 206 Partial content');
                header('Expires: 0');
                header('Content-Description: json response');
                header('Content-Type: Application/json; charset=utf-8');
                header('Content-Disposition: attachment; filename=responce.json');
                $params['result'] = 'error'; $params['code'] = $params['code'] ?? 500;$params['message'] = $params['message'] ?? null;
                return json_encode($params);
            case 'file':
                header('Content-Description: downloading file');
                header('Content-Transfer-Encoding: binary');
                header('Content-Disposition: attachment; filename=upload.ext');
                //DOTO: upload file code
                break;
            case 'view':
                header('Content-Type: text/html; charset=utf-8');
                if (isset($params['code'])) http_response_code(intval($params['code']) ?? 200);
                $pattern = count($params['pattern']) ? $params['pattern'] : 'index.phtml';
                if ( $pattern ) {
                    return $this->context($pattern, array(
                            'self' => &$this,
                            'json' => function (array $params) {
                                echo $this->responce('json', $params);
                                exit(1);
                            }
                        )
                    );
                    break;
                }
            default:
                header('Content-Description: html view');
                header('Content-Type: Application/xml; charset=utf-8');
                header('Content-Disposition: attachment; filename=responce.html');
                return $params;
        }
    }

    public function run(array $opt=[])
    {
        if (isset($opt['method']) && method_exists($this, $opt['method']))
            return call_user_func_array([$this,$opt['method']], [$opt['params'] ?? []]);

        $content = $this->route(isset($this->path) ? $this->path : ['default']);
        if ($content && is_string($content)) return $content;

        return $this->responce('view', ['pattern'=>$this->getPattern(array_merge(['script'=>'index','ext'=>'.phtml'], $opt['tpl'] ?? []))]);
    }
}
?>