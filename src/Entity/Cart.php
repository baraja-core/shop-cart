<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\Entity;


use Baraja\Shop\Cart\CartManager;
use Baraja\Shop\Customer\Entity\Customer;
use Baraja\Shop\Delivery\Entity\Delivery;
use Baraja\Shop\Payment\Entity\Payment;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CartRepository::class)]
#[ORM\Table(name: 'shop__cart')]
class Cart
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	protected int $id;

	#[ORM\ManyToOne(targetEntity: Customer::class)]
	private ?Customer $customer = null;

	#[ORM\ManyToOne(targetEntity: Delivery::class)]
	private ?Delivery $delivery = null;

	#[ORM\ManyToOne(targetEntity: Payment::class)]
	private ?Payment $payment = null;

	#[ORM\Column(type: 'integer', nullable: true)]
	private ?int $saleCoupon = null;

	#[ORM\Column(type: 'integer', nullable: true)]
	private ?int $deliveryBranchId = null;

	#[ORM\Column(type: 'string', length: 32, unique: true)]
	private string $identifier;

	/** @var CartItem[]|Collection */
	#[ORM\OneToMany(mappedBy: 'cart', targetEntity: CartItem::class)]
	private $items;

	#[ORM\Column(type: 'datetime')]
	private \DateTimeInterface $insertedDate;


	public function __construct(string $identifier)
	{
		$this->identifier = $identifier;
		$this->insertedDate = new \DateTimeImmutable;
		$this->items = new ArrayCollection;
	}


	public function getId(): int
	{
		return $this->id;
	}


	public function getCustomer(): ?Customer
	{
		return $this->customer;
	}


	public function setCustomer(?Customer $customer): void
	{
		$this->customer = $customer;
	}


	public function getDelivery(): ?Delivery
	{
		return $this->delivery;
	}


	public function setDelivery(?Delivery $delivery): void
	{
		$this->delivery = $delivery;
	}


	public function getPayment(): ?Payment
	{
		return $this->payment;
	}


	public function setPayment(?Payment $payment): void
	{
		$this->payment = $payment;
	}


	public function getIdentifier(): string
	{
		return $this->identifier;
	}


	public function getItemsPrice(bool $withVat = true): float
	{
		$sum = 0;
		foreach ($this->items as $item) {
			$sum += $withVat ? $item->getPrice() : $item->getPriceWithoutVat();
		}

		return $sum;
	}


	public function getDeliveryPrice(float $itemsPrice = 0): float
	{
		$sum = 0;
		if ($this->delivery !== null && $itemsPrice < CartManager::getFreeDeliveryLimit()) {
			$sum += $this->delivery->getPrice();
		}
		if ($this->payment !== null) {
			$sum += $this->payment->getPrice();
		}

		return $sum;
	}


	public function getPrice(): float
	{
		return $this->getItemsPrice() + $this->getDeliveryPrice($this->getItemsPrice());
	}


	public function getPriceWithoutVat(): float
	{
		return $this->getItemsPrice(false) + $this->getDeliveryPrice($this->getItemsPrice());
	}


	/**
	 * @return CartItem[]|Collection
	 */
	public function getItems()
	{
		return $this->items;
	}


	public function isEmpty(): bool
	{
		return count($this->items->toArray()) === 0;
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
}
