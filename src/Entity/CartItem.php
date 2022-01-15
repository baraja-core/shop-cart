<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\Entity;


use Baraja\EcommerceStandard\DTO\CartItemInterface;
use Baraja\EcommerceStandard\DTO\PriceInterface;
use Baraja\EcommerceStandard\DTO\ProductInterface;
use Baraja\EcommerceStandard\DTO\ProductVariantInterface;
use Baraja\Shop\Price\Price;
use Baraja\Shop\Product\Entity\Product;
use Baraja\Shop\Product\Entity\ProductVariant;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CartItemRepository::class)]
#[ORM\Table(name: 'shop__cart_item')]
class CartItem implements CartItemInterface
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	protected int $id;

	#[ORM\ManyToOne(targetEntity: Cart::class, inversedBy: 'items')]
	private Cart $cart;

	#[ORM\ManyToOne(targetEntity: Product::class)]
	private ProductInterface $product;

	#[ORM\ManyToOne(targetEntity: ProductVariant::class)]
	private ?ProductVariantInterface $variant;

	#[ORM\Column(type: 'integer')]
	private int $count;


	public function __construct(
		Cart $cart,
		ProductInterface $product,
		?ProductVariantInterface $variant = null,
		int $count = 1,
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


	public function getProduct(): ProductInterface
	{
		return $this->product;
	}


	public function getVariant(): ?ProductVariantInterface
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


	public function getBasicPrice(): PriceInterface
	{
		return new Price(
			value: $this->variant !== null
				? $this->variant->getPrice()
				: $this->product->getSalePrice(),
			currency: $this->cart->getCurrency(),
		);
	}


	public function getPrice(): PriceInterface
	{
		return new Price(
			value: $this->getBasicPrice()->getValue() * $this->count,
			currency:  $this->cart->getCurrency(),
		);
	}


	public function getPriceWithoutVat(): PriceInterface
	{
		return new Price(
			value: $this->getPrice()->getValue() / (1 + ($this->getProduct()->getVat() / 100)),
			currency: $this->cart->getCurrency(),
		);
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


	public function isActive(): bool
	{
		return $this->product->isActive()
			&& $this->product->isSoldOut() === false
			&& ($this->variant === null || $this->variant->isSoldOut() === false);
	}
}
