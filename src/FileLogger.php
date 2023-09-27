<?php

namespace Src;

use Src\Exception\FileException;

class FileLogger
{
    final const DEFAULT_BASEPATH = 'var/logs';
    
    private string $basePath;
    private LoggerLevel $level;
    private $fileHandler = null;

    /**
     * @throws FileException
     */
    public function __construct(LoggerLevel $level, string $basePath = self::DEFAULT_BASEPATH)
    {
        $this->level = $level;
        $this->basePath = $basePath;

        $logPath = $this->createMonthDirectory();

        $fileHandler = fopen($logPath, 'a');
        if (!$fileHandler) {
            throw new FileException('File or Directory not found');
        } else {
            $this->fileHandler = $fileHandler;
        }
    }

    public function writeLog(string $message, LoggerLevel $messageLevel): void
    {
        if ($this->fileHandler !== null && $this->level->inLevel($messageLevel)) {
            fwrite($this->fileHandler, $message . "\n");
        }
    }

    /**
     * @throws FileException
     */
    private function createMonthDirectory(): string
    {
        $today = new \DateTime('today');
        $monthPath = $this->basePath . '/' . $today->format('Y-m');

        if (!file_exists($this->basePath)) {
            throw new FileException('Logs base Path "' . $this->basePath . '" not exists');
        }

        if (file_exists($monthPath)) {
            return $monthPath . '/' . $today->format('Y-m-d') . '.log';
        }

        if (!mkdir($monthPath, 0777, true)) {
            throw new FileException('Could not create Log Folder');
        }

        return $monthPath . '/' . $today->format('Y-m-d') . '.log';
    }
}