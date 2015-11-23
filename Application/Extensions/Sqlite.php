<?php
/**
 *  Sqlite.php
 *
 * @category Application
 * @author Андрей Новиков <bermud@nm.ru>
 * @data 11/11/2015
 */
namespace Application\Extensions;
use \PDO;

class Sqlite extends \Application\RollRest
{

    public function post(array $params){
        try{
            //$db = new PDO($this->_config['dsn'], null, null, array(PDO::ATTR_PERSISTENT => true));
           // $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
//            $db->sqliteCreateFunction('MD5', 'md5', 1);
            //$this->_data = $db->exec("SELECT id, name, hash FROM users");
            $params = array_intersect_key($params, array_flip($this->_config['params']));
            $db = new PDO($this->_config['dsn'], null, null, array(PDO::ATTR_PERSISTENT => true));
            $query = $db->query($this->pattern($this->_config['sql'],$params), PDO::FETCH_ASSOC);
            $this->_data = !empty($query) ? $query->{$this->_config['rs-method']}() : null;
            if (empty($this->_data)) {
                $this->_result->isError = 404;
                $this->_result->message = 'Error 404: data not found.';
            }
        } catch(\Exception $e){
            $this->_result->isError = 500;
            $this->_result->message = $e->getMessage();
        }

        return parent::post($params);
    }

}