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
     * @param string $method
     * @param string $url
     * @param $data
     * @param array $opt
     * @return array
     */
    static function rest(string $method, string $url, $data, $opt=[])
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) return ['result'=>'error', 'message'=>'Wrong URL!'];
        if (!in_array(strtoupper($method),['GET','DELETE','POST','PUT'])) return ['result'=>'error', 'message'=>'Wrong Method!'];
        $options = array_merge($opt, [
            'http' => [
//                'header'  => "Content-type: application/x-www-form-urlencoded" . "\r\n",
                'header' => implode("\r\n", [
                    "Content-type: application/json",
                ]),
//                'max_redirects' => '0',
//                'ignore_errors' => '1',
                'method' => strtoupper($method),
//                'content' => http_build_query($data),
                'content' => is_string($data) ? $data : json_encode($data,JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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
        ]);

        try {
            $context = stream_context_create($options);
            $result = file_get_contents($url, NULL, $context) ;
            if ( $result ) {
                $json = json_decode($result, true);
                $err = json_last_error();
                return  $err === JSON_ERROR_NONE ? $json : ['result' => 'error', 'code'=> $err, 'content' => $result];
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
                    $value = $d[$key];
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
     * @function console_error
     * Printin Exception object message to Web borwser console
     *
     * @param \Exception $e
     */
    static function console_error( $e, $tags=['<script>','</script>'])
    {
        echo $tags[0].' console.error("PHP Exception: "+decodeURIComponent("'.rawurlencode( ($e instanceof \Exception) ? $e->getMessage() : strval($e)).'")); '.$tags[1];
    }

    /**
     * @function console_log
     *
     * @param String $e
     */
    static function console_log(string $s, $tags=['<script>','</script>'])
    {
        echo $tags[0].' console.log(decodeURIComponent("'.rawurlencode($s).'")); '.$tags[1];;
    }

    /**
     * @function syslog
     *
     * @param $msg
     * @param string $prefix
     */
    static function syslog($msg, $prefix = __DIR__ .'/../logs/')
    {
        $log_filename = $prefix . '-' . date('j.n.Y');
        $data = date('Y-m-d H:i:s') . ' - ' . $msg;
        file_put_contents($log_filename, $data, FILE_APPEND);
    }
}