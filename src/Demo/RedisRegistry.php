<?php

namespace Sayhey\Rpc\Demo;

use Sayhey\Rpc\Interfaces\RegistryInterface;

/**
 * Redis注册中心
 * 
 */
class RedisRegistry implements RegistryInterface
{

    private static $host;                               // 主机
    private static $port;                               // 端口
    private static $auth;                               // 密码
    private static $db;                                 // 数据库
    private static $key;                                // 键名

    /**
     * 初始化
     * @param array $config
     */
    public static function init(array $config)
    {
        self::$host = $config['host'] ?? '127.0.0.1';
        self::$port = $config['port'] ?? 6379;
        self::$db = $config['db'] ?? 0;
        self::$auth = $config['pass'] ?? '';
        self::$key = (isset($config['key']) && '' !== $config['key']) ? $config['key'] : 'SSRPC_REG_';
    }

    /**
     * 注册
     * @param string $serviceName
     * @param string $host
     * @param int $port
     * @return boolean
     */
    public static function register(string $serviceName, string $host, int $port): bool
    {
        if ('' === $serviceName) {
            return false;
        }

        $connect = self::getConnect();

        $ret = $connect->set(self::$key . $serviceName, json_encode([
            'service_name' => $serviceName,
            'host' => $host,
            'port' => $port,
            'type' => 'TCP'
        ]));

        $connect->close();
        unset($connect);

        return boolval($ret);
    }

    /**
     * 解除注册
     * @param string $serviceName
     * @return bool
     */
    public static function unregister(string $serviceName): bool
    {
        if ('' === $serviceName) {
            return false;
        }

        $connect = self::getConnect();

        $ret = $connect->del(self::$key . $serviceName);

        $connect->close();
        unset($connect);

        return boolval($ret);
    }

    /**
     * 获取服务信息
     * @param string $serviceName
     * @return boolean|array
     */
    public static function getService(string $serviceName)
    {
        if ('' === $serviceName) {
            return false;
        }

        $connect = self::getConnect();

        if (!$ret = $connect->get(self::$key . $serviceName)) {
            $connect->close();
            return false;
        }

        return json_decode($ret);
    }

    /**
     * 获取连接
     * @throws \Exception
     */
    private static function getConnect()
    {
        $connect = new \Redis();

        try {
            if (empty(self::$host) || empty(self::$port)) {
                throw new \Exception('redis host or port is empty');
            }
            $connect->connect(self::$host, self::$port, 3);
            if (!empty(self::$auth)) {
                $connect->auth(self::$auth);
            }
            if (!empty(self::$db)) {
                $connect->select(self::$db);
            }
        } catch (\Exception $e) {
            throw $e;
        }

        return $connect;
    }

}
