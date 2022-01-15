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
		$cart = $this->createQueryBuilder('cart')
			->select('cart, cartItem, product, productVariant')
			->leftJoin('cart.items', 'cartItem')
			->leftJoin('cartItem.product', 'product')
			->leftJoin('cartItem.variant', 'productVariant')
			->andWhere('cart.identifier = :identifier')
			->setParameter('identifier', $identifier)
			->getQuery()
			->getSingleResult();
		assert($cart instanceof Cart);

		return $cart;
	}
}
