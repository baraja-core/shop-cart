<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\Entity;


use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CartItemRepository::class)]
#[ORM\Table(name: 'shop__cart_sale')]
class CartSale
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	protected int $id;

	#[ORM\ManyToOne(targetEntity: Cart::class, inversedBy: 'sales')]
	private Cart $cart;


	public function __construct(Cart $cart)
	{
		$this->cart = $cart;
	}


	public function getId(): int
	{
		return $this->id;
	}


	public function getCart(): Cart
	{
		return $this->cart;
	}
}
