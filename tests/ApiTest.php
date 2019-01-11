<?php

use CupOfTea\ApiLib\Api;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Handler\MockHandler;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;

class ApiTest extends TestCase
{
    protected $api;
    
    protected $handler;
    
    protected $versioned;
    
    protected function setUp()
    {
        $this->handler = new MockHandler;
        
        $this->api = new class($this->handler) extends Api {
            protected $api = [
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
                'query' => [],
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
            ];
            
            public function __construct($handler)
            {
                $this->options['handler'] = $handler;
                
                parent::__construct();
            }
            
            public function getEndpoint()
            {
                return $this->endpoint();
            }
            
            public function getAction()
            {
                return $this->action();
            }
            
            public function getBody()
            {
                return $this->body();
            }
            
            public function getParams()
            {
                return $this->parameters();
            }
            
            public function getQuery()
            {
                return $this->query();
            }
            
            public function doReset()
            {
                $this->reset();
            }
            
            protected function processResponse(ResponseInterface $response)
            {
                return $response;
            }
            
            protected function processHttpException(RequestException $e)
            {
                return $e;
            }
        };
        
        $this->versioned = new class extends Api {
            protected $api = [
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
            ];
            
            protected function processResponse(ResponseInterface $response)
            {
                return $response;
            }
            
            protected function processHttpException(RequestException $e)
            {
                return $e;
            }
        };
    }
    
    public function testNormalizesVersions()
    {
        $this->assertTrue($this->versioned->hasVersion(1));
        
        $this->versioned->useVersion(1);
        $this->assertEquals('v1', $this->versioned->currentVersion());
    }
    
    public function testCanSetEndpoint()
    {
        $this->api->users();
        
        $this->assertEquals('users', $this->api->getEndpoint());
    }
    
    public function testInvalidEndpointThrowsException()
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessageRegExp('/Call to undefined method .*?::comments\(\)/');
        
