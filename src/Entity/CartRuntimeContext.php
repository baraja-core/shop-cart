<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\Entity;


final class CartRuntimeContext
{
	private int $freeDeliveryLimit = 1_000;


	public function getFreeDeliveryLimit(): int
	{
		return $this->freeDeliveryLimit;
	}


	public function setFreeDeliveryLimit(int $freeDeliveryLimit): void
	{
		if ($freeDeliveryLimit < 0) {
			$freeDeliveryLimit = 0;
		}
		$this->freeDeliveryLimit = $freeDeliveryLimit;
	}
}
