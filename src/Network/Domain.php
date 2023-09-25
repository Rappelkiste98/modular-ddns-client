<?php

namespace Src\Network;

class Domain
{
    private string $subDomain = '';
    private string $domain;

    public function getDomainname(): string
    {
        $subDomain = '';

        switch ($this->subDomain) {
            case '':
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

    public function getSubDomain(): string
    {
        return $this->subDomain;
    }

    public function setSubDomain(string $subDomain): self
    {
        $this->subDomain = $subDomain;
        return $this;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): self
    {
        $this->domain = $domain;
        return $this;
    }
}