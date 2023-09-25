<?php

namespace Src\Entities;

use Src\Network\DnsType;
use Src\Network\Domain;
use Src\Network\IPv4;
use Src\Network\IPv6;

class DnsRecord extends BaseEntity
{
    private int $id;
    private string $subDomain;
    private string $domain;
    private IPv4|IPv6|null $ip = null;
    private ?DnsType $type = null;
    private ?\DateTime $lastUpdate = null;
    private int $ttl = 300;

    public function getDnsRecordname(): string
    {
        return $this->subDomain . '.' . $this->domain;
    }

    public function buildDomain(): Domain
    {
        $domain = new Domain();
        $domain->setDomain($this->domain)
            ->setSubDomain(match ($this->subDomain) {
            '@' => '',
            '*' => chr(rand(97, 122)),
            default => $this->subDomain
        });

        return $domain;
    }

    public function getId(): int
    {
        return $this->id ?? 0;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getSubDomain(): string
    {
        return $this->subDomain;
    }

    public function setSubDomain(string $subDomain): self
    {
        $this->subDomain = $subDomain;
        return $this;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): self
    {
        $this->domain = $domain;
        return $this;
    }

    public function getIp(): IPv4|IPv6|null
    {
        return $this->ip;
    }

    public function setIp(IPv4|IPv6|null $ip): self
    {
        $this->ip = $ip;
        return $this;
    }

    public function getType(): ?DnsType
    {
        return $this->type;
    }

    public function setType(?DnsType $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }

    public function setTtl(int $ttl): self
    {
        $this->ttl = $ttl;
        return $this;
    }

    public function getLastUpdate(): ?\DateTime
    {
        return $this->lastUpdate;
    }

    public function setLastUpdate(?\DateTime $lastUpdate): self
    {
        $this->lastUpdate = $lastUpdate;
        return $this;
    }
}