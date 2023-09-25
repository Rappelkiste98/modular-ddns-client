<?php

namespace Acme;

use Acme\Entities\DnsRecord;
use Acme\Network\IPv4;
use Acme\Network\IPv6;
use Modules\DnsService\DnsService;

class Log
{
    const format = '[%Date% | %Module% | %Status%] %Message%';
    final const TYPE_INFO = 'Info';
    final const TYPE_WARNING = 'Warning';
    final const TYPE_ERROR = 'Error';
    final const TYPE_SUCCESS = 'Success';
    final const TYPE_CHANGE = 'Change';

    public static function info(string $message, ?string $className = null): void
    {
        $logMessage = self::buildMessage('0;0', $message, self::TYPE_INFO, $className);
        echo $logMessage;
    }

    public static function warning(string $message, ?string $className = null): void
    {
        $logMessage = self::buildMessage('1;33', $message, self::TYPE_WARNING, $className);
        echo $logMessage;
    }

    public static function error(string $message, ?string $className = null): void
    {
        $logMessage = self::buildMessage('0;31', $message, self::TYPE_ERROR, $className);
        echo $logMessage;
    }

    public static function change(DnsRecord $domain, ?DnsService $dnsModule, IPv4|IPv6|null $oldIp, IPv4|IPv6 $newIp): void
    {
        $oldIp = !is_null($oldIp) ? $oldIp->getAddress() : '';
        $newIp = $newIp->getAddress();

        $message = 'DOMAIN "' . $domain->getDnsRecordname() . '": ' . $oldIp . ' ==> ' . $newIp;
        $logMessage = self::buildMessage('0;36', $message, self::TYPE_CHANGE, $dnsModule::class);
        echo $logMessage;
    }

    public static function success(string $message, ?string $className = null): void
    {
        $logMessage = self::buildMessage('0;32', $message, self::TYPE_SUCCESS, $className);
        echo $logMessage;
    }

    private static function buildMessage(string $color, string $message, string $status, ?string $class): string
    {
        return "\e[". $color . "m" . str_replace(
            ['%Date%', '%Module%', '%Status%', '%Message%'],
            [(new \DateTime)->format('c'), $class ?? 'Client', $status, $message],
            self::format
        ) . "\n\e[0m";
    }
}