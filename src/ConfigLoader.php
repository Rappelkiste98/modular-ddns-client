<?php

namespace Acme;

use Acme\Builder\DnsServiceBuilder;
use Acme\Builder\IpDetectorBuilder;
use Acme\Builder\IPv4Builder;
use Acme\Builder\IPv6Builder;
use Acme\Entities\DnsRecord;
use Acme\Entities\DomainZone;
use Acme\Exception\ConfigException;
use Acme\Exception\RecordNotFoundException;
use Acme\Network\Domain;
use Acme\Network\DomainConfig;
use Modules\DnsService\DnsService;
use Modules\IpDetector\IpDetector;
use SoapFault;

class ConfigLoader
{
    /**
     * @throws SoapFault
     * @throws ConfigException
     */
    public static function loadIpDetector(string $detector, ?int $configPrefixLength, ?string $detectorURL): IpDetector
    {
        $ipDetector = self::createIpDetectorBuilder()
            ->setRouterAddress($detectorURL ?? '')
            ->setConfigPrefix($configPrefixLength)
            ->build($detector);

        Log::info('Initialized IpDetector Module. Ready!', $ipDetector::class);
        return $ipDetector;
    }

    /**
     * @return DomainConfig[]
     * @throws ConfigException*/
    public static function loadDomains(array $rawDomains): array
    {
        $domains = [];

        foreach ($rawDomains as $domain) {
            foreach ($domain->Subdomains as $subdomain) {
                try {
                    $dnsRecord = new DnsRecord();
                    $dnsRecord->setSubDomain($subdomain->Subdomain)
                        ->setDomain($domain->Domain);

                    $domainConfigObj = (new DomainConfig)->setDnsRecord($dnsRecord)
                        ->setModule($domain->Module);

                    if (isset($subdomain->IPv6)) {
                        $domainConfigObj->setStaticIpv6Identifier(
                            IPv6Builder::create()
                                ->setInterfaceIdentifier($subdomain->IPv6)
                                ->build()
                        );
                    }

                    if (isset($subdomain->IPv4)) {
                        $domainConfigObj->setStaticIpv4(
                            IPv4Builder::create()
                                ->setAddress($subdomain->IPv4)
                                ->build()
                        );
                    }

                    $domainConfigObj->getPublicRecords();

                    $domains[$domain->Domain][$subdomain->Subdomain] = $domainConfigObj;
                } catch (RecordNotFoundException $e) {
                    Log::error($e->getMessage(), self::class);
                }
            }
        }

        if(count($domains) == 0) {
            throw new ConfigException('No valid Domains found!');
        }

        return $domains;
    }

    /**
     * @throws ConfigException
     * @return DnsService[]
     */
    public static function loadDnsServices(array $rawModules): array
    {
        $dnsServices = [];

        foreach ($rawModules as $module) {
            $dnsServices[$module->Name] = self::createDnsServiceBuilder()
                ->setUpdateUrl($module->UpdateUrl ?? null)
                ->setUpdateKey($module->UpdateKey ?? null)
                ->setApiUrl($module->ApiUrl ?? null)
                ->setApiKey($module->ApiKey ?? null)
                ->setUsername($module->Username ?? null)
                ->setPassword($module->Password ?? null)
                ->setUpdatePrefix($module->NetworkPrefix ?? false)
                ->build($module->Service);

            Log::info('Initialized DnsService Module. Ready!', $dnsServices[$module->Name]::class);
        }

        if(count($dnsServices) == 0) {
            throw new ConfigException('No active DnsService Modules found!');
        }

        return $dnsServices;
    }


    private static function createIpDetectorBuilder(): IpDetectorBuilder
    {
        return new IpDetectorBuilder();
    }

    private static function createDnsServiceBuilder(): DnsServiceBuilder
    {
        return new DnsServiceBuilder();
    }
}