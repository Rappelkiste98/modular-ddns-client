<?php

namespace Modules\DnsService;

use Src\Builder\IPv4Builder;
use Src\Builder\IPv6Builder;
use Src\Curl\HttpClient;
use Src\Entities\DnsRecord;
use Src\Entities\DomainZone;
use Src\Exception\DnsServiceException;
use Src\Exception\HttpClientException;
use Src\Exception\RecordAnomalyException;
use Src\Logger;
use Src\Network\DnsType;
use Src\Network\Domain;
use Src\Network\IPv4;
use Src\Network\IPv6;

class IPv64Service extends DnsService
{
    final const NAME = 'IPv64';
    private const UPDATE_URL = 'https://ipv64.net/nic/update';
    private const API_URL = 'https://ipv64.net/api';

    private bool $updateNetworkPrefix;
    private string $updateUrl;
    private ?string $updateKey;
    private string $apiUrl;
    private ?string $apiKey;

    public function __construct(?string $updateUrl, ?string $updateKey, ?string $apiUrl, ?string $apiKey, bool $updatePrefix = false)
    {
        $this->updateKey = $updateKey;
        $this->apiKey = $apiKey;
        $this->updateNetworkPrefix = $updatePrefix;

        $this->updateUrl = $updateUrl ?? self::UPDATE_URL;
        $this->apiUrl = $apiUrl ?? self::API_URL;
    }

    /**
     * @throws HttpClientException
     * @throws DnsServiceException
     */
    public function getDomainZone(Domain $domain): ?DomainZone
    {
        if (count($this->domainZones) == 0) {
            $this->domainZones = $this->fetchGetDomains();
        }

        return $this->domainZones[$domain->getDomain()] ?? null;
    }

