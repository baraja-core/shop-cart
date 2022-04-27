<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart;


use Baraja\Plugin\BasePlugin;
use Baraja\Shop\Cart\Entity\Cart;

final class CmsCartPlugin extends BasePlugin
{
	public function getName(): string
	{
		return 'Cart manager';
	}


	public function getBaseEntity(): ?string
	{
		return Cart::class;
	}
}