        $this->api->comments();
    }
    
    /**
     * @depends testCanSetEndpoint
     */
    public function testCanSetAction()
    {
        $this->api->users()->index();
        
        $this->assertEquals('users', $this->api->getEndpoint());
    }
    
    /**
     * @depends testCanSetEndpoint
     */
    public function testInvalidActionThrowsException()
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessageRegExp('/Call to undefined method .*?::users\(\)->delete\(\)/');
        
        $this->api->users()->delete();
    }
    
    /**
     * @depends testCanSetEndpoint
     */
    public function testCanSetActionThroughEndpoint()
    {
        $this->api->users('index');
        
        $this->assertEquals('index', $this->api->getAction());
    }
    
    /**
     * @depends testCanSetAction
     */
    public function testCanSetParameter()
    {
        $this->api->users()->show()->userId(1);
        
        $this->assertEquals(['userId' => 1], $this->api->getParams());
    }
    
    /**
     * @depends testCanSetAction
     */
    public function testCanSetParametersThroughAction()
    {
        $this->api->users()->show(1);
        
        $this->assertEquals(['userId' => 1], $this->api->getParams());
    }
    
    /**
     * @depends testCanSetAction
     */
    public function testCanSetBody()
    {
        $this->api->colors()->store()->body('body');
        
        $this->assertEquals('body', $this->api->getBody());
    }
    
    /**
     * @depends testCanSetParametersThroughAction
     */
    public function testCanSetBodyThroughAction()
    {
        $this->api->colors()->store('body');
        
        $this->assertEquals('body', $this->api->getBody());
        
        $this->api->doReset();
        $this->api->colors()->update(1, 'body');
        
        $this->assertEquals('body', $this->api->getBody());
    }
    
    /**
     * @depends testCanSetParametersThroughAction
     */
    public function testMissingActionParametersThrowsException()
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessageRegExp('/Missing argument \d+ for .*?::colors\(\)->update\(\)/');
        
        $this->api->colors()->update(1);
    }
    
    /**
     * @depends testCanSetAction
     */
    public function testCanSetQuery()
    {
        $this->api->users()->index()->page(2);
        
        $this->assertEquals(['page' => 2], $this->api->getQuery());
    }
    
    /**
     * @depends testCanSetAction
     */
    public function testInvalidQueryThrowsException()
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessageRegExp('/Call to undefined method .*?::users\(\)->index\(\)->offset\(\)/');
        
        $this->api->users()->index()->offset(2);
    }
    
    /**
     * @depends testCanSetAction
     */
    public function testCanSetQueryThroughAction()
    {
        $this->api->users()->index(['page' => 2]);
        
        $this->assertEquals(['page' => 2], $this->api->getQuery());
    }
    
    /**
     * @depends testCanSetAction
     */
    public function testInvalidArgumentForQueryThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/Argument \d+ passed to .*?::users\(\)->index\(\) must be of type array, integer given/');
        
        $this->api->users()->index(2);
    }
    
    /**
     * @depends testCanSetEndpoint
     */
    public function testCanCheckIfCurrentEndpointHasAction()
    {
        $this->assertFalse($this->api->hasAction('index'));
        
        $this->api->users();
        
        $this->assertTrue($this->api->hasAction('index'));
        $this->assertFalse($this->api->hasAction('delete'));
    }
    
    /**
     * @depends testCanSetEndpoint
     */
    public function testCanListCurrentEndpointActions()
    {
        $this->assertEquals([], $this->api->getActions());
        
        $this->api->users();
        
        $this->assertEquals(['index', 'show'], $this->api->getActions());
    }
    
    /**
     * @depends testCanSetAction
     */
    public function testCanCheckIfCurrentEndpointActionHasParameter()
    {
        $this->assertFalse($this->api->hasParameter('userId'));
        
        $this->api->users();
        
        $this->assertFalse($this->api->hasParameter('userId'));
        
        $this->api->show();
        
        $this->assertTrue($this->api->hasParameter('userId'));
        $this->assertFalse($this->api->hasParameter('colorId'));
    }
    
    /**
     * @depends testCanSetAction
     */
    public function testCanListCurrentEndpointActionParameters()
    {
        $this->assertEquals([], $this->api->getParameters());
        
        $this->api->users();
        
        $this->assertEquals([], $this->api->getParameters());
        
        $this->api->show();
        
        $this->assertEquals(['userId'], $this->api->getParameters());
    }
    
    /**
     * @depends testCanSetAction
     */
    public function testCanCheckIfCurrentEndpointActionHasQuery()
    {
        $this->assertFalse($this->api->hasQuery('page'));
        
        $this->api->colors();
        
        $this->assertFalse($this->api->hasQuery('page'));
        
        $this->api->index();
        
        $this->assertTrue($this->api->hasQuery('page'));
    }
    
    /**
     * @depends testCanSetAction
     */
    public function testCanListCurrentEndpointActionQueries()
    {
        $this->assertEquals([], $this->api->getQueries());
        
        $this->api->colors();
        
        $this->assertEquals([], $this->api->getQueries());
        
        $this->api->index();
        
        $this->assertEquals(['page', 'per_page', 'delay'], $this->api->getQueries());
    }
    
    /**
     * @depends testCanSetAction
     */
    public function testCanMakeApiCall()
    {
        $this->handler->append(new Response, new RequestException('', new Request('GET', 'https://reqres.in/api/')));
        
        $response = $this->api->users()->index()->call();
        
        $this->assertInstanceOf(ResponseInterface::class, $response);
        
        $exception = $this->api->users()->show(23)->call();
        
        $this->assertInstanceOf(RequestException::class, $exception);
    }
    
    public function testApiCallWithoutEndpointThrowsException()
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('An endpoint must be set before calling the API');
        
        $this->api->call();
    }
    
    /**
     * @depends testCanSetEndpoint
     */
    public function testApiCallWithoutActionThrowsException()
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('An action must be set before calling the API');
        
        $this->api->users()->call();
    }
}
