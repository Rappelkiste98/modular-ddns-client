<?php

namespace Modules\DnsService;

use Acme\Entities\DnsRecord;
use Acme\Entities\DomainZone;
use Acme\Exception\DnsServiceException;
use Acme\Network\DnsType;
use Acme\Network\Domain;


abstract class DnsService
{
    public const NAME = '';

    /** @var DomainZone[] $domainZones */
    protected array $domainZones = [];

    abstract public function getDomainZone(Domain $domain): ?DomainZone;

    /**
     * Update DnsRecord Data and add to PushQuery
     */
    abstract public function updateDnsRecord(DnsRecord $record): void;

    /**
     * Pushes local Entity changes to Remote API
     */
    abstract public function push(): void;

    /**
     * @return DomainZone[]
     */
    public function getDomainZones(): array
    {
        return $this->domainZones;
    }

    protected function findDnsRecord(DnsRecord $record): ?DnsRecord
    {
        foreach ($this->domainZones as $zone) {
            foreach ($zone->getRecords() as $zoneRecord) {
                if ($zoneRecord->getDnsRecordname() === $record->getDnsRecordname() && $zoneRecord->getType() === $record->getType()) {
                    return $zoneRecord;
                }
            }
        }

        return null;
    }

    protected function findDnsRecordByDomainameAndIpClass(string $domainname, string $ipClass): ?DnsRecord
    {
        foreach ($this->domainZones as $zone) {
            foreach ($zone->getRecords() as $zoneRecord) {
                if ($zoneRecord->getDnsRecordname() === $domainname && $zoneRecord->getIp()::class === $ipClass) {
                    return $zoneRecord;
                }
            }
        }

        return null;
    }
}