<?php

namespace CupOfTea\ApiLib;

use GuzzleHttp\Psr7;
use CupOfTea\Support\Json;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Illuminate\Filesystem\Filesystem;

final class ApiDefinition
{
    /**
     * API definition.
     *
     * @var array
     */
    protected $definition;
    
    /**
     * API.
     *
     * @var array
     */
    protected $api;
    
    /**
     * The current version.
     *
     * @var string
     */
    protected $version;
    
    /**
     * Create a new ApiDefinition instance.
     *
     * @param  array|ArrayAccess $definition
     * @param  string $version
     * @return void
     */
    protected function __construct($definition, $version = null)
    {
        if (! Arr::accessible($definition)) {
            $type = gettype($definition);
            
            throw new InvalidArgumentException('Argument 1 of ' . static::class . "::__construct() must be of type array, $type given");
        }
        
        $this->definition = $definition;
        
        if ($this->isVersioned()) {
            $this->version = $version ?: Arr::last(array_keys($this->definition['versions']));
        }
        
        $this->validate();
    }
    
    /**
     * Create a new ApiDefinition instance from an array.
     *
     * @param  array|ArrayAccess $data
     * @param  string $version
     * @return \CupOfTea\ApiLib\ApiDefinition
     */
    public static function create($data = [], $version = null)
    {
        return new static($data, $version);
    }
    
    /**
     * Create a new ApiDefinition instance from a JSON file.
     *
     * @param  string $path
     * @param  string $version
     * @return \CupOfTea\ApiLib\ApiDefinition
     */
    public static function createFromJson(string $path, $version = null)
    {
        $file = new Filesystem;
        $json = $file->get($path);
        $data = Json::decode($json, true);
        
        if (! is_array($data)) {
            throw new InvalidArgumentException('The JSON file must be an object');
        }
        
        return new static($data, $version);
    }
    
    /**
     * Create a new ApiDefinition instance from a PHP file.
     *
     * @param  string $path
     * @param  string $version
     * @return \CupOfTea\ApiLib\ApiDefinition
     */
    public static function createFromPhp(string $path, $version = null)
    {
        $file = new Filesystem;
        $text = $file->get($path);
        $data = include $path;
        
        if (! Arr::accessible($data)) {
            throw new InvalidArgumentException('The PHP file must return an array');
        }
        
        return new static($data, $version);
    }
    
    /**
     * Check if the API is versioned.
     *
     * @return bool
     */
    public function isVersioned()
    {
        return isset($this->definition['versions']) && Arr::accessible($this->definition['versions']) && count($this->definition['versions']);
    }
    
    /**
     * Check if the API has a given version.
     *
     * @param  string|int $version
     * @return bool
     */
    public function hasVersion($version)
    {
        return isset($this->definition['versions']) && Arr::accessible($this->api['versions']) && Arr::has($this->api['versions'], $version);
    }
    
    /**
     * Get the available versions of the API.
     *
     * @return array
     */
    public function getVersions()
    {
        return isset($this->definition['versions']) && Arr::accessible($this->api['versions']) ? array_keys($this->api['versions']) : [];
    }
    
    /**
     * Get the current active version.
     *
     * @return string|null
     */
    public function currentVersion()
    {
        return $this->version;
    }
    
    /**
     * Use a given API version.
     *
     * @param string $version
     * @return void
     */
    public function useVersion($version)
    {
        if (! $this->isVersioned()) {
            return false;
        }
        
        if (! $this->hasVersion($version)) {
            throw new InvalidArgumentException('There is no version ' . $version);
        }
        
        $this->version = $version;
    }
    
    /**
     * Get the base URI for the API.
     *
     * @return \GuzzleHttp\Psr7\Uri
     */
    public function getBaseUri()
    {
        return Psr7\Uri::resolve($this->api['base'], ($this->isVersioned() ? $this->currentVersion() : ''));
    }
    
    /**
     * Check if the current version has a given endpoint.
     *
     * @param  string $endpoint
     * @return bool
     */
    public function hasEndpoint($endpoint)
    {
        return Arr::has($this->endpoints(), $endpoint);
    }
    
    /**
     * Get the endpoints for the current version.
     *
     * @return array
     */
    public function getEndpoints()
    {
        return array_keys($this->endpoints());
    }
    
    /**
     * Check if the given endpoint has a given action.
     *
     * @param  string $endpoint
     * @param  string $action
     * @return bool
     */
    public function endpointHasAction($endpoint, $action)
    {
        return Arr::has($this->endpoints(), implode('.', [$endpoint, $action]));
    }
    
    /**
     * Get the actions for a given endpoint.
     *
     * @param  string $endpoint
     * @return array
     */
    public function getActionsForEndpoint($endpoint)
    {
        return array_keys(Arr::get($this->endpoints(), $endpoint));
    }
    
    /**
     * Check if the given endpoint action has a given parameter.
     *
     * @param  string $endpoint
     * @param  string $action
     * @param  string $parameter
     * @return bool
     */
    public function actionHasParameter($endpoint, $action, $parameter)
    {
        return in_array($parameter, $this->getParametersForAction($endpoint, $action));
    }
    
