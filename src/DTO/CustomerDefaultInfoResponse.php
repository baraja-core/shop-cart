<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\DTO;


final class CustomerDefaultInfoResponse
{
	public bool $ready = false;

	public string $firstName = '';

	public string $lastName = '';

	public string $email = '';

	public string $phone = '';

	public string $street = '';

	public string $city = '';

	public string $zip = '';

	public string $companyName = '';

	public string $ic = '';
}
