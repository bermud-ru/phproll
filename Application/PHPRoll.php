<?php
/**
 * PHPRoll.php
 *
 * @category RIA (Rich Internet Application) / SPA (Single-page Application) Backend
 * @author Андрей Новиков <andrey@novikov.be>
 * @data 23/06/2019
 * @status beta
 * @version 2.1.12b
 * @revision $Id: PHPRoll.php 2.1.1b 2019-06-23 1:04:01Z $
 *
 */

namespace Application;

/**
 * Simple PHP backend script for RIA (Rich Internet Application) / SPA (Single-page Application) frontend
 */

/**
 * Class PHPRoll
 *
 * @package Application
 */
class PHPRoll extends \Application\Request
{
    const FRAMEWORK = 'PHPRoll';
    const VERSION = '2.1.2b';
    const KEY_SEPARATOR = '.';
    // https://developer.mozilla.org/ru/docs/Web/HTTP/Status
    const HTTP_RESPONSE_CODE = [
        100 => 'Continue', 101 => 'Switching Protocol', 102 => 'Processing',
        200 => 'OK', 201 => 'Created', 202 => 'Accepted', 203 => 'Non-Authoritative Information', 204 => 'No Content', 205 => 'Reset Content', 206 => 'Partial Content',
        300 => 'Multiple Choice', 301 => 'Moved Permanently', 302 => 'Found', 303 => 'See Other', 304 => 'Not Modified', 305 => 'Use Proxy', 306 => 'Switch Proxy', 307 => 'Temporary Redirect', 308 => 'Permanent Redirect',
        400 => 'Bad Request', 401 => 'Unauthorized', 402 => 'Payment Required', 403 => 'Forbidden', 404 => 'Not Found', 405 => 'Method Not Allowed', 406 => 'Not Acceptable', 407 => 'Proxy Authentication Required',
        408 => 'Request Timeout', 409 => 'Conflict', 410 => 'Gone', 411 => 'Length Required', 412 => 'Precondition Failed', 413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long', 415 => 'Unsupported Media Type', 416 => 'Requested Range Not Satisfiable', 417 => 'Expectation Failed',
        500 => 'Internal Server Error', 501 => 'Not Implemented', 502 => 'Bad Gateway', 503 => 'Service Unavailable', 504 => 'Gateway Timeout', 505 => 'HTTP Version Not Supported'
    ];

    public $response_header = [];
    public $path = [];
    public $ACL = null;
    
    protected $parent = null;
    protected $file = null;

