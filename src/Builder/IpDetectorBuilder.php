<?php

namespace Src\Builder;

use Modules\IpDetector\MikrotikDetector;
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
    private ?string $ipv6Nic = null;
    private string $username = '';
    private string $password = '';

    public function setConfigPrefix(?int $prefix): self
    {
        $this->configPrefix = $prefix;
        return $this;
    }

    public function setRouterAddress(string $routerAddress): self
    {
        $this->routerAddress = $routerAddress;
        return $this;
    }

    public function setNic(string $nic): self
    {
        $this->nic = $nic;
        return $this;
    }

    public function setIpv6Nic(?string $ipv6Nic): self
    {
        $this->ipv6Nic = $ipv6Nic;
        return $this;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
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
            MikrotikDetector::NAME => new MikrotikDetector($this->configPrefix, $this->routerAddress, $this->username, $this->password, $this->nic, $this->ipv6Nic),
            default => throw new ConfigException('IpDetector Module not found!'),
        };
    }
}
