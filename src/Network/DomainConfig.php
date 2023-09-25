<?php

namespace Src\Network;

use Src\Builder\IPv4Builder;
use Src\Builder\IPv6Builder;
use Src\Entities\DnsRecord;
use Src\Exception\RecordNotFoundException;

class DomainConfig
{
    private DnsRecord $dnsRecord;
    private string $module;
    private ?IPv4 $ipv4 = null;
    private ?IPv4 $staticIpv4 = null;
    private ?IPv6 $ipv6 = null;
    private ?IPv6 $staticIpv6Identifier = null;
    private ?IPv6 $staticIpv6Prefix = null;

    /**
     * @throws RecordNotFoundException
     */
    public function getPublicRecords(): void
    {
        $domainname = $this->getDnsRecord()->buildDomain()->getDomainname();
        $records = dns_get_record($domainname, DNS_ALL);

        if (!$records) {
            throw new RecordNotFoundException('No DNS Record found for "' . $domainname . '"!');
        }

        foreach ($records as $record) {
            switch ($record['type']) {
                case 'A':
                    $this->ipv4 = IPv4Builder::create()
                        ->setAddress($record['ip'])
                        ->build();
                    break;
                case 'AAAA':
                    $this->ipv6 = IPv6Builder::create()
                        ->setAddress($record['ipv6'])
                        ->build();
                    break;
            }
        }
    }

    public function isUpdateNeeded(IPv4|IPv6|null $checkIp): bool
    {
        return match (get_class($checkIp)) {
            IPv4::class => $this->ipv4 === null || $this->ipv4->getAddress() !== $checkIp?->getAddress(),
            IPv6::class => $this->ipv6 === null || $this->ipv6->getAddress() !== $checkIp?->getAddress(),
            default => false,
        };
    }

    public function getDnsRecord(): DnsRecord
    {
        return $this->dnsRecord;
    }

    public function setDnsRecord(DnsRecord $dnsRecord): self
    {
        $this->dnsRecord = $dnsRecord;
        return $this;
    }

    public function getModule(): string
    {
        return $this->module;
    }

    public function setModule(string $module): self
    {
        $this->module = $module;
        return $this;
    }

    public function getIpv4(): ?IPv4
    {
        return $this->ipv4;
    }

    public function setIpv4(?IPv4 $ipv4): self
    {
        $this->ipv4 = $ipv4;
        return $this;
    }

    public function getIpv6(): ?IPv6
    {
        return $this->ipv6;
    }

    public function setIpv6(?IPv6 $ipv6): self
    {
        $this->ipv6 = $ipv6;
        return $this;
    }

    public function getStaticIpv4(): ?IPv4
    {
        return $this->staticIpv4;
    }

    public function setStaticIpv4(?IPv4 $staticIpv4): self
    {
        $this->staticIpv4 = $staticIpv4;
        return $this;
    }

    public function getStaticIpv6Identifier(): ?IPv6
    {
        return $this->staticIpv6Identifier;
    }

    public function setStaticIpv6Identifier(?IPv6 $staticIpv6Identifier): self
    {
        $this->staticIpv6Identifier = $staticIpv6Identifier;
        return $this;
    }

    public function getStaticIpv6Prefix(): ?IPv6
    {
        return $this->staticIpv6Prefix;
    }

    public function setStaticIpv6Prefix(?IPv6 $staticIpv6Prefix): self
    {
        $this->staticIpv6Prefix = $staticIpv6Prefix;
        return $this;
    }
}