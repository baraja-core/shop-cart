<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart;


use Baraja\Plugin\BasePlugin;
use Baraja\Shop\Cart\Entity\CartVoucher;

final class CmsCartVoucherPlugin extends BasePlugin
{
	public function getName(): string
	{
		return 'Voucher manager';
	}


	public function getBaseEntity(): ?string
	{
		return CartVoucher::class;
	}
}
