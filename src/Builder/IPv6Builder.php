<?php

namespace Src\Builder;

use Src\Exception\BuildIPv6AddressException;
use Src\Network\IPv6;
use Src\Network\IPv6Type;

class IPv6Builder
{
    private ?string $address = null;
    private ?string $networkPrefix = null;
    private ?int $networkPrefixLength = null;
    private ?string $interfaceIdentifier = null;
    private ?IPv6Type $type = null;

    public static function create(): IPv6Builder
    {
        return new IPv6Builder();
    }

    public function setAddress(?string $address): self
    {
        $this->address = $address;

        return $this;
    }

    public function setNetworkPrefix(?string $networkPrefix): self
    {
        $this->networkPrefix = $networkPrefix;

        return $this;
    }

    public function setInterfaceIdentifier(?string $interfaceIdentifier): self
    {
        $this->interfaceIdentifier = $interfaceIdentifier;

        return $this;
    }

    public function setType(?IPv6Type $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function setNetworkPrefixLength(?int $networkPrefixLength): self
    {
        $this->networkPrefixLength = $networkPrefixLength;

        return $this;
    }

    public function build(): IPv6
    {
        $ip = new IPv6();
        $ip->setAddress($this->address);
        $ip->setNetworkPrefix($this->networkPrefix);
        $ip->setInterfaceIdentifier($this->interfaceIdentifier);
        $ip->setNetworkPrefixLength($this->networkPrefixLength);
        $ip->setType($this->type);

        return $ip;
    }

    /**
     * @throws BuildIPv6AddressException
     */
    public function buildByNetworkAndInterface(): IPv6
    {
        if ($this->networkPrefix === null || $this->interfaceIdentifier === null) {
            throw new BuildIPv6AddressException('IPv6 Build Failed! Requires Network- & Interface-Identifier');
        }

        $network = str_replace('::', '', $this->networkPrefix);
        $interface = str_replace('::', '', $this->interfaceIdentifier);

        $this->address = match ((IPv6::countAddressSegments($network) + IPv6::countAddressSegments($interface)) === 8) {
            true => $network . ':' . $interface,
            false => $network . '::' . $interface,
        };

        $ip = $this->build();
        if (!$ip->validate()) {
            throw new BuildIPv6AddressException('IPv6 Build Failed! Generated IPv6 Address is not valid!');
        }

        return $ip;
    }

    /**
     * @throws BuildIPv6AddressException
     */
    public function buildByAddressAndPrefix(): IPv6
    {
        if ($this->address === null || $this->networkPrefixLength === null) {
            throw new BuildIPv6AddressException('IPv6 Build Failed! Requires Address, Network-Prefix & Interface-Identifier');
        }

        $expandedAddress = $this->expandIPv6($this->address);
        $addressBin = $this->ipv62Binary($expandedAddress);
        $networkBinArr = str_split($addressBin, $this->networkPrefixLength);

        $this->networkPrefix = $this->binary2IPv6($networkBinArr[0]);

        $ip = $this->build();
        if (!$ip->validate()) {
            throw new BuildIPv6AddressException('IPv6 Build Failed! Generated IPv6 Address is not valid!');
        }

        return $ip;
    }

    private function expandIPv6(string $ipv6Address): string
    {
        $segmentedAddress = explode(':', $ipv6Address);
        $expandedIPv6 = '';

        foreach ($segmentedAddress as $segment) {
            $expandedIPv6 .= str_pad($segment, 4, '0', STR_PAD_LEFT) . ':';
        }

        return rtrim($expandedIPv6, ':');
    }

    private function ipv62Binary(string $ipv6Address): string
    {
        $splitedAddress = str_split(str_replace(':', '', $ipv6Address));
        $binaryAddress = '';

        // Loop through IPv6 Chars
        foreach ($splitedAddress as $hexChar) {
            $binaryChar = base_convert($hexChar, 16, 2);
            $expandedBinaryChar = str_pad($binaryChar, 4, '0', STR_PAD_LEFT);

            $binaryAddress .= $expandedBinaryChar;
        }

        return $binaryAddress;
    }

    private function binary2IPv6(string $binaryAddress): string
    {
        $hexAddress = base_convert($binaryAddress, 2, 16);
        $ipv6Address = '';

        foreach (str_split($hexAddress, 4) as $segment) {
            $segment = str_pad($segment, 4, '0');
            $ipv6Address .= $segment . ':';
        }

        return rtrim($ipv6Address, ':');
    }
}