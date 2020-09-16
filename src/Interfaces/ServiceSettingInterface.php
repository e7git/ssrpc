<?php

namespace Sayhey\Rpc\Interfaces;

/**
 * 服务设置接口
 * 
 */
interface ServiceSettingInterface
{

    public function beforeWorker(array $params = []);

    public function beforeTasker(array $params = []);

    public function beforeTimer(array $params = []);

    public function setProcess(\Swoole\Server $server, array $data);
}
