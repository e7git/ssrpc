<?php

namespace Sayhey\Rpc\Interfaces;

/**
 * 注册中心接口
 * 
 */
interface RegistryInterface
{

    /**
     * 初始化配置
     * @param array $config
     */
    public static function init(array $config);

    /**
     * 注册服务
     * @param string $serviceName
     * @param string $host
     * @param int $port
     * @return bool
     */
    public static function register(string $serviceName, string $host, int $port): bool;

    /**
     * 解除注册服务
     * @param string $serviceName
     * @return bool
     */
    public static function unregister(string $serviceName): bool;

    /**
     * 获取服务信息
     * @param string $serviceName
     */
    public static function getService(string $serviceName);
}
