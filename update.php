<?php

use Src\Builder\IPv6Builder;
use Src\ConfigLoader;
use Src\Entities\DnsRecord;
use Src\Exception\BuildIPv6AddressException;
use Src\Exception\ConfigException;
use Src\Exception\FileException;
use Src\Logger;
use Src\LoggerLevel;
use Src\Network\DnsType;
use Src\Network\DomainConfig;
use Dallgoot\Yaml\Loader;
use Dallgoot\Yaml\Yaml;
use Src\Cache;
use Src\FileLogger;

require __DIR__ . '/vendor/autoload.php';

try {
    $config = Yaml::parseFile(__DIR__ . '/config.yml', Loader::IGNORE_COMMENTS);
    define('LOGGER', new Logger(LoggerLevel::fromName($config->General->LoggerLevel)));
    if ($config->General->FileLogger) {
        define('FILE_LOGGER', new FileLogger(LoggerLevel::fromName($config->General->FileLoggerLevel)));
    }
    if ($config->General->Cache) {
        define('CACHE', new Cache());
    }

    define('USE_IPv4', $config->Detector->IPv4 ?? false);
    define('USE_IPv6', $config->Detector->IPv6 ?? false);
    define('IP_DETECTOR', ConfigLoader::loadIpDetector($config->Detector->Name, $config->Detector->IPv6PrefixLength ?? null, $config->Detector->URL ?? null));
    define('MODULES', ConfigLoader::loadDnsServices($config->Modules));
    define('DOMAINS', ConfigLoader::loadDomains($config->Domains));

    if (!USE_IPv4 && !USE_IPv6) {
        throw new ConfigException('IPv4 and IPv6 are deactivated. No DomainConfig DNS Update possible!');
    }
    LOGGER->info('DynDNS Client started....');

    // If IPv4 is active than Define current Network IPv4 Address
    if (USE_IPv4) {
        $ipv4 = IP_DETECTOR->getExternalNetworkIPv4();
        if (!$ipv4->validate()) {
            throw new Exception('Global-Network IPv4 is not valid!');
        }

        define('LOCAL_IPv4', $ipv4);
        LOGGER->debug('Current Global-Network IPv4: ' . $ipv4->getAddress());
    }

    // If IPv6 is active than Define current Network IPv6 Address
    if (USE_IPv6) {
        $ipv6 = IP_DETECTOR->getExternalIPv6();
        if (!$ipv6->validate()) {
            throw new Exception('Global-Device IPv6 is not valid!');
        }

        define('LOCAL_IPv6', $ipv6);
        LOGGER->debug('Current Global-Device IPv6: ' . $ipv6->getAddress());
    }

    // Domain Update Process
    /**
     * @var DomainConfig[] $domainConfigs
     */
    foreach (DOMAINS as $domainName => $domainConfigs) {
        foreach ($domainConfigs as $subdomain => $domainConfig) {
            $module = MODULES[$domainConfig->getModule()] ?? null;
            $newIpv4DnsRecord = null;
            $newIpv6DnsRecord = null;

            if ($module === null) {
                LOGGER->error('DnsService Module with Name "' . $domainConfig->getModule() . '" not found! Skip.');
                continue;
            }

            // Set new IPv4 when IPv4 Update Enabled!
            if (USE_IPv4) {
                $newIpv4DnsRecord = (new DnsRecord)->setSubDomain($domainConfig->getDnsRecord()->getSubDomain())
                    ->setDomain($domainConfig->getDnsRecord()->getDomain())
                    ->setType(DnsType::A);

                if ($domainConfig->getStaticIpv4() !== null && $domainConfig->getStaticIpv4()->validate()) {
                    $newIpv4DnsRecord->setIp($domainConfig->getStaticIpv4());
                } else {
                    $newIpv4DnsRecord->setIp(LOCAL_IPv4);
                }
            }

            // Set new IPv6 when IPv6 Update Enabled!
            if (USE_IPv6) {
                $newIpv6DnsRecord = (new DnsRecord)
                    ->setSubDomain($domainConfig->getDnsRecord()->getSubDomain())
                    ->setDomain($domainConfig->getDnsRecord()->getDomain())
                    ->setType(DnsType::AAAA);
                $ipv6Builder = new IPv6Builder();
                $ipv6Builder->setAddress(LOCAL_IPv6->getAddress())
                    ->setNetworkPrefix(LOCAL_IPv6->getNetworkPrefix())
                    ->setNetworkPrefixLength(LOCAL_IPv6->getNetworkPrefixLength());

                $staticInterface = $domainConfig->getStaticIpv6Identifier()?->getInterfaceIdentifier();

                if ($staticInterface !== null) {
                    try {
                        $newIpv6DnsRecord->setIp(
                            $ipv6Builder
                                ->setInterfaceIdentifier($staticInterface)
                                ->buildByNetworkAndInterface()
                        );
                    } catch (BuildIPv6AddressException $e) {
                        LOGGER->error($e->getMessage());
                    }
                } else {
                    $newIpv6DnsRecord->setIp($ipv6Builder->build());
                }
            }


            // Update DomainConfig DNS-Entry
            if ((USE_IPv4 && $domainConfig->isUpdateNeeded($newIpv4DnsRecord->getIp())) || (USE_IPv6 && $domainConfig->isUpdateNeeded($newIpv6DnsRecord->getIp()))) {
                LOGGER->info('DOMAIN "' . $domainConfig->getDnsRecord()->getDnsRecordname() . '": Update DynDNS Entry ...', $module::class);

                $cachedIpv4DnsRecord = USE_IPv4 ? CACHE?->loadDnsRecord($newIpv4DnsRecord) : null;
                $cachedIpv6DnsRecord = USE_IPv4 ? CACHE?->loadDnsRecord($newIpv6DnsRecord) : null;

                try {
                    if (USE_IPv4 && ($cachedIpv4DnsRecord === null || $cachedIpv4DnsRecord->getIp()->getAddress() !== $newIpv4DnsRecord->getIp()->getAddress())) {
                        LOGGER->change($domainConfig->getDnsRecord(), $module, $domainConfig->getIpv4(), $newIpv4DnsRecord->getIp());
                        $module->updateDnsRecord($newIpv4DnsRecord);
                    } else if (USE_IPv4) {
                        LOGGER->info('DOMAIN "' . $domainConfig->getDnsRecord()->getDnsRecordname() . ': IPv4 DynDNS Entry no Update needed (Cached)');
                    }

                    if (USE_IPv6 && ($cachedIpv6DnsRecord === null || $cachedIpv6DnsRecord->getIp()->getAddress() !== $newIpv6DnsRecord->getIp()->getAddress())) {
                        LOGGER->change($domainConfig->getDnsRecord(), $module, $domainConfig->getIpv6(), $newIpv6DnsRecord->getIp());
                        $module->updateDnsRecord($newIpv6DnsRecord);
                    } else if (USE_IPv6) {
                        LOGGER->info('DOMAIN "' . $domainConfig->getDnsRecord()->getDnsRecordname() . ': IPv6 DynDNS Entry no Update needed (Cached)');
                    }

                    LOGGER->success('DOMAIN "' . $domainConfig->getDnsRecord()->getDnsRecordname() . '": DynDNS Entry successfully updated', $module::class);
                } catch (Exception $e) {
                    LOGGER->warning('DOMAIN "' . $domainConfig->getDnsRecord()->getDnsRecordname() . '": DynDNS Entry could not be updated', $module::class);
                }
            } else {
                LOGGER->info('DOMAIN "' . $domainConfig->getDnsRecord()->getDnsRecordname() . '": DynDNS Entry no Update needed', $module::class);
            }
        }
    }

    foreach (MODULES as $dnsService) {
        try {
            $dnsService->push();
        } catch (\Exception $e) {
            LOGGER->error($e->getMessage());
        }
    }

    LOGGER->success('DynDNS Client successfully');
} catch (SoapFault | ConfigException $e) {
    LOGGER->error($e::class . ' -> ' . $e->getMessage());
} catch (FileException $e) {
    LOGGER->error($e->getMessage());
}