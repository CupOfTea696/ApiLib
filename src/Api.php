<?php

namespace CupOfTea\ApiLib;

use LogicException;
use BadMethodCallException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use CupOfTea\Package\Package;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Client as HttpClient;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use CupOfTea\Package\Contracts\Package as PackageContract;

abstract class Api implements PackageContract
{
    use Package;
    
    /**
     * Package Vendor.
     *
     * @const string
     */
    const VENDOR = 'CupOfTea';
    
    /**
     * Package Name.
     *
     * @const string
     */
    const PACKAGE = 'ApiLib';
    
    /**
     * Package Version.
     *
     * @const string
     */
    const VERSION = '0.0.0';
    
    /**
     * Array representation of the API.
     * 
     * @var array
     */
    protected $api;
    
    /**
     * Default options for the Guzzle Client.
     * 
     * @var array
     */
    protected $options = [];
    
    /**
     * HTTP methods for each API endpoint.
     * 
     * @var array
     */
    protected $methods = [
        'index' => 'GET',
        'show' => 'GET',
        'store' => 'POST',
        'update' => 'PUT',
        'delete' => 'DELETE',
    ];
    
    /**
     * ApiDefinition instance.
     * 
     * @var \CupOfTea\ApiLib\ApiDefinition
     */
    private $definition;
    
    /**
     * Http Client instance.
     * 
     * @var \GuzzleHttp\Client
     */
    private $client;
    
    /**
     * Http Client instances.
     * 
     * @var \GuzzleHttp\Client[]
     */
    private $clients = [];
    
    /**
     * API actions.
     * 
     * @var array
     */
    private $actions = [
        'index',
        'show',
        'store',
        'update',
        'delete',
    ];
    
    /**
     * Http methods that have a body.
     * 
     * @var array
     */
    private $methodsWithBody = [
        'PUT',
        'POST',
        'PATCH',
    ];
    
    /**
     * Current endpoint.
     * 
     * @var string
     */
    private $endpoint;
    
    /**
     * Current action.
     * 
     * @var string
     */
    private $action;
    
    /**
     * Request body.
     * 
     * @var mixed
     */
    private $body;
    
    /**
     * Request parameters.
     * 
     * @var array
     */
    private $parameters = [];
    
    /**
     * Request query.
     * 
     * @var array
     */
    private $query = [];
    
    /**
     * Create a new Api instance.
     * 
     * @return void
     */
    public function __construct()
    {
        if (! Arr::accessible($this->api)) {
            throw new LogicException(static::class . '::$api must be of type array, ' . gettype($this->api) . ' given');
        }
        
        $this->setDefinition(ApiDefinition::create($this->api));
    }
    
    /**
     * Check if the API is versioned.
     * 
     * @return bool
     */
    final public function isVersioned()
    {
        return $this->api()->isVersioned();
    }
    
    /**
     * Check if the API has a given version
     * 
     * @param  string|int $version
     * @return bool
     */
    final public function hasVersion($version)
    {
        return $this->api()->hasVersion($this->normalizeVersion($version));
    }
    
    /**
     * Get the available versions of the API.
     * 
     * @return array
     */
    final public function getVersions()
    {
        return $this->api()->getVersions();
    }
    
    /**
     * Get the current active version.
     * 
     * @return string|null
     */
    final public function currentVersion()
    {
        return $this->api()->currentVersion();
    }
    
    /**
     * Use a given API version.
     * 
     * @param string $version
     */
    final public function useVersion($version)
    {
        $this->api()->useVersion($this->normalizeVersion($version));
    }
    
    /**
     * Check if the current version has a given endpoint.
     * 
     * @param  string $endpoint
     * @return bool
     */
    final public function hasEndpoint($endpoint)
    {
        return $this->api()->hasEndpoint($endpoint);
    }
    
    /**
     * Get the endpoints for the current version.
     * 
     * @return array
     */
    final public function getEndPoints()
    {
        return $this->api()->getEndPoints();
    }
    
    /**
     * Check if the current endpoint has a given action.
     * 
     * @param  string $action
     * @return bool
     */
    final public function hasAction($action)
    {
        if (! $this->endpoint) {
            return false;
        }
        
        return $this->api()->endpointHasAction($this->endpoint, $action);
    }
    
    /**
     * Get the actions for the current endpoint.
     * 
     * @return array
     */
    final public function getActions()
    {
        if (! $this->endpoint) {
            return [];
        }
        
        return $this->api()->getActionsForEndpoint($this->endpoint);
    }
    
