<?php

namespace Modules\IpDetector;

use Src\Builder\IPv4Builder;
use Src\Builder\IPv6Builder;
use Src\Exception\BuildIPv6AddressException;
use Src\Exception\IpNotFoundException;
use Src\Logger;
use Src\Network\IPv4;
use Src\Network\IPv6;
use Src\Network\IPv6Type;

class GenericDetector extends IpDetector
{
    const NAME = 'generic';


    /**
     * @throws IpNotFoundException
     */
    public function getExternalNetworkIPv4(): IPv4
    {
        $netInterfaces = net_get_interfaces();

        if (!$netInterfaces) {
            throw new IpNotFoundException('No Network-Interfaces found!');
        }

        foreach ($netInterfaces as $netInterface) {
            foreach ($netInterface['unicast'] as $unicast) {
                if (str_contains($unicast['address'], '.')) {
                    return $this->createIPv4Builder()
                        ->setAddress($unicast['address'])
                        ->build();
                }
            }
        }

        throw new IpNotFoundException('No Global IPv4 Address found!');
    }

    /**
     * @throws IpNotFoundException
     */
    public function getExternalIPv6(): IPv6
    {
        $netInterfaces = net_get_interfaces();

        if (!$netInterfaces) {
            throw new IpNotFoundException('No Network-Interfaces found!');
        }

        foreach ($netInterfaces as $netInterface) {
            foreach ($netInterface['unicast'] as $unicast) {
                if (str_contains($unicast['address'], ':') && IPv6Type::getIPv6Type($unicast['address']) === IPv6Type::GlobalUnicast) {
                    $ipv6Builder = $this->createIPv6Builder()
                        ->setAddress($unicast['address'])
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
        }

        throw new IpNotFoundException('No Global IPv6 Address found!');
    }
}