<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\Entity;


use Baraja\Shop\Cart\FreeDelivery\FreeDeliveryResolver;
use Baraja\Shop\Currency\CurrencyManagerAccessor;

final class CartRuntimeContext
{
	private int $freeDeliveryLimit = 1_000;

	private FreeDeliveryResolver $freeDeliveryResolver;


	public function __construct(CurrencyManagerAccessor $currencyManager)
	{
		$this->freeDeliveryResolver = new FreeDeliveryResolver($currencyManager);
	}


	public function getFreeDeliveryResolver(): FreeDeliveryResolver
	{
		return $this->freeDeliveryResolver;
	}


	public function getFreeDeliveryLimit(): int
	{
		return $this->freeDeliveryLimit;
	}


	public function setFreeDeliveryLimit(int $freeDeliveryLimit): void
	{
		if ($freeDeliveryLimit < 0) {
			$freeDeliveryLimit = 0;
		}
		$this->freeDeliveryLimit = $freeDeliveryLimit;
	}
}
