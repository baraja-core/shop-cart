<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart;


use Baraja\Doctrine\ORM\DI\OrmAnnotationsExtension;
use Nette\DI\CompilerExtension;

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
	}
}
