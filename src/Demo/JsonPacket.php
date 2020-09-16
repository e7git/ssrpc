<?php

namespace Sayhey\Rpc\Demo;

use Sayhey\Rpc\Interfaces\PacketInterface;

/**
 * Json包
 */
class JsonPacket implements PacketInterface
{

    /**
     * HTTP粘包
     * @param array $arr
     */
    public function httpPack(array $arr)
    {
        return json_encode($arr);
    }

    /**
     * HTTP拆包
     * @param string $str
     */
    public function httpUnpack(string $str)
    {
        return json_decode($str, true);
    }

    /**
     * TCP粘包
     * @param array $arr
     */
    public function tcpPack(array $arr, $pack_type = 'length')
    {
        $data = json_encode($arr);

        if ('eof' === strtolower($pack_type)) {
            return $data . '\r\n';
        }

        if ('length' === strtolower($pack_type)) {
            return pack('N', strlen($data)) . $data;
        }

        return $data;
    }

    /**
     * TCP拆包
     * @param string $pack_type
     */
    public function tcpUnpack(string $str, $pack_type = 'length')
    {
        if ('eof' === strtolower($pack_type)) {
            return json_decode(str_replace('\r\n', '', $str), true);
        }

        if ('length' === strtolower($pack_type)) {
            $header = substr($str, 0, 4);
            return json_decode(substr($str, 4, unpack('Nlen', $header)['len']), true);
        }

        return json_decode($str, true);
    }

    /**
     * 构建提示的数据返回结构
     * @param string $msg
     * @param bool $is_success
     * @return array
     */
    public function formatReturnMsg(string $msg = '', bool $is_success = true): array
    {
        return [
            'code' => $is_success ? 0 : -1,
            'msg' => $msg
        ];
    }

}
