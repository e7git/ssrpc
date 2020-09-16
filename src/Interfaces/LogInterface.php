<?php

namespace Sayhey\Rpc\Interfaces;

/**
 * 日志接口
 * 
 */
interface LogInterface
{

    /**
     * 构造方法
     * @param array $config
     */
    public function __construct(array $config);

    /**
     * 记录info级别日志
     * @param string $msg
     */
    public function info(string $msg);

    /**
     * 记录error级别日志
     * @param string $msg
     */
    public function error(string $msg);
}
