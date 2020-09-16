<?php

namespace Sayhey\Rpc\Common;

/**
 * 工具类
 * 
 */
class Util
{

    /**
     * 创建目录
     * @param string $dir
     * @return bool
     */
    public static function mkdir(string $dir): bool
    {
        if (is_dir($dir)) {
            clearstatcache();
            if (is_dir($dir)) {
                return true;
            }
        }

        return @mkdir($dir, 0777, true);
    }

    /**
     * 删除文件
     * @param string $file
     * @return bool
     */
    public static function unlink(string $file): bool
    {

        if (!self::is_file($file)) {
            return false;
        }

        return @unlink($file);
    }

    /**
     * 文件是否存在
     * @param string $file
     * @return bool
     */
    public static function is_file(string $file): bool
    {
        if ('' === $file) {
            return false;
        }

        clearstatcache();

        if (!is_file($file)) {
            return false;
        }

        return true;
    }

    /**
     * 写入文件
     * @param string $filename
     * @param mix $content
     * @param int $mode
     * @return bool
     */
    public static function file_put_contents(string $filename, $content, int $mode = null): bool
    {
        if ('' === $filename) {
            return false;
        }

        if (true !== self::mkdir(dirname($filename))) {
            return false;
        }

        if (null === $mode) {
            $ret = @file_put_contents($filename, $content);
        } else {
            $ret = @file_put_contents($filename, $content, $mode);
        }

        if (false === $ret) {
            return false;
        }

        return $ret;
    }

    /**
     * 读取文件
     * @param string $filename
     * @return string|false
     */
    public static function file_get_contents(string $filename)
    {
        if (!self::is_file($filename) || !is_readable($filename)) {
            return false;
        }

        if (false === $ret = @file_get_contents($filename)) {
            return false;
        }

        return $ret;
    }

    /**
     * 获取文件是否为最近3秒更新
     * @param string $filename
     * @return bool
     */
    public static function file_is_latest(string $filename): bool
    {
        if (!self::is_file($filename) || !is_readable($filename) || !filemtime($filename)) {
            return false;
        }

        for ($i = 0; $i < 30; $i++) {
            clearstatcache();
            if (abs(time() - filemtime($filename)) < 3) {
                return true;
            }
            usleep(100000);
        }

        return false;
    }

    /**
     * 获取负载情况
     * @return string
     */
    public static function getSysLoadAvg()
    {
        $loadavg = function_exists('sys_getloadavg') ? array_map('round', sys_getloadavg(), [2]) : ['-', '-', '-'];
        return implode(', ', $loadavg);
    }

    /**
     * 获取内存使用情况
     * @return string
     */
    public static function getMemoryUsage()
    {
        return round(memory_get_usage(true) / (1024 * 1024), 2) . ' MB';
    }

    /**
     * 生成唯一ID
     * @return string
     */
    public static function createUniqid(): string
    {
        $uniqstr = strtoupper(md5(uniqid(session_create_id(date('YmdHis')), true)));

        $tmp = [];
        $offset = 0;
        foreach ([8, 4, 4, 4, 12] as $v) {
            $tmp[] = substr($uniqstr, $offset, $v);
            $offset += $v;
        }
        unset($offset, $v);

        return implode('-', $tmp);
    }

}
