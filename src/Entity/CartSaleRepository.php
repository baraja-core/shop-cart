<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\Entity;


use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

final class CartSaleRepository extends EntityRepository
{
	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function getById(int $id): CartSale
	{
		$cartItem = $this->createQueryBuilder('cartSale')
			->where('cartSale.id = :id')
			->setParameter('id', $id)
			->setMaxResults(1)
			->getQuery()
			->getSingleResult();
		assert($cartItem instanceof CartSale);

		return $cartItem;
	}
}
