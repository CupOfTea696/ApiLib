<?php

use PHPUnit\Framework\TestCase;
use CupOfTea\ApiLib\ApiDefinition;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

class ApiDefinitionTest extends TestCase
{
    public function testCreate()
    {
        $definition = $this->getDefinitionArray('valid');
        
        $api = ApiDefinition::create($definition);
        
        $this->assertInstanceOf(ApiDefinition::class, $api);
    }
    
    public function testCreateFromPhp()
    {
        $api = ApiDefinition::createFromPhp(__DIR__ . '/fixtures/definition.php');
        
        $this->assertInstanceOf(ApiDefinition::class, $api);
    }
    
    public function testCreateFromJson()
    {
        $api = ApiDefinition::createFromJson(__DIR__ . '/fixtures/definition.json');
        
        $this->assertInstanceOf(ApiDefinition::class, $api);
    }
    
    public function testCreateFromPhpInvalidDataThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The PHP file must return an array');
        
        $api = ApiDefinition::createFromPhp(__DIR__ . '/fixtures/invalidData.php');
    }
    
    public function testCreateFromJsonInvalidDataThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The JSON file must be an object');
        
        $api = ApiDefinition::createFromJson(__DIR__ . '/fixtures/invalidData.json');
    }
    
    public function testCreateBaseMissingThrowsException()
    {
        $definition = $this->getDefinitionArray('baseMissing');
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The base URI must be set');
        
        $api = ApiDefinition::create($definition);
    }
    
    public function testCreateInvalidBaseThrowsException()
    {
        $definition = $this->getDefinitionArray('invalidBase');
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The base URI must be a valid URI');
        
        $api = ApiDefinition::create($definition);
    }
    
    public function testCanCheckIfVersioned()
    {
        $versioned = $this->getDefinition('versioned');
        $unversioned = $this->getDefinition('valid');
        
        $this->assertTrue($versioned->isVersioned());
        $this->assertFalse($unversioned->isVersioned());
    }
    
    public function testCanCheckIfVersionExists()
    {
        $api = $this->getDefinition('versioned');
        
        $this->assertTrue($api->hasVersion('v2'));
        $this->assertFalse($api->hasVersion('v3'));
    }
    
    public function testCanListAvailableVersions()
    {
        $api = $this->getDefinition('versioned');
        
        $this->assertEquals(['v1', 'v2'], $api->getVersions());
    }
    
    public function testCanGiveCurrentVersion()
    {
        $definition = $this->getDefinitionArray('versioned');
        $api = ApiDefinition::create($definition, 'v1');
        
        $this->assertEquals('v1', $api->currentVersion());
    }
    
    public function testCurrentVersionDefaultsToLatest()
    {
        $api = $this->getDefinition('versioned');
        
        $this->assertEquals('v2', $api->currentVersion());
    }
    
    public function testCanChangeVersion()
    {
        $api = $this->getDefinition('versioned');
        
        $this->assertEquals('v2', $api->currentVersion());
        
        $api->useVersion('v1');
        
        $this->assertEquals('v1', $api->currentVersion());
    }
    
    public function testChangeVersionToInvalidVersionThrowsException()
    {
        $api = $this->getDefinition('versioned');
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('There is no version v3');
        
        $api->useVersion('v3');
    }
    
    public function testChangeVersionOnUnversionedApiReturnsFalse()
    {
        $api = $this->getDefinition('valid');
        
        $this->assertFalse($api->useVersion('v1'));
    }
    
    public function testCanGiveBaseUri()
    {
        $unversioned = $this->getDefinition('valid');
        $versioned = $this->getDefinition('versioned');
        
        $this->assertEquals('https://reqres.in/api/', $unversioned->getBaseUri());
        $this->assertEquals('https://reqres.in/api/v2', $versioned->getBaseUri());
    }
    
    public function testCanCheckIfEndpointExists()
    {
        $api = $this->getDefinition('valid');
        
        $this->assertTrue($api->hasEndpoint('colors'));
        $this->assertFalse($api->hasEndpoint('comments'));
    }
    
    public function testCanListEndpoints()
    {
        $api = $this->getDefinition('valid');
        
        $this->assertEquals(['colors', 'users'], $api->getEndpoints());
    }
    
    public function testCanCheckIfEndpointHasAction()
    {
        $api = $this->getDefinition('valid');
        
        $this->assertTrue($api->endpointHasAction('colors', 'index'));
        $this->assertTrue($api->endpointHasAction('colors', 'show'));
        $this->assertFalse($api->endpointHasAction('users', 'delete'));
    }
    
    public function testCanListEndpointActions()
    {
        $api = $this->getDefinition('valid');
        
        $this->assertEquals(['index', 'store', 'show', 'update', 'delete'], $api->getActionsForEndpoint('colors'));
    }
    
    public function testCanListEndpointActionParameters()
    {
        $api = $this->getDefinition('valid');
        
        $this->assertEquals(['colorId'], $api->getParametersForAction('colors', 'show'));
    }
    
    /**
     * @depends testCanListEndpointActionParameters
     */
    public function testCanCheckIfEndpointActionHasParameter()
    {
        $api = $this->getDefinition('valid');
        
        $this->assertTrue($api->actionHasParameter('colors', 'show', 'colorId'));
        $this->assertFalse($api->actionHasParameter('colors', 'show', 'userId'));
        $this->assertFalse($api->actionHasParameter('colors', 'index', 'colorId'));
    }
    
    public function testCanListEndpointActionQueries()
    {
        $api = $this->getDefinition('valid');
        
        $this->assertEquals(['page', 'per_page', 'delay', 'format'], $api->getQueriesForAction('colors', 'index'));
    }
    
    /**
     * @depends testCanListEndpointActionQueries
     */
    public function testCanCheckIfEndpointActionHasQuery()
    {
        $api = $this->getDefinition('valid');
        
        $this->assertTrue($api->actionHasQuery('colors', 'index', 'format'));
        $this->assertFalse($api->actionHasQuery('colors', 'show', 'page'));
    }
    
    public function testCanBuildPath()
    {
        $api = $this->getDefinition('valid');
        
        $this->assertEquals('colors', $api->buildPath('colors', 'index'));
        $this->assertEquals('colors/1', $api->buildPath('colors', 'show', ['colorId' => 1]));
    }
    
    // helpers
    protected function getDefinition($definition)
    {
        return ApiDefinition::create($this->getDefinitionArray($definition));
    }
    
    protected function getDefinitionArray($definition)
    {
        $definitions = [
            'baseMissing' => [
                'endpoints' => [
                    'colors' => [
                        'colors' => ['index', 'store'],
                        'colors/{colorId}' => ['show', 'update', 'delete'],
                    ],
                    'users' => [
                        'users' => ['index'],
                        'users/{userId}' => ['show'],
                    ],
                ],
            ],
            'invalidBase' => [
                'base' => 'api',
            ],
            'valid' => [
                'base' => 'https://reqres.in/api/',
                'endpoints' => [
                    'colors' => [
                        'colors' => ['index', 'store'],
                        'colors/{colorId}' => ['show', 'update', 'delete'],
                    ],
                    'users' => [
                        'users' => ['index'],
                        'users/{userId}' => ['show'],
                    ],
                ],
                'query' => [
                    'colors' => [
                        'format' => ['index', 'show'],
                    ],
                ],
                'global_query' => [
                    'page' => ['index'],
                    'per_page' => ['index'],
                    'delay' => [
                        'index',
                        'show',
                        'store',
                        'update',
                        'delete',
                    ],
                ],
            ],
            'versioned' => [
                'base' => 'https://reqres.in/api/',
                'versions' => [
                    'v1' => [
                        'endpoints' => [
                            'colors' => [
                                'colors' => ['index', 'store'],
                                'colors/{colorId}' => ['show', 'update', 'delete'],
                            ],
                            'users' => [
                                'users' => ['index'],
                                'users/{userId}' => ['show'],
                            ],
                        ],
                    ],
                    'v2' => [
                        'endpoints' => [
                            'hexcodes' => [
                                'hexcodes' => ['index', 'store'],
                                'hexcodes/{hexcodeId}' => ['show', 'update', 'delete'],
                            ],
                            'users' => [
                                'users' => ['index'],
                                'users/{userId}' => ['show'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        
        return $definitions[$definition];
    }
}
