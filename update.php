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

define('DRY_RUN', array_search('--dry-run', $argv) !== false);

try {
    $config = Yaml::parseFile(__DIR__ . '/config.yml', Loader::IGNORE_COMMENTS);
    define('LOGGER', new Logger(LoggerLevel::fromName($config->General->LoggerLevel)));
    define('FILE_LOGGER', $config->General->FileLogger ? new FileLogger(LoggerLevel::fromName($config->General->FileLoggerLevel)) : null);
    define('CACHE', $config->General->Cache ? new Cache() : null);

    define('USE_IPv4', $config->Detector->IPv4 ?? false);
    define('USE_IPv6', $config->Detector->IPv6 ?? false);
    define('IP_DETECTOR', ConfigLoader::loadIpDetector($config->Detector->Name, $config->Detector->IPv6PrefixLength ?? null, $config->Detector->URL ?? null, $config->Detector->NIC ?? null));
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
                LOGGER->error(sprintf('DnsService Module with Name "%s" not found! Skip.', $domainConfig->getModule()));
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

            LOGGER->info('DOMAIN "' . $domainConfig->getDnsRecord()->getDnsRecordname() . '": Update DynDNS Record ...', $module::class);
            // Update DomainConfig DNS-Entry IPv4
            if (USE_IPv4 && $domainConfig->isUpdateNeeded($newIpv4DnsRecord->getIp())) {
                $cachedIpv4DnsRecord = CACHE?->loadDnsRecord($newIpv4DnsRecord);

                if ($cachedIpv4DnsRecord === null || $cachedIpv4DnsRecord->getIp()->getAddress() !== $newIpv4DnsRecord->getIp()->getAddress()) {
                    try {
                        $module->updateDnsRecord($newIpv4DnsRecord);
                        LOGGER->change($domainConfig->getDnsRecord(), $module, $domainConfig->getIpv4(), $newIpv4DnsRecord->getIp());
                    } catch (\Exception $e) {
                        LOGGER->warning(sprintf('DOMAIN "%s": DynDNS IPv4 Record could not be updated (%s)', $domainConfig->getDnsRecord()->getDnsRecordname(), $e->getMessage()), $module::class);
                    }
                } else {
                    LOGGER->info(sprintf('DOMAIN "%s": DynDNS IPv4 Record no Update needed (Cached [%s])', $domainConfig->getDnsRecord()->getDnsRecordname(), $cachedIpv4DnsRecord->getIp()->getAddress()));
                }
            }

            // Update DomainConfig DNS-Entry IPv6
            if (USE_IPv6 && $domainConfig->isUpdateNeeded($newIpv6DnsRecord->getIp())) {
                $cachedIpv6DnsRecord = CACHE?->loadDnsRecord($newIpv6DnsRecord);

                if ($cachedIpv6DnsRecord === null || $cachedIpv6DnsRecord->getIp()->getAddress() !== $newIpv6DnsRecord->getIp()->getAddress()) {
                    try {
                        $module->updateDnsRecord($newIpv6DnsRecord);
                        LOGGER->change($domainConfig->getDnsRecord(), $module, $domainConfig->getIpv6(), $newIpv6DnsRecord->getIp());
                    } catch (\Exception $e) {
                        LOGGER->warning(sprintf('DOMAIN "%s": DynDNS IPv6 Record could not be updated (%s)', $domainConfig->getDnsRecord()->getDnsRecordname(), $e->getMessage()), $module::class);
                    }
                } else {
                    LOGGER->info(sprintf('DOMAIN "%s": DynDNS IPv6 Record no Update needed (Cached [%s])', $domainConfig->getDnsRecord()->getDnsRecordname(), $cachedIpv6DnsRecord->getIp()->getAddress()));
                }
            }
        }
    }

    // For all Modules Push DnsRecords in Change/Create/Delete Query
    if (!DRY_RUN) {
        foreach (MODULES as $dnsService) {
            try {
                $dnsService->push();
            } catch (\Exception $e) {
                LOGGER->error($e->getMessage());
            }
        }
    }

    LOGGER->success('DynDNS Client successfully');
} catch (SoapFault | ConfigException $e) {
    LOGGER->error(sprintf('Error while Config initialization (%s)', $e->getMessage()));
} catch (\Exception $e) {
    echo sprintf("Exception: %s -> %s\n", $e::class, $e->getMessage());
}