<?php

namespace Sayhey\Rpc\Core;

use Sayhey\Rpc\Common\FatalException;
use Sayhey\Rpc\Interfaces\ServiceSettingInterface;
use Sayhey\Rpc\Interfaces\PacketInterface;
use Sayhey\Rpc\Common\Util;
use Sayhey\Rpc\Interfaces\LogInterface;
use Sayhey\Rpc\Interfaces\RegistryInterface;

class Service
{

    // 配置
    private $dataDir = '';                              // 数据存储目录
    private $name = '';                                 // 服务名
    private $processPostfix = 'ssrpc';                  // 进程名后缀
    private $requestTypeKey = '';                       // 请求类型参数名
    private $remote = '0.0.0.0.';                       // 外部主机
    private $masterPidFile = '';                        // 主进程PID文件
    // 运行时
    private static $taskResult = [];                    // 任务结果数据缓存
    // HTTP默认配置
    private $httpConf = [
        'max_conn' => 100,
        'max_request' => 100,
        'reactor_num' => 2,
        'worker_num' => 2,
        'task_worker_num' => 8,
        'daemonize' => true,
        'package_max_length' => 1024,
        'buffer_output_size' => 1024,
        'log_level' => 2,
        'dispatch_mode' => 3,
        'task_ipc_mode' => 1,
        'backlog' => 2000,
        'task_max_request' => 5,
        'host' => '0.0.0.0',
        'port' => 9527,
    ];
    // TCP默认配置
    private $tcpConf = [
        'open_length_check' => true,
        'package_length_type' => 'N',
        'package_length_offset' => 0,
        'package_body_offset' => 4,
        'heartbeat_check_interval' => 60,
        'port' => 9528,
        'pack_type' => 'length', // 预留粘包拆包类型，默认按长度，可自定义
    ];

    /**
     * 服务设置类
     * @var ServiceSettingInterface 
     */
    private $setting = null;

    /**
     * 拆包粘包类
     * @var PacketInterface 
     */
    private $packet = null;

    /**
     * 日志类
     * @var LogInterface 
     */
    private $logger = null;

    /**
     * TCP服务实例
     * @var \Swoole\Service 
     */
    private $tcp = null;

    /**
     * HTTP服务实例
     * @var \Swoole\Service
     */
    private $http = null;

