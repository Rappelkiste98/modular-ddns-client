<?php

namespace Modules\IpDetector;

use Src\Builder\IPv4Builder;
use Src\Builder\IPv6Builder;
use Src\Exception\BuildIPv6AddressException;
use Src\Exception\ConfigException;
use Src\Exception\IpNotFoundException;
use Src\Logger;
use Src\Network\IPv4;
use Src\Network\IPv6;
use Src\Network\IPv6Type;

class GenericDetector extends IpDetector
{
    const NAME = 'generic';
    private ApiDetector $apiDetector;

    /**
     * @throws ConfigException
     */
    public function __construct(
        $configPrefixLength = null,
        private readonly string $nic = ''
    ) {
        if ($nic === '') {
            throw new ConfigException('No NIC configured for GenericDetector!');
        }

        $this->apiDetector = new ApiDetector($configPrefixLength);
        parent::__construct($configPrefixLength);
    }

    /**
     * @throws IpNotFoundException
     */
    public function getWanIPv4(): IPv4
    {
        return $this->apiDetector->getWanIPv4();
    }

    /**
     * @throws IpNotFoundException
     */
    public function getLanIPv4(): IPv4
    {
        $netInterfaces = net_get_interfaces();

        if (!$netInterfaces) {
            throw new IpNotFoundException('No Network-Interfaces found!');
        }

        // Compability for Windows and Linux
        $nicInterfaces = [];
        foreach ($netInterfaces as $key => $netInterface) {
            $nicInterfaces[$netInterface['mac'] ?? $key] = $netInterface;
        }

        if (!array_key_exists($this->nic, $nicInterfaces)) {
            throw new IpNotFoundException('No Network-Interface found with NIC: ' . $this->nic);
        }

        $nicInterface = $nicInterfaces[$this->nic];
        foreach ($nicInterface['unicast'] as $unicast) {
            $address = $unicast['address'] ?? null;

            if ($address !== null && str_contains($address, '.')) {
                return $this->createIPv4Builder()
                    ->setAddress($address)
                    ->setSubnetMask($unicast['netmask'])
                    ->build();
            }
        }

        throw new IpNotFoundException('No Global IPv4 Address found!');
    }

    /**
     * @throws IpNotFoundException
     */
    public function getWanIPv6(): IPv6
    {
        $netInterfaces = net_get_interfaces();

        if (!$netInterfaces) {
            throw new IpNotFoundException('No Network-Interfaces found!');
        }

        // Compability for Windows and Linux
        $nicInterfaces = [];
        foreach ($netInterfaces as $key => $netInterface) {
            $nicInterfaces[$netInterface['mac'] ?? $key] = $netInterface;
        }

        if (!array_key_exists($this->nic, $nicInterfaces)) {
            throw new IpNotFoundException('No Network-Interface found with NIC: ' . $this->nic);
        }

        $nicInterface = $nicInterfaces[$this->nic];
        foreach ($nicInterface['unicast'] as $unicast) {
            $address = $unicast['address'] ?? null;

            if ($address !== null && str_contains($address, ':') && IPv6Type::getIPv6Type($address) === IPv6Type::GlobalUnicast) {
                $ipv6Builder = $this->createIPv6Builder()
                    ->setAddress($address)
                    ->setSubnetMask($unicast['netmask'])
                    ->setType(IPv6Type::GlobalUnicast);

                if ($this->configPrefixLength != null) {
                    try {
                        return $ipv6Builder->setNetworkPrefixLength($this->configPrefixLength)
                            ->buildByAddressAndPrefix();
                    } catch (BuildIPv6AddressException $e) {
                        LOGGER->error('Error while building IPv6 with Address & Network Prefix-Length! Continue without Network-Prefix', $this::class);
                    }
                }

                return $ipv6Builder->build();
            }
        }

        throw new IpNotFoundException('No Global IPv6 Address found!');
    }
}