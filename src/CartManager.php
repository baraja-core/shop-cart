<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart;


use Baraja\EcommerceStandard\DTO\CartInterface;
use Baraja\EcommerceStandard\DTO\CustomerInterface;
use Baraja\EcommerceStandard\DTO\PriceInterface;
use Baraja\EcommerceStandard\DTO\ProductInterface;
use Baraja\EcommerceStandard\DTO\ProductVariantInterface;
use Baraja\EcommerceStandard\Service\CartManagerInterface;
use Baraja\Shop\Cart\Bridge\NetteSessionBridge;
use Baraja\Shop\Cart\Entity\Cart;
use Baraja\Shop\Cart\Entity\CartItem;
use Baraja\Shop\Cart\Entity\CartItemRepository;
use Baraja\Shop\Cart\Entity\CartRepository;
use Baraja\Shop\Cart\Entity\CartRuntimeContext;
use Baraja\Shop\Cart\Session\NativeSessionProvider;
use Baraja\Shop\Cart\Session\SessionProvider;
use Baraja\Shop\ContextAccessor;
use Baraja\Shop\Currency\CurrencyManagerAccessor;
use Baraja\Shop\Price\Price;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Nette\Http\Session;
use Nette\Security\User;
use Nette\Utils\Random;

final class CartManager implements CartManagerInterface
{
	private SessionProvider $sessionProvider;

	private CartSecondLevelCache $secondLevelCache;

	private CartRuntimeContext $runtimeContext;


	public function __construct(
		private EntityManagerInterface $entityManager,
		private User $user,
		private ContextAccessor $context,
		CurrencyManagerAccessor $currencyManager,
		SessionProvider|Session|null $sessionProvider = null,
	) {
		if ($sessionProvider === null) {
			$this->sessionProvider = new NativeSessionProvider;
		} elseif ($sessionProvider instanceof Session) {
			$this->sessionProvider = new NetteSessionBridge($sessionProvider);
		} else {
			$this->sessionProvider = $sessionProvider;
		}
		$this->secondLevelCache = new CartSecondLevelCache;
		$this->runtimeContext = new CartRuntimeContext($currencyManager);
	}


	/**
	 * @deprecated since 2021-11-12, use CartRuntimeContext.
	 */
	public static function getFreeDeliveryLimit(): float
	{
		trigger_error(__METHOD__ . ': Method is deprecated, use CartRuntimeContext instead.');

		return 1_000;
	}


	public function getCart(bool $flush = true): CartInterface
	{
		$identifier = $this->getIdentifier();
		$cart = $this->secondLevelCache->getCart($identifier);
		if ($cart === null) {
			try {
				$cartRepo = $this->entityManager->getRepository(Cart::class);
				assert($cartRepo instanceof CartRepository);
				$cart = $cartRepo->getCart($identifier);
			} catch (NoResultException | NonUniqueResultException) {
				$cart = new Cart($identifier, $this->context->get()->getCurrency());
				$this->entityManager->persist($cart);
			}
		}
		if ($cart instanceof Cart) {
			$cart->setRuntimeContext($this->runtimeContext);
			if ($cart->isCurrency() === false) { // back compatibility
				$cart->setCurrency($this->context->get()->getCurrency());
				$flush = true;
			}
		}
		$this->secondLevelCache->saveCart($identifier, $cart);
		if ($flush === true) {
			$this->entityManager->flush();
		}

		return $cart;
	}


	public function getCartFlushed(): CartInterface
	{
		return $this->getCart(true);
	}


	public function isCartFlushed(): bool
	{
		return $this->getCart(false)->isFlushed();
	}


	public function buyProduct(ProductInterface $product, ?ProductVariantInterface $variant, int $count = 1): CartItem
	{
		$cart = $this->getCartFlushed();
		if ($variant === null && $product->isVariantProduct() === true) {
			throw new \InvalidArgumentException(sprintf('Please select variant for product "%s" (%s).',
				$product->getLabel(),
				$product->getId(),
			));
		}
		try {
			/** @var CartItemRepository $cartItemRepo */
			$cartItemRepo = $this->entityManager->getRepository(CartItemRepository::class);
			$cartItem = $cartItemRepo->getByProduct($this->getIdentifier(), $product, $variant);
		} catch (NoResultException | NonUniqueResultException) {
			assert($cart instanceof Cart);
			$cartItem = new CartItem($cart, $product, $variant, 0);
			$this->entityManager->persist($cartItem);
		}

		$cartItem->addCount($count);
		$this->entityManager->flush();

		return $cartItem;
	}


	public function getItemsCount(): int
	{
		if ($this->user->isLoggedIn() === false && $this->sessionProvider->getHash() === null) {
			return 0;
		}

		return count($this->getCartFlushed()->getItems());
	}


	public function isFreeDelivery(?CartInterface $cart = null): bool
	{
		$cart ??= $this->getCart(false);
		assert($cart instanceof Cart);

		return $cart->getRuntimeContext()->getFreeDeliveryResolver()->isFreeDelivery($cart);
	}


	public function getFreeDeliveryMinimalPrice(
		?CartInterface $cart = null,
		?CustomerInterface $customer = null,
	): PriceInterface {
		if ($cart === null) {
			$cart = $this->getCart(false);
		}
		assert($cart instanceof Cart);

		return $cart->getRuntimeContext()->getFreeDeliveryResolver()->getMinimalPrice($cart, $customer);
	}


	/**
	 * @deprecated since 2021-12-03, use Price entity instead.
	 */
	public function formatPrice(float $price): string
	{
		return str_replace(',00', '', number_format($price, 2, ',', ' '));
	}


	public function removeCart(CartInterface $cart): void
	{
		foreach ($cart->getAllItems() as $item) {
			$this->entityManager->remove($item);
		}
		$this->entityManager->remove($cart);
		$this->entityManager->flush();
	}


	private function getIdentifier(): string
	{
		if ($this->user->isLoggedIn()) {
			$userId = $this->user->getId();
			if (is_numeric($userId) || is_string($userId)) {
				return 'user_' . substr(md5((string) $userId), 0, 27);
			}
			throw new \LogicException(
				sprintf('User id must be a scalar, but type "%s" given.', get_debug_type($userId)),
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
