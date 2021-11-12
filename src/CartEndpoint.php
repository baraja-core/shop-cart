<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart;


use Baraja\AdminBar\User\AdminIdentity;
use Baraja\Cms\User\Entity\User;
use Baraja\Doctrine\EntityManager;
use Baraja\ImageGenerator\ImageGenerator;
use Baraja\Shop\Cart\Entity\CartItem;
use Baraja\Shop\Customer\Entity\Customer;
use Baraja\Shop\Delivery\Entity\Delivery;
use Baraja\Shop\Payment\Entity\Payment;
use Baraja\Shop\Price\PriceRendererInterface;
use Baraja\Shop\Product\Entity\Product;
use Baraja\Shop\Product\Entity\ProductImage;
use Baraja\Shop\Product\Entity\ProductVariant;
use Baraja\StructuredApi\Attributes\PublicEndpoint;
use Baraja\StructuredApi\BaseEndpoint;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

#[PublicEndpoint]
final class CartEndpoint extends BaseEndpoint
{
	public function __construct(
		private CartManager $cartManager,
		private OrderManager $orderManager,
		private EntityManager $entityManager,
		private PriceRendererInterface $priceRenderer,
	) {
	}


	public function actionDefault(): void
	{
		$items = [];
		$price = 0;
		$priceWithoutVat = 0;
		$cart = $this->cartManager->getCart();
		if ($cart !== null) {
			foreach ($cart->getItems() as $cartItem) {
				$items[] = [
					'id' => $cartItem->getId(),
					'url' => $this->linkSafe(':Front:Product:detail', [
						'slug' => $cartItem->getProduct()->getSlug(),
					]),
					'mainImageUrl' => (static function (?ProductImage $image): ?string {
						if ($image === null) {
							return null;
						}

						return ImageGenerator::from($image->getRelativePath(), ['w' => 150, 'h' => 150]);
					})($cartItem->getProduct()->getMainImage()),
					'name' => $cartItem->getName(),
					'count' => $cartItem->getCount(),
					'price' => $this->cartManager->formatPrice($cartItem->getBasicPrice()),
					'description' => $cartItem->getDescription(),
				];
				$price += $cartItem->getPrice();
				$priceWithoutVat += $cartItem->getPriceWithoutVat();
			}
		}

		$this->sendJson([
			'items' => $items,
			'priceToFreeDelivery' => (int) ($price >= 1_000 ? 0 : (1_000 - $price)),
			'price' => [
				'final' => $this->cartManager->formatPrice($price),
				'withoutVat' => $this->cartManager->formatPrice($priceWithoutVat),
			],
		]);
	}


	/**
	 * @param string[] $variantOptions
	 */
	public function postCheckVariantStatus(int $productId, array $variantOptions = []): void
	{
		/** @var Product $product */
		$product = $this->entityManager->getRepository(Product::class)->find($productId);

		$hash = ProductVariant::serializeParameters($variantOptions);

		/** @var ProductVariant[] $variants */
		$variants = $this->entityManager->getRepository(ProductVariant::class)
			->createQueryBuilder('variant')
			->where('variant.product = :productId')
			->setParameter('productId', $productId)
			->getQuery()
			->getResult();

		$exist = false;
		$variantId = null;
		$variantAvailable = false;
		$variantPrice = null;
		$regularPrice = null;
		$sale = false;
		$variantEntity = null;
		if ($variants === []) {
			$variantPrice = $product->getPrice();
			$regularPrice = $product->getPrice();
		} else {
			foreach ($variants as $variant) {
				if ($variant->getRelationHash() === $hash) {
					$exist = true;
					$variantId = $variant->getId();
					$variantAvailable = $variant->isSoldOut() === false;
					$variantPrice = $variant->getPrice();
					$regularPrice = $variant->getPrice(false);
					$sale = $variant->getProduct()->isSale();
					$variantEntity = $variant;
					break;
				}
			}
		}

		$this->sendJson([
			'exist' => $exist,
			'variantId' => $variantId,
			'available' => $variantAvailable,
			'price' => $variantPrice !== null ? $this->priceRenderer->render($variantPrice) : null,
			'regularPrice' => $regularPrice !== null ? $this->priceRenderer->render($regularPrice) : null,
			'sale' => $sale,
			'dataLayer' => $this->getDataLayer($product, $variantEntity),
		]);
	}


