<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart;


final class OrderInfo
{
	public function __construct(
		private OrderInfoBasic $info,
		private OrderInfoAddress $address,
		private OrderInfoAddress $invoiceAddress
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
	 * @return array{firstName: string, lastName: string, street: string, city: string, zip: string, companyName: string, ic: string, dic}
	 */
	public function toArray(OrderInfoAddress $address): array
	{
		return array_merge([
			'firstName' => $this->info->getFirstName(),
			'lastName' => $this->info->getLastName(),
		], $address->toArray());
	}
}
