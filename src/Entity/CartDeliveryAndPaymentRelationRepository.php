<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\Entity;


use Baraja\EcommerceStandard\DTO\DeliveryInterface;
use Baraja\EcommerceStandard\DTO\PaymentInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

final class CartDeliveryAndPaymentRelationRepository extends EntityRepository
{
	/** @return array<int, PaymentInterface> */
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
			static fn(CartDeliveryAndPaymentRelation $relation): PaymentInterface => $relation->getPayment(),
			$relations,
		);
	}


	public function isCompatibleDeliveryAndPayment(DeliveryInterface $delivery, PaymentInterface $payment): bool
	{
		try {
			$this->createQueryBuilder('rel')
				->select('PARTIAL rel.{id}')
				->where('rel.delivery = :deliveryId')
				->andWhere('rel.payment = :paymentId')
				->setParameter('deliveryId', $delivery->getId())
				->setParameter('paymentId', $payment->getId())
				->setMaxResults(1)
				->getQuery()
				->getSingleResult();
		} catch (NoResultException|NonUniqueResultException) {
			return false;
		}

		return true;
	}
}
