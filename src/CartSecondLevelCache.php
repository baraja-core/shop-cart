<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart;


use Baraja\EcommerceStandard\DTO\CartInterface;

final class CartSecondLevelCache
{
	/** @var array<string, CartInterface> */
	private array $storage = [];


	public function getCart(string $identifier): ?CartInterface
	{
		return $this->storage[$identifier] ?? null;
	}


	public function saveCart(string $identifier, CartInterface $cart): void
	{
		$this->storage[$identifier] = $cart;
	}
}
