<?php
/**
 *  File.php
 *
 * @category   Application
 * @author Андрей Новиков <bermud@nm.ru>
 * @data 11/11/2015
 */
namespace Application\Extensions;

class File extends \Application\RollRest {

    public function getContent($file){
        if (file_exists($file)) {
            $content = file_get_contents($file, FILE_USE_INCLUDE_PATH);
            switch (strtolower($this->_config['format'])){
                case 'json':
                    $this->_data = json_decode($content, true);
                    if (empty($this->_data)) {
                        $this->_result->isError = 422;
                        $this->_result->message = 'Error 422: json not valid.';
                    }
                    break;
                case 'php':
                    extract($this->_config);
                    ob_start();
                    @require($file);
                    $this->_data = ob_get_clean();
                    if (empty($this->_data)) {
                        $this->_result->isError = 422;
                        $this->_result->message = 'Error 422: in php file.';
                    } else {
                        return true;
                    }
                    break;
                default:
                    $this->_data = $content;
                    return true;
            }
        } else {
            $this->_result->isError = 404;
            $this->_result->message = 'File not found.';
        }

        return false;
    }

    public function get(array $params){
        $name = ''; // Если  $this->_config['path'] содержит путь и имяфайла
        if (isset($params['file'])) {
            $name = '/'.$params['file'];
        } elseif(count($this->_route) > 1) {
            $name = '/'.end($this->_route);
        }
        $this->getContent($this->_config['path'] . $name);

        return parent::get($params);
    }

    public function post(array $params){
        return $this->get($params);
    }
}
?>