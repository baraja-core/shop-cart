<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart;


use Baraja\Shop\Cart\Entity\Cart;

final class CartSecondLevelCache
{
	/** @var array<string, Cart> */
	private array $storage = [];


	public function getCart(string $identifier): ?Cart
	{
		return $this->storage[$identifier] ?? null;
	}


	public function saveCart(string $identifier, Cart $cart): void
	{
		$this->storage[$identifier] = $cart;
	}
}
