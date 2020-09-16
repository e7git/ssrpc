<?php

namespace Sayhey\Rpc\Interfaces;

/**
 * 拆包粘包接口
 * 
 */
interface PacketInterface
{

    /**
     * HTTP粘包
     * @param array $arr
     */
    public function httpPack(array $arr);

    /**
     * HTTP拆包
     * @param string $str
     */
    public function httpUnpack(string $str);

    /**
     * TCP粘包
     * @param string $str
     * @param string $pack_type
     */
    public function tcpUnpack(string $str, $pack_type = 'length');

    /**
     * TCP拆包
     * @param array $arr
     * @param string $pack_type
     */
    public function tcpPack(array $arr, $pack_type = 'length');

    /**
     * 构建提示的数据返回结构
     * @param string $msg
     * @param bool $is_success
     * @return array
     */
    public function formatReturnMsg(string $msg = '', bool $is_success = true): array;
}
