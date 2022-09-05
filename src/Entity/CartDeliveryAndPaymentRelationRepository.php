<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\Entity;


use Baraja\EcommerceStandard\DTO\DeliveryInterface;
use Baraja\EcommerceStandard\DTO\PaymentInterface;
use Baraja\Shop\Payment\Entity\Payment;
use Doctrine\ORM\EntityRepository;

final class CartDeliveryAndPaymentRelationRepository extends EntityRepository
{
	/**
	 * @return array<int, PaymentInterface>
	 */
	public function getCompatiblePaymentsByDelivery(DeliveryInterface $delivery): array
	{
		/** @var array<int, CartDeliveryAndPaymentRelation> $relations */
		$relations = $this->createQueryBuilder('rel')
			->select('rel, payment')
			->join('rel.payment', 'payment')
			->where('rel.delivery = :deliveryId')
			->setParameter('deliveryId', $delivery->getId())
			->getQuery()
			->getResult();

		return array_map(
			static fn(CartDeliveryAndPaymentRelation $relation): Payment => $relation->getPayment(),
			$relations,
		);
	}
}
