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

    /**
     * Конструктор
     *
     * @param $config данные из файла конфигурации
     */
    public function __construct($params)
    {
        $this->cfg = new \Application\Jsonb($params, ['owner' => $this]);
        $this->header = (function_exists('getallheaders')) ? getallheaders() : $this->__getAllHeaders($_SERVER);
        $opt = $this->initParams();
        $this->params = new \Application\Jsonb($opt, ['owner'=>$this, 'assoc'=>true, 'mode'=>\Application\Jsonb::JSON_ALWAYS]);
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
                    $params = file_get_contents('php://input');
//                    $params = json_decode($json,true);
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
     * run Abstract method
     *
     * @return mixed
     */
    abstract function run();
}