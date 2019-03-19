<?php
namespace Lfda\Monkeyhead\Provider;

interface ProviderInterface
{
    public function setConfiguration(array $configuration);
    public function getConfiguration($key, $default = NULL);

    public function setArgument($name, $value);
    public function getArgument($name, $default = NULL);

    public function fetchData();
}