    /**
     * Check if the current endpoint action has a body.
     * 
     * @return bool
     */
    final public function hasBody()
    {
        if (! $this->endpoint || ! $this->action) {
            return false;
        }
        
        return in_array($this->getActionMethod(), $this->methodsWithBody);
    }
    
    /**
     * Check if the current endpoint action has a given parameter.
     * 
     * @param  string $parameter
     * @return bool
     */
    final public function hasParameter($parameter)
    {
        if (! $this->endpoint || ! $this->action) {
            return false;
        }
        
        return $this->api()->actionHasParameter($this->endpoint, $this->action, $parameter);
    }
    
    /**
     * Get the parameters for the current endpoint action.
     * 
     * @return array
     */
    final public function getParameters()
    {
        if (! $this->endpoint || ! $this->action) {
            return [];
        }
        
        return $this->api()->getParametersForAction($this->endpoint, $this->action);
    }
    
    /**
     * Check if the current endpoint action has a given query.
     * 
     * @param  string $query
     * @return bool
     */
    final public function hasQuery($query)
    {
        if (! $this->endpoint || ! $this->action) {
            return false;
        }
        
        return $this->api()->actionHasQuery($this->endpoint, $this->action, $query);
    }
    
    /**
     * Get the queries for the current endpoint action.
     * 
     * @return array
     */
    final public function getQueries()
    {
        if (! $this->endpoint || ! $this->action) {
            return [];
        }
        
        return $this->api()->getQueriesForAction($this->endpoint, $this->action);
    }
    
    /**
     * Call the API.
     * 
     * @return mixed
     */
    final public function call()
    {
        if (! $this->endpoint || ! $this->action) {
            $notSet = ! $this->endpoint ? 'endpoint' : 'action';
            
            throw new BadMethodCallException("An $notSet must be set before calling the API");
        }
        
        try {
            $result = $this->processResponse($this->send());
        } catch (RequestException $e) {
            $result = $this->processHttpException($e);
        } finally {
            $this->reset();
        }
        
        return $result;
    }
    
    // API magic.
    final public function __call($method, $args)
    {
        try {
            if (! $this->endpoint) {
                return $this->handleEndpoint($method, $args);
            }
            
            if (! $this->action) {
                return $this->handleAction($method, $args);
            }
            
            if ($this->hasBody() && $method === 'body') {
                return $this->setBody(...$args);
            }
            
            if ($this->hasParameter($method)) {
                return $this->handleParameter($method, ...$args);
            }
            
            return $this->handleQuery($method, ...$args);
        } catch (BadMethodCallException | InvalidArgumentException $e) {
            $message = $e->getMessage();
            $endpointRegex = '~^The endpoint \'([^\']+)\' does not exist$~';
            $actionRegex = '~^The endpoint \'([^\']+)\' does not support \'([^\']+)\'$~';
            $parameterQueryRegex = '~^The (parameter|query) \'([^\']+)\' is not supported by \'([^\']+)\'$~';
            $actionMissingArgRegex = '~^Missing argument (\d+) for \'([^\']+)\'$~';
            $actionArgTypeMisatchRegex = '~^Argument (\d+) passed to \'([^\']+)\' must be of type (\w+), (\w+) given$~';
            
            if (preg_match($endpointRegex, $message)) {
                throw new BadMethodCallException("Call to undefined method " . static::class . "::{$method}()");
            }
            
            if (preg_match($actionRegex, $message)) {
                throw new BadMethodCallException("Call to undefined method " . static::class . "::{$this->endpoint}()->{$method}()");
            }
            
            if (preg_match($parameterQueryRegex, $message)) {
                throw new BadMethodCallException("Call to undefined method " . static::class . "::{$this->endpoint}()->{$this->action}()->{$method}()");
            }
            
            if (preg_match($actionMissingArgRegex, $message, $matches)) {
                throw new BadMethodCallException("Missing argument {$matches[1]} for " . static::class . "::{$this->endpoint}()->{$this->action}()");
            }
            
            if (preg_match($actionArgTypeMisatchRegex, $message, $matches)) {
                throw new InvalidArgumentException("Argument {$matches[1]} passed to " . static::class . "::{$this->endpoint}()->{$this->action}() must be of type {$matches[3]}, {$matches[4]} given");
            }
            
            throw $e;
        }
    }
    
    /**
     * Normalize the version string.
     * 
     * @param  string $version
     * @return string
     */
    protected function normalizeVersion($version)
    {
        return Str::start($version, 'v');
    }
    
