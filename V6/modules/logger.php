<?php
// modules/logger.php
namespace Modules;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * 統一日誌管理器
 * - 當 LOG_ENABLED=false 時，appLogger / aiLogger 回傳 NullLogger
 * - errorLogger 永遠啟用
 */
class LoggerManager
{
    private static ?LoggerInterface $appLogger = null;
    private static ?LoggerInterface $aiLogger = null;
    private static LoggerInterface $errorLogger;

    public static function init(bool $logEnabled, string $logsDir): void
    {
        if (!is_dir($logsDir) && !@mkdir($logsDir, 0755, true)) {
            throw new \RuntimeException("無法建立日誌目錄: {$logsDir}");
        }

        // errorLogger 永遠啟用
        $errorLogger = new \Monolog\Logger('error');
        $errorLogger->pushHandler(new \Monolog\Handler\RotatingFileHandler("{$logsDir}/error.log", 7, \Monolog\Logger::WARNING));
        self::$errorLogger = $errorLogger;

        if ($logEnabled) {
            $appLogger = new \Monolog\Logger('app');
            $appLogger->pushHandler(new \Monolog\Handler\RotatingFileHandler("{$logsDir}/app.log", 7, \Monolog\Logger::INFO));

            $aiLogger = new \Monolog\Logger('ai');
            $aiLogger->pushHandler(new \Monolog\Handler\RotatingFileHandler("{$logsDir}/ai.log", 7, \Monolog\Logger::INFO));

            self::$appLogger = $appLogger;
            self::$aiLogger = $aiLogger;
        } else {
            self::$appLogger = new NullLogger();
            self::$aiLogger = new NullLogger();
        }
    }

    public static function app(): LoggerInterface
    {
        return self::$appLogger ?? new NullLogger();
    }

    public static function ai(): LoggerInterface
    {
        return self::$aiLogger ?? new NullLogger();
    }

    public static function error(): LoggerInterface
    {
        return self::$errorLogger;
    }
}