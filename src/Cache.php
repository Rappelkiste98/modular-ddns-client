<?php

namespace Src;

use Src\Builder\IPv4Builder;
use Src\Builder\IPv6Builder;
use Src\Exception\CacheException;
use Src\Entities\DnsRecord;
use Src\Network\DnsType;
use Src\Network\IPv4;
use Src\Network\IPv6;

class Cache
{
    final const DEFAULT_PATH = 'var/cache.json';

    private string $path;
    /** @var DnsRecord[] $cache  */
    private array $cache = [];

    public function __construct(string $path = self::DEFAULT_PATH)
    {
        $this->path = $path;

        try {
            $this->loadCache();
            LOGGER->debug('Cache "' . $this->path . '" successfully loaded', $this::class);
        } catch (CacheException $e) {
            LOGGER->warning($e->getMessage(), $this::class);
        }
    }

    public function __destruct()
    {
        try {
            $this->createCacheFile();
            LOGGER->debug('Cache "' . $this->path . '" successfully saved', $this::class);
        } catch (CacheException $e) {
            LOGGER->warning($e->getMessage(), $this::class);
        }
    }

    public function cacheDnsRecord(DnsRecord $record): void
    {
        $cacheRecord = $this->loadDnsRecord($record);

        if ($cacheRecord === null) {
            $this->cache[] = $record;
        } else {
            $cacheRecord->setIp($record->getIp())
                ->setLastUpdate($record->getLastUpdate());
        }
    }

    public function loadDnsRecord(DnsRecord $record): ?DnsRecord
    {
        $records = array_filter($this->cache, fn($cacheRecord) =>
            $cacheRecord->getSubDomain() === $record->getSubDomain()
            && $cacheRecord->getDomain() === $record->getDomain()
            && $cacheRecord->getType() === $record->getType()
            && $cacheRecord->getIp()::class === $record->getIp()::class
        );

        if (count($records) === 0) {
            return null;
        }

        return reset($records);
    }

    /**
     * @throws CacheException
     */
    private function createCacheFile(): void
    {
        $jsonCache = [];
        foreach ($this->cache as $record) {
            $jsonRecord = [
                'subdomain' => $record->getSubDomain(),
                'domain' => $record->getDomain(),
                'type' => $record->getType()->value,
                'ip' => $record->getIp()?->getAddress(),
                'ipType' => $record->getIp()::class,
                'lastUpdate' => $record->getLastUpdate()?->format('c'),
            ];

            $jsonCache[] = $jsonRecord;
        }

        $cacheJson = json_encode($jsonCache, JSON_PRETTY_PRINT);
        $cacheFile = fopen($this->path, 'w', true);
        if (!$cacheFile) {
            throw new CacheException('Could not Open Cache-File');
        } else {
            if (!fwrite($cacheFile, $cacheJson)) {
                throw new CacheException('Could not Write to Cache-File');
            }

            fclose($cacheFile);
        }
    }

    /**
     * @throws CacheException
     */
    private function loadCache(): array
    {
        if (!file_exists($this->path)) {
            throw new CacheException('Cache-File not found!');
        }

        $cacheFile = fopen($this->path, 'r', true);
        if (!$cacheFile) {
            throw new CacheException('Could not Open Cache-File');
        } else {
            $fileContent = fread($cacheFile, filesize($this->path));

            if (!$fileContent) {
                throw new CacheException('Could not Read from Cache-File');
            }
        }

        $cacheJson = json_decode($fileContent, true);
        if ($cacheJson === null) {
            throw new CacheException('Could not Parse Cache-File');
        }

        foreach ($cacheJson as $rawRecord) {
            $record = (new DnsRecord())
                ->setSubDomain($rawRecord['subdomain'])
                ->setDomain($rawRecord['domain'])
                ->setType(DnsType::from($rawRecord['type']))
                ->setLastUpdate(($rawRecord['lastUpdate'] ?? null) !== null ? \DateTime::createFromFormat('c', $rawRecord['lastUpdate']) : null);

            switch ($rawRecord['ipType']) {
                case IPv4::class:
                    $ipv4 = (new IPv4Builder())
                        ->setAddress($rawRecord['ip'])
                        ->build();

                    $record->setIp($ipv4);
                    break;
                case IPv6::class:
                    $ipv6 = (new IPv6Builder())
                        ->setAddress($rawRecord['ip'])
                        ->build();

                    $record->setIp($ipv6);
                    break;
            }

            $this->cache[] = $record;
        }

        return $this->cache;
    }
}