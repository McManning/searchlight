<?php namespace McManning\Searchlight;

class ConfigProvider
{
    protected string $name;

    /** @var array Provider configuration */
    protected array $config;

    public function __construct(string $name, array $config)
    {
        $this->name = $name;
        $this->config = $config;
    }

    public function get(string $key, $default = null)
    {
        return data_get($this->config, $key, $default);
    }
}
