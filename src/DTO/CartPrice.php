<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\DTO;


final class CartPrice
{
	public function __construct(
		public string $final,
		public string $withoutVat,
	) {
	}
}
