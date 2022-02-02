<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\FreeDelivery;


use Baraja\EcommerceStandard\DTO\CartInterface;
use Baraja\EcommerceStandard\DTO\CustomerInterface;
use Baraja\EcommerceStandard\Service\FreeDeliveryResolverInterface;
use Baraja\Shop\Currency\CurrencyManagerAccessor;
use Baraja\Shop\Price\Price;

final class FreeDeliveryResolver implements FreeDeliveryResolverInterface
{
	private const DEFAULT_MINIMAL_PRICE = 1_000;


	public function __construct(
		private CurrencyManagerAccessor $currencyManager,
	) {
	}


	public function isFreeDelivery(CartInterface $cart, ?CustomerInterface $customer = null): bool
	{
		if ($customer === null) {
			$customer = $cart->getCustomer();
		}

		return $this->getMinimalPrice($cart, $customer)->isBiggerThan($cart->getItemsPrice());
	}


	public function getMinimalPrice(?CartInterface $cart = null, ?CustomerInterface $customer = null): Price
	{
		if ($cart !== null && $customer === null) {
			$customer = $cart->getCustomer();
		}

		return $this->getDefaultMinimalPrice();
	}


	public function getDefaultMinimalPrice(): Price
	{
		return new Price(
			self::DEFAULT_MINIMAL_PRICE,
			$this->currencyManager->get()->getMainCurrency(),
		);
	}
}
