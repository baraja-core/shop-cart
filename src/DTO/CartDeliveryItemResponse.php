<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\DTO;


use Baraja\EcommerceStandard\DTO\CartInterface;
use Baraja\EcommerceStandard\DTO\DeliveryInterface;
use Baraja\Shop\Price\Price;

final class CartDeliveryItemResponse
{
	public function __construct(
		public int $id,
		public string $code,
		public string $name,
		public string $price,
	) {
	}


	public static function fromEntity(CartInterface $cart, DeliveryInterface $delivery): self
	{
		return new self(
			id: $delivery->getId(),
			code: $delivery->getCode(),
			name: $delivery->getLabel(),
			price: (new Price($delivery->getPrice(), $cart->getCurrency()))->render(true),
		);
	}
}
