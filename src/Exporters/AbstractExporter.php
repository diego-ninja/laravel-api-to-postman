<?php

namespace AndreasElia\PostmanGenerator\Exporters;

use AndreasElia\PostmanGenerator\Authentication\AuthenticationMethod;
use AndreasElia\PostmanGenerator\Collections\RequestCollection;
use AndreasElia\PostmanGenerator\Concerns\HasAuthentication;
use AndreasElia\PostmanGenerator\Contracts\Exporter;
use AndreasElia\PostmanGenerator\Processors\RouteProcessor;
use Illuminate\Config\Repository;
use ReflectionException;

abstract class AbstractExporter implements Exporter
{
    use HasAuthentication;

    protected string $filename;
    protected array $output;

    protected RequestCollection $requests;

    public function __construct(protected readonly Repository $config, private readonly RouteProcessor $processor)
    {
    }

    public function to(string $filename): self
    {
        $this->filename = $filename;
        return $this;
    }

    public function getOutput(): bool|string
    {
        return json_encode($this->output, JSON_PRETTY_PRINT);
    }

    /**
     * @throws ReflectionException
     */
    public function export(): void
    {
        $this->resolveAuth();
        $this->requests = $this->processor->process();
        $this->output = $this->generateStructure();
    }

    public function setAuthentication(?AuthenticationMethod $authentication): self
    {
        $this->authentication = $authentication;
        return $this;
    }

    abstract protected function generateStructure(): array;
}
