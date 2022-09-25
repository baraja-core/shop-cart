<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart;


use Baraja\EcommerceStandard\DTO\CartInterface;
use Baraja\EcommerceStandard\DTO\CustomerInterface;
use Baraja\EcommerceStandard\DTO\DeliveryInterface;
use Baraja\EcommerceStandard\DTO\PaymentInterface;
use Baraja\EcommerceStandard\DTO\PriceInterface;
use Baraja\EcommerceStandard\DTO\ProductInterface;
use Baraja\EcommerceStandard\DTO\ProductVariantInterface;
use Baraja\EcommerceStandard\Service\CartManagerInterface;
use Baraja\EcommerceStandard\Service\CurrencyResolverInterface;
use Baraja\Shop\Cart\Bridge\NetteSessionBridge;
use Baraja\Shop\Cart\Entity\Cart;
use Baraja\Shop\Cart\Entity\CartDeliveryAndPaymentRelation;
use Baraja\Shop\Cart\Entity\CartDeliveryAndPaymentRelationRepository;
use Baraja\Shop\Cart\Entity\CartItem;
use Baraja\Shop\Cart\Entity\CartItemRepository;
use Baraja\Shop\Cart\Entity\CartRepository;
use Baraja\Shop\Cart\Entity\CartRuntimeContext;
use Baraja\Shop\Cart\Session\NativeSessionProvider;
use Baraja\Shop\Cart\Session\SessionProvider;
use Baraja\Shop\Currency\CurrencyManagerAccessor;
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
		private CurrencyResolverInterface $currencyResolver,
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
				$cart = new Cart($identifier, $this->currencyResolver->resolveEntity());
				$this->entityManager->persist($cart);
			}
		}
		if ($cart instanceof Cart) {
			$cart->setRuntimeContext($this->runtimeContext);
			if ($cart->isCurrency() === false) { // back compatibility
				$cart->setCurrency($this->currencyResolver->resolveEntity());
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
		if ($variant === null && $product->isVariantProduct() === true) {
			throw new \InvalidArgumentException(sprintf(
				'Please select variant for product "%s" (%s).',
				$product->getLabel(),
				$product->getId(),
			));
		}
		if ($product->isSoldOut()) {
			throw new \InvalidArgumentException(sprintf(
				'You cannot purchase the product "%s" (%s) because it is sold out.',
				$product->getLabel(),
				$product->getId(),
			));
		}
		$cart = $this->getCartFlushed();
		try {
			$cartItemRepo = $this->entityManager->getRepository(CartItem::class);
			assert($cartItemRepo instanceof CartItemRepository);
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
		if ($this->user->isLoggedIn() === false) {
			return count($this->getCart(false)->getItems());
		}

		return count($this->getCartFlushed()->getItems());
	}


	public function isFreeDelivery(?CartInterface $cart = null): bool
	{
		$cart ??= $this->getCart(false);
		assert($cart instanceof Cart);

		return $cart->getRuntimeContext()->getFreeDeliveryResolver()->isFreeDelivery($cart);
	}


	public function isFreePayment(?CartInterface $cart = null): bool
	{
		$cart ??= $this->getCart(false);
		assert($cart instanceof Cart);

		return true; // TODO: Implement me!
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


	public function removeCart(CartInterface $cart): void
	{
		foreach ($cart->getAllItems() as $item) {
			$this->entityManager->remove($item);
		}
		$this->entityManager->remove($cart);
		$this->entityManager->flush();
	}


	public function setDelivery(DeliveryInterface $delivery): void
	{
		$cart = $this->getCart(false);
		$cart->setDelivery($delivery);
	}


	public function setPayment(PaymentInterface $payment): void
	{
		$cart = $this->getCart(false);
		$delivery = $cart->getDelivery();
		if ($delivery === null) {
			throw new \InvalidArgumentException('Payment can not be selected when delivery does not exist.');
		}

		$relationRepository = $this->entityManager->getRepository(CartDeliveryAndPaymentRelation::class);
		assert($relationRepository instanceof CartDeliveryAndPaymentRelationRepository);
		if ($relationRepository->isCompatibleDeliveryAndPayment($delivery, $payment) === false) {
			throw new \InvalidArgumentException(sprintf(
				'Payment "%s" is not compatible with selected delivery "%s".',
				$payment->getCode(),
				$delivery->getCode(),
			));
		}

		$cart->setPayment($payment);
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
