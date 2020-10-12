<?php
/**
 * ISocket.php
 *
 * @category SPA (Single-page Application) WebSocket Backend
 * @author Андрей Новиков <andrey (at) novikov (dot) be>
 * @data 26/09/2020
 * @status beta
 * @version 0.1.0
 * @revision $Id: ISocket.php 0001 2020-04-14 15:00:01Z $
 *
 */
namespace Application;

interface ISocket {
    public function write(string $string, array $opt = []): ?int;
    public function read(): ?string;
    public function close();
}
