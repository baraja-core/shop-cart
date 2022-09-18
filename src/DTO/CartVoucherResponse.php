<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\DTO;


use Baraja\Shop\Cart\Entity\CartVoucher;

final class CartVoucherResponse
{
	/**
	 * @param numeric-string|null $value
	 */
	public function __construct(
		public bool $status,
		public ?string $code = null,
		public ?string $message = null,
		public ?string $type = null,
		public ?string $value = null,
		public ?bool $available = null,
		public ?bool $active = null,
		public ?bool $mustBeUnique = null,
	) {
	}


	public static function fromEntity(CartVoucher $voucher, ?string $message = null): self
	{
		return new self(
			status: $voucher->isAvailable(),
			code: $voucher->getCode(),
			message: $message,
			type: $voucher->getType(),
			value: $voucher->getValue(),
			available: $voucher->isAvailable(),
			active: $voucher->isActive(),
			mustBeUnique: $voucher->isMustBeUnique(),
		);
	}
}
