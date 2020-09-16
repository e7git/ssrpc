<?php

if ('cli' !== php_sapi_name()) {
    echo 'service can only run in cli mode', PHP_EOL;
    exit;
}

if (!extension_loaded('swoole')) {
    echo 'swoole extension not exist', PHP_EOL;
    exit;
}

// 自动加载
if (is_file(__DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php')) {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
} else {

    function autoload($class)
    {
        $file = __DIR__ . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
        require_once str_replace('Sayhey/Rpc', 'src', $file);
    }

    spl_autoload_register('autoload');
}


$config = [
    'service' => [
        'data_dir' => __DIR__ . '/../data/',
        'name' => 'USER_CENTER',
        'process_postfix' => 'ssrpc',
        'request_type_key' => 'rpc_type',
        'setting_class' => Sayhey\Rpc\Demo\TestServiceSetting::class,
        'packet_class' => Sayhey\Rpc\Demo\JsonPacket::class,
        'host' => '0.0.0.0',
        'remote' => '10.0.3.15',
        'http' => [
            'port' => 9527,
        ],
        'tcp' => [
            'port' => 9528,
        ],
    ],
    'registry' => [
        'class' => Sayhey\Rpc\Demo\RedisRegistry::class,
        'params' => [
            'key_prefix' => 'SSRPC_REG_',
            'host' => '127.0.0.1',
            'port' => 6379,
            'pass' => '',
            'db' => 0
        ]
    ],
    'log' => [
        'class' => Sayhey\Rpc\Demo\FileLogger::class,
        'params' => [
            'log_dir' => __DIR__ . '/../data/log/',
        ]
    ],
];

(new Sayhey\Rpc\Core\Service($config))->run();
