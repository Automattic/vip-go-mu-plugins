<?php

declare(strict_types=1);

namespace Prometheus;

class Sample
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string[]
     */
    private $labelNames;

    /**
     * @var mixed[]
     */
    private $labelValues;

    /**
     * @var int|double
     */
    private $value;

    /**
     * Sample constructor.
     * @param mixed[] $data
     */
    public function __construct(array $data)
    {
        $this->name = $data['name'];
        $this->labelNames = (array) $data['labelNames'];
        $this->labelValues = (array) $data['labelValues'];
        $this->value = $data['value'];
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string[]
     */
    public function getLabelNames(): array
    {
        return $this->labelNames;
    }

    /**
     * @return mixed[]
     */
    public function getLabelValues(): array
    {
        return $this->labelValues;
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        return (string) $this->value;
    }

    /**
     * @return bool
     */
    public function hasLabelNames(): bool
    {
        return $this->labelNames !== [];
    }
}