    /**
     * Конструктор
     *
     * @param $config данные из файла конфигурации
     */
    public function __construct($params)
    {
        $this->file = pathinfo(__FILE__, PATHINFO_BASENAME);
        if ($params instanceof \Application\PHPRoll)
        {
            $this->setParent($params);
        }
        else
        {
            parent::__construct($params);
            $this->path = array_filter(explode("/", substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), 1)));
        }
    }

    /**
     * Маршрутизатор позволяет переопределить обработку урлов
     *
     * @param $params
     * @return mixed
     */
    protected function route($params, array $opt = [])
    {
        return $this->cfg->route($params, $opt);
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
        if (strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== FALSE) {
            $params = $_POST;
        } else if (strpos($_SERVER['CONTENT_TYPE'], 'json') !== FALSE) {
            $params = json_decode($this->request, true);
        } else {
            mb_parse_str($this->request, $params);
        }
        $this->params = new \Application\Jsonb($params, ['owner'=> $this, 'assoc'=>true, 'mode'=>\Application\Jsonb::JSON_ALWAYS]);
    }

    /**
     * Дерево прараметров в запросе разворачивает в массив ключ-значение,
     * создавая идекс вложенности
     *
     * @param array $a
     * @param $r
     * @param null $key
     * @return array
     */
    static function rebuildParams(array $a, &$r, $key = null):array
    {
        function rebuild(array $a, &$r, $key = null)
        {
            foreach ($a as $k => $v)
                if (!is_array($v))
                    $r[$key ? $key . \Application\PHPRoll::KEY_SEPARATOR . $k : $k] = $v;
                else
                    rebuild($v, $r, $key ? $key . \Application\PHPRoll::KEY_SEPARATOR . $k : $k);
        }
        rebuild($a, $r, $key); //rebuild($params, $this->params);

        return $r;
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
    protected function tpl($param, array $opt)
    {
        $ext = $opt['ext'] ?? '.phtml';
        $script = $opt['script'] ?? null;
        $prefix = '';
        $name = [];
        $path = $this->cfg->get('view',__DIR__ . DIRECTORY_SEPARATOR );

        $param = is_array($param) ? $param : [$param];
        if (count($param) ) {
            foreach ($param as $k => $v) {
                $tmpl = $prefix . $v . (strpos($v, $ext) ? '' : $ext);
                if (file_exists($path . $tmpl)) $name[] = $tmpl;
                $prefix .= $v . DIRECTORY_SEPARATOR;
            }
            $uri = implode(DIRECTORY_SEPARATOR, $param);
            if (is_dir($path . DIRECTORY_SEPARATOR . $uri) && file_exists($path . DIRECTORY_SEPARATOR . $uri . DIRECTORY_SEPARATOR . $script . $ext)) {
                $name[] = $uri . DIRECTORY_SEPARATOR . $script . $ext;
            } elseif (strtolower($v) === $script . $ext && file_exists($path . $uri)) {
                return [$uri];
            }
            if ($script && !in_array($script, $param)) array_unshift($name, $script . $ext); elseif ($script !== null) $name[] = $script . $ext;
        }
        return $name;
    }

    /**
     * Получаем название шаблона | последовательности вложенных вдруг в друга шаблонов
     *
     * @param $inc boolean шаблон в шаблоне
     * @return string
     */
    public function getPattern(array $opt)
    {
        switch ($_SERVER['REQUEST_METHOD'])
        {
            case 'PUT':
            case 'POST':
                $result = $this->tpl(key($this->params->get()), $opt);
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
     * @param bool $assoc_index
     * @return false|mixed|null|string
     * @throws ContextException
     */
    public function context($pattern, array &$options = [], &$assoc_index = false, $depth = 0)
    {
        $path = $this->cfg->get('view',__DIR__ . DIRECTORY_SEPARATOR );
        $is_set = is_array($pattern);
        $is_assoc = $is_set ? \Application\Parameter::is_assoc($pattern) : false;
        if (!isset($options['include'][$depth])) $options['include'][$depth] = [];

        if ($is_assoc) {
            $bound = range(0, count($pattern) - 1);
            $keys = [];
            foreach ($pattern as $x => $y) {
                $keys[] = $key = isset($bound[$x]) ? $y : $x;
                $options['include'][$depth][$key] = $this->context($y, $options, $x, ++$depth);
            }
            return $this->context($keys, $options);
        } else {
            $p = array_reverse($is_set ? $pattern : [$pattern]);
        }

        $count = count($p) - 1;
        foreach ($p as $k => $f) {
            $file = (preg_match('/^\\' . DIRECTORY_SEPARATOR . '.+$/i', $f)) ? $f : $path . $f;

            if (!is_file($file)) {
                $file = ($is_set) && ($k != $count) ? ((!$k) ? (isset($options['404']) ? $path . $options['404'] : null) : null) : (isset($options['404']) ? $path . $options['404'] : null);
            }

            $context = null;
            if ($file && is_file($file)) {
                extract($options); ob_start(); require($file);
                $context = ob_get_clean();
                $context = array_key_exists('grinder', $options) && is_callable($options['grinder']) ?
                    call_user_func_array($options['grinder']->bindTo($this), ['file'=>$f, 'contex'=>$context, 'depth'=>$depth, 'assoc_index'=>$assoc_index]) : $context;
            } else {
                if (!$assoc_index) throw new \Application\ContextException($this, $pattern, $options+['code'=>404]);
            }

            if ($assoc_index) {
                if (!isset($options[$assoc_index])) $options[$assoc_index] = [];
                $options[$assoc_index][$f] = $context;
            } else {
                if ($is_set && $k < $count) {
                    $options['include'][$depth][$f] = $context;
                } else {
                    return $context;
                }
            }
        }
        
        return $assoc_index ? $options[$assoc_index] : $context;
    }

    /**
     * @param array $a
     * @param array $opt
     * @return string|null
     */
    public function wring(?array $a, array $opt = []): ?string
    {
        $res = ''; $counter = 0;
        if ($a) array_walk_recursive($a, function($cnx, $key, $counter) use (&$res, $opt) {
            $counter++;
            $res .= $cnx;
        }, $counter);

        return $res;
    }

    /**
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
     * Генерация заголовка ответа и форматирование кода ответа
     * @param $type
     * @param $params
     * @return mixed
     */
    public function response(string $type, $params)
    {
        $code = $params['code'] ?? 200;
        if (array_key_exists($code, \Application\PHPRoll::HTTP_RESPONSE_CODE))  {
            header("HTTP/1.1 {$code} " . \Application\PHPRoll::HTTP_RESPONSE_CODE[$code], false);
        }
        http_response_code(intval($code));
        header('Expires: 0', false);
//        header("Content-Security-Policy: default-src *; connect-src *; script-src *; object-src *;", false);
//        header("X-Content-Security-Policy: default-src *; connect-src *; script-src *; object-src *;", false);
//        header("X-Webkit-CSP: default-src *; connect-src *; script-src 'unsafe-inline' 'unsafe-eval' *; object-src *;", false);

        if (isset($_SERVER['HTTP_USER_AGENT']) && strstr($_SERVER['HTTP_USER_AGENT'], 'MSIE') == false) {
            header('Cache-Control: no-cache', false);
            header('Pragma: no-cache', false);
        } else {
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0', false);
            header('Pragma: public', false);
        }

        switch ($type) {
            case 'websocket':
                header('Access-Control-Max-Age: 0', false);
                header('Sec-WebSocket-Origin:', false);
                header('Sec-WebSocket-Location:', false);
                header('Upgrade: websocket', false);
                header('Connection: Upgrade', false);
                header('Sec-WebSocket-Accept: ' . \Application\WebSocket::accept($this->header['Sec-Websocket-Key']), false);
                if(isset($params['Sec-WebSocket-Protocol']) || isset($this->header['Sec-WebSocket-Protocol']) && !empty($this->header['Sec-WebSocket-Protocol'])) {
                    header('Sec-WebSocket-Protocol: ' . $params['Sec-WebSocket-Protocol'] ?? $this->header['Sec-WebSocket-Protocol']);
                }
                header('Sec-WebSocket-Version: 13', false);
                $this->set_response_header();
                break;

            case 'json':
                header('Content-Description: json data container');
                header('Content-Type: Application/json; charset=utf-8;');
                header('Access-Control-Max-Age: 0', false);
                header('Access-Control-Allow-Origin: *', false);
                //header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, HEAD, OPTIONS, DELETE', false);
                header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Origin, Accept, X-Requested-With, Content-Type, Access-Control-Request-Method, Access-Control-Request-Headers, Xhr-Version', false);
                header('Content-Encoding: utf-8');
                $this->set_response_header();
                
                switch (gettype($params)) {
                    case 'object':
                    case 'array':
                        return json_encode($params, JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE);
                    case 'string':
                    default:
                        return $params;
                }
                break;

            case 'file':
                header('Access-Control-Max-Age: 0', false);
                header('Content-Description: File Transfer');
                header('Content-Transfer-Encoding: binary',false);
                header('Connection: Keep-Alive', false);
                header('Cache-Control: must-revalidate');
                $this->set_response_header();

                if ( is_resource($params['file']) ) {
                    fseek($params['file'], 0);
                    fpassthru($params['file']);
                    fclose($params['file']);
                    exit;
                }
                break;

            case 'view':
                header('Content-Description: html view');
//                header('Content-Security-Policy: default-src "self"; frame-ancestors "self"');
                header('Strict-Transport-Security: max-age=86400; includeSubDomains');
                header('X-XSS-Protection: 1; mode=block');
                header('X-Content-Type-Options: nosniff');
                header('Content-Type: text/html; charset=utf-8');
                header('Content-Encoding: utf-8');

                $pattern = isset($params['pattern']) && !empty($params['pattern']) ? $params['pattern'] : 'index.phtml';
                if ( $pattern ) {
                    try {
                        $context = $this->context($pattern, $params);
                    } catch (\Application\ContextException $e) {
                        $context = $this->context($this->cfg->get('404','index.phtml'), $params);
                    }
                    $this->set_response_header();
                    return $context;
                }
                
            case 'xml':
            default:
                header('Content-Description: ' . \Application\PHPRoll::FRAMEWORK . ' '. \Application\PHPRoll::VERSION, false);
                header('Content-Type: Application/xml; charset=utf-8');
                header('Content-Encoding: utf-8');
                header('Access-Control-Allow-Origin: *');
                header('Referer-Policy: origin-when-cross-origin');
                header('Strict-Transport-Security: max-age=86400');
                header('X-XSS-Protection: 1; mode=block');
                header('X-Content-Type-Options: nosniff');
                header('Timing-Allow-Origin: *');
                $this->set_response_header();
        }
        return $params;
    }

    /**
     * @function exceptionInfo
     *
     * @param \Exception $e
     * @param false|int $is_view
     *
     */
    public function exceptionInfo(\Exception $e, $is_view = 0)
    {
        if ($is_view)
        {
            $this->response_header['Action-Status'] = 'SYSTEM ERROR';
            \Application\IO::console_error($e, [1=>['<script>','</script>'],2=>['{%','%}'],3=>['<%','%>']][$is_view]);
        } else {
            echo $this->response('json', ['result' => 'error', 'code' => 500, 'message' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Сборка и генерация контента
     *
     * @param array $opt
     * @return mixed
     */
    public function run(array $opt=[])
    {
        if (isset($opt['method']) && method_exists($this, $opt['method']))
            return call_user_func_array([$this, $opt['method']], [$opt['params'] ?? []]);

        $content = $this->route(isset($this->path) ? $this->path : ['default'], $opt);
        return $content ?? $this->response('view', ['pattern' => ['index.phtml' => '404.phtml']]);

    }
}
?>