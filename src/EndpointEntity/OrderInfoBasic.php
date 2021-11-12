<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart;


use Nette\Utils\Validators;

class OrderInfoBasic
{
	protected string $firstName;

	protected string $lastName;

	protected string $email;

	protected string $phone;

	protected ?string $registerPassword = null;

	protected ?string $notice = null;

	protected bool $newsletter = false;

	protected bool $register = false;

	protected bool $gdpr = false;


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
		$notice = trim($this->notice ?? '');

		return $notice !== '' ? $notice : null;
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
