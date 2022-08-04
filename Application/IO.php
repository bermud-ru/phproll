<?php
/**
 * IO.php
 *
 * @category Input/Output tools Helper
 * @author Андрей Новиков <andrey (at) novikov (dot) be>
 * @data 11/06/2020
 * @status beta
 * @version 0.1.2
 * @revision $Id: IO.php 0004 2020-06-11 23:44:01Z $
 *
 */

namespace Application;

class IO
{
    /**
     * @param string $path
     * @param array $paginator
     * @return array
     *
     * @Example:
     * <code>
     * return ['result' => 'ok', 'data'=>['rows'=>\Application\IO::folder_list( "{$cfg->basedir}/files", $paginator)], 'paginator'=>$paginator];
     * </code>
     */
    static function folder_list(string $path, array &$paginator = ['page'=>0, 'limit'=>10] ): array
    {
        $result=[];
        $files = array_slice(scandir($path),2);

        $totalSize = 0;
        foreach (new \DirectoryIterator($path) as $file) {
            if ($file->isFile()) {
                $totalSize += $file->getSize();
            }
        }

        $list = $files;
        if (array_key_exists('page', $paginator) && array_key_exists('limit', $paginator)) {
            $page = $paginator['page'] instanceof \Application\Parameter ? $paginator['page']->__toInt() : intval($paginator['page']);
            $limit = $paginator['limit'] instanceof \Application\Parameter ? $paginator['limit']->__toInt() : intval($paginator['limit']);
            $list = array_slice($files, ($page * $limit), $limit);
        }

        foreach ( $list as $file ) {
            array_push ($result, [
                'filename'=> $file,
                'size'=>filesize("$path/".$file),
                'type'=>filetype("$path/".$file),
                'mtime'=> filemtime("$path/".$file), // unixtimestamp  ! multiplying it to 1000 and create a date object
//                'date'=> date ('Y-m-d H:i:s', filemtime("$path/".$file))
            ]);
        }
        $paginator['count'] = count($files);
        $paginator['size'] = $totalSize;
        return  $result;
    }

    /**
     * @param array $params - $params['file'] resource $params['size'] - file size
     * @param array $rs DataSet to CSV
     */
    static function csv_open(array $rs)
    {
//        $z=new SplFileObject('compress.zlib:///tmp/test.csv.gz','w');
//        $arr=[['a','b','c'],[1,2,3],[2,4,8],[3,6,9]];
//        foreach($arr as $f) $z->fputcsv($f);
        $params = ['file' => fopen('php://memory', 'w')];
//        $opt = ['level' => 6, 'window' => 15, 'memory' => 9];
//        stream_filter_append($params['file'], 'zlib.deflate', STREAM_FILTER_WRITE, $opt);
        fputcsv($params['file'], array_keys($rs[0]), ';');
        # fputcsv ( resource $handle , array $fields [, string $delimiter = "," [, string $enclosure = '"' [, string $escape_char = "\\" ]]] ) : int
        foreach ($rs as $fields) fputcsv($params['file'], array_values($fields), ';');
        $info = array_slice(fstat($params['file']), 13);
        $params['size'] = $info['size'];

        return $params;
    }

    /**
     * file_stream
     *
     * @param string $source
     * @return array|null
     * Extension MIME Type
     *     .doc      application/msword
     *     .dot      application/msword
     *
     *     .docx     application/vnd.openxmlformats-officedocument.wordprocessingml.document
     *     .dotx     application/vnd.openxmlformats-officedocument.wordprocessingml.template
     *     .docm     application/vnd.ms-word.document.macroEnabled.12
     *     .dotm     application/vnd.ms-word.template.macroEnabled.12
     *
     *     .xls      application/vnd.ms-excel
     *     .xlt      application/vnd.ms-excel
     *     .xla      application/vnd.ms-excel
     *
     *     .xlsx     application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
     *     .xltx     application/vnd.openxmlformats-officedocument.spreadsheetml.template
     *     .xlsm     application/vnd.ms-excel.sheet.macroEnabled.12
     *     .xltm     application/vnd.ms-excel.template.macroEnabled.12
     *     .xlam     application/vnd.ms-excel.addin.macroEnabled.12
     *     .xlsb     application/vnd.ms-excel.sheet.binary.macroEnabled.12
     *
     *     .ppt      application/vnd.ms-powerpoint
     *     .pot      application/vnd.ms-powerpoint
     *     .pps      application/vnd.ms-powerpoint
     *     .ppa      application/vnd.ms-powerpoint
     *
     *     .pptx     application/vnd.openxmlformats-officedocument.presentationml.presentation
     *     .potx     application/vnd.openxmlformats-officedocument.presentationml.template
     *     .ppsx     application/vnd.openxmlformats-officedocument.presentationml.slideshow
     *     .ppam     application/vnd.ms-powerpoint.addin.macroEnabled.12
     *     .pptm     application/vnd.ms-powerpoint.presentation.macroEnabled.12
     *     .potm     application/vnd.ms-powerpoint.template.macroEnabled.12
     *     .ppsm     application/vnd.ms-powerpoint.slideshow.macroEnabled.12
     *
     *     .mdb      application/vnd.ms-access
     */
    static function file_stream(string $source): ?array
    {
        $params = ['file' => fopen($source, "rb")];
        if ($params['file']) {
            $params['size'] = filesize($source);
            $info = pathinfo($source);
            switch (strtolower($info["extension"])) {
                case 'pdf':
                    $params['mime'] = 'application/pdf';
                    break;
                case 'zip':
                    $params['mime'] = 'application/zip';
                    break;
//                case 'xlsx':
                default:
                    $params['mime'] = 'application/octet-stream';
            }
        }
        return $params;
    }

