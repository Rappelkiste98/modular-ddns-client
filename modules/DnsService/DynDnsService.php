<?php

namespace Modules\DnsService;

use Exception;
use Src\Curl\HttpClient;
use Src\Entities\DnsRecord;
use Src\Entities\DomainZone;
use Src\Exception\DnsServiceException;
use Src\Logger;
use Src\Network\DnsType;
use Src\Network\Domain;
use Src\Network\IPv4;
use Src\Network\IPv6;

class DynDnsService extends DnsService
{
    final const NAME = 'DynDNS';
    private ?string $updateUrl;
    private ?string $updateKey;

    public function __construct(?string $updateUrl, ?string $updateKey)
    {
        $this->updateUrl = $updateUrl;
        $this->updateKey = $updateKey;
    }

    /**
     * @throws Exception
     */
    public function getDomainZone(Domain $domain): ?DomainZone
    {
        if (count($this->domainZones) === 0) {
            $this->domainZones[] = $this->createDomainZoneByIpDetector($domain);
        }

        return $this->domainZones[$domain->getDomainname()] ?? null;
    }

    /**
     * Update DnsRecord Data and add to PushQuery
     * @throws Exception
     */
    public function updateDnsRecord(DnsRecord $record): void
    {
        $domain = (new Domain())->setDomain($record->getDomain())->setSubDomain($record->getSubDomain());

        $record->setLastUpdate(new \DateTime('now'));
        $zone = $this->getDomainZone($domain);
        $zoneRecord = $this->findDnsRecord($record);

        if ($zoneRecord === null) {
            $record->setCreate();
            $zone->addRecord($record);
        } else if ($zoneRecord->getIp()->getAddress() !== $record->getIp()->getAddress()) {
            $zoneRecord->setIp($record->getIp())
                ->setLastUpdate($record->getLastUpdate())
                ->setUpdate();
        }
    }

    /**
     * Pushes local Entity changes to Remote API
     */
    public function push(): void
    {
        foreach ($this->getDomainZones() as $zone) {
            foreach ($zone->getRecords() as $record) {
                if ($record->isUpdate() || $record->isCreate() || $record->isDelete()) {
                    if ($record->getIp() instanceof IPv4) {
                        $recordIpv4 = $record;
                        $recordIpv6 = $this->findDnsRecordByDomainameAndIpClass($record->getDnsRecordname(), IPv6::class);
                        $recordIpv6->setUpdate(false);
                        $recordIpv6->setCreate(false);
                    } else {
                        $recordIpv6 = $record;
                        $recordIpv4 = $this->findDnsRecordByDomainameAndIpClass($record->getDnsRecordname(), IPv4::class);
                        $recordIpv4->setUpdate(false);
                        $recordIpv4->setCreate(false);
                    }

                    $this->execDDnsUpdate($recordIpv4, $recordIpv6);
                }
            }
        }
    }

    // ------------------------------------------ Custom Service Methods ------------------------------------------

    public function execDDnsUpdate(?DnsRecord $recordIpv4, ?DnsRecord $recordIpv6): void
    {
        if ($recordIpv4 === null && $recordIpv6 === null) {
            return;
        }
        $baseRecord = $recordIpv4 ?? $recordIpv6;

        $url = HttpClient::addUrlParams($this->updateUrl, [
            'key' => $this->updateKey,
            'host' => $baseRecord->getDnsRecordname(),
        ]);

        if(USE_IPv4 && !is_null($recordIpv4?->getIp())) {
            $url = HttpClient::addUrlParams($url, ['ip' => $recordIpv4->getIp()->getAddress()]);
        }

        if (USE_IPv6 && !is_null($recordIpv6?->getIp())) {
            $url = HttpClient::addUrlParams($url, ['ip6' => $recordIpv6->getIp()->getAddress()]);
        }

        $client = new HttpClient($url);

        try {
            $response = $client->getRequest();

            if (!str_contains($response, 'Updated 1 hostname')) {
                throw new DnsServiceException('Set Record Request not succesfully', $response);
            }

            $recordIpv4?->setUpdate(false);
            $recordIpv6?->setUpdate(false);

            $recordIpv4 === null ? : CACHE?->cacheDnsRecord($recordIpv4);
            $recordIpv6 === null ? : CACHE?->cacheDnsRecord($recordIpv6);

            LOGGER->success('Record Update "' . $baseRecord->getDnsRecordname() . '" successfully pushed!', $this::class);
        } catch (\Exception $e) {
            LOGGER->error($e->getMessage(), $this::class);
        }
    }

    /**
     * @throws Exception
     */
    public function createDomainZoneByIpDetector(Domain $domain): DomainZone
    {
        $zone = (new DomainZone())->setDomain($domain);

        $ipv4 = LOCAL_IPv4 ?? IP_DETECTOR->getExternalNetworkIPv4();
        if (!$ipv4->validate()) {
            throw new Exception('Global-Network IPv4 is not valid!');
        }
        $zone->addRecord(
            (new DnsRecord)->setSubDomain($domain->getSubDomain())
            ->setDomain($domain->getDomain())
            ->setType(DnsType::A)
            ->setIp($ipv4)
        );

        $ipv6 = LOCAL_IPv6 ?? IP_DETECTOR->getExternalIPv6();
        if (!$ipv6->validate()) {
            throw new Exception('Global-Device IPv6 is not valid!');
        }
        $zone->addRecord(
            (new DnsRecord)->setSubDomain($domain->getSubDomain())
                ->setDomain($domain->getDomain())
                ->setType(DnsType::AAAA)
                ->setIp($ipv6)
        );

        return $zone;
    }
}