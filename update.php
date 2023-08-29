<?php

use Acme\Builder\IPv6Builder;
use Acme\ConfigLoader;
use Acme\Exception\BuildIPv6AddressException;
use Acme\Exception\ConfigException;
use Acme\Log;
use Acme\Network\Domain;
use Acme\Network\DomainRecord;
use Dallgoot\Yaml\Yaml;
use \Dallgoot\Yaml\Loader;

require __DIR__ . '/vendor/autoload.php';

try {
    $config = Yaml::parseFile(__DIR__ . '/config.yml', Loader::IGNORE_COMMENTS);
    define('USE_IPv4', $config->Detector->IPv4 ?? false);
    define('USE_IPv6', $config->Detector->IPv6 ?? false);
    define('IP_DETECTOR', ConfigLoader::loadIpDetector($config->Detector->Name, $config->Detector->IPv6PrefixLength ?? null, $config->Detector->URL ?? null));
    define('MODULES', ConfigLoader::loadDnsServices($config->Modules));
    define('DOMAINS', ConfigLoader::loadDomains($config->Domains));

    if (!USE_IPv4 && !USE_IPv6) {
        throw new ConfigException('IPv4 and IPv6 are deactivated. No Domain DNS Update possible!');
    }
    Log::info('DynDNS Client started....');

    // If IPv4 is active than Define current Network IPv4 Address
    if (USE_IPv4) {
        $ipv4 = IP_DETECTOR->getExternalNetworkIPv4();
        if (!$ipv4->validate()) {
            throw new Exception('Global-Network IPv4 is not valid!');
        }

        define('LOCAL_IPv4', $ipv4);
        Log::info('Current Global-Network IPv4: ' . $ipv4->getAddress());
    }

    // If IPv6 is active than Define current Network IPv6 Address
    if (USE_IPv6) {
        $ipv6 = IP_DETECTOR->getExternalIPv6();
        if (!$ipv6->validate()) {
            throw new Exception('Global-Device IPv6 is not valid!');
        }

        define('LOCAL_IPv6', $ipv6);
        Log::info('Current Global-Device IPv6: ' . $ipv6->getAddress());
    }

    // Domain Update Process
    /**
     * @var Domain[] $subdomains
     */
    foreach (DOMAINS as $domainName => $subdomains) {
        foreach ($subdomains as $subdomainName => $subdomain) {
            $module = MODULES[$subdomain->getModule()] ?? null;
            $newDomainRecord = new DomainRecord($subdomain);

            if ($module === null) {
                Log::error('DnsService Module with Name "' . $subdomain->getModule() . '" not found! Skip.');
                continue;
            }

            // Set new IPv4 when IPv4 Update Enabled!
            if (USE_IPv4) {
                if ($subdomain->getStaticIpv4() !== null && $subdomain->getStaticIpv4()->validate()) {
                    $newDomainRecord->setIpv4($subdomain->getStaticIpv4());
                } else {
                    $newDomainRecord->setIpv4(LOCAL_IPv4);
                }
            }

            // Set new IPv6 when IPv6 Update Enabled!
            if (USE_IPv6) {
                $ipv6Builder = new IPv6Builder();
                $ipv6Builder->setAddress(LOCAL_IPv6->getAddress())
                    ->setNetworkPrefix(LOCAL_IPv6->getNetworkPrefix())
                    ->setNetworkPrefixLength(LOCAL_IPv6->getNetworkPrefixLength());

                $staticInterface = $subdomain->getStaticIpv6Identifier()?->getInterfaceIdentifier();

                if ($staticInterface !== null) {
                    try {
                        $newDomainRecord->setIpv6(
                            $ipv6Builder
                                ->setInterfaceIdentifier($staticInterface)
                                ->buildByNetworkAndInterface()
                        );
                    } catch (BuildIPv6AddressException $e) {
                        Log::error($e->getMessage());
                    }
                } else {
                    $newDomainRecord->setIpv6($ipv6Builder->build());
                }
            }

            // Update Domain DNS-Entry
            if ((USE_IPv4 && $subdomain->isUpdateNeeded($newDomainRecord->getIpv4())) || (USE_IPv6 && $subdomain->isUpdateNeeded($newDomainRecord->getIpv6()))) {
                Log::info('DOMAIN "' . $subdomain->getDomainname() . '": Update DynDNS Entry ...', $module::class);

                if(USE_IPv4) {
                    Log::change($subdomain, $module, $subdomain->getIpv4(), $newDomainRecord->getIpv4());
                }
                if(USE_IPv6) {
                    Log::change($subdomain, $module, $subdomain->getIpv6(), $newDomainRecord->getIpv6());
                }

                try {
                    //$response = $module->setDomainInformation($newDomainRecord);
                    Log::success('DOMAIN "' . $subdomain->getDomainname() . '": DynDNS Entry successfully updated', $module::class);
                } catch (Exception $e) {
                    Log::warning('DOMAIN "' . $subdomain->getDomainname() . '": DynDNS Entry could not be updated', $module::class);
                }
            } else {
                Log::info('DOMAIN "' . $subdomain->getDomainname() . '": DynDNS Entry no Update needed', $module::class);
            }
        }
    }

    Log::info('DynDNS Client successfully');
} catch (SoapFault | ConfigException $e) {
    Log::error($e::class . ' -> ' . $e->getMessage());
}