<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\DTO;


final class CartCustomer
{
	/**
	 * @param array<int, mixed> $items
	 */
	public function __construct(
		public bool $loggedIn,
		public array $items,
		public string $price,
		public string $itemsPrice,
		public string $deliveryPrice,
		public CartDeliveryItemResponse $delivery,
		public CartPaymentItemResponse $payment,
	) {
	}
}
