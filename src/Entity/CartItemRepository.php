<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\Entity;


use Baraja\Shop\Product\Entity\Product;
use Baraja\Shop\Product\Entity\ProductVariant;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

final class CartItemRepository extends EntityRepository
{
	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function getByProduct(string $identifier, Product $product, ?ProductVariant $variant = null): CartItem
	{
		$select = $this->createQueryBuilder('cartItem')
			->leftJoin('cartItem.cart', 'cart')
			->where('cartItem.product = :productId')
			->andWhere('cart.identifier = :identifier')
			->setParameter('productId', $product->getId())
			->setParameter('identifier', $identifier);

		if ($variant !== null) {
			$select->andWhere('cartItem.variant = :variantId')
				->setParameter('variantId', $variant->getId());
		}

		/** @var CartItem $cartItem */
		$cartItem = $select->setMaxResults(1)
			->getQuery()
			->getSingleResult();

		return $cartItem;
	}
}
