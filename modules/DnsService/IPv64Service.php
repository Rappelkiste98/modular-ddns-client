<?php

namespace Modules\DnsService;

use Acme\Curl\HttpClient;
use Acme\Log;
use Acme\Network\Domain;
use Acme\Network\DomainDto;
use Acme\Network\DomainRecord;

class IPv64Service implements DnsService
{
    const NAME = 'IPv64';
    const API_URL = 'https://ipv64.net/nic/update';
    const UPDATE_URL = 'https://ipv64.net/api';

    private bool $updateNetworkPrefix;
    private string $updateUrl;
    private string $updateKey;
    private string $apiUrl;
    private string $apiKey;
    private array $apiDomains = [];

    public function __construct(?string $updateUrl, string $updateKey, ?string $apiUrl, string $apiKey, bool $updatePrefix = false)
    {
        $this->updateKey = $updateKey;
        $this->apiKey = $apiKey;
        $this->updateNetworkPrefix = $updatePrefix;

        $this->updateUrl = $updateUrl ?? self::UPDATE_URL;
        $this->apiUrl = $apiUrl ?? self::API_URL;
    }

    public function getAccountInfo(): array
    {
        $client = new HttpClient($this->apiUrl . '?get_account_info');
        $client->setHttpBearerToken($this->apiKey);

        try {
            return $client->getRequest(true);
        } catch (\Exception $e) {
            Log::Error($e->getMessage(), $this);
            return [];
        }
    }

    public function getLogs(): array
    {
        $client = new HttpClient($this->apiUrl . '?get_logs');
        $client->setHttpBearerToken($this->apiKey);

        try {
            return $client->getRequest(true)['logs'];
        } catch (\Exception $e) {
            Log::Error($e->getMessage(), $this);
            return [];
        }
    }

    public function getDomainInformation(Domain $domain): array
    {
        if(count($this->apiDomains) == 0) {
            try {
                $client = new HttpClient($this->apiUrl . '?get_domains');
                $client->setHttpBearerToken($this->apiKey);

                $this->apiDomains = $client->getRequest(true)['subdomains'];
            } catch (\Exception $e) {
                Log::Error($e->getMessage(), $this);
            }
        }

        return $this->apiDomains[$domain->getDomainname()] ?? [];
    }

    public function setDomainInformation(DomainRecord $domainRecord): bool
    {
        $url = HttpClient::addUrlParams($this->updateUrl,[
            'key' => $this->updateKey,
            'domain' => $domainRecord->getDomainname(),
            'output' => 'full'
        ]);

        if($domainRecord->getDomain()->getSubDomain() != '@') {
            $url = HttpClient::setUrlParam($url, 'prefix', $domainRecord->getDomain()->getSubDomain());
        }

        if(USE_IPv4 && !is_null($domainRecord->getIpv4())) {
            $url = HttpClient::setUrlParam($url, 'ip', $domainRecord->getIpv4()->getAddress());
        }

        if(USE_IPv6 && !is_null($domainRecord->getIpv6())) {
            $url = HttpClient::setUrlParam($url, 'ip6', $domainRecord->getIpv6()->getAddress());
        }

        if(USE_IPv6 && $this->updateNetworkPrefix && !is_null($domainRecord->getNetworkIpv6())) {
            $url = HttpClient::setUrlParam($url, 'ip6lanprefix', $domainRecord->getNetworkIpv6()->getAddress() . '/' . $domainRecord->getNetworkIpv6()->getNetworkPrefixLength());
        }

        $client = new HttpClient($url);

        try {
            $response = $client->getRequest(true);

            return $response['status'] === 'success';
        } catch (\Exception $e) {
            Log::Error($e->getMessage(), $this::class);
            return false;
        }
    }
}