<?php

namespace Modules\IpDetector;
use Src\Builder\IPv4Builder;
use Src\Builder\IPv6Builder;
use Src\Exception\IpNotFoundException;
use Src\Network\IPv4;
use Src\Network\IPv6;

abstract class IpDetector
{
    protected ?int $configPrefixLength;

    public function __construct($configPrefixLength = null)
    {
        $this->configPrefixLength = $configPrefixLength;
    }

    public abstract function getExternalNetworkIPv4(): IPv4;
    public abstract function getExternalIPv6(): IPv6;

    /**
     * @throws IpNotFoundException
     */
    public function getDeviceIPv4(): IPv4
    {
        throw new IpNotFoundException('Method not implemented in this Detector!');
    }

    protected function createIPv4Builder(): IPv4Builder
    {
        return new IPv4Builder();
    }

    protected function createIPv6Builder(): IPv6Builder
    {
        return new IPv6Builder();
    }
}