    /**
     * @function uuid
     *
     * @return string
     */
    static function uuid(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * @function crypt
     *
     * @param string $key
     * @param string $msg
     * @return string
     */
    static function crypt(string $key, string $msg): string
    {
        $textToChars = function($txt) {
            return array_map(function($i) {
                return mb_detect_encoding($i) === 'UTF-8' ? (ord($i[0]) - 192) * 64 + (ord($i[1]) - 128) : ord($i);
            }, mb_str_split($txt));// str_split($txt));
        };
        $salt = $textToChars($key);
        $charSet = $textToChars($msg);
        array_walk($charSet, function(&$i) use ($salt) {
            $ch = dechex(array_reduce($salt, function($a, $b) { return $a ^ $b; }, $i));
            $i = str_pad($ch, 3, '0', STR_PAD_LEFT);
        });
        return implode('', $charSet);
    }

    /**
     * @function decrypt
     *
     * @param string $key
     * @param string $msg
     * @return string
     */
    static function decrypt(string $key, string $msg): string
    {
        $salt = array_map(function($i){ return ord($i); }, str_split($key));
        preg_match_all('/.{1,3}/', $msg, $charSet);
        array_walk($charSet[0], function(&$i) use ($salt) {
            $i = array_reduce($salt, function($a, $b) { return $a ^ $b; }, intval($i, 16));
        });
        return implode('', array_map(function($i) { return mb_chr($i); }, $charSet[0]));
    }

    /**
     * @param string $method
     * @param string $url
     * @param $data
     * @param array $opt
     * @return array
     */
    static function rest(string $method, string $url, $data, $opt=[])
    {
        if ($method === 'RAW') return @file_get_contents($url . (strpos('?', $url) === FALSE ? '?' : '&') . \Application\Request::http_build_query($data));
        if (!filter_var($url, FILTER_VALIDATE_URL)) return ['result'=>'error', 'message'=>'Wrong URL!'];
            if (!in_array(strtoupper($method), ['GET', 'DELETE', 'POST', 'PUT'])) return ['result' => 'error', 'message' => 'Wrong Method!'];
            if (isset($opt['http']) && isset($opt['http']['header']) && is_array($opt['http']['header'])) {
                $opt['http']['header'] = implode("\r\n", $opt['http']['header']);
            }
            $is_json = isset($opt['http']) && isset($opt['http']['header']) ? strpos('json', $opt['http']['header']) !== false : false;
            if (is_array($data)) {
                if (!$is_json) {
                    if (in_array($method, ['GET', 'DELETE'])) {
                        $url .= (strpos('?', $url) === FALSE ? '?' : '&') . http_build_query($data); //,'Prefix','&',PHP_QUERY_RFC3986);
                        $data = '';
                    } else {
                        $data = http_build_query($data); //,'','&',PHP_QUERY_RFC3986);
                    }
                } else {
                    $data = json_encode($data,JSON_BIGINT_AS_STRING | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
                }
            }

            $options = array_replace_recursive([
                'http' => [
    //                'header'  => "Content-type: application/x-www-form-urlencoded" . "\r\n",
    //                'header' => implode("\r\n", [
    //                    "Content-type: application/json",
    //                ]),
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
//                    'header' => "Content-type: application/json\r\n",
    //                'max_redirects' => '0',
    //                'ignore_errors' => '1',
                    'method' => strtoupper($method),
    //                'content' => http_build_query($data),
                    'content' => $data,
    //                'protocol_version' => '1.1',
                ],
    //            'ssl'  => [ // here comes the actual SSL part...
    ////                'local_cert'        => '/path/to/key.pem',
    ////                'peer_fingerprint'  => openssl_x509_fingerprint(file_get_contents('/path/to/key.crt')),
    //                "verify_peer"       => false, // Self signed! option
    //                "verify_peer_name"  => false, // Self signed! option
    //                'allow_self_signed' => true,  // Self signed! option
    ////                'verify_depth'      => 0,
    ////                'verify_peer'   => true,
    ////                'cafile'        => __DIR__ . '/cacert.pem',
    ////                'verify_depth'  => 5,
    ////                'CN_match'      => $url
    ////                'ciphers' => 'HIGH:TLSv1.2:TLSv1.1:TLSv1.0:!SSLv3:!SSLv2',
    ////                'CN_match' =>  'bingo2020.vegas',
    ////                'disable_compression' => true,
    //            ]
            ], $opt);

        try {
            $context = stream_context_create($options);
            $response = @file_get_contents($url, NULL, $context) ;
            if ( $response ) if ( $is_json ) {
                $json = json_decode($response, true);
                $error = json_last_error();
                return  $error === JSON_ERROR_NONE ? $json : ['result' => 'error', 'code'=> $error, 'content' => $response];
            } else {
                return $response;
            } else {
                preg_match('{HTTP\/\S*\s(\d{3})}', $http_response_header[0], $match);
                return ['result' => 'error', 'code'=> $match[1], 'message' => \Application\PHPRoll::HTTP_RESPONSE_CODE[intval($match[1])]];
            }
            return ['result' => 'error', 'message' => (error_get_last())['message']];
        } catch (\Exception $e) {
            return ['result' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * @function replace
     *
     * @param string $str
     * @param array|null $d
     * @param array $r
     * @return string
     */
    final static function replace(string $str, ?array $d = null, array $r = []): string
    {
        return ($d === null && !count($r)) ? $str : preg_replace_callback('|{\w+}|',
            function($matches) use($str, $d, $r) {
                $key = trim($matches[0],"{}");
                if (array_key_exists($key, $r)) {
                    $value = is_callable($r[$key]) ? call_user_func_array($r[$key], array_map(function (&$item) use($str, $d, $key) {
                        switch (strtolower($item->name)) {
                            case 'str': $item->value = $str; break;
                            case 'data': $item->value = $d; break;
                            case 'key': $item->value = $key; break;
                            default: $item->value = null;
                        } return $item->value;
                    }, (new \ReflectionFunction($r[$key]))->getParameters())) : $r[$key];
                } elseif (array_key_exists($key, $d)) {
                    $value = \Application\Parameter::ize($d[$key],\Application\PDA::ARRAY_STRINGIFY|\Application\PDA::OBJECT_STRINGIFY);
                } else {
                    $value = $matches[0];
                }
                return $value;
            }, $str);
    }

    /**
     * @function cast
     *
     * @param $src
     * @param string $cn $className = "Some\\NameSpace\\SomeClassName";
     * @return mixed
     */
    public static function cast($src, string $cn)
    {
//        return class_exists($className) ? unserialize(sprintf('O:%d:"%s"%s', strlen($cn), $cn,
//            strstr(strstr(serialize($src), '"'), ':' )
//        )) : $src;

        $len = strlen($cn);
        return class_exists($cn) ? unserialize(preg_replace('/^O:\d+:"[^"]++"/',"O:$len:\"$cn\"", serialize($src))) : $src;
    }

    /**
     * @function  serialize
     *
     * @param object $obj
     * @param int $opt
     * @return string|null
     */
    static public function serialize(object $obj, int $opt = \Application\Request::DEFAULT): ?string
    {
        $a = explode('\\', get_class($obj));
        if (count($a)) {
            $s = array_pop($a) . ':' . json_encode($obj);
            if ($opt & \Application\Request::BASE64) $s = base64_encode($s);
            return $s;
        }
        return null;
    }

    /**
     * @function unserialize
     *
     * @param string $obj
     * @param int $opt
     * @return object|null
     */
    static public function unserialize(string $src, int $opt = \Application\Request::DEFAULT): ?object
    {
        $s = $src;
        if ($opt & \Application\Request::BASE64) $s = base64_decode($s);
        preg_match('/^(([a-zA-Z0-9_]+?):)*(.*)$/', $s, $a);
        $class = $a[2] ? ((strpos($a[2], '\\') !== false) ? $a[2] : __NAMESPACE__ . '\\' .$a[2]) : null;
        $p = json_decode($a[3], false, 512, JSON_INVALID_UTF8_IGNORE);
        if (json_last_error() !== JSON_ERROR_NONE) return null;
        if (class_exists($class)) {
            $o = new $class; foreach (get_object_vars($p) as $k => $v) $o->{$k} = $v;
            return $o;
        }

        return $p;
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
     * @function console_error
     * Printin Exception object message to Web borwser console
     *
     * @param \Exception $e
     */
    static function console_error( $e, $tags=['<script>','</script>'])
    {
        echo $tags[0].' console.error("PHP Exception: "+decodeURIComponent("'.rawurlencode( addslashes($e instanceof \Exception ? $e->getMessage() : strval($e))).'")); '.$tags[1];
    }

    /**
     * @function console_log
     *
     * @param String $e
     */
    static function console_log(string $s, $tags=['<script>','</script>'])
    {
        echo $tags[0].' console.log(decodeURIComponent("'.rawurlencode(addslashes($s)).'")); '.$tags[1];;
    }

    /**
     * @function syslog
     *
     * @param $msg
     * @param string $prefix
     */
    static function syslog($msg, $prefix = __DIR__ . DIRECTORY_SEPARATOR . 'log')
    {
        $log_filename = $prefix . '-' . date('j.n.Y');
        $data = date('Y-m-d H:i:s') . ' - ' . $msg;
        file_put_contents($log_filename, $data, FILE_APPEND);
    }
}