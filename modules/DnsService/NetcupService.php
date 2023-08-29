<?php

namespace Modules\DnsService;

use Acme\Curl\HttpClient;
use Acme\Log;
use Acme\Network\Domain;
use Acme\Network\DomainDto;
use Acme\Network\DomainRecord;

class NetcupService implements DnsService
{
    const NAME = 'Netcup';
    const API_URL = 'https://ccp.netcup.net/run/webservice/servers/endpoint.php?JSON';

    private string $apiUrl;

    private string $customerNr;
    private string $apiPassword;
    private string $apiKey;
    private string $apiSession = '';

    public function __construct(?string $apiURL, string $customerNr, string $apiPassword, string $apiKey)
    {
        $this->customerNr = $customerNr;
        $this->apiPassword = $apiPassword;
        $this->apiKey = $apiKey;

        $this->apiUrl = $apiURL ?? self::API_URL;
    }

    private function login(): bool
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
        $response = $client->postRequest($request, 'json', true);

        if($response['status'] === 'success') {
            Log::info('Netcup API Login successfully');
            $this->apiSession = $response['responsedata']['apisessionid'];
            return true;
        } else if($response['statuscode'] === 4013) {
            $message = $response['longmessage'] . ' [ADDITIONAL INFORMATION: This error from the netcup DNS API also often indicates that you have supplied wrong API credentials. Please check them in the config file.]';
            Log::error($message, $this);
        } else {
            Log::error($response['longmessage'], $this);
        }

        return false;
    }

    private function logout(): bool
    {
        $request = [
            'action' => 'logout',
            'param' => [
                'customernumber' => $this->customerNr,
                'apikey' => $this->apiKey,
                'apisessionid' => $this->apiSession
            ]
        ];

        $client = new HttpClient($this->apiUrl);
        $response = $client->postRequest($request, 'json', true);

        if($response['status'] === 'success') {
            Log::info('Netcup API Logout successfully');
            return true;
        } else {
            Log::error($response['longmessage'], $this);
        }

        return false;
    }

    private function getZoneInformation(Domain $domain):array
    {
        $request = [
            'action' => 'infoDnsZone',
            'param' => [
                'domainname' => $domain->getBase(),
                'customernumber' => $this->customerNr,
                'apikey' => $this->apiKey,
                'apisessionid' => $this->apiSession
            ]
        ];
    }

    public function getDomainInformation(Domain $domain): array
    {
        // TODO: Implement getDomainInformation() method.
    }

    public function setDomainInformation(DomainRecord $domainRecord): bool
    {
        $test1 = $this->login();
        $zone = $this->getZoneInformation($domainRecord->getDomain());
        $test2 = $this->logout();

        $test3 = "";
    }
}