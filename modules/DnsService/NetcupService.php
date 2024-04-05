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

class NetcupService extends DnsService
{
    final const NAME = 'Netcup';
    private const API_URL = 'https://ccp.netcup.net/run/webservice/servers/endpoint.php?JSON';

    private string $apiUrl;
    private ?string $customerNr;
    private ?string $apiPassword;
    private ?string $apiKey;
    private ?string $apiSession = null;
    private bool $initialized = false;

    public function __construct(?string $apiURL, ?string $customerNr, ?string $apiPassword, ?string $apiKey)
    {
        $this->customerNr = $customerNr;
        $this->apiPassword = $apiPassword;
        $this->apiKey = $apiKey;

        $this->apiUrl = $apiURL ?? self::API_URL;

        try {
            $this->login();
            $this->initialized = true;
        } catch (\Exception $e) {
        }
    }

    public function __destruct()
    {
        try {
            $this->logout();
        } catch (DnsServiceException | HttpClientException $e) {
        }
    }

    /**
     * @throws DnsServiceException
     * @throws HttpClientException
     */
    public function getDomainZone(Domain $domain): DomainZone|null
    {
        if (!isset($this->domainZones[$domain->getDomainname()])) {
            $this->domainZones[$domain->getDomainname()] = $this->fetchInfoDnsZone($domain);
        }

        return $this->domainZones[$domain->getDomainname()] ?? null;
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
     *
     * @throws HttpClientException
     * @throws DnsServiceException
     */
    public function push(): void
    {
        foreach ($this->getDomainZones() as $zone) {
            $zone->setTtl(300);
            $isPushNeeded = false;

            foreach ($zone->getRecords() as $record) {
                if ($record->isUpdate() || $record->isCreate() || $record->isDelete()) {
                    $isPushNeeded = true;
                }
            }

            if ($zone->getTtl() !== 300) {
                $this->pushUpdateDnsZone($zone);
            }

            if ($isPushNeeded) {
                $this->pushUpdateDnsRecords($zone);
            }
        }
    }

    // ------------------------------------------ Custom Service Methods ------------------------------------------

    /**
     * @throws HttpClientException
     */
    private function login(): void
    {
        $request = [
            'action' => 'login',
            'param' => [
                'customernumber' => $this->customerNr,
                'apikey' => $this->apiKey,
                'apipassword' => $this->apiPassword
            ]
        ];

        $client = new HttpClient($this->apiUrl);

        try {
            $response = $client->postRequest($request, HttpClient::CONTENT_TYPE_JSON, true);

            if ($response['status'] === 'success') {
                LOGGER->debug('Netcup API Login successfully', $this::class);
                $this->apiSession = $response['responsedata']['apisessionid'];
                return;
            } else if ($response['statuscode'] === 4013) {
                $message = $response['longmessage'] . ' [ADDITIONAL INFORMATION: This error from the netcup DNS API also often indicates that you have supplied wrong API credentials. Please check them in the config file.]';
                LOGGER->error($message, $this::class);
            } else {
                LOGGER->error($response['longmessage'], $this::class);
            }
        } catch (HttpClientException $e) {
            LOGGER->error($e->getMessage(), $this::class);
            throw $e;
        }
    }

    /**
     * @throws HttpClientException
     * @throws DnsServiceException
     */
    private function logout(): void
    {
        if (!$this->initialized || $this->apiSession === null) {
            throw new DnsServiceException('Netcup Service failed initialization');
        }

        $request = [
            'action' => 'logout',
            'param' => [
                'customernumber' => $this->customerNr,
                'apikey' => $this->apiKey,
                'apisessionid' => $this->apiSession
            ]
        ];

        $client = new HttpClient($this->apiUrl);
        try {
            $response = $client->postRequest($request, HttpClient::CONTENT_TYPE_JSON, true);

            if ($response['status'] === 'success') {
                LOGGER->debug('Netcup API Logout successfully', $this::class);
            } else {
                LOGGER->error($response['longmessage'], $this::class);
            }
        } catch (HttpClientException $e) {
            LOGGER->warning($e->getMessage(), $this::class);
            throw $e;
        }
    }

    /**
     * @throws HttpClientException
     * @throws DnsServiceException
     */
    public function fetchInfoDnsZone(Domain $domain): ?DomainZone
    {
        if (!$this->initialized || $this->apiSession === null) {
            throw new DnsServiceException('Netcup Service failed initialization');
        }

        $request = [
            'action' => 'infoDnsZone',
            'param' => [
                'domainname' => $domain->getDomain(),
                'customernumber' => $this->customerNr,
                'apikey' => $this->apiKey,
                'apisessionid' => $this->apiSession
            ]
        ];

        $client = new HttpClient($this->apiUrl);

        try {
            $response = $client->postRequest($request, HttpClient::CONTENT_TYPE_JSON, true);

            if ($response['status'] !== 'success') {
                throw new DnsServiceException('Fetch "InfoDnsRecords" Request not successfully', $response);
            }
        } catch (DnsServiceException $e) {
            LOGGER->error($e->getMessage() . ' => ' . $response['longmessage'], $this::class);
            return null;
        } catch (HttpClientException $e) {
            LOGGER->Error($e->getMessage(), $this::class);
            return null;
        }

        $domain = (new Domain)->setDomain($response['responsedata']['name']);

        $zone = (new DomainZone())->setDomain($domain)
            ->setTtl($response['responsedata']['ttl'])
            ->setRefresh($response['responsedata']['refresh'])
            ->setRawData($response['responsedata']);

        $zoneRecords = $this->fetchInfoDnsRecords($zone);
        $zone->setRecords($zoneRecords);

        return $zone;
    }

    /**
     * @return DnsRecord[]
     * @throws HttpClientException
     * @throws DnsServiceException
     */
    public function fetchInfoDnsRecords(DomainZone $zone): array
    {
        if (!$this->initialized || $this->apiSession === null) {
            throw new DnsServiceException('Netcup Service failed initialization');
        }

        $request = [
            'action' => 'infoDnsRecords',
            'param' => [
                'domainname' => $zone->getDomain()->getDomain(),
                'customernumber' => $this->customerNr,
                'apikey' => $this->apiKey,
                'apisessionid' => $this->apiSession
            ]
        ];

        $client = new HttpClient($this->apiUrl);

        try {
            $response = $client->postRequest($request, HttpClient::CONTENT_TYPE_JSON, true);

            if ($response['status'] !== 'success') {
                throw new DnsServiceException('Fetch "InfoDnsRecords" Request not successfully', $response);
            }
        } catch (DnsServiceException $e) {
            LOGGER->error($e->getMessage() . ' => ' . $response['longmessage'], $this::class);
            throw $e;
        } catch (HttpClientException $e) {
            LOGGER->Error($e->getMessage(), $this::class);
            throw $e;
        }

        $records = [];
        foreach ($response['responsedata']['dnsrecords'] as $recordData) {
            $newRecord = (new DnsRecord)
                ->setId($recordData['id'])
                ->setSubDomain($recordData['hostname'])
                ->setDomain($zone->getDomain()->getDomain())
                ->setDelete($recordData['deleterecord'])
                ->setRawData($recordData);

            try {
                $newRecord->setType(DnsType::from($recordData['type']));
            } catch (\ValueError $valueError) {
                LOGGER->error($valueError->getMessage(), $this::class);
            }

            switch ($newRecord->getType()) {
                case DnsType::A:
                    $newRecord->setIp(
                        (new IPv4Builder())->setAddress($recordData['destination'])->build()
                    );
                    break;
                case DnsType::AAAA:
                    $newRecord->setIp(
                        (new IPv6Builder())->setAddress($recordData['destination'])->build()
                    );
                    break;
                default:
                    break;
            }
            $records[] = $newRecord;
        }

        return $records;
    }

    /**
     * @throws DnsServiceException
     * @throws HttpClientException
     */
    public function pushUpdateDnsZone(DomainZone $zone): void
    {
        if (!$this->initialized || $this->apiSession === null) {
            throw new DnsServiceException('Netcup Service failed initialization');
        }

        $request = [
            'action' => 'updateDnsZone',
            'param' => [
                'domainname' => $zone->getDomain()->getDomain(),
                'customernumber' => $this->customerNr,
                'apikey' => $this->apiKey,
                'apisessionid' => $this->apiSession,
                'dnszone' => [
                    'name' => $zone->getDomain()->getDomain(),
                    'ttl' => $zone->getTtl(),
                    'refresh' => $zone->getRefresh(),
                    'retry' => $zone->getRawData()['retry'] ?? '',
                    'expire' => $zone->getRawData()['expire'] ?? '',
                    'dnssecstatus' => $zone->getRawData()['dnssecstatus'] ?? false,
                ],
            ]
        ];

        $client = new HttpClient($this->apiUrl);

        try {
            $response = $client->postRequest($request, HttpClient::CONTENT_TYPE_JSON, true);

            if ($response['status'] !== 'success') {
                throw new DnsServiceException('Push "updateDnsZone" Request not successfully', $response);
            }

            LOGGER->success('Zone Update for "' . $zone->getDomain()->getDomainname() . '" successfully pushed!', $this::class);
        } catch (DnsServiceException $e) {
            LOGGER->error($e->getMessage() . ' => ' . $response['longmessage'], $this::class);
            throw $e;
        } catch (HttpClientException $e) {
            LOGGER->Error($e->getMessage(), $this::class);
            throw $e;
        }
    }

    /**
     * @throws DnsServiceException
     * @throws HttpClientException
     */
    public function pushUpdateDnsRecords(DomainZone $zone): void
    {
        if (!$this->initialized || $this->apiSession === null) {
            throw new DnsServiceException('Netcup Service failed initialization');
        }

        $request = [
            'action' => 'updateDnsRecords',
            'param' => [
                'domainname' => $zone->getDomain()->getDomain(),
                'customernumber' => $this->customerNr,
                'apikey' => $this->apiKey,
                'apisessionid' => $this->apiSession,
                'dnsrecordset' => [
                    'dnsrecords' => [],
                ],
            ]
        ];

        foreach ($zone->getRecords() as $record) {
            $raw = [
                'id' => (string) $record->getId(),
                'hostname' => $record->getSubDomain(),
                'type' => $record->getType()->value ?? $record->getRawData()['type'],
                'priority' => $record->getRawData()['priority'] ?? '0',
                'destination' => $record->getIp()?->getAddress() ?? $record->getRawData()['destination'] ?? '',
                'deleterecord' => $record->isDelete(),
                'state' => $record->getRawData()['state'] ?? 'yes',
            ];

            $request['param']['dnsrecordset']['dnsrecords'][] = $raw;
        }

        $client = new HttpClient($this->apiUrl);

        try {
            $response = $client->postRequest($request, HttpClient::CONTENT_TYPE_JSON, true);

            if ($response['status'] !== 'success') {
                throw new DnsServiceException('Push "updateDnsRecords" Request not successfully', $response);
            }

            LOGGER->success('Records Update for "' . $zone->getDomain()->getDomainname() . '" successfully pushed!', $this::class);
        } catch (DnsServiceException $e) {
            LOGGER->error($e->getMessage() . ' => ' . $response['longmessage'], $this::class);
            throw $e;
        } catch (HttpClientException $e) {
            LOGGER->Error($e->getMessage(), $this::class);
            throw $e;
        }
    }
}