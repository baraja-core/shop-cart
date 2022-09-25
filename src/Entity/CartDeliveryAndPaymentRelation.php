<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\Entity;


use Baraja\EcommerceStandard\DTO\DeliveryInterface;
use Baraja\EcommerceStandard\DTO\PaymentInterface;
use Baraja\Shop\Delivery\Entity\Delivery;
use Baraja\Shop\Payment\Entity\Payment;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CartDeliveryAndPaymentRelationRepository::class)]
#[ORM\UniqueConstraint(name: 'shop__cart_delivery_and_payment_relation_delivery_payment', columns: ['delivery_id', 'payment_id'])]
#[ORM\Table(name: 'shop__cart_delivery_and_payment_relation')]
class CartDeliveryAndPaymentRelation
{
	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	protected int $id;

	#[ORM\ManyToOne(targetEntity: Delivery::class)]
	private DeliveryInterface $delivery;

	#[ORM\ManyToOne(targetEntity: Payment::class)]
	private PaymentInterface $payment;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $description = null;


	public function __construct(DeliveryInterface $delivery, PaymentInterface $payment)
	{
		$this->delivery = $delivery;
		$this->payment = $payment;
	}


	public function getId(): int
	{
		return $this->id;
	}


	public function getDelivery(): DeliveryInterface
	{
		return $this->delivery;
	}


	public function getPayment(): PaymentInterface
	{
		return $this->payment;
	}


	public function getDescription(): ?string
	{
		return $this->description;
	}


	public function setDescription(?string $description): void
	{
		$this->description = $description;
	}
}
