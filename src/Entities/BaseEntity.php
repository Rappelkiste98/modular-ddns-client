<?php

namespace Acme\Entities;

class BaseEntity
{
    private bool $delete = false;
    private bool $create = false;
    private bool $update = false;
    private ?array $rawData = null;

    public function __construct(?array $rawData = null)
    {
        $this->rawData = $rawData;
    }

    public function isDelete(): bool
    {
        return $this->delete;
    }

    public function setDelete(bool $delete = true): self
    {
        $this->delete = $delete;
        return $this;
    }

    public function isCreate(): bool
    {
        return $this->create;
    }

    public function setCreate(bool $create = true): self
    {
        $this->create = $create;
        return $this;
    }

    public function isUpdate(): bool
    {
        return $this->update;
    }

    public function setUpdate(bool $update = true): self
    {
        $this->update = $update;
        return $this;
    }

    public function getRawData(): ?array
    {
        return $this->rawData;
    }

    public function setRawData(?array $rawData): self
    {
        $this->rawData = $rawData;
        return $this;
    }
}