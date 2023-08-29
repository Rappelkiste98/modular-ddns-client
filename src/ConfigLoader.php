<?php

namespace Acme;

use Acme\Builder\DnsServiceBuilder;
use Acme\Builder\IpDetectorBuilder;
use Acme\Builder\IPv4Builder;
use Acme\Builder\IPv6Builder;
use Acme\Exception\ConfigException;
use Acme\Exception\RecordNotFoundException;
use Acme\Network\Domain;
use Acme\Network\IPv6;
use Modules\DnsService\DnsService;
use Modules\DnsService\DynDnsService;
use Modules\DnsService\IPv64Service;
use Modules\DnsService\NetcupService;
use Modules\IpDetector\GenericDetector;
use Modules\IpDetector\IpDetector;
use Modules\IpDetector\AvmDetector;
use Modules\IpDetector\ApiDetector;
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
     * @return Domain[]
     * @throws ConfigException*/
    public static function loadDomains(array $rawDomains): array
    {
        $domains = [];

        foreach ($rawDomains as $domain) {
            foreach ($domain->Subdomains as $subdomain) {
                try {
                    $domainObj = new Domain(
                        $subdomain->Subdomain,
                        $domain->Domain,
                        $domain->Module
                    );

                    if (isset($subdomain->IPv6)) {
                        $domainObj->setStaticIpv6Identifier(
                            IPv6Builder::create()
                                ->setInterfaceIdentifier($subdomain->IPv6)
                                ->build()
                        );
                    }

                    if (isset($subdomain->IPv4)) {
                        $domainObj->setStaticIpv4(
                            IPv4Builder::create()
                                ->setAddress($subdomain->IPv4)
                                ->build()
                        );
                    }

                    $domainObj->getPublicRecords();

                    $domains[$domain->Domain][$subdomain->Subdomain] = $domainObj;
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
        $modules = [];

        foreach ($rawModules as $module) {
            $modules[$module->Name] = self::createDnsServiceBuilder()
                ->setUpdateUrl($module->UpdateUrl ?? '')
                ->setUpdateKey($module->UpdateKey ?? '')
                ->setApiUrl($module->ApiUrl ?? '')
                ->setApiKey($module->ApiKey ?? '')
                ->setUsername($module->Username ?? '')
                ->setPassword($module->Password ?? '')
                ->setUpdatePrefix($module->NetworkPrefix ?? false)
                ->build($module->Service);

            Log::info('Initialized DnsService Module. Ready!', $modules[$module->Name]::class);
        }

        if(count($modules) == 0) {
            throw new ConfigException('No active DnsService Modules found!');
        }

        return $modules;
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