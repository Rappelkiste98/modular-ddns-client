<?php

namespace Acme\Network;

class DomainRecord
{
    private Domain $domain;
    private ?IPv4 $ipv4 = null;
    private ?IPv6 $ipv6 = null;
    private ?IPv6 $networkIpv6 = null;
    private int $ttl = 300;

    public function __construct(Domain $domain) {
        $this->domain = $domain;
    }

    public function getDomain(): Domain
    {
        return $this->domain;
    }

    public function getIpv4(): ?IPv4
    {
        return $this->ipv4;
    }

    public function setIpv4(?IPv4 $ipv4): void
    {
        $this->ipv4 = $ipv4;
    }

    public function getIpv6(): ?IPv6
    {
        return $this->ipv6;
    }

    public function setIpv6(?IPv6 $ipv6): void
    {
        $this->ipv6 = $ipv6;
    }

    public function getNetworkIpv6(): ?IPv6
    {
        return $this->networkIpv6;
    }

    public function setNetworkIpv6(?IPv6 $networkIpv6): void
    {
        $this->networkIpv6 = $networkIpv6;
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }

    public function setTtl(int $ttl): void
    {
        $this->ttl = $ttl;
    }
}