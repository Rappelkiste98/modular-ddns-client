<?php

namespace Modules\IpDetector;

use Src\Exception\BuildIPv6AddressException;
use Src\Logger;
use Src\Network\IPv4;
use Src\Network\IPv6;
use Src\Network\IPv6Type;
use SoapClient;
use SoapFault;

class AvmDetector extends IpDetector
{
    const NAME = 'avm';
    private SoapClient $client;

    /**
     * @throws SoapFault
     */
    public function __construct(?int $configPrefixLength, string $fritzAddress)
    {
        parent::__construct($configPrefixLength);

        $this->client = new SoapClient(
            null,
            [
                'location' => "http://" . $fritzAddress . ":49000/igdupnp/control/WANIPConn1",
                'uri' => "urn:schemas-upnp-org:service:WANIPConnection:1",
                'noroot' => True
            ]
        );
    }

    public function getWanIPv4(): IPv4
    {
        $ip = $this->client->GetExternalIPAddress();

        return $this->createIPv4Builder()
            ->setAddress($ip)
            ->build();
    }

    public function getWanIPv6(): IPv6
    {
        $network = $this->client->X_AVM_DE_GetIPv6Prefix();
        $ip = $this->client->X_AVM_DE_GetExternalIPv6Address();

        $ipv6Builder = $this->createIPv6Builder()
            ->setAddress($ip['NewExternalIPv6Address'])
            ->setNetworkPrefix($network['NewIPv6Prefix'] ?? null)
            ->setNetworkPrefixLength($network['NewPrefixLength'] ?? $this->configPrefixLength)
            ->setType(IPv6Type::GlobalUnicast);

        if (!isset($network['NewPrefixLength']) && $this->configPrefixLength != null) {
            try {
                return $ipv6Builder->buildByAddressAndPrefix();
            } catch (BuildIPv6AddressException $e) {
                LOGGER->error('Error while building IPv6 with Address & Network Prefix-Length! Continue without Network-Prefix', $this::class);
                return $ipv6Builder->build();
            }
        } else {
            return $ipv6Builder->build();
        }
    }
}