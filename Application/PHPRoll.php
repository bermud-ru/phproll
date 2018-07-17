<?php
/**
 * PHPRoll.php
 *
 * @category RIA (Rich Internet Application) / SPA (Single-page Application) Backend
 * @author Андрей Новиков <andrey@novikov.be>
 * @data 16/05/2018
 * @status beta
 * @version 0.1.3
 * @revision $Id: PHPRoll.php 0013 2018-05-16 1:04:01Z $
 *
 */

namespace Application;

/**
 * Simple PHP backend script for RIA (Rich Internet Application) / SPA (Single-page Application) frontend
 */

class PHPRoll
{
    const VERSION = 'PHPRoll v2.0.12b';
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

    public $config = [];
    public $header = [];
    public $params = [];
    public $path = [];

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
        if (is_array($params)) {
            $this->config = $params;
            $this->header = (function_exists('getallheaders')) ? getallheaders() : $this->__getAllHeaders($_SERVER);
            $this->params = $this->initParams();
            $this->path = array_filter(explode("/", substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), 1)));
        }
        elseif ($params instanceof \Application\PHPRoll)
        {
            $this->setParent($params);
        }
    }

    /**
     * Маршрутизатор позволяет переопределить обработку урлов
     *
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
     * Форматированный вывод значений в строку
     * formatter("Текст %(<имя переменной>)s",['<имя переменной>' => <значение>]);
     *
     * @param string $pattern
     * @param array $properties
     * @return bool|mixed
     */
    public static function formatter($pattern, array $properties)
    {
        if ($pattern && count($properties)) {
            $keys = array_keys($properties);
            $keysmap = array_flip($keys);
            $values = array_values($properties);
            while (preg_match('/%\(([a-zA-Z0-9_ -]+)\)/', $pattern, $m)) {
                if (!isset($keysmap[$m[1]]))  $pattern = str_replace($m[0], '% - $', $pattern);
                else $pattern = str_replace($m[0], '%' . ($keysmap[$m[1]] + 1) . '$', $pattern);
            }
            array_unshift($values, $pattern);
            return call_user_func_array('sprintf', $values);
        } else {
            return $pattern;
        }
    }

    /**
     * Проверяем является массив ассоциативным
     *
     * @param array $a
     * @return bool
     */
    public static function is_assoc(array $a): bool
    {
        return array_keys($a) !== range(0, count($a) - 1);
    }

    /**
     * Приводим ключи массива к нормализованному виду
     *
     * @param array $a
     * @return array
     */
    public static function array_keys_normalization(array $a): array
    {
        $data = [];
        foreach ($a as $k=>$v) {
            $keys = explode(\Application\PHPRoll::KEY_SEPARATOR, $k);
            $data[end($keys)] = $v;
        }
        return $data;
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
     * Получаем значение параменных в запросе
     *
     */
    protected function initParams()
    {
        $params = [];
        switch ($_SERVER['REQUEST_METHOD'])
        {
            case 'PUT':
//                if (strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== FALSE) {
//                //  http://php.net/manual/ru/features.file-upload.put-method.php
//                $CHUNK = 8192;
//
//                try {
//                    if (!($putData = fopen("php://input", "r")))
//                        throw new Exception("Can't get PUT data.");
//
//                    // now the params can be used like any other variable
//                    // see below after input has finished
//
//                    $tot_write = 0;
//                    $tmpFileName = "/var/dev/tmp/PUT_FILE";
//                    // Create a temp file
//                    if (!is_file($tmpFileName)) {
//                        fclose(fopen($tmpFileName, "x")); //create the file and close it
//                        // Open the file for writing
//                        if (!($fp = fopen($tmpFileName, "w")))
//                            throw new Exception("Can't write to tmp file");
//
//                        // Read the data a chunk at a time and write to the file
//                        while ($data = fread($putData, $CHUNK)) {
//                            $chunk_read = strlen($data);
//                            if (($block_write = fwrite($fp, $data)) != $chunk_read)
//                                throw new Exception("Can't write more to tmp file");
//
//                            $tot_write += $block_write;
//                        }
//
//                        if (!fclose($fp))
//                            throw new Exception("Can't close tmp file");
//
//                        unset($putData);
//                    } else {
//                        // Open the file for writing
//                        if (!($fp = fopen($tmpFileName, "a")))
//                            throw new Exception("Can't write to tmp file");
//
//                        // Read the data a chunk at a time and write to the file
//                        while ($data = fread($putData, $CHUNK)) {
//                            $chunk_read = strlen($data);
//                            if (($block_write = fwrite($fp, $data)) != $chunk_read)
//                                throw new Exception("Can't write more to tmp file");
//
//                            $tot_write += $block_write;
//                        }
//
//                        if (!fclose($fp))
//                            throw new Exception("Can't close tmp file");
//
//                        unset($putData);
//                    }
//
//                    // Check file length and MD5
//                    if ($tot_write != $file_size)
//                        throw new Exception("Wrong file size");
//
//                    $md5_arr = explode(' ', exec("md5sum $tmpFileName"));
//                    $md5 = $md5sum_arr[0];
//                    if ($md5 != $md5sum)
//                        throw new Exception("Wrong md5");
//                } catch (Exception $e) {
//                    echo '', $e->getMessage(), "\n";
//                }
//                  break;
//                }
            case 'POST':
                // POST upload files RFC-1867
                if (strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== FALSE) {
                    $params = $_POST;

//                    // read incoming data
//                    $input = file_get_contents('php://input');
//
//                    // grab multipart boundary from content type header
//                    preg_match('/boundary=(.*)$/', $_SERVER['CONTENT_TYPE'], $matches);
//                    $boundary = $matches[1];
//
//                    // split content by boundary and get rid of last -- element
//                    $a_blocks = preg_split("/-+$boundary/", $input);
//                    array_pop($a_blocks);
//
//                    // loop data blocks
//                    foreach ($a_blocks as $id => $block)
//                    {
//                        if (empty($block))
//                            continue;
//
//                        // you'll have to var_dump $block to understand this and maybe replace \n or \r with a visibile char
//
//                        // parse uploaded files
//                        if (strpos($block, 'application/octet-stream') !== FALSE)
//                        {
//                            // match "name", then everything after "stream" (optional) except for prepending newlines
//                            preg_match("/name=\"([^\"]*)\".*stream[\n|\r]+([^\n\r].*)?$/s", $block, $matches);
//                        }
//                        // parse all other fields
//                        else
//                        {
//                            // match "name" and optional value in between newline sequences
//                            preg_match('/name=\"([^\"]*)\"[\n|\r]+([^\n\r].*)?\r$/s', $block, $matches);
//                        }
//                        $a_data[$matches[1]] = $matches[2];
//                    }
                } else if (strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== FALSE) {
                    $json = file_get_contents('php://input');
                    $params = json_decode($json,true);
                } else {
//                    mb_parse_str(file_get_contents('php://input'), $params );
                    parse_str(file_get_contents('php://input'), $params );
                }
                break;
            case 'GET':
            case 'DELETE':
            default:
                mb_parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), $params);
        }
        return $params;
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
    public function getPattern(array $opt)
    {
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
    public function context($pattern, array $options = array()): string
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
        return $context;
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
     * Установка параметров заголовка ответа
     * @param $extra array - injection items for response headers
     */
    final function set_response_header(array $extra = [])
    {
        $a = array_merge($extra, $this->response_header);
        array_walk($a, function ($v, $k) { header("$k: $v");});
    }

    /**
     * Генерация заголовка ответа и форматирование кода ответа
     * @param $type
     * @param $params
     * @return mixed
     */
    public function response(string $type, array $params)
    {
        $code = $params['code'] ?? 200;
        if (array_key_exists($code, \Application\PHPRoll::HTTP_RESPONSE_CODE))  {
            header("HTTP/1.1 {$code} {\Application\PHPRoll::HTTP_RESPONSE_CODE[$code]}");
        }
        http_response_code(intval($code));
        header('Expires: 0');

        if (isset($_SERVER['HTTP_USER_AGENT']) && strstr($_SERVER['HTTP_USER_AGENT'], 'MSIE') == false) {
            header('Cache-Control: no-cache');
            header('Pragma: no-cache');
        } else {
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
        }

        switch ($type) {
            case 'json':
                header('Content-Description: '+\Application\PHPRoll::VERSION);
                header('Content-Type: Application/json; charset=utf-8;');
                header('Access-Control-Allow-Origin: *');
                //header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, HEAD, OPTIONS, DELETE');
                header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Origin, Accept, X-Requested-With, Content-Type, Access-Control-Request-Method, Access-Control-Request-Headers, Xhr-Version');
                header('Content-Encoding: utf-8');
                $this->set_response_header();
                return json_encode($params, JSON_UNESCAPED_UNICODE);
            case 'file':
                header('Content-Description: File Transfer');
                header('Content-Transfer-Encoding: binary');
                header('Connection: Keep-Alive');
                header('Cache-Control: must-revalidate');
                $this->set_response_header();
//                header('Content-Type: '.(isset($params['mime']) ? $params['mime'] : 'application/octet-stream'));
//                header('Content-Disposition: attachment; filename="'.(isset($params['name']) ? $params['name'] : 'download.file').'";');
//                if (isset($params['size'])) header("Content-length: " . $params['size']);

                if ( is_resource($params['file']) ) {
                    fseek($params['file'], 0);
                    fpassthru($params['file']);
                }
                break;
            case 'view':
                header('Content-Description: html view');
//                header('Content-Security-Policy: default-src "self"; frame-ancestors "self"');
                header('Strict-Transport-Security: max-age=86400');
                header('X-XSS-Protection: 1; mode=block');
                header('X-Content-Type-Options: nosniff');
                header('Content-Type: text/html; charset=utf-8');
                header('Content-Encoding: utf-8');
                $this->set_response_header();
                $pattern = count($params['pattern']) ? $params['pattern'] : 'index.phtml';
                if ( $pattern ) {
                    return $this->context($pattern, array(
                            'self' => &$this,
                            'json' => function (array $params) {
                                echo $this->response('json', $params);
                                exit(1);
                            }
                        )
                    );
                    break;
                }
            default:
                header('Content-Description: '+\Application\PHPRoll::VERSION);
                header('Content-Type: Application/xml; charset=utf-8');
                header('Content-Encoding: utf-8');
                header('Access-Control-Allow-Origin: *');
                header('Referer-Policy: origin-when-cross-origin');
                header('Strict-Transport-Security: max-age=86400');
                header('X-XSS-Protection: 1; mode=block');
                header('X-Content-Type-Options: nosniff');
                header('Timing-Allow-Origin: *');

        }
        return $params;
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
            return call_user_func_array([$this,$opt['method']], [$opt['params'] ?? []]);

        $content = $this->route(isset($this->path) ? $this->path : ['default']);
        if ($content && is_string($content)) return $content;
        return $this->response('view', ['pattern'=>$this->getPattern(array_merge(['script'=>'index','ext'=>'.phtml'], $opt['tpl'] ?? []))]);
    }
}
?>