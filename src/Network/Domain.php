<?php

namespace Acme\Network;

use Acme\Builder\IPv4Builder;
use Acme\Builder\IPv6Builder;
use Acme\Exception\RecordNotFoundException;

class Domain
{
    private string $subDomain;
    private string $domain;
    private string $module;
    private ?IPv4 $ipv4 = null;
    private ?IPv4 $staticIpv4 = null;
    private ?IPv6 $ipv6 = null;
    private ?IPv6 $staticIpv6Identifier = null;

    public function __construct(string $subDomain, string $domain, string $module, IPv6|null $interfaceIpv6 = null)
    {
        $this->subDomain = $subDomain;
        $this->domain = $domain;
        $this->module = $module;
        $this->ipv6 = $interfaceIpv6;
    }

    /**
     * @throws RecordNotFoundException
     */
    public function getPublicRecords(): void
    {
        $records = dns_get_record($this->getDomainname(), DNS_ALL);

        if (!$records) {
            throw new RecordNotFoundException('No DNS Record found for ' . $this->getDomainname() . '!');
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

    public function getDomainname(): string
    {
        $subDomain = '';

        switch ($this->subDomain) {
            case '@':
                break;
            case '*':
                for ($i = 0; $i < 6; $i++) {
                    $subDomain .= chr(rand(97, 122));
                }
                $subDomain .= '.';
                break;
            default:
                $subDomain .= $this->subDomain . '.';
        }

        return $subDomain . $this->domain;
    }

    public function getRecordDomainname(): string
    {
        return $this->subDomain . '.' . $this->domain;
    }

    public function getSubDomain(): string
    {
        return $this->subDomain;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getModule(): string
    {
        return $this->module;
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

    public function getStaticIpv4(): ?IPv4
    {
        return $this->staticIpv4;
    }

    public function setStaticIpv4(?IPv4 $staticIpv4): void
    {
        $this->staticIpv4 = $staticIpv4;
    }

    public function getStaticIpv6Identifier(): ?IPv6
    {
        return $this->staticIpv6Identifier;
    }

    public function setStaticIpv6Identifier(?IPv6 $staticIpv6Identifier): void
    {
        $this->staticIpv6Identifier = $staticIpv6Identifier;
    }
}