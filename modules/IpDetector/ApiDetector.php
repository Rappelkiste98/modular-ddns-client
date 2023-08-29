<?php

namespace Modules\IpDetector;

use Acme\Curl\HttpClient;
use Acme\Exception\BuildIPv6AddressException;
use Acme\Exception\HttpClientException;
use Acme\Exception\IpNotFoundException;
use Acme\Log;
use Acme\Network\IPv4;
use Acme\Network\IPv6;
use Acme\Network\IPv6Type;

class ApiDetector extends IpDetector
{
    const NAME = 'api';
    const apisV4 = ['https://api.ipify.org', 'https://ip4.seeip.org'];
    const apisV6 = ['https://v6.ident.me/', 'https://ip6.seeip.org'];

    /**
     * @throws IpNotFoundException
     */
    private function fetchIpRemote(array $apis): IPv4|IPv6
    {
        foreach ($apis as $api) {
            $client = new HttpClient($api);

            try {
                $response = $client->getRequest();

                if(str_contains($response, ':')) {
                    $ipv6Builder = $this->createIPv6Builder()
                        ->setAddress($response)
                        ->setType(IPv6Type::GlobalUnicast);

                    if ($this->configPrefixLength != null) {
                        try {
                            return $ipv6Builder->setNetworkPrefixLength($this->configPrefixLength)
                                ->buildByAddressAndPrefix();
                        } catch (BuildIPv6AddressException $e) {
                            Log::error('Error while building IPv6 with Address & Network Prefix-Length! Continue without Network-Prefix', $this::class);
                        }
                    }

                    return $ipv6Builder->build();
                } else {
                    return $this->createIPv4Builder()
                        ->setAddress($response)
                        ->build();
                }
            } catch (HttpClientException $e) {
                Log::error('Error while fetching External IP Address', $this::class);
            }
        }

        throw new IpNotFoundException('No External IP Address found!');
    }

    /**
     * @throws IpNotFoundException
     */
    public function getExternalNetworkIPv4(): IPv4
    {
        return $this->fetchIpRemote(self::apisV4);
    }

    /**
     * @throws IpNotFoundException
     */
    public function getExternalIPv6(): IPv6
    {
        return $this->fetchIpRemote(self::apisV6);
    }
}