<?php

namespace Lfda\Headless\Provider;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BaseProvider implements ProviderInterface
{
    protected $table = '';
    protected $configuration = [];
    protected $arguments = [];

    /**
     * @param array $configuration
     */
    public function setConfiguration(array $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * @param $key
     * @param null $default
     * @return array|mixed|null
     */
    public function getConfiguration($key, $default = NULL)
    {
        return $this->fetchFromArray($key, $this->configuration, $default);
    }

    /**
     * @param $name
     * @param $value
     */
    public function setArgument($name, $value)
    {
        $this->arguments[$name] = $value;
    }

    /**
     * @param $name
     * @param null $default
     * @return mixed|null
     */
    public function getArgument($name, $default = NULL)
    {
        return $this->fetchFromArray($name, $this->arguments, $default);
    }

    public function fetchData()
    {
        throw new \Exception('fetchData not implemented');
    }

    protected function fetchFromArray($key, $data, $default = NULL)
    {
        if (strpos($key, '/') !== false) {
            $path = explode('/', $key);
            $result = $data;
            foreach ($path as $path_segment) {
                if (array_key_exists($path_segment, $result)) {
                    $result = $result[$path_segment];
                } else {
                    return $default;
                }
            }
            return $result;

        } else {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
            return $default;
        }
    }

    /**
     * @param $table
     * @param array $fields
     * @param array $selection
     * @param null $orderBy
     * @param int $limit
     * @return mixed
     */
    protected function fetch($table, array $fields, array $selection, $orderBy = NULL, $limit = 0)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $query = $queryBuilder
            ->select(...$fields)
            ->from($table);

        foreach ($selection as $key => $conf) {
            $query->andWhere(
                $queryBuilder->expr()->eq(
                    $key,
                    $queryBuilder->createNamedParameter($conf['value'], $conf['type'])
                )
            );
        }

        if ($orderBy !== NULL) {
            $query->orderBy($orderBy);
        }

        if ($limit > 0) {
            $query->setMaxResults($limit);
        }

        return $query->execute();
    }
}
