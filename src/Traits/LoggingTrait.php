<?php

namespace ApeliMpesa\Mpesa\Traits;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

/**
 * LoggingTrait
 * 
 * Provides logging functionality to classes
 * 
 * @package ApeliMpesa\Mpesa\Traits
 */
trait LoggingTrait
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * Initialize the logger
     * 
     * @return void
     */
    protected function initializeLogger(): void
    {
        if ($this->logger instanceof Logger) {
            return;
        }

        $config = $this->config ?? null;
        
        // Create logger with a unique channel name based on the class
        $loggerName = str_replace('\\', '.', static::class);
        $this->logger = new Logger($loggerName);

        // Determine log level
        $logLevel = Logger::INFO;
        if ($config && method_exists($config, 'get')) {
            $configLevel = strtolower($config->get('LOG_LEVEL', 'info'));
            $logLevel = $this->getLogLevelConstant($configLevel);
        }

        // Determine log path
        $logPath = './logs';
        if ($config && method_exists($config, 'get')) {
            $logPath = $config->get('LOG_PATH', $logPath);
        }

        // Ensure log directory exists
        if (!is_dir($logPath)) {
            mkdir($logPath, 0755, true);
        }

        // Set up log formatter
        $dateFormat = "Y-m-d H:i:s";
        $output = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";
        $formatter = new LineFormatter($output, $dateFormat);

        // Create a rotating file handler (10MB max size, keep 14 days of logs)
        $handler = new RotatingFileHandler(
            $logPath . '/mpesa.log',
            14,
            $logLevel,
            true,
            0664
        );
        $handler->setFormatter($formatter);
        $this->logger->pushHandler($handler);

        // Add stdout handler if in debug mode
        if ($config && method_exists($config, 'isDebugEnabled') && $config->isDebugEnabled()) {
            $stdoutHandler = new StreamHandler('php://stdout', $logLevel);
            $stdoutHandler->setFormatter($formatter);
            $this->logger->pushHandler($stdoutHandler);
        }
    }

    /**
     * Convert log level string to Monolog constant
     * 
     * @param string $level
     * @return int
     */
    protected function getLogLevelConstant(string $level): int
    {
        $levels = [
            'debug' => Logger::DEBUG,
            'info' => Logger::INFO,
            'notice' => Logger::NOTICE,
            'warning' => Logger::WARNING,
            'error' => Logger::ERROR,
            'critical' => Logger::CRITICAL,
            'alert' => Logger::ALERT,
            'emergency' => Logger::EMERGENCY,
        ];

        return $levels[$level] ?? Logger::INFO;
    }

    /**
     * Log a message
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if (!$this->logger instanceof Logger) {
            $this->initializeLogger();
        }

        $this->logger->log($this->getLogLevelConstant($level), $message, $context);
    }
}