<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\DTO;


final class BuyResponse
{
	/**
	 * @param array<int, RelatedProductResponse> $related
	 */
	public function __construct(
		public int $count,
		public DataLayer $dataLayer,
		public array $related,
	) {
	}
}