    /**
     * Get the parameters for a given endpoint action.
     *
     * @param  string $endpoint
     * @param  string $action
     * @return array
     */
    public function getParametersForAction($endpoint, $action)
    {
        return Arr::get($this->parameters(), implode('.', [$endpoint, $action]));
    }
    
    /**
     * Check if the given endpoint action has a given query.
     *
     * @param  string $endpoint
     * @param  string $action
     * @param  string $query
     * @return bool
     */
    public function actionHasQuery($endpoint, $action, $query)
    {
        return in_array($query, $this->getQueriesForAction($endpoint, $action));
    }
    
    /**
     * Get the queries for a given endpoint action.
     *
     * @param  string $endpoint
     * @param  string $action
     * @return array
     */
    public function getQueriesForAction($endpoint, $action)
    {
        return Arr::get($this->query(), implode('.', [$endpoint, $action]));
    }
    
    /**
     * Build the API request path for a given endpoint action.
     *
     * @param  string $endpoint
     * @param  string $action
     * @param  array $parameters
     * @return string
     */
    public function buildPath($endpoint, $action, array $parameters = [])
    {
        $path = Arr::get($this->endpoints(), implode('.', [$endpoint, $action]));
        $path = preg_replace_callback('/\\{([^\\}]+)\\}/', function ($matches) use ($parameters) {
            return Arr::get($parameters, $matches[1]);
        }, $path);
        
        return $path;
    }
    
    /**
     * Validate the API definition.
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function validate()
    {
        if (empty($this->definition['base'])) {
            throw new InvalidArgumentException('The base URI must be set');
        }
        
        if (! filter_var($this->definition['base'], FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('The base URI must be a valid URI');
        }
        
        $this->build();
    }
    
    /**
     * Get the property for the current API version.
     *
     * @param  string $prop
     * @return mixed
     */
    protected function current($prop = null)
    {
        $current = $this->isVersioned() ? $this->api['versions'][$this->version] : $this->api;
        
        if ($prop) {
            return $current[$prop];
        }
        
        return $current;
    }
    
    /**
     * Get the endpoint definitions for the current version.
     *
     * @return array
     */
    protected function endpoints()
    {
        return $this->current('endpoints');
    }
    
    /**
     * Get the parameter definitions for the current version.
     *
     * @return array
     */
    protected function parameters()
    {
        return $this->current('parameters');
    }
    
    /**
     * Get the query definitions for the current version.
     *
     * @return array
     */
    protected function query()
    {
        return $this->current('query');
    }
    
    /**
     * Buid the API defitions.
     *
     * @return void
     */
    protected function build()
    {
        $this->api = Arr::except($this->definition, ['base', 'versions', 'endpoints', 'query', 'global_query']);
        $this->api['base'] = Psr7\uri_for($this->definition['base']);
        
        if ($this->isVersioned()) {
            $this->api['versions'] = [];
            
            foreach ($this->definition['versions'] as $version => $definition) {
                $this->api['versions'][$version] = $this->buildVersion($definition);
            }
            
            return;
        }
        
        $this->api = array_merge($this->api, $this->buildVersion($this->definition));
    }
    
    /**
     * Build the API defition from an array.
     *
     * @param  array $definition
     * @return array
     */
    protected function buildVersion($definition)
    {
        $api = Arr::except($definition, ['base', 'endpoints', 'query', 'global_query']);
        
        foreach ($definition['endpoints'] as $endpointName => $endpoint) {
            $query = [];
            $endpoints = [];
            $parameters = [];
            
            foreach ($endpoint as $path => $actions) {
                $paths = array_fill(0, count($actions), $path);
                $params = array_fill(0, count($actions), $this->getPathParameters($path));
                
                $endpoints = array_merge($endpoints, array_combine($actions, $paths));
                $parameters = array_merge($parameters, array_combine($actions, $params));
            }
            
            $api['endpoints'][$endpointName] = $endpoints;
            $api['parameters'][$endpointName] = $parameters;
            
            if (! empty($definition['global_query'])) {
                foreach ($definition['global_query'] as $queryString => $actions) {
                    foreach ($actions as $action) {
                        if (! Arr::has($api['endpoints'][$endpointName], $action)) {
                            continue;
                        }
                        
                        $query[$action][] = $queryString;
                    }
                }
            }
            
            $api['query'][$endpointName] = $query;
        }
        
        if (! empty($definition['query'])) {
            foreach ($definition['query'] as $endpointName => $endpoint) {
                foreach ($endpoint as $queryString => $actions) {
                    foreach ($actions as $action) {
                        if (in_array($queryString, $api['query'][$endpointName][$action])) {
                            continue;
                        }
                        
                        $api['query'][$endpointName][$action][] = $queryString;
                    }
                }
            }
        }
        
        return $api;
    }
    
    /**
     * Get the parameter names from the URI path.
     *
     * @param  string $path
     * @return array
     */
    protected function getPathParameters($path)
    {
        preg_match_all('/\\{([^\\}]+)\\}/', $path, $matches);
        
        return $matches[1];
    }
}