    /**
     * Update DnsRecord Data and add to PushQuery
     *
     * @throws HttpClientException
     * @throws DnsServiceException
     * @throws RecordAnomalyException
     */
    public function updateDnsRecord(DnsRecord $record): void
    {
        $domain = (new Domain())->setDomain($record->getDomain());
        $zone = $this->getDomainZone($domain);

        if ($zone === null) {
            throw new DnsServiceException('DomainZone "' . $domain->getDomain() . '" not found! Skip');
        }

        $record->setLastUpdate(new \DateTime('now'));
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
            if ($zone->isCreate()) {
                try {
                    $this->pushAddDomain($zone);
                } catch (HttpClientException|DnsServiceException $e) {
                    $zone->setRecords([]);
                }
            }

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

    public function execDDnsUpdate(?DnsRecord $recordIpv4 = null, ?DnsRecord $recordIpv6 = null): void
    {
        if ($recordIpv4 === null && $recordIpv6 === null) {
            return;
        }
        $baseRecord = $recordIpv4 ?? $recordIpv6;

        $url = HttpClient::addUrlParams($this->updateUrl,[
            'key' => $this->updateKey,
            'domain' => $baseRecord->getDomain(),
        ]);

        if($baseRecord->getSubDomain() != '@') {
            $url = HttpClient::setUrlParam($url, 'praefix', $baseRecord->getSubDomain());
        }

        if(USE_IPv4 && !is_null($recordIpv4?->getIp())) {
            $url = HttpClient::setUrlParam($url, 'ipv4', $recordIpv4->getIp()->getAddress());
        }

        if(USE_IPv6 && !is_null($recordIpv6?->getIp())) {
            $url = HttpClient::setUrlParam($url, 'ipv6', $recordIpv6->getIp()->getAddress());
        }

        if(USE_IPv6 && $this->updateNetworkPrefix && !is_null($recordIpv6->getIp()->getNetworkPrefix())) {
            $url = HttpClient::setUrlParam($url, 'ipv6prefix', $recordIpv6->getIp()->getNetworkPrefix() . '/' . $recordIpv6->getIp()->getNetworkPrefixLength());
        }

        $url = HttpClient::setUrlParam($url, 'output', 'full');

        $client = new HttpClient($url);
        $client->setDisableHttpCodeValidation();

        try {
            $response = $client->getRequest(true);

            if($response['status'] !== 'success') {
                throw new DnsServiceException('Update Record Request not successfully');
            }

            $recordIpv4?->setUpdate(false);
            $recordIpv6?->setUpdate(false);

            $recordIpv4 === null ? : CACHE?->cacheDnsRecord($recordIpv4);
            $recordIpv6 === null ? : CACHE?->cacheDnsRecord($recordIpv6);

            LOGGER->success('Record Update "' . $baseRecord->getDnsRecordname() . '" successfully pushed!', $this::class);
        } catch (\Exception $e) {
            LOGGER->Error($e->getMessage(), $this::class);
        }
    }

    public function execPushDDnsUpdate(?DnsRecord $recordIpv4 = null, ?DnsRecord $recordIpv6 = null): void
    {
        if ($recordIpv4 === null && $recordIpv6 === null) {
            return;
        }
        $baseRecord = $recordIpv4 ?? $recordIpv6;

        $request = [
            'domain' => $baseRecord->getDomain(),
        ];

        if($baseRecord->getSubDomain() != '@') {
            $request['praefix'] = $baseRecord->getSubDomain();
        }

        if(USE_IPv4 && !is_null($recordIpv4?->getIp())) {
            $request['ipv4'] = $recordIpv4->getIp()->getAddress();
        }

        if(USE_IPv6 && !is_null($recordIpv6?->getIp())) {
            $request['ipv6'] = $recordIpv6->getIp()->getAddress();
        }

        $client = new HttpClient($this->apiUrl);
        $client->setHttpBearerToken($this->updateKey);

        try {
            $response = $client->postRequest($request, HttpClient::CONTENT_TYPE_FORMDATA, true);

            if ($response['info'] !== 'success' || $client->getHttpCode() !== 201) {
                throw new DnsServiceException('Push Update Record Request not successfully', $response);
            }

            $recordIpv4?->setUpdate(false);
            $recordIpv6?->setUpdate(false);
            LOGGER->success('Record Update "' . $baseRecord->getDnsRecordname() . '" successfully pushed!', $this::class);
        } catch (\Exception $e) {
            LOGGER->Error($e->getMessage(), $this::class);
        }
    }

    public function fetchGetAccountInfo(): array
    {
        $client = new HttpClient($this->apiUrl . '?get_account_info');
        $client->setHttpBearerToken($this->apiKey);

        try {
            $response = $client->getRequest(true);

            if ($response['info'] !== 'success') {
                throw new DnsServiceException('Fetch "getAccountInfo" Request not successfully', $response);
            }

            return $response;
        } catch (\Exception $e) {
            LOGGER->Error($e->getMessage(), $this::class);
        }

        return [];
    }

    public function fetchGetLogs(): array
    {
        $client = new HttpClient($this->apiUrl . '?get_logs');
        $client->setHttpBearerToken($this->apiKey);

        try {
            $response = $client->getRequest(true);

            if ($response['info'] !== 'success') {
                throw new DnsServiceException('Fetch "getLogs" Request not successfully', $response);
            }

            return $response['logs'];
        } catch (\Exception $e) {
            LOGGER->Error($e->getMessage(), $this::class);
        }

        return [];
    }

    /**
     * @throws HttpClientException
     * @throws DnsServiceException
     * @return DomainZone[]
     */
    public function fetchGetDomains(): array
    {
        $client = new HttpClient($this->apiUrl . '?get_domains');
        $client->setHttpBearerToken($this->apiKey);

        try {
            $response = $client->getRequest(true);

            if ($response['info'] !== 'success') {
                throw new DnsServiceException('Fetch "getDomains" Request not successfully', $response);
            }
        } catch (HttpClientException | DnsServiceException $e) {
            LOGGER->Error($e->getMessage(), $this::class);
            throw $e;
        }

        $zones = [];
        foreach ($response['subdomains'] as $domainName => $zoneData) {
            $domain = new Domain();
            $domain->setDomain($domainName);

            $newZone = new DomainZone();
            $newZone->setDomain($domain)
                ->setRefresh($zoneData['updates'])
                ->setRawData($zoneData);

            foreach ($zoneData['records'] as $recordData) {
                $record = new DnsRecord();
                $record->setDomain($domainName)
                    ->setSubDomain($recordData['praefix'] === '' ? '@' : $recordData['praefix'])
                    ->setType(DnsType::from($recordData['type']))
                    ->setLastUpdate(\DateTime::createFromFormat('Y-m-d H:i:s', $recordData['last_update']))
                    ->setRawData($recordData);

                switch ($record->getType()) {
                    case DnsType::A:
                        $record->setIp(
                            (new IPv4Builder)
                                ->setAddress($recordData['content'])
                                ->build()
                        );
                        break;
                    case DnsType::AAAA:
                        $record->setIp(
                            (new IPv6Builder)
                                ->setAddress($recordData['content'])
                                ->build()
                        );
                        break;
                    default:
                        break;
                }

                $newZone->addRecord($record);
            }

            $zones[$domainName] = $newZone;
        }

        return $zones;
    }

    /**
     * @throws DnsServiceException
     * @throws HttpClientException
     */
    public function pushAddDomain(DomainZone $zone): void
    {
        $client = new HttpClient($this->apiUrl);
        $client->setHttpBearerToken($this->apiKey);

        $request = ['add_domain' => $zone->getDomain()->getDomainname()];

        try {
            $response = $client->postRequest($request, HttpClient::CONTENT_TYPE_FORMDATA, true);

            if ($response['info'] !== 'success' || $client->getHttpCode() !== 201) {
                throw new DnsServiceException('Push "addDomain" Request not successfully', $response);
            }
        } catch (\Exception $e) {
            LOGGER->Error($e->getMessage(), $this::class);
            throw $e;
        }
    }

    /**
     * @throws DnsServiceException
     * @throws HttpClientException
     */
    public function pushDeleteDomain(Domain $domain): void
    {
        $client = new HttpClient($this->apiUrl);
        $client->setHttpBearerToken($this->apiKey);

        $request = ['del_domain' => $domain->getDomainname()];

        try {
            $response = $client->deleteRequest($request, HttpClient::CONTENT_TYPE_WWWFORM, true);

            if ($response['info'] !== 'success' || $client->getHttpCode() !== 202) {
                throw new DnsServiceException('Push "deleteDomain" Request not successfully', $response);
            }
        } catch (\Exception $e) {
            LOGGER->Error($e->getMessage(), $this::class);
            throw $e;
        }
    }

    /**
     * @throws DnsServiceException
     * @throws HttpClientException
     */
    public function pushAddRecord(DnsRecord $record): void
    {
        $client = new HttpClient($this->apiUrl);
        $client->setHttpBearerToken($this->apiKey);

        $request = [
            'add_record' => $record->getDnsRecordname(),
            'praefix' => $record->getSubDomain(),
            'type' => $record->getType()->value,
            'content' => $record->getIp()->getAddress(),
        ];

        try {
            $response = $client->postRequest($request, HttpClient::CONTENT_TYPE_FORMDATA, true);

            if ($response['info'] !== 'success' || $client->getHttpCode() !== 201) {
                throw new DnsServiceException('Push "addRecord" Request not successfully', $response);
            }
        } catch (\Exception $e) {
            LOGGER->Error($e->getMessage(), $this::class);
            throw $e;
        }
    }

    /**
     * @throws DnsServiceException
     * @throws HttpClientException
     */
    public function pushDeleteRecord(DnsRecord $record): void
    {
        $client = new HttpClient($this->apiUrl);
        $client->setHttpBearerToken($this->apiKey);

        $request = [
            'del_record' => $record->getDnsRecordname(),
            'praefix' => $record->getSubDomain(),
            'type' => $record->getType()->value,
            'content' => $record->getIp()->getAddress(),
        ];

        try {
            $response = $client->deleteRequest($request, HttpClient::CONTENT_TYPE_WWWFORM, true);

            if ($response['info'] !== 'success' || $client->getHttpCode() !== 202) {
                throw new DnsServiceException('Push "deleteRecord" Request not successfully', $response);
            }
        } catch (\Exception $e) {
            LOGGER->Error($e->getMessage(), $this::class);
            throw $e;
        }
    }
}