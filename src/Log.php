<?php

namespace Acme;

use Acme\Network\Domain;
use Acme\Network\IPv4;
use Acme\Network\IPv6;
use Modules\DnsService\DnsService;

class Log
{
    const format = "[%Date% | %Module% | %Status%] %Message%\n";

    public static function info(string $message, ?string $className = null): void
    {
        $logMessage = str_replace(
            ['%Date%', '%Module%', '%Status%', '%Message%'],
            [(new \DateTime)->format('c'), (is_null($className) ? 'Update' : $className), 'Info', $message],
            self::format
        );
        $logMessage .= "\e[0m";

        echo $logMessage;
    }

    public static function warning(string $message, ?string $className = null): void
    {
        $logMessage = "\e[1;33m";
        $logMessage .= str_replace(
            ['%Date%', '%Module%', '%Status%', '%Message%'],
            [(new \DateTime)->format('c'), (is_null($className) ? 'Update' : $className), 'Warning', $message],
            self::format
        );
        $logMessage .= "\e[0m";

        echo $logMessage;
    }

    public static function error(string $message, ?string $className = null): void
    {
        $logMessage = "\e[0;31m";
        $logMessage .= str_replace(
            ['%Date%', '%Module%', '%Status%', '%Message%'],
            [(new \DateTime)->format('c'), (is_null($className) ? 'Update' : $className), 'Error', $message],
            self::format
        );
        $logMessage .= "\e[0m";

        echo $logMessage;
    }

    public static function change(Domain $domain, ?DnsService $dnsModule, IPv4|IPv6|null $oldIp, IPv4|IPv6 $newIp): void
    {
        $oldIp = !is_null($oldIp) ? $oldIp->getAddress() : '';
        $newIp = $newIp->getAddress();

        $logMessage = "\e[0;36m";
        $logMessage .= str_replace(
            ['%Date%', '%Module%', '%Status%', '%Message%'],
            [(new \DateTime)->format('c'), $dnsModule::class, 'Change', ('DOMAIN "' . $domain->getDomainname() . '": ' . $oldIp . ' ==> ' . $newIp)],
            self::format
        );
        $logMessage .= "\e[0m";

        echo $logMessage;
    }

    public static function success(string $message, ?string $className = null): void
    {
        $logMessage = "\e[0;32m";
        $logMessage .= str_replace(
            ['%Date%', '%Module%', '%Status%', '%Message%'],
            [(new \DateTime)->format('c'), (is_null($className) ? 'Update' : $className), 'Success', $message],
            self::format
        );
        $logMessage .= "\e[0m";

        echo $logMessage;
    }
}