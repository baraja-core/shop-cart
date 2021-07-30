<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart;


final class OrderInfoAddress
{
	private string $street;

	private string $city;

	private string $zip;

	private string $companyName = '';

	private string $ic = '';

	private string $dic = '';

	private bool $buyAsCompany = false;

	private bool $invoiceAddressIsDifferent = false;


	/**
	 * @return array{street: string, city: string, zip: string, companyName: string, ic: string, dic: string}
	 */
	public function toArray(): array
	{
		return [
			'street' => $this->getStreet(),
			'city' => $this->getCity(),
			'zip' => $this->getZip(),
			'companyName' => $this->getCompanyName(),
			'ic' => $this->getIc(),
			'dic' => $this->getDic(),
		];
	}


	public function getStreet(): string
	{
		return $this->street;
	}


	public function getCity(): string
	{
		return $this->city;
	}


	public function getZip(): string
	{
		return $this->zip;
	}


	public function getCompanyName(): string
	{
		return $this->companyName;
	}


	public function getIc(): string
	{
		return $this->ic;
	}


	public function getDic(): string
	{
		return $this->dic;
	}


	public function isBuyAsCompany(): bool
	{
		return $this->buyAsCompany;
	}


	public function isInvoiceAddressIsDifferent(): bool
	{
		return $this->invoiceAddressIsDifferent;
	}
}
