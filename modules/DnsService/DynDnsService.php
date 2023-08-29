<?php

namespace Modules\DnsService;

use Acme\Curl\HttpClient;
use Acme\Log;
use Acme\Module;
use Acme\Network\Domain;
use Acme\Network\DomainDto;
use Acme\Network\DomainRecord;

class DynDnsService implements DnsService
{
    const NAME = 'DynDNS';
    private string $updateUrl;
    private string $updateKey;

    public function __construct(string $updateUrl, string $updateKey)
    {
        $this->updateUrl = $updateUrl;
        $this->updateKey = $updateKey;
    }

    public function getDomainInformation(Domain $domain): array
    {
        return [];
    }

    public function setDomainInformation(DomainRecord $domainRecord): bool
    {
        $url = HttpClient::addUrlParams($this->updateUrl, [
            'key' => $this->updateKey,
            'host' => $domainRecord->getDomain()->getRecordDomainname()
        ]);

        if(USE_IPv4 && !is_null($domainRecord->getIpv4())) {
            $url = HttpClient::addUrlParams($url, ['ip' => $domainRecord->getIpv4()->getAddress()]);

        }

        if (USE_IPv6 && !is_null($domainRecord->getIpv6())) {
            $url = HttpClient::addUrlParams($url, ['ip6' => $domainRecord->getIpv6()->getAddress()]);
        }

        $client = new HttpClient($url);

        try {
            $response = $client->getRequest();

            return str_contains($response, 'Updated 1 hostname');
        } catch (\Exception $e) {
            Log::Error($e->getMessage(), $this);
            return false;
        }
    }
}