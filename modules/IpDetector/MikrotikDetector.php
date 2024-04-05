<?php

namespace Modules\IpDetector;

use Src\Curl\HttpClient;
use Src\Exception\BuildIPv6AddressException;
use Src\Exception\HttpClientException;
use Src\Exception\IpNotFoundException;
use Src\Exception\NicNotFoundException;
use Src\Network\IPv4;
use Src\Network\IPv6;
use Src\Network\IPv6Type;

class MikrotikDetector extends IpDetector
{
    const NAME = 'mikrotik';

    public function __construct(
        ?int $configPrefixLength,
        private readonly string $routerAddress,
        private readonly string $username,
        private readonly string $password,
        private readonly string $wanNic,
        private readonly ?string $dmzNic = null
    ) {
        parent::__construct($configPrefixLength);
    }

    /**
     * @throws NicNotFoundException
     * @throws HttpClientException
     */
    public function getWanIPv4(): IPv4
    {
        $wanNicData = $this->getNicData($this->wanNic);

        return $this->createIPv4Builder()
            ->setAddress(explode('/', $wanNicData['address'])[0])
            ->setPrefix(explode('/', $wanNicData['address'])[1])
            ->build();
    }

    /**
     * @throws NicNotFoundException
     * @throws IpNotFoundException
     * @throws HttpClientException
     */
    public function getWanIPv6(): IPv6
    {
        $wanNicData = $this->getNicData($this->wanNic, true);
        $dmzNicData = $this->getNicData($this->dmzNic ?? $this->wanNic, true);

        return $this->createIPv6Builder()
            ->setAddress(explode('/', $wanNicData['address'])[0])
            ->setNetworkPrefix(explode('/', $dmzNicData['address'])[0])
            ->setNetworkPrefixLength(explode('/', $dmzNicData['address'])[1])
            ->setType(IPv6Type::GlobalUnicast)
            ->build();
    }

    /**
     * @throws NicNotFoundException
     * @throws IpNotFoundException
     * @throws HttpClientException
     */
    private function getNicData(string $nic, bool $ipv6 = false): array
    {

        $client = new HttpClient('http://' . $this->routerAddress . '/rest/' . ($ipv6 ? 'ipv6' : 'ip') . '/address?interface=' . $nic);
        $client->setHttpBasicAuthentication($this->username, $this->password);


        $response = $client->getRequest(true);
        if (count($response) < 1) {
            throw new NicNotFoundException('No NIC found with Name "' . $nic . '" at Router "' . $this->routerAddress . '"');
        }

        if ($ipv6) {
            $nicData = null;
            foreach ($response as $ipAdressData) {
                if (IPv6Type::getIPv6Type($ipAdressData['address']) === IPv6Type::GlobalUnicast) {
                    $nicData = $ipAdressData;
                    break;
                }
            }
        } else {
            $nicData = $response[0];
        }

        if ($nicData === null) {
            throw new IpNotFoundException('No Global IPv6 Adresse found for NIC "' . $nic . '" at Router "' . $this->routerAddress . '"');
        }

        return $nicData;
    }
}