<?php

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

// 参数示例
$swp = ['id' => 1, 'rpc_type' => 'SW'];
$snp = ['id' => 2, 'rpc_type' => 'SN'];
$mwp = ['rpc_type' => 'MW', 'params' => [
        ['id' => 3], ['id' => 4], ['id' => 5], ['id' => 6], ['id' => 7],
        ['id' => 8], ['id' => 9], ['id' => 10], ['id' => 11], ['id' => 12]]];
$mnp = ['rpc_type' => 'MN', 'params' => [
        ['id' => 13], ['id' => 14], ['id' => 15], ['id' => 16], ['id' => 17],
        ['id' => 18], ['id' => 19], ['id' => 20], ['id' => 21], ['id' => 22]]];

// Json包
$packet = new Sayhey\Rpc\Demo\JsonPacket();

// 协程
Co\run(function ()use($swp, $snp, $mwp, $mnp, $packet) {
    // TCP客户端
    $tcp = new \Swoole\Coroutine\Client(SWOOLE_SOCK_TCP);
    $tcp->connect('127.0.0.1', 9528, 1);
    for ($i = 0; $i < 10; $i++) {
        $tcp->send($packet->tcpPack($swp));
        echo PHP_EOL, 'TCP SW Result: ', json_encode($packet->tcpUnpack($tcp->recv())), PHP_EOL;

        $tcp->send($packet->tcpPack($snp));
        echo PHP_EOL, 'TCP SN Result: ', json_encode($packet->tcpUnpack($tcp->recv())), PHP_EOL;

        $tcp->send($packet->tcpPack($mwp));
        echo PHP_EOL, 'TCP MW Result: ', json_encode($packet->tcpUnpack($tcp->recv())), PHP_EOL;

        $tcp->send($packet->tcpPack($mnp));
        echo PHP_EOL, 'TCP MN Result: ', json_encode($packet->tcpUnpack($tcp->recv())), PHP_EOL;
    }
    $tcp->close();

    // HTTP客户端
    $http = new \Swoole\Coroutine\Http\Client('127.0.0.1', 9527);
    $http->set(['timeout' => 1]);
    for ($i = 0; $i < 10; $i++) {
        $http->post('/', $packet->httpPack($swp));
        echo PHP_EOL, 'HTTP SW Result: ', $http->body, PHP_EOL;

        $http->post('/', $packet->httpPack($snp));
        echo PHP_EOL, 'HTTP SN Result: ', $http->body, PHP_EOL;

        $http->post('/', $packet->httpPack($mwp));
        echo PHP_EOL, 'HTTP MW Result: ', $http->body, PHP_EOL;

        $http->post('/', $packet->httpPack($mnp));
        echo PHP_EOL, 'HTTP MN Result: ', $http->body, PHP_EOL;
    }
    $http->close();
});

echo PHP_EOL, 'OK', PHP_EOL;


