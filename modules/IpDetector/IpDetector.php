<?php

namespace Modules\IpDetector;
use Acme\Builder\IPv4Builder;
use Acme\Builder\IPv6Builder;
use Acme\Network\IPv4;
use Acme\Network\IPv6;

abstract class IpDetector
{
    protected ?int $configPrefixLength;

    public function __construct($configPrefixLength = null)
    {
        $this->configPrefixLength = $configPrefixLength;
    }

    public abstract function getExternalNetworkIPv4(): IPv4;
    public abstract function getExternalIPv6(): IPv6;

    protected function createIPv4Builder(): IPv4Builder
    {
        return new IPv4Builder();
    }

    protected function createIPv6Builder(): IPv6Builder
    {
        return new IPv6Builder();
    }
}