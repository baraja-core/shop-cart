<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\DTO;


final class CheckVariantFeedItem
{
	public function __construct(
		public string $text,
		public string $value,
		public int $id,
		public string $hash,
	) {
	}
}
