<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\Entity;


use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

final class CartRepository extends EntityRepository
{
	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function getCart(string $identifier): Cart
	{
		/** @phpstan-ignore-next-line */
		return $this->createQueryBuilder('cart')
			->andWhere('cart.identifier = :identifier')
			->setParameter('identifier', $identifier)
			->setMaxResults(1)
			->getQuery()
			->getSingleResult();
	}
}
