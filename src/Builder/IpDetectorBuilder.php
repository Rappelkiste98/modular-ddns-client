<?php

namespace Src\Builder;

use Src\Exception\ConfigException;
use Modules\IpDetector\ApiDetector;
use Modules\IpDetector\AvmDetector;
use Modules\IpDetector\GenericDetector;
use Modules\IpDetector\IpDetector;
use SoapFault;

class IpDetectorBuilder
{
    private ?int $configPrefix = null;
    private string $routerAddress = '';
    private string $nic = '';

    public function setConfigPrefix(int $prefix): self
    {
        $this->configPrefix = $prefix;

        return $this;
    }

    public function setRouterAddress(string $routerAddress): self
    {
        $this->routerAddress = $routerAddress;

        return $this;
    }

    public function setNIC(string $nic): self
    {
        $this->nic = $nic;

        return $this;
    }

    /**
     * @throws SoapFault
     * @throws ConfigException
     */
    public function build(string $ipDetector): IpDetector
    {
        return match ($ipDetector) {
            ApiDetector::NAME => new ApiDetector($this->configPrefix),
            AvmDetector::NAME => new AvmDetector($this->configPrefix, $this->routerAddress),
            GenericDetector::NAME => new GenericDetector($this->configPrefix, $this->nic),
            default => throw new ConfigException('IpDetector Module not found!'),
        };
    }
}