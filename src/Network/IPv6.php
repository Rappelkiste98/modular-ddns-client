<?php

namespace Src\Network;

use Src\Exception\BuildIPv6AddressException;
use Exception;

class IPv6
{
    private ?string $address = null;
    private ?string $subnetMask = null;
    private ?string $networkPrefix = null;
    private ?int $networkPrefixLength = null;
    private ?string $interfaceIdentifier = null;
    private ?IPv6Type $type = IPv6Type::Unspecified;

    public static function countAddressSegments(string $ipv6Address): int
    {
        $ipv6Address = str_replace('::', '', $ipv6Address);
        return count(explode(':', $ipv6Address));
    }

    public static function ipToBinary(string $ipAddress): string
    {
        $splitedAddress = str_split(str_replace(':', '', $ipAddress));
        $binaryAddress = '';

        // Loop through IPv6 Chars
        foreach ($splitedAddress as $hexChar) {
            $binaryChar = base_convert($hexChar, 16, 2);
            $expandedBinaryChar = str_pad($binaryChar, 4, '0', STR_PAD_LEFT);

            $binaryAddress .= $expandedBinaryChar;
        }

        return $binaryAddress;
    }

    public static function binaryToIp(string $binaryAddress): string
    {
        $hexAddress = '';
        foreach (str_split($binaryAddress, 4) as $binaryChar) {
            $hexAddress .= base_convert($binaryChar, 2, 16);
        }

        $ipv6Address = '';
        foreach (str_split($hexAddress, 4) as $segment) {
            $segment = str_pad($segment, 4, '0');
            $ipv6Address .= $segment . ':';
        }

        return rtrim($ipv6Address, ':');
    }

    public static function trim(string $address): string
    {
        $segmented = explode(':', $address);
        foreach ($segmented as $key => $segment) {
            $segmented[$key] = ltrim($segment, '0');
        }

        return implode(':', $segmented);
    }

    public static function expand(string $address): string
    {
        $segmented = explode(':', $address);
        $expanded = '';

        foreach ($segmented as $segment) {
            $expanded .= str_pad($segment, 4, '0', STR_PAD_LEFT) . ':';
        }

        return rtrim($expanded, ':');
    }

    public function validate(): bool
    {
        if (filter_var($this->address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return true;
        } else {
            return false;
        }
    }

    public function setAddress(?string $address): self
    {
        $this->address = $address !== null ? $this::trim($address) : null;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function getSubnetMask(): ?string
    {
        return $this->subnetMask;
    }

    public function setSubnetMask(?string $subnetMask): self
    {
        $this->subnetMask = $subnetMask;
        return $this;
    }

    public function getNetworkPrefix(): ?string
    {
        return $this->networkPrefix;
    }

    public function setNetworkPrefix(?string $networkPrefix): self
    {
        $this->networkPrefix = $networkPrefix !== null ? $this::trim($networkPrefix) : null;
        return $this;
    }

    public function getInterfaceIdentifier(): ?string
    {
        return $this->interfaceIdentifier;
    }

    public function setInterfaceIdentifier(?string $interfaceIdentifier): self
    {
        $this->interfaceIdentifier = $interfaceIdentifier !== null ? $this::trim($interfaceIdentifier) : null;
        return $this;
    }

    public function getNetworkPrefixLength(): ?int
    {
        return $this->networkPrefixLength;
    }

    public function setNetworkPrefixLength(?int $networkPrefixLength): self
    {
        $this->networkPrefixLength = $networkPrefixLength;
        return $this;
    }

    public function getType(): IPv6Type
    {
        return $this->type;
    }

    public function setType(?IPv6Type $type): self
    {
        $this->type = $type;
        return $this;
    }
}