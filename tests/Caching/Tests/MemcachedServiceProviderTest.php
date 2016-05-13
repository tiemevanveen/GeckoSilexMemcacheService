<?php

/*
 * This file is part of the GeckoPackages.
 *
 * (c) GeckoPackages https://github.com/GeckoPackages
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use GeckoPackages\Silex\Services\Caching\MemcachedServiceProvider;
use Silex\Application;

/**
 * @author SpacePossum
 *
 * @internal
 */
final class MemcachedServiceProviderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @requires extension memcached
     */
    public function testNoLoggerAtDefault()
    {
        $app = new Application();
        $app['logger'] = false;
        $app->register(new MemcachedServiceProvider());
        $this->assertInstanceof('GeckoPackages\Silex\Services\Caching\Clients\Memcached', $app['memcache']);
        $this->assertSame('', $app['memcache']->getPrefix());
    }

    /**
     * @requires extension memcached
     */
    public function testDefaults()
    {
        $name = 'UnitTest';
        $app = new Application();
        $app['debug'] = true;
        $app['logger'] = new TestLogger();
        $app->register(
            new MemcachedServiceProvider(),
            array(
                'memcache.prefix' => $name,
            )
        );

        /** @var GeckoPackages\MemcacheMock\MemcachedLogger $logger */
        $logger = $app['memcache']->getLogger();
        $this->assertInstanceOf('GeckoPackages\MemcacheMock\MemcachedLogger', $logger);

        /** @var array $calls */
        $calls = $logger->getLogger()->getDebugLog();
        $this->assertInternalType('array', $calls);
        $this->assertCount(2, $calls);

        $this->assertSame('addServer', $calls[0][0]);
        $this->assertSame(
            array(
                'host' => '127.0.0.1',
                'port' => 11211,
                'weight' => 0,
            ),
            $calls[0][1]
        );

        $this->assertSame('setOption', $calls[1][0]);
        $this->assertSame(
            array(
                'option' => Memcached::OPT_PREFIX_KEY,
                'value' => $name,
            ),
            $calls[1][1]
        );

        $prefix = $app['memcache']->getOption(\Memcached::OPT_PREFIX_KEY);
        $this->assertSame($name, $prefix);
        $this->assertSame($name, $app['memcache']->getPrefix());
        $servers = $app['memcache']->getServerList();
        $this->assertInternalType('array', $servers);
        $this->assertCount(1, $servers);
        $server = $servers[0];
        $this->assertArrayHasKey('host', $server);
        $this->assertSame('127.0.0.1', $server['host']);
        $this->assertArrayHasKey('port', $server);
        $this->assertSame(11211, $server['port']);
    }

    /**
     * @requires extension memcached
     */
    public function testConfigSetting()
    {
        $prefix = 'UnitTest2';
        $app = new Application();
        $app['name'] = 'UnitTest';
        $app->register(
            new MemcachedServiceProvider(),
            array(
                'memcache.prefix' => $prefix,
                'memcache.servers' => array(
                    array('127.0.0.2', 11212),
                    array('127.0.0.3', '11213'),
                    array('127.0.0.4'),
                ),
            )
        );
        $this->runCacheTest($app, $prefix);
    }

    /**
     * @requires extension memcached
     */
    public function testConfigSettingLater()
    {
        $prefix = 'UnitTest3';
        $app = new Application();
        $app['name'] = 'UnitTest';
        $app->register(new MemcachedServiceProvider());
        $app['memcache.prefix'] = $prefix;
        $app['memcache.servers'] =
            array(
                array('127.0.0.2', 11212),
                array('127.0.0.3', '11213'),
                array('127.0.0.4'),
            );
        $this->runCacheTest($app, $prefix);
    }

    public function testCustomClient()
    {
        $app = new Application();
        $app->register(new MemcachedServiceProvider(), array('memcache.client' => 'CustomCacheClientTestClass'));
        $app['memcache']->addServer();
    }

    public function testMockClient()
    {
        $app = new Application();
        $app->register(new MemcachedServiceProvider(), array('memcache.client' => 'mock'));
        $this->assertInstanceOf('GeckoPackages\MemcacheMock\MemcachedMock', $app['memcache']);
    }

    public function testMockWithLoggerClient()
    {
        $logger = new TestLogger();
        $app = new Application();
        $app['logger'] = $logger;
        $app->register(new MemcachedServiceProvider(), array('memcache.client' => 'mock'));
        $this->assertInstanceOf('GeckoPackages\MemcacheMock\MemcachedMock', $app['memcache']);
        $this->assertSame($logger, $app['memcache']->getLogger()->getLogger());
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessageRegExp #^Cannot find class "\\Foo\\Bar" to use as cache client.$#
     */
    public function testExceptionMissingCustomClient()
    {
        $app = new Application();
        $app->register(new MemcachedServiceProvider(), array('memcache.client' => '\Foo\Bar'));
        $app['memcache']->getServerList();
    }

    public function testServiceName()
    {
        $app = new Application();

        $name1 = 'memcached';
        $prefix1 = 'prefix1';
        $service1 = new MemcachedServiceProvider($name1);
        $app->register($service1, array($name1.'.client' => 'mock', $name1.'.prefix' => $prefix1));

        $name2 = 'cache';
        $prefix2 = 'prefix2';
        $service2 = new MemcachedServiceProvider($name2);
        $app->register($service2, array($name2.'.client' => 'mock', $name2.'.prefix' => $prefix2));

        $this->assertFalse(isset($app['memcache']));

        $this->assertTrue(isset($app[$name1]));
        $this->assertInstanceOf('GeckoPackages\MemcacheMock\MemcachedMock', $app[$name1]);

        $this->assertTrue(isset($app[$name2]));
        $this->assertInstanceOf('GeckoPackages\MemcacheMock\MemcachedMock', $app[$name2]);

        $app[$name1]->set('foo', 'bar');

        $this->assertSame('bar', $app[$name1]->get('foo'));
        $this->assertFalse($app[$name2]->get('foo'));

        $this->assertSame($prefix1, $app[$name1]->getPrefix());
        $this->assertSame($prefix2, $app[$name2]->getPrefix());
    }

    private function runCacheTest(Application $app, $prefix)
    {
        $prefixReadBack = $app['memcache']->getOption(\Memcached::OPT_PREFIX_KEY);
        $this->assertSame($prefix, $prefixReadBack);
        $this->assertSame($prefix, $app['memcache']->getPrefix());
        $servers = $app['memcache']->getServerList();
        $this->assertInternalType('array', $servers);
        $this->assertCount(3, $servers);
        $server = $servers[0];
        $this->assertArrayHasKey('host', $server);
        $this->assertSame('127.0.0.2', $server['host']);
        $this->assertArrayHasKey('port', $server);
        $this->assertSame(11212, $server['port']);
        $server = $servers[1];
        $this->assertArrayHasKey('host', $server);
        $this->assertSame('127.0.0.3', $server['host']);
        $this->assertArrayHasKey('port', $server);
        $this->assertSame(11213, $server['port']);
        $server = $servers[2];
        $this->assertArrayHasKey('host', $server);
        $this->assertSame('127.0.0.4', $server['host']);
        $this->assertArrayHasKey('port', $server);
        $this->assertSame(11211, $server['port']);
    }
}

class CustomCacheClientTestClass
{
    public function addServer()
    {
        //
    }
}
