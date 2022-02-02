<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart;


class OrderInfoAddress
{
	protected string $street;

	protected string $city;

	protected string $zip;

	protected string $companyName = '';

	protected string $ic = '';

	protected string $dic = '';

	protected ?int $country = null;

	protected bool $buyAsCompany = false;

	protected bool $invoiceAddressIsDifferent = false;


	/**
	 * @return array{street: string, city: string, zip: string, country: int|null, companyName: string, ic: string, dic: string}
	 */
	public function toArray(): array
	{
		return [
			'street' => $this->getStreet(),
			'city' => $this->getCity(),
			'zip' => $this->getZip(),
			'country' => $this->getCountry(),
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


	public function getCountry(): ?int
	{
		return $this->country;
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