	public function postBuy(int $productId, ?int $variantId = null, int $count = 1): void
	{
		/** @var Product $product */
		$product = $this->entityManager->getRepository(Product::class)->find($productId);

		$variant = null;
		if ($product->isVariantProduct()) {
			foreach ($product->getVariants() as $variantItem) {
				if ($variantItem->getId() === $variantId) {
					$variant = $variantItem;
				}
			}
			if ($variant === null) {
				$this->sendError('Varianta "' . $variantId . '" neexistuje. Vyberte jinou variantu produktu.');
			}
		}

		$cartItem = $this->cartManager->buyProduct($product, $variant, $count);

		$this->sendJson([
			'count' => $this->cartManager->getItemsCount(),
			'dataLayer' => $this->getDataLayer(
				$cartItem->getProduct(),
				$cartItem->getVariant(),
				$cartItem->getCount()
			),
		]);
	}


	public function actionCheckVoucher(string $code): void
	{
		$this->sendJson([
			'status' => false,
		]);
		/*
		try {
			/** @var SaleCoupon $voucher * /
			$voucher = $this->entityManager->getRepository(SaleCoupon::class)
				->createQueryBuilder('voucher')
				->where('voucher.code = :code')
				->setParameter('code', trim($code))
				->setMaxResults(1)
				->getQuery()
				->getSingleResult();
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->sendJson([
				'status' => false,
			]);
		}
		if ($voucher->isReady() === false) {
			$this->sendJson([
				'status' => false,
			]);
		}

		/** @var Product|null $product * /
		$product = $this->entityManager->getRepository(Product::class)->find((int) $voucher->getValue());

		$this->sendJson([
			'status' => true,
			'type' => $voucher->getType(),
			'product' => $product === null ? null : (string) $product->getName(),
		]);
		*/
	}


	public function postUseVoucher(string $code): void
	{
		$this->sendError('Voucher "' . $code . '" does not exist.');
		/*
		try {
			/** @var SaleCoupon $voucher * /
			$voucher = $this->entityManager->getRepository(SaleCoupon::class)
				->createQueryBuilder('voucher')
				->where('voucher.code = :code')
				->setParameter('code', trim($code))
				->setMaxResults(1)
				->getQuery()
				->getSingleResult();
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->sendError('Voucher "' . $code . '" neexistuje.');
		}
		if ($voucher->isReady() === false) {
			$this->sendError('Voucher "' . $code . '" není aktivní.');
		}

		/** @var Cart $cart * /
		$cart = $this->cartManager->getCart(true);
		$cart->setSaleCoupon($voucher);
		$this->entityManager->flush();
		$this->sendOk();
		*/
	}


	public function actionChangeItemsCount(int $id, int $count): void
	{
		$item = $this->getItemById($id);
		if ($item !== null) {
			if ($count === 0) {
				$this->actionDeleteItem($id);
			}
			$item->setCount($count);
			$this->entityManager->flush();
		}

		$this->sendOk();
	}


	public function actionDeleteItem(int $id): void
	{
		$cartItem = $this->getItemById($id);
		if ($cartItem === null) {
			$this->sendError('Cart item "' . $id . '" does not exist.');
		}
		$this->entityManager->remove($cartItem);
		$this->entityManager->flush();

		$this->sendOk([
			'dataLayer' => $this->getDataLayer(
				$cartItem->getProduct(),
				$cartItem->getVariant(),
				$cartItem->getCount()
			),
		]);
	}


	public function postDeliveryAndPayments(string $delivery, string $payment, ?int $branch = null): void
	{
		$cart = $this->cartManager->getCart(true);
		if ($cart === null) {
			$this->sendError('Košík nemůže být prázdný.');
		}

		/** @var Delivery $deliveryEntity */
		$deliveryEntity = $this->entityManager->getRepository(Delivery::class)
			->createQueryBuilder('delivery')
			->where('delivery.code = :code')
			->setParameter('code', $delivery)
			->setMaxResults(1)
			->getQuery()
			->getSingleResult();

		/** @var Payment $paymentEntity */
		$paymentEntity = $this->entityManager->getRepository(Payment::class)
			->createQueryBuilder('payment')
			->where('payment.code = :code')
			->setParameter('code', $payment)
			->setMaxResults(1)
			->getQuery()
			->getSingleResult();

		$cart->setDelivery($deliveryEntity);
		$cart->setPayment($paymentEntity);
		$cart->setDeliveryBranchId($branch);

		$this->entityManager->flush();
		$this->sendOk();
	}