    /**
     * Process the API call's response.
     * 
     * @param  \Psr\Http\Message\ResponseInterface $response
     * @return mixed
     */
    abstract protected function processResponse(ResponseInterface $response);
    
    /**
     * Process the API call's RequestException.
     * 
     * @param  \GuzzleHttp\Exception\RequestException $e
     * @return mixed
     */
    abstract protected function processHttpException(RequestException $e);
    
    /**
     * Reset the API to its initial state.
     * 
     * @return void
     */
    final protected function reset()
    {
        $this->endpoint = $this->action = $this->body = null;
        $this->parameters = $this->query = [];
    }
    
    /**
     * Set the ApiDefinition instance.
     * 
     * @param  \CupOfTea\ApiLib\ApiDefinition $definition
     * @return void
     */
    final protected function setDefinition(ApiDefinition $definition)
    {
        $this->definition = $definition;
    }
    
    // $this->users();
    /**
     * Handle a dynamic endpoint call.
     * 
     * @param  string $endpoint
     * @param  array $args
     * @return \CupOfTea\ApiLib\Api
     */
    final protected function handleEndpoint($endpoint, $args)
    {
        if (! $this->hasEndpoint($endpoint)) {
            throw new InvalidArgumentException("The endpoint '{$endpoint}' does not exist");
        }
        
        $this->endpoint = $endpoint;
        
        if (count($args)) {
            $action = array_shift($args);
            
            $this->handleAction($action, $args);
        }
        
        return $this;
    }
    
    // $this->users()->index();
    // $this->users()->index(['delay' => 1]);
    // $this->users()->show();
    // $this->users()->show(1);
    // $this->users()->show(2, ['delay' => 1]);
    // $this->users()->update(1, ['fname' => 'Frankie', 'lname' => Wittevrongel]);
    // $this->users()->update(1, ['fname' => 'Frankie', 'lname' => Wittevrongel], ['delay' => 1]);
    /**
     * Handle a dynamic action call.
     * 
     * @param  string $action
     * @param  array $args
     * @return \CupOfTea\ApiLib\Api
     */
    final protected function handleAction($action, $args)
    {
        if (! $this->hasAction($action)) {
            throw new InvalidArgumentException("The endpoint '{$this->endpoint}' does not support '{$action}'");
        }
        
        $argCount = count($args);
        
        $this->action = $action;
        
        if ($argCount === 0) {
            return $this;
        } elseif ($this->hasBody()) {
            if ($argCount < count($parameterNames = $this->getParameters()) + 1) {
                throw new BadMethodCallException("Missing argument " . (count($args) + 1) . " for '{$this->endpoint}.{$action}'");
            }
            
            $values = array_splice($args, 0, count($parameterNames));
            $parameters = array_combine($parameterNames, $values);
            $body = array_shift($args);
            
            $this->setParameters($parameters);
            $this->setBody($body);
        } elseif ($argCount < count($parameterNames = $this->getParameters())) {
            throw new BadMethodCallException("Missing argument " . (count($args) + 1) . " for '{$this->endpoint}.{$action}'");
        } else {
            $values = array_splice($args, 0, count($parameterNames));
            $parameters = array_combine($parameterNames, $values);
            
            $this->setParameters($parameters);
        }
        
        if (count($args)) {
            if (! Arr::accessible($query = head($args))) {
                $type = gettype($query);
                
                throw new InvalidArgumentException("Argument {$argCount} passed to '{$this->endpoint}.{$action}' must be of type array, {$type} given");
            }
            
            $this->setQuery($query);
        }
        
        return $this;
    }
    
    // $this->users()->show()->userId(1);
    /**
     * Handle a dynamic parameter call.
     * 
     * @param  string $parameter
     * @param  mixed $value
     * @return \CupOfTea\ApiLib\Api
     */
    final protected function handleParameter($parameter, $value)
    {
        if (! $this->hasParameter($parameter)) {
            throw new InvalidArgumentException("The parameter '{$parameter}' is not supported by '{$this->endpoint}.{$this->action}'");
        }
        
        $this->parameters[$parameter] = $value;
        
        return $this;
    }
    
    // $this->users()->index()->page(2);
    /**
     * Handle a dynamic query call.
     * 
     * @param  string $query
     * @param  mixed $value
     * @return \CupOfTea\ApiLib\Api
     */
    final protected function handleQuery($query, $value = true)
    {
        if (! $this->hasQuery($query)) {
            throw new InvalidArgumentException("The query '{$query}' is not supported by '{$this->endpoint}.{$this->action}'");
        }
        
        $this->query[$query] = $value;
        
        return $this;
    }
    
