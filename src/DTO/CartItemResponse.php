<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\DTO;


final class CartItemResponse
{
	public function __construct(
		public int|string $id,
		public ?string $url,
		public ?string $mainImageUrl,
		public string $name,
		public int $count,
		public ?string $price,
		public ?string $description,
		public bool $sale,
	) {
	}
}
