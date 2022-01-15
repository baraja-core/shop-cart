<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\DTO;


final class DataLayer
{
	public function __construct(
		public string $id,
		public string $name,
		public string $price,
		public ?string $brand,
		public ?string $category,
		public ?string $variant,
		public float $quantity,
	) {
	}
}
