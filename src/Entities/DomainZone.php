<?php

namespace Acme\Entities;

use Acme\Network\Domain;

class DomainZone extends BaseEntity
{
    private Domain $domain;
    private int $refresh;

    /** @var DnsRecord[] $records */
    private array $records = [];
    private int $ttl;

    public function getDomain(): Domain
    {
        return $this->domain;
    }

    public function setDomain(Domain $domain): self
    {
        $this->domain = $domain;
        return $this;
    }

    public function getRefresh(): int
    {
        return $this->refresh;
    }

    public function setRefresh(int $refresh): self
    {
        $this->refresh = $refresh;
        return $this;
    }

    /**
     * @return DnsRecord[]
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    /**
     * @param DnsRecord[] $records
     */
    public function setRecords(array $records): self
    {
        $this->records = $records;
        return $this;
    }

    public function addRecord(DnsRecord $record): self
    {
        $this->records[] = $record;
        return $this;
    }

    public function removeRecord(DnsRecord $record): self
    {
        $this->records = array_diff($this->records, [$record]);
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
}