    /**
     * Set the request body.
     * 
     * @param  mixed $body
     * @return void
     */
    final protected function setBody($body)
    {
        if (! is_null($body) && ! is_scalar($body) && ! is_resource($body) && ! Arr::accessible($body) && ! $body instanceof StreamInterface) {
            throw new InvalidArgumentException('Provide a valid request body');
        }
        
        $this->body = $body;
    }
    
    /**
     * Set the request parameters.
     * 
     * @param  array $parameters
     * @return void
     */
    final protected function setParameters(array $parameters)
    {
        foreach ($parameters as $parameter => $value) {
            $this->handleParameter($parameter, $value);
        }
    }
    
    /**
     * Set the request query.
     * 
     * @param  array $query
     * @return void
     */
    final protected function setQuery(array $query)
    {
        foreach ($query as $query => $value) {
            $this->handleQuery($query, $value);
        }
    }
    
    /**
     * Get the current endpoint.
     * 
     * @return string
     */
    final protected function endpoint()
    {
        return $this->endpoint;
    }
    
    /**
     * Get the current action.
     * 
     * @return string
     */
    final protected function action()
    {
        return $this->action;
    }
    
    /**
     * Get the request body.
     * 
     * @return mixed
     */
    final protected function body()
    {
        return $this->body;
    }
    
    /**
     * Get the request parameters.
     * 
     * @return array
     */
    final protected function parameters()
    {
        return $this->parameters;
    }
    
    /**
     * Get the request query.
     * 
     * @return array
     */
    final protected function query()
    {
        return $this->query;
    }
    
    /**
     * Send the API request.
     * 
     * @return \Psr\Http\Message\ResponseInterface
     */
    final protected function send()
    {
        $client = $this->getClient();
        $method = $this->getActionMethod();
        $path = $this->api()->buildPath($this->endpoint(), $this->action(), $this->parameters());
        
        $options = [
            'query' => $this->query(),
        ];
        
        if ($body = $this->body()) {
            if (Arr::accessible($body)) {
                $options['json'] = $body;
            } else {
                $options['body'] = $body;
            }
        }
        
        return $client->request($method, $path, $options);
    }
    
    /**
     * Get the ApiDefinition instance.
     * 
     * @return \CupOfTea\ApiLib\ApiDefinition
     */
    final protected function api()
    {
        return $this->definition;
    }
    
    /**
     * Get the API's base URI.
     * 
     * @return \GuzzleHttp\Psr7\Uri
     */
    final protected function getBaseUri()
    {
        return $this->api()->getBaseUri();
    }
    
    /**
     * Get the Http Client.
     * 
     * @return \GuzzleHttp\Client
     */
    final protected function getClient()
    {
        return $this->isVersioned() ? $this->getVersionedClient() : $this->getUnversionedClient();
    }
    
    /**
     * Get the versioned Http Client for the current version.
     * 
     * @return \GuzzleHttp\Client
     */
    private function getVersionedClient()
    {
        if (! isset($this->clients[$this->getVersion()])) {
            $this->clients[$this->getVersion()] = $this->createClient();
        }
        
        return $this->clients[$this->getVersion()];
    }
    
    /**
     * Get the Http Client for an unversioned API.
     * 
     * @return \GuzzleHttp\Client
     */
    private function getUnversionedClient()
    {
        if (! isset($this->client)) {
            $this->client = $this->createClient();
        }
        
        return $this->client;
    }
    
    /**
     * Create the Http Client.
     * 
     * @return \GuzzleHttp\Client
     */
    private function createClient()
    {
        $options = $this->options;
        $options['headers'] = $this->getHeaders($options);
        $options['base_uri'] = $this->getBaseUri();
        
        return new HttpClient($options);
    }
    
    /**
     * Get the headers for the Http Client.
     * 
     * @param  array $options
     * @return array
     */
    private function getHeaders(array $options)
    {
        $userAgent  = static::getPackageInfo('V/P/n');
        $userAgent .= ' Guzzle/' . ClientInterface::VERSION;
        
        if (extension_loaded('curl')) {
            $userAgent .= ' curl/' . curl_version()['version'];
        }
        
        $userAgent .= ' PHP/' . PHP_VERSION;
        
        $default = [
            'Accept' => 'application/json',
            'User-Agent' => $userAgent,
        ];
        
        return array_merge($default, Arr::get($options, 'headers', []));
    }
    
    /**
     * Get the Http method for the current action.
     * 
     * @return string
     */
    private function getActionMethod()
    {
        return Arr::get($this->methods, $this->action, 'GET');
    }
}
