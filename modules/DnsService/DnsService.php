<?php

namespace Modules\DnsService;

use Src\Entities\DnsRecord;
use Src\Entities\DomainZone;
use Src\Exception\DnsServiceException;
use Src\Exception\RecordAnomalyException;
use Src\Network\DnsType;
use Src\Network\Domain;


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

    /**
     * @throws RecordAnomalyException
     */
    protected function findDnsRecord(DnsRecord $record): ?DnsRecord
    {
        foreach ($this->domainZones as $zone) {
            $findRecords = array_filter($zone->getRecords(), fn($zoneRecord) => $zoneRecord->getDnsRecordname() === $record->getDnsRecordname()
                && $zoneRecord->getType() === $record->getType()
                && $zoneRecord->getIp()::class === $record->getIp()::class );

            if (count($findRecords) > 1) {
                throw new RecordAnomalyException('Found ' . count($findRecords) . ' Records for "' . $record->getDnsRecordname() . '" (' . $record->getIp()::class . ')');
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