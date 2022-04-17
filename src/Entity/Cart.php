<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\Entity;


use Baraja\EcommerceStandard\DTO\CartInterface;
use Baraja\EcommerceStandard\DTO\CurrencyInterface;
use Baraja\EcommerceStandard\DTO\CustomerInterface;
use Baraja\EcommerceStandard\DTO\DeliveryInterface;
use Baraja\EcommerceStandard\DTO\PaymentInterface;
use Baraja\EcommerceStandard\DTO\PriceInterface;
use Baraja\Shop\Customer\Entity\Customer;
use Baraja\Shop\Delivery\Entity\Delivery;
use Baraja\Shop\Entity\Currency\Currency;
use Baraja\Shop\Payment\Entity\Payment;
use Baraja\Shop\Price\Price;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CartRepository::class)]
#[ORM\Table(name: 'shop__cart')]
class Cart implements CartInterface
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	protected int $id;

	#[ORM\ManyToOne(targetEntity: Customer::class)]
	private ?CustomerInterface $customer = null;

	#[ORM\ManyToOne(targetEntity: Delivery::class)]
	private ?DeliveryInterface $delivery = null;

	#[ORM\ManyToOne(targetEntity: Payment::class)]
	private ?PaymentInterface $payment = null;

	#[ORM\Column(type: 'integer', nullable: true)]
	private ?int $saleCoupon = null;

	#[ORM\Column(type: 'integer', nullable: true)]
	private ?int $deliveryBranchId = null;

	#[ORM\Column(type: 'string', length: 32, unique: true)]
	private string $identifier;

	#[ORM\ManyToOne(targetEntity: Currency::class)]
	private ?Currency $currency;

	/** @var CartItem[]|Collection */
	#[ORM\OneToMany(mappedBy: 'cart', targetEntity: CartItem::class)]
	private Collection $items;

	/** @var CartSale[]|Collection */
	#[ORM\OneToMany(mappedBy: 'cart', targetEntity: CartSale::class)]
	private Collection $sales;

	#[ORM\Column(type: 'datetime')]
	private \DateTimeInterface $insertedDate;

	private CartRuntimeContext $runtimeContext;


	public function __construct(string $identifier, Currency $currency)
	{
		$this->identifier = $identifier;
		$this->currency = $currency;
		$this->insertedDate = new \DateTimeImmutable;
		$this->items = new ArrayCollection;
		$this->sales = new ArrayCollection;
	}


	public function getId(): int
	{
		return $this->id;
	}


	public function isFlushed(): bool
	{
		return isset($this->id);
	}


	public function getCustomer(): ?CustomerInterface
	{
		return $this->customer;
	}


	public function setCustomer(?CustomerInterface $customer): void
	{
		$this->customer = $customer;
	}


	public function getDelivery(): ?DeliveryInterface
	{
		return $this->delivery;
	}


	public function setDelivery(?DeliveryInterface $delivery): void
	{
		$this->delivery = $delivery;
	}


	public function getPayment(): ?PaymentInterface
	{
		return $this->payment;
	}


	public function setPayment(?PaymentInterface $payment): void
	{
		$this->payment = $payment;
	}


	public function getIdentifier(): string
	{
		return $this->identifier;
	}


	public function getCurrency(): CurrencyInterface
	{
		assert($this->currency !== null);

		return $this->currency;
	}


	public function setCurrency(CurrencyInterface $currency): void
	{
		assert($currency instanceof Currency);
		$this->currency = $currency;
	}


	/** Back compatibility. */
	public function isCurrency(): bool
	{
		return $this->currency !== null;
	}


	public function getItemsPrice(bool $withVat = true): PriceInterface
	{
		$sum = '0';
		foreach ($this->getItems() as $item) {
			$sum = bcadd($sum, $withVat ? $item->getPrice()->getValue() : $item->getPriceWithoutVat()->getValue());
		}

		return new Price($sum, $this->getCurrency());
	}


	public function getDeliveryPrice(?PriceInterface $itemsPrice = null): PriceInterface
	{
		$sum = '0';
		if ($itemsPrice === null) {
			$itemsPrice = new Price('0', $this->getCurrency());
		}
		if ($this->delivery !== null
			&& $itemsPrice->isSmallerThan((string) $this->runtimeContext->getFreeDeliveryLimit())
		) {
			$sum = bcadd($sum, $this->delivery->getPrice());
		}
		if ($this->payment !== null) {
			$sum = bcadd($sum, $this->payment->getPrice());
		}

		return new Price($sum, $this->getCurrency());
	}


	public function getPrice(): PriceInterface
	{
		return $this->getItemsPrice()->plus($this->getDeliveryPrice($this->getItemsPrice()));
	}


	public function getPriceWithoutVat(): PriceInterface
	{
		return $this->getItemsPrice(false)->plus($this->getDeliveryPrice($this->getItemsPrice()));
	}


	/**
	 * @return array<int, CartItem>
	 */
	public function getItems(): array
	{
		$return = [];
		foreach ($this->items as $item) {
			if ($item->isActive() === false) {
				continue;
			}
			$return[] = $item;
		}

		return $return;
	}


	/**
	 * @return array<int, CartItem>
	 */
	public function getAllItems(): array
	{
		/** @phpstan-ignore-next-line */
		return $this->items->toArray();
	}


	/**
	 * @return array<int, CartSale>
	 */
	public function getSales(): array
	{
		$return = [];
		foreach ($this->sales as $sale) {
			$return[] = $sale;
		}

		return $return;
	}


	public function addSale(CartSale $sale): void
	{
		$this->sales[] = $sale;
	}


	public function isEmpty(): bool
	{
		return $this->getItems() === [];
	}


	public function getInsertedDate(): \DateTimeInterface
	{
		return $this->insertedDate;
	}


	public function getDeliveryBranchId(): ?int
	{
		return $this->deliveryBranchId;
	}


	public function setDeliveryBranchId(?int $deliveryBranchId): void
	{
		$this->deliveryBranchId = $deliveryBranchId;
	}


	public function getSaleCoupon(): ?int
	{
		return $this->saleCoupon;
	}


	public function setSaleCoupon(?int $saleCoupon): void
	{
		$this->saleCoupon = $saleCoupon;
	}


	public function getRuntimeContext(): CartRuntimeContext
	{
		return $this->runtimeContext;
	}


	/**
	 * @internal
	 */
	public function setRuntimeContext(CartRuntimeContext $runtimeContext): void
	{
		$this->runtimeContext = $runtimeContext;
	}
}
