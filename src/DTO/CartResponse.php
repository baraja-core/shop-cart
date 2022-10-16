<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\DTO;


final class CartResponse
{
	/**
	 * @param array<int, CartItemResponse> $items
	 * @param array<int, RelatedProductResponse> $related
	 */
	public function __construct(
		public array $items,
		public ?string $priceToFreeDelivery,
		public CartPrice $price,
		public ?CartDeliveryItemResponse $delivery,
		public ?CartPaymentItemResponse $payment,
		public array $related,
	) {
	}
}
