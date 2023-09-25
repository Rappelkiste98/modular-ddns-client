<?php

namespace Acme\Builder;

use Acme\Entities\DomainZone;
use Acme\Exception\ConfigException;
use Modules\DnsService\DnsService;
use Modules\DnsService\DynDnsService;
use Modules\DnsService\IPv64Service;
use Modules\DnsService\NetcupService;

class DnsServiceBuilder
{
    /** @var DomainZone[] $dnsZones */
    private array $dnsZones= [];
    private ?string $updateUrl = null;
    private ?string $updateKey = null;
    private ?string $apiUrl = null;
    private ?string $apiKey = null;
    private ?string $username = null;
    private ?string $password = null;
    private bool $updatePrefix = false;

    public function setUpdateUrl(?string $updateUrl): self
    {
        $this->updateUrl = $updateUrl;

        return $this;
    }

    public function setUpdateKey(?string $updateKey): self
    {
        $this->updateKey = $updateKey;

        return $this;
    }

    public function setApiUrl(?string $apiUrl): self
    {
        $this->apiUrl = $apiUrl;

        return $this;
    }

    public function setApiKey(?string $apiKey): self
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    public function setUsername(?string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function setUpdatePrefix(bool $updatePrefix): self
    {
        $this->updatePrefix = $updatePrefix;

        return $this;
    }

    public function addDnsZone(DomainZone $zone){
        $this->dnsZones[] = $zone;
    }

    /**
     * @throws ConfigException
     */
    public function build($dnsService): DnsService
    {
        return match ($dnsService) {
            DynDnsService::NAME => new DynDnsService(
                $this->updateUrl,
                $this->updateKey
            ),
            IPv64Service::NAME => new IPv64Service(
                $this->updateUrl,
                $this->updateKey,
                $this->apiUrl,
                $this->apiKey,
                $this->updatePrefix
            ),
            NetcupService::NAME => new NetcupService(
                $this->apiUrl,
                $this->username,
                $this->password,
                $this->apiKey
            ),
            default => throw new ConfigException('DnsService Module not found!'),
        };
    }
}