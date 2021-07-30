<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart;


interface PriceRenderer
{
	public function render(
		float|string $price,
		?string $locale = null,
		?string $expectedCurrency = null,
		?string $currentCurrency = null
	): string;
}
