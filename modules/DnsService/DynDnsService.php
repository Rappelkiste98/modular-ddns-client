<?php

namespace Modules\DnsService;

use Src\Curl\HttpClient;
use Src\Entities\DnsRecord;
use Src\Entities\DomainZone;
use Src\Exception\DnsServiceException;
use Src\Logger;
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

    public function getDomainZone(Domain $domain): ?DomainZone
    {
        return $this->domainZones[$domain->getDomainname()] ?? null;
    }

    /**
     * Update DnsRecord Data and add to PushQuery
     */
    public function updateDnsRecord(DnsRecord $record): void
    {
        $domain = (new Domain())->setDomain($record->getDomain());
        $zone = $this->getDomainZone($domain) ?? (new DomainZone)->setDomain($domain);

        $record->setLastUpdate(new \DateTime('now'));
        $record->setUpdate();
        $zone->addRecord($record);

        $this->domainZones[] = $zone;
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
                    } else {
                        $recordIpv6 = $record;
                        $recordIpv4 = $this->findDnsRecordByDomainameAndIpClass($record->getDnsRecordname(), IPv4::class);
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

        if(USE_IPv4 && !is_null($recordIpv4->getIp())) {
            $url = HttpClient::addUrlParams($url, ['ip' => $recordIpv4->getIp()->getAddress()]);
        }

        if (USE_IPv6 && !is_null($recordIpv6->getIp())) {
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
}