<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\DTO;


use Baraja\EcommerceStandard\DTO\CurrencyInterface;
use Baraja\EcommerceStandard\DTO\PaymentInterface;
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


	public static function fromEntity(CurrencyInterface $currency, PaymentInterface $payment): self
	{
		return new self(
			id: $payment->getId(),
			code: $payment->getCode(),
			name: $payment->getName(),
			description: $payment->getDescription(),
			price: (new Price($payment->getPrice(), $currency))->render(true),
		);
	}
}
