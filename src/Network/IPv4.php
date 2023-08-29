<?php

namespace Acme\Network;

class IPv4
{
    private string $address;
    private ?string $subnetMask;
    private ?int $prefix;

    public static function countAddressSegments(string $ipv4Address): int
    {
        return count(explode('.', $ipv4Address));
    }

    public function validate(): bool
    {
        if (filter_var($this->address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return true;
        } else {
            return false;
        }
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): void
    {
        $this->address = $address;
    }

    public function getSubnetMask(): ?string
    {
        return $this->subnetMask;
    }

    public function setSubnetMask(?string $subnetMask): void
    {
        $this->subnetMask = $subnetMask;
    }

    public function getPrefix(): ?int
    {
        return $this->prefix;
    }

    public function setPrefix(?int $prefix): void
    {
        $this->prefix = $prefix;
    }
}