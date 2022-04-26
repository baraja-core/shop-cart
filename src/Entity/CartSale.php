<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\Entity;


use Baraja\EcommerceStandard\DTO\CartSaleInterface;
use Baraja\EcommerceStandard\DTO\CartVoucherInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CartItemRepository::class)]
#[ORM\Table(name: 'shop__cart_sale')]
class CartSale implements CartSaleInterface
{
	public const Types = [CartVoucher::TypeFixValue, CartVoucher::TypePercentage, CartVoucher::TypeFreeProduct];

	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	protected int $id;

	#[ORM\ManyToOne(targetEntity: Cart::class, inversedBy: 'sales')]
	private Cart $cart;

	#[ORM\ManyToOne(targetEntity: CartVoucher::class, inversedBy: 'sales')]
	private ?CartVoucher $voucher = null;

	#[ORM\Column(type: 'string', length: 8)]
	private string $type;

	/** @var numeric-string */
	#[ORM\Column(type: 'string', length: 64)]
	private string $value;


	/**
	 * @param numeric-string $value
	 */
	public function __construct(Cart $cart, string $type, string $value)
	{
		if (!in_array($type, self::Types, true)) {
			throw new \InvalidArgumentException(
				sprintf('Type "%s" is not valid option from "%s".', $type, implode('", "', self::Types)),
			);
		}
		$this->cart = $cart;
		$this->type = $type;
		$this->value = $value;
	}


	public function getId(): int
	{
		return $this->id;
	}


	public function getCart(): Cart
	{
		return $this->cart;
	}


	public function getType(): string
	{
		return $this->type;
	}


	/**
	 * @return numeric-string
	 */
	public function getValue(): string
	{
		return $this->value;
	}


	public function getVoucher(): ?CartVoucher
	{
		return $this->voucher;
	}


	public function setVoucher(?CartVoucherInterface $voucher): void
	{
		assert($voucher instanceof CartVoucher);
		$this->voucher = $voucher;
	}
}
