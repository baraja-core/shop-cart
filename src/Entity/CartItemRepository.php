<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\Entity;


use Baraja\EcommerceStandard\DTO\ProductInterface;
use Baraja\EcommerceStandard\DTO\ProductVariantInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

final class CartItemRepository extends EntityRepository
{
	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function getById(int $id, ?int $cartId = null): CartItem
	{
		$cartItem = $this->createQueryBuilder('cartItem')
			->where('cartItem.id = :id')
			->andWhere('cartItem.cart = :cartId')
			->setParameter('id', $id)
			->setParameter('cartId', $cartId)
			->setMaxResults(1)
			->getQuery()
			->getSingleResult();
		assert($cartItem instanceof CartItem);

		return $cartItem;
	}

	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function getByProduct(string $identifier, ProductInterface $product, ?ProductVariantInterface $variant = null): CartItem
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

		$cartItem = $select->setMaxResults(1)
			->getQuery()
			->getSingleResult();
		assert($cartItem instanceof CartItem);

		return $cartItem;
	}
}
