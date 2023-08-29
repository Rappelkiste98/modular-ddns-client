<?php

namespace Acme\Network;

use Acme\Exception\BuildIPv6AddressException;
use Exception;

class IPv6
{
    private ?string $address = null;
    private ?string $networkPrefix = null;
    private ?int $networkPrefixLength = null;
    private ?string $interfaceIdentifier = null;
    private ?IPv6Type $type = IPv6Type::Unspecified;

    public static function countAddressSegments(string $ipv6Address): int
    {
        $ipv6Address = str_replace('::', '', $ipv6Address);
        return count(explode(':', $ipv6Address));
    }

    public function validate(): bool
    {
        if (filter_var($this->address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return true;
        } else {
            return false;
        }
    }

    public function setAddress(?string $address): void
    {
        $this->address = $address;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function getNetworkPrefix(): ?string
    {
        return $this->networkPrefix;
    }

    public function setNetworkPrefix(?string $networkPrefix): void
    {
        $this->networkPrefix = $networkPrefix;
    }

    public function getInterfaceIdentifier(): ?string
    {
        return $this->interfaceIdentifier;
    }

    public function setInterfaceIdentifier(?string $interfaceIdentifier): void
    {
        $this->interfaceIdentifier = $interfaceIdentifier;
    }

    public function getNetworkPrefixLength(): ?int
    {
        return $this->networkPrefixLength;
    }

    public function setNetworkPrefixLength(?int $networkPrefixLength): void
    {
        $this->networkPrefixLength = $networkPrefixLength;
    }

    public function getType(): IPv6Type
    {
        return $this->type;
    }

    public function setType(?IPv6Type $type): void
    {
        $this->type = $type;
    }
}