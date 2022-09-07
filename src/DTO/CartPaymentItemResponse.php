<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\DTO;


use Baraja\Shop\Cart\Entity\Cart;
use Baraja\Shop\Payment\Entity\Payment;
use Baraja\Shop\Price\Price;

final class CartPaymentItemResponse
{
	public function __construct(
		public int $id,
		public string $code,
		public string $name,
		public ?string $description,
		public string $price,
	) {
	}


	public static function fromEntity(Cart $cart, Payment $payment): self
	{
		return new self(
			id: $payment->getId(),
			code: $payment->getCode(),
			name: $payment->getName(),
			description: $payment->getDescription(),
			price: (new Price($payment->getPrice(), $cart->getCurrency()))->render(true),
		);
	}
}
