<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart;


use Baraja\Shop\Cart\Bridge\NetteSessionBridge;
use Baraja\Shop\Cart\Entity\Cart;
use Baraja\Shop\Cart\Entity\CartItem;
use Baraja\Shop\Cart\Entity\CartItemRepository;
use Baraja\Shop\Cart\Entity\CartRepository;
use Baraja\Shop\Cart\Session\NativeSessionProvider;
use Baraja\Shop\Cart\Session\SessionProvider;
use Baraja\Shop\Product\Entity\Product;
use Baraja\Shop\Product\Entity\ProductVariant;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Nette\Http\Session;
use Nette\Security\User;
use Nette\Utils\Random;

final class CartManager
{
	private SessionProvider $sessionProvider;


	public function __construct(
		private EntityManagerInterface $entityManager,
		private User $user,
		SessionProvider|Session|null $sessionProvider = null,
	) {
		if ($sessionProvider === null) {
			$this->sessionProvider = new NativeSessionProvider;
		} elseif ($sessionProvider instanceof Session) {
			$this->sessionProvider = new NetteSessionBridge($sessionProvider);
		} else {
			$this->sessionProvider = $sessionProvider;
		}
	}


	public static function getFreeDeliveryLimit(): float
	{
		return 1_000;
	}


	public function getCart(bool $flush = true): ?Cart
	{
		try {
			/** @var CartRepository $cartRepo */
			$cartRepo = $this->entityManager->getRepository(CartRepository::class);

			return $cartRepo->getCart($this->getIdentifier());
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


	public function getCartFlushed(): Cart
	{
		$cart = $this->getCart(true);
		assert($cart !== null);

		return $cart;
	}


	public function isCartFlushed(): bool
	{
		return $this->getCart(false) !== null;
	}


	public function buyProduct(Product $product, ?ProductVariant $variant, int $count = 1): CartItem
	{
		$cart = $this->getCartFlushed();
		if ($variant === null && $product->isVariantProduct() === true) {
			throw new \InvalidArgumentException(
				sprintf('Please select variant for product "%s" (%s).', $product->getName(), $product->getId())
			);
		}
		try {
			/** @var CartItemRepository $cartItemRepo */
			$cartItemRepo = $this->entityManager->getRepository(CartItemRepository::class);
			$cartItem = $cartItemRepo->getByProduct($this->getIdentifier(), $product, $variant);
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
		if ($this->user->isLoggedIn() === false && $this->sessionProvider->getHash() === null) {
			return 0;
		}
		static $count;
		if ($count === null || $flush === true) {
			/** @var CartItemRepository $cartItemRepo */
			$cartItemRepo = $this->entityManager->getRepository(CartItemRepository::class);
			$count = $cartItemRepo->getItemsCount($this->getIdentifier());
		}

		return $count;
	}


	public function isFreeDelivery(): bool
	{
		$cart = $this->getCart(false);
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
			$userId = $this->user->getId();
			if (is_numeric($userId) || is_string($userId)) {
				return 'user_' . substr(md5((string) $userId), 0, 27);
			}
			throw new \LogicException(
				sprintf('User id must be a scalar, but type "%s" given.', get_debug_type($userId))
			);
		}
		$identifier = $this->sessionProvider->getHash();
		if ($identifier === null) {
			$identifier = 'anonymous_' . Random::generate(22);
			$this->sessionProvider->setHash($identifier);
		}

		return $identifier;
	}
}
