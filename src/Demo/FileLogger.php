<?php

namespace Sayhey\Rpc\Demo;

use Sayhey\Rpc\Common\Util;
use Sayhey\Rpc\Interfaces\LogInterface;
use Sayhey\Rpc\Common\FatalException;

/**
 * 日志
 * 
 */
class FileLogger implements LogInterface
{

    const LOG_FILENAME = 'rpc';                         // 日志文件名
    const LOG_FILENAME_POSTFIX = '.log';                // 日志文件名后缀
    const LEVEL_INFO = 'INFO';                          // info级日志
    const LEVEL_ERROR = 'ERROR';                        // error级日志

    private $logFile = '';                              // 日志文件
    private $logMaxSize = 10;                           // 单个日志文件大小限制，单位(MB)，日志超出大小将切割
    private $logMaxCount = 9;                           // 日志文件数量限制，超出将删除

    /**
     * 构造方法
     * @param array $config
     * @throws FatalException
     */
    public function __construct(array $config)
    {
        if (empty($config['log_dir'])) {
            throw new FatalException('filelogger init error, config log_dir is empty');
        }

        $this->logFile = rtrim($config['log_dir'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::LOG_FILENAME . self::LOG_FILENAME_POSTFIX;

        if (!Util::mkdir(dirname($this->logFile))) {
            throw new FatalException('filelogger init error, mkdir log_dir failed');
        }
    }

    /**
     * 记录error级别日志
     * @param string $msg
     */
    public function error(string $msg)
    {
        try {
            $this->_log($msg, self::LEVEL_ERROR);
        } catch (\Exception $e) {
            // no code
        }
    }

    /**
     * 记录info级别日志
     * @param string $msg
     */
    public function info(string $msg)
    {
        try {
            $this->_log($msg, self::LEVEL_INFO);
        } catch (\Exception $e) {
            // no code
        }
    }

    /**
     * 记录日志
     * @param string $msg 日志内容
     * @param string $level 级别
     * @throws FatalException
     */
    private function _log(string $msg, string $level)
    {
        if (!Util::mkdir(dirname($this->logFile))) {
            throw new FatalException('filelogger record error, mkdir log_dir failed');
        }

        // 格式化
        $content = sprintf("[%s%s][%s][pid=%s]%s\n", date('Y-m-d H:i:s'), strstr(microtime(true), '.'), $level, getmypid(), $msg);

        try {
            // 切割
            if (is_file($this->logFile) && filesize($this->logFile) > $this->logMaxSize * 1024 * 1024) {
                self::rotateFiles();
            }

            // 读写
            if (false === ($fp = @fopen($this->logFile, 'a'))) {
                throw new FatalException('unable to append to log file');
            }
            flock($fp, LOCK_EX);
            fwrite($fp, $content);
            flock($fp, LOCK_UN);
            fclose($fp);
        } catch (\Exception $e) {
            throw new FatalException($e->getMessage());
        }
    }

    /**
     * 切割日志文件
     * @throws FatalException
     */
    private function rotateFiles()
    {
        for ($i = $this->logMaxCount; $i >= 0; $i--) {
            $rotate_name = dirname($this->logFile) . DIRECTORY_SEPARATOR . self::LOG_FILENAME . ($i == 0 ? '' : '-' . $i) . self::LOG_FILENAME_POSTFIX;

            if (!is_file($rotate_name)) {
                continue;
            }

            if ($i == $this->logMaxCount) {
                if (!Util::unlink($rotate_name)) {
                    throw new FatalException('unlink log file failed ');
                }
            } else {
                $rotate_next = dirname($this->logFile) . DIRECTORY_SEPARATOR . self::LOG_FILENAME . '-' . ($i + 1) . self::LOG_FILENAME_POSTFIX;
                if (!rename($rotate_name, $rotate_next)) {
                    throw new FatalException('rename log file failed ');
                }
            }
        }
    }

}
