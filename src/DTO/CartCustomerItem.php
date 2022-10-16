<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\DTO;


use Baraja\EcommerceStandard\DTO\PriceInterface;

final class CartCustomerItem
{
	public function __construct(
		public int $id,
		public ?string $mainImage,
		public ?string $url,
		public string $name,
		public PriceInterface $price,
	) {
	}
}
