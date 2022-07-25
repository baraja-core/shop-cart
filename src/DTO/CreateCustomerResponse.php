<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\DTO;


final class CreateCustomerResponse
{
	public function __construct(
		public int $id,
		public string $hash,
	) {
	}
}
