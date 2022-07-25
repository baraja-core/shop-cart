<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\DTO;


final class CartCustomer
{
	/**
	 * @param array<int, mixed> $items
	 * @param array{name: string, price: string} $delivery
	 * @param array{name: string, price: string} $payment
	 */
	public function __construct(
		public bool $loggedIn,
		public array $items,
		public string $price,
		public string $itemsPrice,
		public string $deliveryPrice,
		public array $delivery,
		public array $payment,
	) {
	}
}
