<?php
/**
 * download.php
 *
 * @category REST model
 * @author Андрей Новиков <andrey (at) novikov (dot) be>
 * @data 12/12/2017
 * @status beta
 * @version 0.1.0
 * @revision $Id: download.php 0001 2017-12-12 11:30:01Z $
 *
 */
namespace Application;

return [
    'groups' => ['admin',],
    'get'=>[
        'params' => [
            ['name'=>'name', 'required'=>true, 'message'=>'Неверное имя файла: {value}'],
        ],
        'action'=>function($db, $owner, $params,$acl, $cfg) {
            //            cupsfilter test.txt > test.pdf
            $path = "{$cfg->basedir}/files/{$params['name']}";
            if (!is_file($path)) {
                $owner->response_header['Action-Status'] =  rawurlencode ("{\"result\":\"error\",\"message\":\"PHP ERROR: file {$params['name']} not exist!\"}");
                return ['result' => 'error', 'message' => ['file' => "File '{$params['name']}' not exist!"]];
            }

            try {
                $out = \Application\IO::file_stream($path);
                $out['name'] = $params['name'];
                $owner->response_header['Content-Type'] = $out['mime'];
                $owner->response_header['Content-Disposition'] = 'attachment; filename="' . $params['name'] . '";';
                $owner->response_header['Content-length'] = $out['size'];
                $owner->response_header['Action-Status'] = '{"result":"ok"}';
                \Application\Helper::log($db, 'download',['params'=>$owner->response_header,'user'=>$acl->user_id]);
                return ['result'=>'ok', 'Content-Type'=>'file', 'file'=>$out['file']] ;
            } catch (\Exception $e) {
                $owner->response_header['Action-Status'] =  rawurlencode ("{\"result\":\"error\",\"message\":\"PHP ERROR: file {$e->getMessage()} not exist!\"}");
                return ['result' => 'error', 'message' => ['file' => $e->getMessage()]];
            }

        }
    ]
];
?>