<?php
/**
 * IWSServer.php
 *
 * @category SPA (Single-page Application) WebSocket Backend
 * @author Андрей Новиков <andrey (at) novikov (dot) be>
 * @data 14/04/2020
 * @status beta
 * @version 0.1.0
 * @revision $Id: IWSServer.php 0001 2020-04-14 15:00:01Z $
 *
 */
namespace Application;

interface IWSServer {
    const WEBSOCKET_RESPONSE_CODE = [
        1000 => 'normal closure',
        1001 => 'going away',
        1002 => 'protocol error',
        1003 => 'unknown data (opcode)',
        1004 => 'frame too large',
        1005 => 'Unknow rrmote host error',
        1006 => 'Unknow rrmote host error',
        1007 => 'utf8 expected',
        1008 => 'message violates server policy',
        1015 => 'Untrusted SSL certificate'
    ];

    public function handshake(\Application\ISocket $s);
    public function open(\Application\ISocket $s, array $opt = []);
    public function close(\Application\ISocket $s);
    public function clear();
    public function message($data);
    public function send(\Application\ISocket $socket, $data, array $opt = []);
}
