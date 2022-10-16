<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\DTO;


final class CheckVariantItem
{
	public function __construct(
		public int $variantId,
		public string $hash,
		public bool $available,
		public string $price,
		public string $regularPrice,
		public bool $sale,
	) {
	}
}
