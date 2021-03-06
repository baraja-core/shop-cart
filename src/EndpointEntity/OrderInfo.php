<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart;


use Baraja\EcommerceStandard\DTO\OrderInfoInterface;

final class OrderInfo implements OrderInfoInterface
{
	public function __construct(
		private OrderInfoBasic $info,
		private OrderInfoAddress $address,
		private OrderInfoAddress $invoiceAddress,
	) {
	}


	public function getInfo(): OrderInfoBasic
	{
		return $this->info;
	}


	public function getAddress(): OrderInfoAddress
	{
		return $this->address;
	}


	public function getInvoiceAddress(): OrderInfoAddress
	{
		return $this->invoiceAddress;
	}


	/**
	 * @return array{firstName: string, lastName: string, street: string, city: string, zip: string, country: int|null, companyName: string, ic: string, dic: string}
	 */
	public function toArray(OrderInfoAddress $address): array
	{
		return array_merge([
			'firstName' => $this->info->getFirstName(),
			'lastName' => $this->info->getLastName(),
		], $address->toArray());
	}
}
