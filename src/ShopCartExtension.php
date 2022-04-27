<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart;


use Baraja\Doctrine\ORM\DI\OrmAnnotationsExtension;
use Baraja\Plugin\Component\VueComponent;
use Baraja\Plugin\PluginManager;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;

final class ShopCartExtension extends CompilerExtension
{
	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();
		OrmAnnotationsExtension::addAnnotationPathToManager($builder, 'Baraja\Shop\Cart\Entity', __DIR__ . '/Entity');

		$builder->addDefinition($this->prefix('cartManager'))
			->setFactory(CartManager::class);

		$builder->addDefinition($this->prefix('voucherManager'))
			->setFactory(VoucherManager::class);

		/** @var ServiceDefinition $pluginManager */
		$pluginManager = $this->getContainerBuilder()->getDefinitionByType(PluginManager::class);
		$pluginManager->addSetup('?->addComponent(?)', ['@self', [
			'key' => 'cartVoucherDefault',
			'name' => 'cms-cart-voucher-default',
			'implements' => CmsCartVoucherPlugin::class,
			'componentClass' => VueComponent::class,
			'view' => 'default',
			'source' => __DIR__ . '/../templates/default.js',
			'position' => 100,
			'tab' => 'Voucher manager',
			'params' => [],
		]]);
	}
}
