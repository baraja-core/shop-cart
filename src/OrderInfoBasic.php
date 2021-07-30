<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart;


use Nette\Utils\Validators;

final class OrderInfoBasic
{
	private string $firstName;

	private string $lastName;

	private string $email;

	private string $phone;

	private ?string $registerPassword = null;

	private ?string $notice = null;

	private bool $newsletter = false;

	private bool $register = false;

	private bool $gdpr = false;


	public function getFirstName(): string
	{
		return $this->firstName;
	}


	public function getLastName(): string
	{
		return $this->lastName;
	}


	public function getEmail(): string
	{
		return $this->email;
	}


	public function setEmail(string $email): void
	{
		if (Validators::isEmail($email) === false) {
			throw new \InvalidArgumentException('Customer e-mail "' . $email . '" is not valid.');
		}
		$this->email = $email;
	}


	public function getPhone(): string
	{
		return $this->phone;
	}


	public function getRegisterPassword(): ?string
	{
		return $this->registerPassword;
	}


	public function getNotice(): ?string
	{
		return trim($this->notice ?? '') ?: null;
	}


	public function isNewsletter(): bool
	{
		return $this->newsletter;
	}


	public function isRegister(): bool
	{
		return $this->register;
	}


	public function isGdpr(): bool
	{
		return $this->gdpr;
	}
}
