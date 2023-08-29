<?php

namespace Modules\DnsService;

use Acme\Network\Domain;
use Acme\Network\DomainRecord;


interface DnsService
{
    const NAME = '';

    public function getDomainInformation(Domain $domain): array;
    public function setDomainInformation(DomainRecord $domainRecord): bool;
}