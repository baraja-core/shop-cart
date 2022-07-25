<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\DTO;


final class RelatedProductResponse
{
	public function __construct(
		public int $id,
		public string $name,
		public ?string $mainImage,
		public string $price,
		public ?string $url,
	) {
	}
}
