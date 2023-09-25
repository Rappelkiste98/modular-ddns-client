<?php

namespace Src\Builder;

use Src\Network\IPv4;

class IPv4Builder
{
    private ?string $address = null;
    private ?string $subnetMask = null;
    private ?int $prefix = null;

    public static function create(): IPv4Builder
    {
        return new IPv4Builder();
    }

    public function setAddress(?string $address): self
    {
        $this->address = $address;

        return $this;
    }

    public function setSubnetMask(?string $subnetMask): self
    {
        $this->subnetMask = $subnetMask;

        return $this;
    }

    public function setPrefix(?int $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function build(): IPv4
    {
        $ip = new IPv4();
        $ip->setAddress($this->address);
        $ip->setSubnetMask($this->subnetMask);
        $ip->setPrefix($this->prefix);

        return $ip;
    }
}