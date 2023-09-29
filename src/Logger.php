<?php

namespace Src;

use Src\Curl\HttpClient;
use Src\Entities\DnsRecord;
use Src\Network\IPv4;
use Src\Network\IPv6;
use Modules\DnsService\DnsService;

class Logger
{
    const format = '[%Date% | %Module% | %Status%] %Message%';
    private LoggerLevel $level;

    public function __construct(LoggerLevel $level)
    {
        $this->level = $level;
    }

    public function request(HttpClient $client, mixed $payload = null): void
    {
        $message = $client->getHttpMethod() . ' "' . $client->getUrl() . '"';
        if ($client->getHttpMethod() !== HttpClient::METHOD_GET && $payload !== null) {
            $message .= 'Payload: ';

            if (is_array($payload)) {
                $message .= json_encode($payload);
            } else {
                $message .= $payload;
            }
        }

        $logMessage = self::buildMessage('0;35', $message, LoggerLevel::REQUEST, $client::class);

        if($this->level->inLevel(LoggerLevel::DEBUG)) {
            echo $logMessage;
        }
    }

    public function debug(string $message, ?string $className = null): void
    {
        $logMessage = self::buildMessage('0;37', $message, LoggerLevel::DEBUG, $className);

        if($this->level->inLevel(LoggerLevel::DEBUG)) {
            echo $logMessage;
        }
    }

    public function info(string $message, ?string $className = null): void
    {
        $logMessage = self::buildMessage('0;0', $message, LoggerLevel::INFO, $className);

        if($this->level->inLevel(LoggerLevel::INFO)) {
            echo $logMessage;
        }
    }

    public function warning(string $message, ?string $className = null): void
    {
        $logMessage = self::buildMessage('1;33', $message, LoggerLevel::WARNING, $className);

        if($this->level->inLevel(LoggerLevel::WARNING)) {
            echo $logMessage;
        }
    }

    public function error(string $message, ?string $className = null): void
    {
        $logMessage = self::buildMessage('0;31', $message, LoggerLevel::ERROR, $className);

        if($this->level->inLevel(LoggerLevel::ERROR)) {
            echo $logMessage;
        }
    }

    public function change(DnsRecord $domain, ?DnsService $dnsModule, IPv4|IPv6|null $oldIp, IPv4|IPv6 $newIp): void
    {
        $oldIp = !is_null($oldIp) ? $oldIp->getAddress() : '';
        $newIp = $newIp->getAddress();

        $message = sprintf('DOMAIN "%s": %s ==> %s', $domain->getDnsRecordname(), $oldIp, $newIp);
        $logMessage = self::buildMessage('0;36', $message, LoggerLevel::CHANGE, $dnsModule::class);

        if($this->level->inLevel(LoggerLevel::CHANGE)) {
            echo $logMessage;
        }
    }

    public function success(string $message, ?string $className = null): void
    {
        $logMessage = self::buildMessage('0;32', $message, LoggerLevel::SUCCESS, $className);

        if($this->level->inLevel(LoggerLevel::SUCCESS)) {
            echo $logMessage;
        }
    }

    private function buildMessage(string $color, string $message, LoggerLevel $level, ?string $class): string
    {
        $logMessage = str_replace(
                ['%Date%', '%Module%', '%Status%', '%Message%'],
                [(new \DateTime)->format('c'), $class ?? 'Client', $level->name, $message],
                self::format
            );

        FILE_LOGGER?->writeLog($logMessage, $level);

        return "\e[{$color}m{$logMessage}\n\e[0m";
    }
}