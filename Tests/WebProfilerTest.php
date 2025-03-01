<?php

/*
 * This file is part of the Silex framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silex\Provider\Tests;

use Silex\Application;
use Silex\WebTestCase;
use Silex\Provider;

class WebProfilerTest extends WebTestCase
{
    public function createApplication()
    {
        $app = new Application();

        // Service providers
        $app->register(new Provider\HttpFragmentServiceProvider());
        $app->register(new Provider\ServiceControllerServiceProvider());
        $app->register(new Provider\TwigServiceProvider(), array(
            'twig.templates' => array(
                'index.twig' => '<body>OK</body>',
            ),
        ));

        $app->register(new Provider\WebProfilerServiceProvider(), array(
            'profiler.cache_dir' => __DIR__.'/cache/profiler',
        ));

        // Test Config
        $app['debug'] = true;
        unset($app['exception_handler']);
        $app['session.test'] = true;

        // Test route
        $app->get('/', function () use ($app) {
            return $app['twig']->render('index.twig');
        });

        return $app;
    }

    public function testRun()
    {
        $client = $this->createClient();
        $crawler = $client->request('GET', '/');

        $this->assertTrue($client->getResponse()->isOk(), 'Response successful');
        $this->assertCount(1, $crawler->filter('.sf-toolbar'), 'WDT is inserted.');
        $this->assertTrue($client->getResponse()->headers->has('X-Debug-Token'), 'Profile ID');
        $this->assertTrue($client->getResponse()->headers->has('X-Debug-Token-Link'), 'Profiler link');

        $link = $client->getResponse()->headers->get('X-Debug-Token-Link');

        $crawler = $client->request('GET', $link);
        $this->assertTrue($client->getResponse()->isOk(), 'Profile accessible');

        $client->followRedirects(true);
        $crawler = $client->request('GET', '/_profiler/');
        $this->assertTrue($client->getResponse()->isOk(), 'Profiler accessible');
    }

    public function testRoutingProfiler()
    {
        $client = $this->createClient();
        $client->request('GET', '/');

        $link = $client->getResponse()->headers->get('X-Debug-Token-Link');
        $crawler = $client->request('GET', $link);

        $crawler = $client->click($crawler->selectLink('Routing')->link());
        $this->assertTrue($client->getResponse()->isOk(), 'Routing profiler is enabled');
        $this->assertCount(1, $crawler->filter('h2:contains("Routing for")'), 'Routing profiler is working');
    }

    public function testTwigProfiler()
    {
        if (!class_exists('Symfony\Bridge\Twig\Extension\ProfilerExtension')) {
            $this->markTestSkipped(
              'Twig profiler extension is available in Symfony 2.7+'
            );
        }

        $client = $this->createClient();
        $client->request('GET', '/');

        $link = $client->getResponse()->headers->get('X-Debug-Token-Link');
        $crawler = $client->request('GET', $link);

        $crawler = $client->click($crawler->selectLink('Twig')->link());
        $this->assertTrue($client->getResponse()->isOk(), 'Twig profiler is enabled');
        $this->assertCount(1, $crawler->filter('h2:contains("Twig Stats")'), 'Twig profiler is working');
    }
}
