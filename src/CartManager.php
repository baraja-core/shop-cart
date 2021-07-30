<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart;


use Baraja\Doctrine\EntityManager;
use Baraja\Shop\Cart\Entity\Cart;
use Baraja\Shop\Cart\Entity\CartItem;
use Baraja\Shop\Product\Entity\Product;
use Baraja\Shop\Product\Entity\ProductVariant;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Nette\Http\Session;
use Nette\Http\SessionSection;
use Nette\Security\User;
use Nette\Utils\Random;
use Tracy\Debugger;
use Tracy\ILogger;

final class CartManager
{
	private SessionSection $session;


	public function __construct(
		private EntityManager $entityManager,
		private User $user,
		Session $session,
	) {
		$this->session = $session->getSection('cart');
	}


	public static function getFreeDeliveryLimit(): float
	{
		return 1_000;
	}


	public function getCart(bool $flush = true): ?Cart
	{
		try {
			return $this->entityManager->getRepository(Cart::class)
				->createQueryBuilder('cart')
				->andWhere('cart.identifier = :identifier')
				->setParameter('identifier', $this->getIdentifier())
				->setMaxResults(1)
				->getQuery()
				->getSingleResult();
		} catch (NoResultException | NonUniqueResultException) {
			if ($flush === true) {
				$cart = new Cart($this->getIdentifier());
				$this->entityManager->persist($cart);
				$this->entityManager->flush();

				return $cart;
			}
		}

		return null;
	}


	public function buyProduct(Product $product, ?ProductVariant $variant, int $count = 1): CartItem
	{
		$cart = $this->getCart(true);
		if ($variant === null && $product->isVariantProduct() === true) {
			throw new \InvalidArgumentException('Please select variant for product "' . $product->getName() . '" (' . $product->getId() . ').');
		}
		try {
			$select = $this->entityManager->getRepository(CartItem::class)
				->createQueryBuilder('cartItem')
				->leftJoin('cartItem.cart', 'cart')
				->where('cartItem.product = :productId')
				->andWhere('cart.identifier = :identifier')
				->setParameter('productId', $product->getId())
				->setParameter('identifier', $this->getIdentifier());

			if ($variant !== null) {
				$select->andWhere('cartItem.variant = :variantId')
					->setParameter('variantId', $variant->getId());
			}

			/** @var CartItem $cartItem */
			$cartItem = $select->setMaxResults(1)
				->getQuery()
				->getSingleResult();
		} catch (NoResultException | NonUniqueResultException) {
			$cartItem = new CartItem($cart, $product, $variant, 0);
			$this->entityManager->persist($cartItem);
		}

		$cartItem->addCount($count);
		$this->entityManager->flush();

		return $cartItem;
	}


	public function getItemsCount(bool $flush = false): int
	{
		if ($this->user->isLoggedIn() === false && $this->session->offsetGet('hash') === null) {
			return 0;
		}
		static $count;
		if ($count === null || $flush === true) {
			try {
				$count = (int) $this->entityManager->getRepository(CartItem::class)
					->createQueryBuilder('cartItem')
					->select('COUNT(cartItem)')
					->leftJoin('cartItem.cart', 'cart')
					->where('cart.identifier = :identifier')
					->setParameter('identifier', $this->getIdentifier())
					->getQuery()
					->getSingleScalarResult();
			} catch (\Throwable $e) {
				Debugger::log($e, ILogger::CRITICAL);

				$count = 0;
			}
		}

		return $count;
	}


	public function isFreeDelivery(): bool
	{
		$cart = $this->getCart();
		if ($cart !== null) {
			return $cart->getItemsPrice() >= 1_000;
		}

		return false;
	}


	public function formatPrice(float $price): string
	{
		return str_replace(',00', '', number_format($price, 2, ',', ' '));
	}


	public function removeCart(Cart $cart): void
	{
		foreach ($cart->getItems() as $item) {
			$this->entityManager->remove($item);
		}
		$this->entityManager->remove($cart);
	}


	private function getIdentifier(): string
	{
		if ($this->user->isLoggedIn()) {
			return 'user_' . substr(md5((string) $this->user->getId()), 0, 27);
		}
		$identifier = $this->session->offsetGet('hash');
		if ($identifier === null) {
			$identifier = 'anonymous_' . Random::generate(22);
			$this->session->offsetSet('hash', $identifier);
		}

		return $identifier;
	}
}
