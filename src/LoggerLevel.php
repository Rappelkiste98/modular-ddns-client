<?php

namespace Src;

enum LoggerLevel: int
{
    case REQUEST = 0;
    case DEBUG = 1;
    case INFO = 2;
    case CHANGE = 3;
    case SUCCESS = 4;
    case WARNING = 5;
    case ERROR = 6;

    public function inLevel(LoggerLevel $level): bool
    {
        $currentLevel = $this->value;
        return $level->value >= $currentLevel;
    }

    public static function fromName(string $name): ?LoggerLevel
    {
        return match (strtoupper($name)) {
            'REQUEST' => LoggerLevel::REQUEST,
            'DEBUG' => LoggerLevel::DEBUG,
            'INFO' => LoggerLevel::INFO,
            'CHANGE' => LoggerLevel::CHANGE,
            'SUCCESS' => LoggerLevel::SUCCESS,
            'WARNING' => LoggerLevel::WARNING,
            'ERROR' => LoggerLevel::ERROR,
            default => null,
        };
    }
}