	public function actionCustomer(): void
	{
		$items = [];
		$cart = $this->cartManager->getCart();
		if ($cart === null) {
			$this->sendError('Cart is empty.');
		}

		$delivery = $cart->getDelivery();
		$payment = $cart->getPayment();

		if ($delivery === null || $payment === null) {
			$this->sendError('Nebyla vybrána doprava a platba.');
		}
		foreach ($cart->getItems() as $item) {
			$img = $item->getMainImageRelativePath();
			$items[] = [
				'id' => $item->getId(),
				'mainImage' => $img !== null
					? ImageGenerator::from($img, ['w' => 96, 'h' => 96])
					: null,
				'url' => $this->linkSafe('Front:Product:detail', [
					'slug' => $item->getProduct()->getSlug(),
				]),
				'name' => $item->getName(),
				'price' => $item->getPrice(),
			];
		}

		$this->sendJson([
			'loggedIn' => $this->getUser()->isLoggedIn(),
			'items' => $items,
			'price' => $this->cartManager->formatPrice($cart->getPrice()),
			'itemsPrice' => $this->cartManager->formatPrice($cart->getItemsPrice()),
			'deliveryPrice' => $this->cartManager->formatPrice($cart->getDeliveryPrice()),
			'delivery' => [
				'name' => (string) $delivery->getName(),
				'price' => $delivery->getPrice(),
			],
			'payment' => [
				'name' => $payment->getName(),
				'price' => $payment->getPrice(),
			],
		]);
	}


	public function postCustomer(OrderInfo $orderInfo): void
	{
		if ($orderInfo->getInfo()->isGdpr() === false) {
			$this->sendError('Musíte souhlasit s podmínkami služby.');
		}
		$cart = $this->cartManager->getCart();
		if ($cart === null) {
			$this->sendError('Neexistuje košík.');
		}

		$order = $this->orderManager->createOrder($orderInfo, $cart);
		$this->sendOk([
			'id' => $order->getId(),
			'hash' => $order->getHash(),
		]);
	}


	public function actionCustomerDefaultInfo(): void
	{
		$return = [
			'ready' => false,
			'firstName' => '',
			'lastName' => '',
			'email' => '',
			'phone' => '',
			'street' => '',
			'city' => '',
			'zip' => '',
			'companyName' => '',
			'ic' => '',
		];
		if ($this->getUser()->isLoggedIn()) {
			$identity = $this->getUser()->getIdentity();
			$customer = null;
			if ($identity instanceof AdminIdentity) {
				/** @var User $adminUser */
				$adminUser = $this->entityManager->getRepository(User::class)->find($this->getUser()->getId());
				try {
					/** @var Customer $customer */
					$customer = $this->entityManager->getRepository(Customer::class)
						->createQueryBuilder('customer')
						->where('customer.email = :email')
						->setParameter('email', $adminUser->getEmail())
						->setMaxResults(1)
						->getQuery()
						->getSingleResult();
				} catch (NoResultException | NonUniqueResultException) {
					// Admin customer does not exist.
				}
			} else {
				/** @var Customer $customer */
				$customer = $this->entityManager->getRepository(Customer::class)->find($this->getUser()->getId());
			}
			if ($customer !== null) {
				$return = array_merge($return, [
					'ready' => true,
					'firstName' => $customer->getFirstName(),
					'lastName' => $customer->getLastName(),
					'email' => $customer->getEmail(),
					'phone' => $customer->getPhone() ?? '',
					'street' => $customer->getStreet() ?? '',
					'city' => $customer->getCity() ?? '',
					'zip' => (string) $customer->getZip(),
					'companyName' => $customer->getCompanyName() ?? '',
					'ic' => $customer->getIc() ?? '',
				]);
			}
		}

		$this->sendJson($return);
	}


	/**
	 * @return array{name: string, id: string, price: string, brand: string, category: string|null, variant: string|null, quantity: int}
	 */
	private function getDataLayer(Product $product, ?ProductVariant $variant = null, int $count = 1): array
	{
		$mainCategory = $product->getMainCategory();
		if ($variant === null) {
			return [
				'name' => (string) $product->getName(),
				'id' => (string) $product->getId(),
				'price' => (string) $product->getPrice(),
				'brand' => 'CLEVER MINDS',
				'category' => $mainCategory !== null
					? (string) $mainCategory->getName()
					: null,
				'variant' => null,
				'quantity' => $count,
			];
		}

		return [
			'name' => (string) $product->getName(),
			'id' => $product->getId() . '-' . $variant->getId(),
			'price' => (string) $variant->getPrice(),
			'brand' => 'CLEVER MINDS',
			'category' => $mainCategory !== null
				? (string) $mainCategory->getName()
				: null,
			'variant' => $variant->getLabel(),
			'quantity' => $count,
		];
	}


	private function getItemById(int $id): ?CartItem
	{
		$cart = $this->cartManager->getCart();

		try {
			/** @var CartItem $cartItem */
			$cartItem = $this->entityManager->getRepository(CartItem::class)
				->createQueryBuilder('cartItem')
				->where('cartItem.id = :id')
				->andWhere('cartItem.cart = :cartId')
				->setParameter('id', $id)
				->setParameter('cartId', $cart !== null ? $cart->getId() : null)
				->setMaxResults(1)
				->getQuery()
				->getSingleResult();

			return $cartItem;
		} catch (NoResultException | NonUniqueResultException) {
			// Silence is golden.
		}

		return null;
	}
}
