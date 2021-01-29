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
//    'params' => [],
    'get'=>[
        'action'=>function($db, $owner, $acl) {
            $date = date("Y-m-d_H_i_s");
            $file_name = "statistic-$date.csv";
            try {
                $rs = $db->filter("select * from datamart.candidates", ['polling_district'=>$acl->polling_district], ['order'=>'ORDER BY staff ASC', 'paginator'=>false])->fetchall();
                if (count($rs)) {
                    $csv = \Application\IO::csv_open($rs);
                    $csv['name'] = $file_name;
                    $owner->response_header['Content-Type'] = $csv['mime'] = 'application/csv; charset=UTF-8';
                    $owner->response_header['Content-Disposition'] = 'attachment; filename="' . $file_name . '";';
                    $owner->response_header['Content-length'] = $csv['size'];
                    $owner->response_header['Action-Status'] = '{"result":"ok"}';
                }
                \Application\Helper::log($db, 'download', ['params'=>$owner->response_header,'user_id'=>$acl->user_id]);
                return ['result'=>'ok', 'Content-Type'=>'file', 'file'=>$csv['file']] ;
            } catch (\Exception $e) {
                $owner->response_header['Action-Status'] = rawurlencode ("{\"result\":\"error\",\"message\":\"PHP ERROR: CSV streem {$e->getMessage()} not exist!\"}");
                return ['result' => 'error', 'message' => ['file' => $e->getMessage()]];
            }

        }
    ]
];
?>