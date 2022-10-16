<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\DTO;


final class CheckVariantStatusResponse
{
	/**
	 * @param array<int, CheckVariantItem> $variantList
	 * @param array<string, array<int, CheckVariantFeedItem>> $variantsFeed
	 */
	public function __construct(
		public bool $exist,
		public ?int $variantId,
		public bool $available,
		public ?string $price,
		public ?string $regularPrice,
		public bool $sale,
		public DataLayer $dataLayer,
		public array $variantList,
		public array $variantsFeed,
	) {
	}
}
