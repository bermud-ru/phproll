<?php
/**
 *  CRollResult.php
 *
 * @category Application
 * @author Андрей Новиков <bermud@nm.ru>
 * @data 11/11/2015
 */
namespace Application;

class CRollResult {
    protected $owner = null;
    protected $type = 'json';
    public $isError = false;
    public $message = '';

    function __construct(&$owner){
        $this->owner = $owner;
    }

    public function __invoke($type)
    {
        $result = array('result'=>'error','message'=>$this->message);
        $this->type = $type;

        if (!$this->isError){
            $data = $this->owner->_data;
            switch ($type){
                case 'json':
                    if (!isset($data['view']) && isset($this->owner->_config['view']['template'])){
                        $data['view'] = $this->owner->_config['view']['template'];
                    }
                    break;
                default:
            }
            $result = array('result'=>'ok', 'dataset' => $data);
        } else {
            http_response_code(intval($this->isError));
            $result['code'] = $this->isError;
        }
        echo $this->{(empty($type)? 'json' : $type) .'Responce'}($result);

    }

    /**
     * Ответ в виде HTML данных
     * @param array $result
     * @return string
     */
    public function phpResponce($result)
    {
        if ($result['result'] == 'ok') {
            return $result['dataset'];
        } else {
            return null;
        }
    }

    /**
     * Ответ в виде JSON объекта
     * @param array $result
     * @return string
     */
    public function jsonResponce(array $result)
    {
        if (strstr($_SERVER["HTTP_USER_AGENT"], "MSIE") == false) {
            header("Cache-Control: no-cache");
            header("Pragma: no-cache");
        } else {
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Pragma: public");
        }
        header('HTTP/1.1 206 Partial content');
        header('Content-Encoding: utf-8');
        header('Content-Description: json response');
        header('Content-Type: Application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=result.json');
        header('Content-Transfer-Encoding: binary');

        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, PUT");
        header("Access-Control-Allow-Headers: Content-Type");
        header('Expires: 0');

        return json_encode($result);
    }

    /**
     *
     */
    public function headerResponce()
    {
        if (strstr($_SERVER["HTTP_USER_AGENT"], "MSIE") == false) {
            header("Cache-Control: no-cache");
            header("Pragma: no-cache");
        } else {
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Pragma: public");
        }
        header('HTTP/1.1 206 Partial content');
        header('Content-Encoding: utf-8');
        header('Content-Description: html response');
        header('Content-Type: Application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename=view.html');
        header('Content-Transfer-Encoding: binary');

        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, PUT");
        header("Access-Control-Allow-Headers: Content-Type");
        //header('Expires: 0');

    }

    public function downloadResponce($name='file')
    {
        if (strstr($_SERVER["HTTP_USER_AGENT"], "MSIE") == false) {
            header("Cache-Control: no-cache");
            header("Pragma: no-cache");
        } else {
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Pragma: public");
        }
        header('HTTP/1.1 206 Partial content');
        header('Content-Encoding: utf-8');
        header('Content-Description: downloading file');
        header('Content-Transfer-Encoding: binary');
        header('Content-Disposition: attachment; filename='.$name);
        header('Content-Transfer-Encoding: binary');

        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, PUT");
        header("Access-Control-Allow-Headers: Content-Type");
        //header('Expires: 0');

    }
}