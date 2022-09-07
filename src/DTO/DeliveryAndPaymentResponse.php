<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\DTO;


final class DeliveryAndPaymentResponse
{
	/**
	 * @param array<int, CartDeliveryItemResponse> $deliveries
	 * @param array<int, CartPaymentItemResponse> $payments
	 */
	public function __construct(
		public string $price,
		public array $deliveries,
		public array $payments,
		public bool $isFreeDelivery,
		public bool $isFreePayment,
	) {
	}
}
