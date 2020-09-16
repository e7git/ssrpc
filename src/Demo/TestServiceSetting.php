<?php

namespace Sayhey\Rpc\Demo;

use Sayhey\Rpc\Interfaces\ServiceSettingInterface;

/**
 * 服务设置
 * 
 */
class TestServiceSetting implements ServiceSettingInterface
{

    public function beforeTasker(array $params = [])
    {
        
    }

    public function beforeTimer(array $params = [])
    {
        
    }

    public function beforeWorker(array $params = [])
    {
        
    }

    public function setProcess(\Swoole\Server $server, array $data)
    {
//        $servername = 'localhost';
//        $username = 'root';
//        $password = 'root';
//        $dbname = '_temp';
//
//        $conn = new \PDO("mysql:host={$servername};dbname={$dbname}", $username, $password);
//        $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
//        $sql = 'INSERT INTO t1 (c1) VALUES (' . intval($data['params']['id'] ?? 0) . ')';
//        $conn->exec($sql);

        return ['code' => 0, 'msg' => 'succ', 'request_id' => $data['requestId'] ?? '', 'data' => ['id' => $data['params']['id'] ?? 0]];
    }

}
