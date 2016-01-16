<?php
/**
 * This file is part of the Nella Project (https://monolog-tracy.nella.io).
 *
 * Copyright (c) 2014 Pavel Kučera (http://github.com/pavelkucera)
 * Copyright (c) Patrik Votoček (https://patrik.votocek.cz)
 *
 * For the full copyright and license information,
 * please view the file LICENSE.md that was distributed with this source code.
 */

namespace Nella\MonologTracyBundle\DependencyInjection;

use Monolog\Logger;
use Nella\MonologTracy\BlueScreenHandler;
use Symfony\Bundle\MonologBundle\DependencyInjection\MonologExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class MonologTracyExtensionTest extends \Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase
{

	/** @var YamlFileLoader */
	private $loader;

	protected function setUp()
	{
		parent::setUp();

		$logDirectory = sys_get_temp_dir() . '/' . getmypid() . microtime() . '-monologExtensionsTest';
		@mkdir($logDirectory);

		$this->container->setParameter('kernel.environment', 'test');
		$this->container->setParameter('kernel.logs_dir', $logDirectory);

		$this->loader = new YamlFileLoader($this->container, new FileLocator(__DIR__ . '/fixtures'));
	}

	/**
	 * @return \Symfony\Component\DependencyInjection\Extension\ExtensionInterface[]
	 */
	protected function getContainerExtensions()
	{
		return [
			new MonologExtension(),
			new MonologTracyExtension(),
		];
	}

	public function testParameters()
	{
		$this->load();

		$this->assertContainerBuilderHasParameter(MonologTracyExtension::LOG_DIRECTORY_PARAMETER);
		$this->assertContainerBuilderHasParameter(MonologTracyExtension::HANDLER_BUBBLE_PARAMETER);
		$this->assertContainerBuilderHasParameter(MonologTracyExtension::HANDLER_LEVEL_PARAMETER);

		$this->compile();
	}

	public function testHandlerService()
	{
		$this->load();

		$this->assertContainerBuilderHasService(MonologTracyExtension::BLUESCREEN_HANDLER_SERVICE_ID);

		$this->compile();
	}

	public function testFactoryService()
	{
		$this->load();

		$this->assertContainerBuilderHasService(MonologTracyExtension::BLUESCREEN_FACTORY_SERVICE_ID);

		$this->compile();
	}

	public function testFactoryServiceNoAlias()
	{
		$this->loadConfigs([
			'blueScreenFactoryNoAlias.yml',
		]);
		$this->load();

		$this->assertContainerBuilderHasService(MonologTracyExtension::BLUESCREEN_FACTORY_SERVICE_ID);
		$this->assertFalse($this->container->hasAlias(MonologTracyExtension::BLUESCREEN_FACTORY_SERVICE_ID));

		$this->compile();
	}

	public function testNoDefaultLogDirectory()
	{
		$this->load([], [
			'sectionLogDirectory.yml',
		]);

		$this->assertContainerBuilderHasParameter(
			MonologTracyExtension::LOG_DIRECTORY_PARAMETER,
			'%kernel.logs_dir%/logs'
		);

		$this->compile();
	}

	public function testNoDefaultHandlerBubble()
	{
		$this->load([], [
			'sectionHandlerBubble.yml',
		]);

		$this->assertContainerBuilderHasParameter(
			MonologTracyExtension::HANDLER_BUBBLE_PARAMETER,
			FALSE
		);

		$this->compile();
	}

	public function testNoDefaultHandlerLevel()
	{
		$this->load([], [
			'sectionHandlerLevel.yml',
		]);

		$this->assertContainerBuilderHasParameter(
			MonologTracyExtension::HANDLER_LEVEL_PARAMETER,
			Logger::ERROR
		);

		$this->compile();
	}

	public function testNoDefaultHandlerLevelAsString()
	{
		$this->load([], [
			'sectionHandlerLevelAsString.yml',
		]);

		$this->assertContainerBuilderHasParameter(
			MonologTracyExtension::HANDLER_LEVEL_PARAMETER,
			Logger::ERROR
		);

		$this->compile();
	}

	public function testInfoItems()
	{
		$this->load([], [
			'sectionInfoItems.yml',
		]);

		$this->assertContainerBuilderHasServiceDefinitionWithMethodCall(
			MonologTracyExtension::BLUESCREEN_FACTORY_SERVICE_ID,
			'registerInfo',
			[
				'Foo',
			]
		);

		$this->compile();
	}

	public function testPanels()
	{
		$this->load([], [
			'sectionPanels.yml',
		]);

		$this->assertContainerBuilderHasServiceDefinitionWithMethodCall(
			MonologTracyExtension::BLUESCREEN_FACTORY_SERVICE_ID,
			'registerPanel',
			[
				'@nella.monolog_tracy.panel.test_panel',
			]
		);

		$this->compile();
	}

	public function testHandlerInstance()
	{
		$this->load();
		$this->compile();

		@mkdir($this->container->getParameter(MonologTracyExtension::LOG_DIRECTORY_PARAMETER), 0777, TRUE);

		$handler = $this->container->get(MonologTracyExtension::BLUESCREEN_HANDLER_SERVICE_ID);
		$this->assertInstanceOf(BlueScreenHandler::class, $handler);
	}

	public function testHandlerInstanceWithPanel()
	{
		$this->load([], [
			'sectionPanels.yml',
		]);
		$this->compile();

		@mkdir($this->container->getParameter(MonologTracyExtension::LOG_DIRECTORY_PARAMETER), 0777, TRUE);

		$handler = $this->container->get(MonologTracyExtension::BLUESCREEN_HANDLER_SERVICE_ID);
		$this->assertInstanceOf(BlueScreenHandler::class, $handler);
	}

	/**
	 * @param mixed[] $configurationValues
	 * @param string[] $configFiles
	 */
	protected function load(array $configurationValues = [], array $configFiles = [])
	{
		$this->loadConfigs($configFiles);

		foreach ($this->container->getExtensions() as $extension) {
			$configs = [];
			foreach ($this->container->getExtensionConfig($extension->getAlias()) as $config) {
				$configs[] = $config;
			}
			$configs[] = $configurationValues;

			$extension->load($configs, $this->container);
		}
	}

	private function loadConfigs(array $configs)
	{
		foreach ($configs as $config) {
			$this->loader->load($config);
		}
	}

}
