<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\Entity;


use Baraja\Shop\Product\Entity\Product;
use Baraja\Shop\Product\Entity\ProductVariant;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'shop__cart_item')]
class CartItem
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	protected int $id;

	#[ORM\ManyToOne(targetEntity: Cart::class, inversedBy: 'items')]
	private Cart $cart;

	#[ORM\ManyToOne(targetEntity: Product::class)]
	private Product $product;

	#[ORM\ManyToOne(targetEntity: ProductVariant::class)]
	private ?ProductVariant $variant;

	#[ORM\Column(type: 'integer')]
	private int $count;


	public function __construct(
		Cart $cart,
		Product $product,
		?ProductVariant $variant = null,
		int $count = 1
	) {
		$this->cart = $cart;
		$this->product = $product;
		$this->variant = $variant;
		$this->count = $count;
	}


	public function getId(): int
	{
		return $this->id;
	}


	public function getCart(): Cart
	{
		return $this->cart;
	}


	public function getProduct(): Product
	{
		return $this->product;
	}


	public function getVariant(): ?ProductVariant
	{
		return $this->variant;
	}


	public function getCount(): int
	{
		return $this->count;
	}


	public function setCount(int $count): void
	{
		if ($count < 1) {
			throw new \InvalidArgumentException(
				'Count must be minimal 1. If you want delete item, you must delete it in database.',
			);
		}
		$this->count = $count;
	}


	public function getMainImageRelativePath(): ?string
	{
		$main = $this->product->getMainImage();

		return $main !== null ? $main->getRelativePath() : null;
	}


	public function getName(): string
	{
		return (string) $this->product->getName();
	}


	public function getBasicPrice(): float
	{
		return $this->variant !== null
			? $this->variant->getPrice()
			: $this->product->getSalePrice();
	}


	public function getPrice(): float
	{
		return $this->getBasicPrice() * $this->count;
	}


	public function getPriceWithoutVat(): float
	{
		return $this->getPrice() / (1 + ($this->getProduct()->getVat() / 100));
	}


	public function getDescription(): string
	{
		if ($this->variant !== null) {
			$return = '';
			foreach (ProductVariant::unserializeParameters($this->variant->getRelationHash()) as $key => $value) {
				$return .= ($return !== '' ? ', ' : '') . $key . ': ' . $value;
			}

			return $return;
		}

		return '';
	}


	public function addCount(int $count): void
	{
		if ($count < 1) {
			throw new \InvalidArgumentException(
				'Count must be minimal 1. If you want delete item, you must delete it in database.',
			);
		}
		$this->count += $count;
	}
}