    /**
     * 构造方法
     * @param array $config
     * @throws FatalException
     */
    public function __construct(array $config)
    {
        // 检查配置
        if (true !== $err = $this->checkConfig($config)) {
            throw new FatalException($err);
        }

        // 初始化日志
        $this->logger = new $config['log']['class']($config['log']['params'] ?? []);

        // 初始化服务配置
        $this->dataDir = $config['service']['data_dir'];
        $this->name = $config['service']['name'];
        $this->requestTypeKey = $config['service']['request_type_key'];
        $this->processPostfix = $config['service']['process_postfix'] ?? $this->processPostfix;
        $this->setting = new $config['service']['setting_class']();
        $this->packet = new $config['service']['packet_class']();
        if (!empty($config['service']['host'])) {
            $this->httpConf['host'] = $config['service']['host'];
        }
        if (!empty($config['service']['http'])) {
            $this->httpConf = array_merge($this->httpConf, $config['service']['http']);
        }
        if (!empty($config['service']['tcp'])) {
            $this->tcpConf = array_merge($this->tcpConf, $config['service']['tcp']);
        }
        $this->masterPidFile = rtrim($this->dataDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'master.pid';

        // 创建目录文件
        if (!Util::mkdir(dirname($this->masterPidFile))) {
            throw new FatalException('mkdir failed, ' . dirname($this->masterPidFile));
        }
    }

    /**
     * 检查配置
     * @param array $config
     * @return boolean|string
     */
    private function checkConfig(array $config)
    {
        $service_config = $config['service'] ?? null;
        if (empty($service_config['data_dir']) || empty($service_config['name'])) {
            return 'config service.data_dir or service.name is empty';
        }
        if (isset($service_config['process_postfix']) && '' === $service_config['process_postfix']) {
            return 'config service.process_postfix must be a non-empty string';
        }
        if (empty($service_config['setting_class'])) {
            return 'config service.setting_class must be a non-empty string';
        }
        if (!class_exists($service_config['setting_class'])) {
            return 'config service.setting_class ' . $service_config['class'] . ' not exists';
        }
        if (!isset(class_implements($service_config['setting_class'])[ServiceSettingInterface::class])) {
            return 'config service.setting_class ' . $service_config['class'] . ' must implements class ' . ServiceSettingInterface::class;
        }
        if (empty($service_config['packet_class'])) {
            return 'config service.packet_class must be a non-empty string';
        }
        if (!class_exists($service_config['packet_class'])) {
            return 'config service.packet_class ' . $service_config['class'] . ' not exists';
        }
        if (!isset(class_implements($service_config['packet_class'])[PacketInterface::class])) {
            return 'config service.packet_class ' . $service_config['class'] . ' must implements class ' . PacketInterface::class;
        }
        if (!isset($service_config['request_type_key']) || '' === $service_config['request_type_key']) {
            return 'config service.request_type_key must be a non-empty string';
        }

        if (empty($config['log'])) {
            throw new FatalException('config log must be a non-empty array');
        }
        if (isset($config['log']['params']) && !is_array($config['log']['params'])) {
            throw new FatalException('config log.params must be a array');
        }
        if (empty($config['log']['class'])) {
            throw new FatalException('config log.class must be a non-empty string');
        }
        if (!class_exists($config['log']['class'])) {
            throw new FatalException('log.class ' . $config['log']['class'] . ' not exists');
        }
        if (!isset(class_implements($config['log']['class'])[LogInterface::class])) {
            throw new FatalException('log.class ' . $config['log']['class'] . ' must implements class ' . LogInterface::class);
        }

        if (isset($config['registry'])) {
            if (!is_array($config['registry'])) {
                throw new FatalException('config registry must be a array');
            }
            if (isset($config['registry']['params']) && !is_array($config['registry']['params'])) {
                throw new FatalException('config registry.params must be a array');
            }
            if (empty($config['registry']['class'])) {
                throw new FatalException('config registry.class must be a non-empty string');
            }
            if (!class_exists($config['registry']['class'])) {
                throw new FatalException('registry.class ' . $config['registry']['class'] . ' not exists');
            }
            if (!isset(class_implements($config['registry']['class'])[RegistryInterface::class])) {
                throw new FatalException('registry.class ' . $config['registry']['class'] . ' must implements class ' . RegistryInterface::class);
            }
        }

        return true;
    }

    /**
     * 运行
     */
    public function run(string $action = '')
    {
        if (!$action) {
            global $argv;
            $action = $argv[1] ?? 'help';
        }

        switch ($action) {
            case 'start': // 启动
                echo 'start success', PHP_EOL;
                $this->startRun();
                return true;
            case 'stop': // 停止
                if (!$pid = $this->readRunningMasterPid()) {
                    echo 'service has stopped', PHP_EOL;
                    return true;
                }
                \Swoole\Process::kill($pid, SIGTERM);
                for ($i = 0; $i < 100; $i++) {
                    if (!\Swoole\Process::kill($pid, 0)) {
                        echo 'stop success', PHP_EOL;
                        Util::unlink($this->masterPidFile);
                        return true;
                    }
                    usleep(100000); // 0.1秒
                }
                echo 'stop success', PHP_EOL;
                return true;
            case 'restart': // 重启
                if (!!$pid = $this->readRunningMasterPid()) {
                    echo 'service already running, it will restart', PHP_EOL;
                    $this->run('stop');
                }
                if (!!$pid = $this->readRunningMasterPid()) {
                    echo 'restart failed, please try again', PHP_EOL;
                    return true;
                }

                echo 'restart success', PHP_EOL;
                $this->startRun();
                return true;
            default : // 打印帮助信息
                echo strtr('{#y}Service Command:' . PHP_EOL . '{#g}  help | start | stop | restart{##}', ['{#y}' => "\033[0;33m", '{#g}' => "\033[0;32m", '{##}' => "\033[0m"]), PHP_EOL, PHP_EOL;
                return true;
        }
    }

    /**
     * 启动
     * @return bool
     */
    private function startRun(): bool
    {
        $this->setProcessName('master');

        $this->http = new \Swoole\Http\Server($this->httpConf['host'], $this->httpConf['port']);
        $this->http->set($this->httpConf);

        // HTTP
        $this->http->on('request', [$this, 'request']);
        $this->http->on('workerStart', [$this, 'workerStart']);
        $this->http->on('managerStart', [$this, 'managerStart']);
        $this->http->on('task', [$this, 'task']);
        $this->http->on('finish', [$this, 'finish']);
        $this->http->on('start', [$this, 'start']);

        // TCP 
        $this->tcp = $this->http->addListener($this->httpConf['host'], $this->tcpConf['port'], SWOOLE_SOCK_TCP);
        $this->tcp->set($this->tcpConf);
        $this->tcp->on('receive', [$this, 'receive']);

        $this->logger->info("start success, http port is {$this->httpConf['port']}, tcp port is {$this->tcpConf['port']}");

        $this->http->start();

        return true;
    }

    /**
     * 主进程启动后回调
     * @param \Swoole\Server $server
     */
    public function start(\Swoole\Server $server)
    {
        Util::file_put_contents($this->masterPidFile, $server->master_pid);
    }

    /**
     * 子进程进程启动后回调
     * @param \Swoole\Server $server
     * @param int $worker_id
     */
    public function workerStart(\Swoole\Server $server, int $worker_id)
    {
        if ($worker_id >= $this->httpConf['worker_num']) {
            $this->setProcessName('tasker');
            $this->setting->beforeTasker();
        } else {
            $this->setProcessName('worker');
            $this->setting->beforeWorker();
        }
    }

    /**
     * 管理进程进程启动后回调
     * @param \Swoole\Server $server
     */
    public function managerStart(\Swoole\Server $server)
    {
        $this->setProcessName('manager');
    }

    /**
     * HTTP请求回调
     * @param \Swoole\Http\Request $request
     * @param \Swoole\Http\Response $response
     * @return mix
     */
    public function request(\Swoole\Http\Request $request, \Swoole\Http\Response $response)
    {
        // 获取包体
        $body = trim($request->rawContent());

        // 包解析
        if (false === $params = $this->packet->httpUnpack($body)) {
            $this->logger->error('package resolution failed: ' . $body);
            $response->end($this->buildPackMsg('package resolution failed', false, false));
            return true;
        }

        // 组装数据包
        $data = [
            'params' => $params,
            'header' => [],
            'server' => [],
            'fd' => $request->fd,
            'requestId' => $this->createRequestId(),
            'rpcType' => $params[$this->requestTypeKey] ?? null,
            'rpcCount' => 1,
            'rpcKey' => null,
        ];
        if (false === $data['requestId']) {
            $response->end($this->buildPackMsg('create requestId fail, please try again', false, false));
            return true;
        }

        switch ($data['rpcType']) {
            // 单请求同步
            case 'SW':
                $this->http->task($data, -1, function ($server, $task_id, $result) use ($response) {
                    $this->onHttpFinished($server, $task_id, $result, $response);
                });
                break;

            // 单请求异步
            case 'SN':
                $this->http->task($data);
                $response->end($this->buildPackMsg('task deliver success', true, false));
                break;

            // 多请求同步
            case 'MW':
                $data['rpcCount'] = count($params['params']);
                foreach ($params['params'] as $key => $value) {
                    $this->http->task(array_merge($data, ['params' => $value, 'rpcKey' => $key]), -1
                            , function ($server, $task_id, $result) use ($response) {
                        $this->onHttpFinished($server, $task_id, $result, $response);
                    });
                }
                break;

            // 多请求异步
            case 'MN':
                foreach ($params['params'] as $key => $value) {
                    $this->http->task(array_merge($data, ['params' => $value, 'rpcKey' => $key]));
                }
                $response->end($this->buildPackMsg('task deliver success', true, false));
                break;

            // 异常
            default:
                $response->end($this->buildPackMsg('param ' . $this->requestTypeKey . ' only SW|SN|MW|MN', false, false));
                break;
        }

        return true;
    }

    /**
     * TCP请求回调
     * @param \Swoole\Server $server
     * @param int $fd
     * @param int $from_id
     * @param string $body
     * @return bool
     */
    public function receive(\Swoole\Server $server, $fd, $from_id, $body)
    {
        // 包解析
        if (false === $params = $this->packet->tcpUnpack($body, $this->tcpConf['pack_type'])) {
            $this->logger->error('package resolution failed: ' . $body);
            $server->send($fd, $this->buildPackMsg('package resolution failed', false, true));
            return true;
        }

        // 组装数据包
        $data = [
            'params' => $params,
            'header' => [],
            'server' => [],
            'fd' => $fd,
            'requestId' => $this->createRequestId(),
            'rpcType' => $params[$this->requestTypeKey] ?? null,
            'rpcCount' => 1,
            'rpcKey' => null,
        ];
        if (false === $data['requestId']) {
            $server->send($fd, $this->buildPackMsg('create requestId fail, please try again', false, true));
            return true;
        }

        switch ($data['rpcType']) {
            // 单请求同步
            case 'SW':
                $server->task($data);
                break;

            // 单请求异步
            case 'SN':
                $server->task($data);
                $server->send($fd, $this->buildPackMsg('task deliver success', true, true));
                break;

            // 多请求同步
            case 'MW':
                $data['rpcCount'] = count($params['params']);
                foreach ($params['params'] as $key => $value) {
                    $server->task(array_merge($data, ['params' => $value, 'rpcKey' => $key]));
                }
                break;

            // 多请求异步
            case 'MN':
                foreach ($params['params'] as $key => $value) {
                    $server->task(array_merge($data, ['params' => $value, 'rpcKey' => $key]));
                }
                $server->send($fd, $this->buildPackMsg('task deliver success', true, true));
                break;

            // 异常
            default:
                $server->send($fd, $this->buildPackMsg('param ' . $this->requestTypeKey . ' only SW|SN|MW|MN', false, true));
                break;
        }

        return true;
    }

    /**
     * 
     * @param \Swoole\Server $server
     * @param int $task_id
     * @param int $worker_id
     * @param array $data
     * @return array
     */
    public function task(\Swoole\Server $server, $task_id, $worker_id, $data)
    {
        $data['result'] = $this->setting->setProcess($server, $data);
        return $data;
    }

    /**
     * 
     * @param \Swoole\Server $server
     * @param int $task_id
     * @param array $data
     * @return bool
     */
    public function finish(\Swoole\Server $server, $task_id, $data)
    {
        switch ($data['rpcType']) {
            // 单请求同步
            case 'SW':
                $server->send($data['fd'], $this->packet->tcpPack($data['result'], $this->tcpConf['pack_type']));
                break;

            // 多请求同步
            case 'MW':
                self::$taskResult[$data['requestId']][$data['rpcKey']] = $data['result'];
                if (isset(self::$taskResult[$data['requestId']]) && $data['rpcCount'] === count(self::$taskResult[$data['requestId']])) {
                    $result = self::$taskResult[$data['requestId']];
                    unset(self::$taskResult[$data['requestId']]);
                    $server->send($data['fd'], $this->packet->tcpPack($result, $this->tcpConf['pack_type']));
                    unset($result);
                }
                break;
        }

        return true;
    }

    /**
     * 
     * @param \Swoole\Server $server
     * @param type $task_id
     * @param type $data
     * @param type $response
     * @return boolean
     */
    private function onHttpFinished(\Swoole\Server $server, $task_id, $data, $response)
    {
        switch ($data['rpcType']) {
            // 单请求同步
            case 'SW':
                $response->end($this->packet->httpPack($data['result']));
                break;

            // 多请求同步
            case 'MW':
                self::$taskResult[$data['requestId']][$data['rpcKey']] = $data['result'];
                if (isset(self::$taskResult[$data['requestId']]) && $data['rpcCount'] === count(self::$taskResult[$data['requestId']])) {
                    $result = self::$taskResult[$data['requestId']];
                    unset(self::$taskResult[$data['requestId']]);
                    $response->end($this->packet->httpPack($result));
                    unset($result);
                }
                break;
        }

        return true;
    }

    /**
     * 设置进程名
     * @param string $type
     */
    private function setProcessName(string $type)
    {
        swoole_set_process_name($type . ':' . $this->processPostfix);
    }

    /**
     * 通过读文件获取主进程pid，并检测是否运行
     * @return int
     */
    private function readRunningMasterPid(): int
    {
        if (!$pid = intval(Util::file_get_contents($this->masterPidFile))) {
            return 0;
        }
        if (!\Swoole\Process::kill($pid, 0)) {
            Util::unlink($this->masterPidFile);
            return 0;
        }

        return $pid;
    }

    /**
     * 构建提示
     * @param string $msg
     * @param bool $is_success
     * @param bool $istcp
     * @return string
     */
    private function buildPackMsg(string $msg, bool $is_success, bool $istcp): string
    {
        if (!$istcp) {
            return $this->packet->httpPack($this->packet->formatReturnMsg($msg, $is_success));
        }

        return $this->packet->tcpPack($this->packet->formatReturnMsg($msg, $is_success), $this->tcpConf['pack_type']);
    }

    /**
     * 创建请求ID
     * @return bool|string
     */
    private function createRequestId()
    {
        for ($i = 0; $i < 3; $i++) {
            $request_id = Util::createUniqid();
            if (!isset(self::$taskResult[$request_id])) {
                return $request_id;
            }
        }
        return false;
    }

}
