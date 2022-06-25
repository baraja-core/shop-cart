<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart;


use Baraja\AdminBar\User\AdminIdentity;
use Baraja\Cms\User\Entity\User;
use Baraja\Doctrine\EntityManager;
use Baraja\EcommerceStandard\DTO\CurrencyInterface;
use Baraja\EcommerceStandard\DTO\ImageInterface;
use Baraja\EcommerceStandard\DTO\ProductInterface;
use Baraja\EcommerceStandard\DTO\ProductVariantInterface;
use Baraja\EcommerceStandard\Service\OrderManagerInterface;
use Baraja\ImageGenerator\ImageGenerator;
use Baraja\Shop\Cart\DTO\DataLayer;
use Baraja\Shop\Cart\Entity\CartItem;
use Baraja\Shop\Cart\Entity\CartItemRepository;
use Baraja\Shop\Cart\Entity\CartVoucher;
use Baraja\Shop\Cart\Entity\CartVoucherRepository;
use Baraja\Shop\Currency\CurrencyManager;
use Baraja\Shop\Customer\Entity\Customer;
use Baraja\Shop\Delivery\Entity\Delivery;
use Baraja\Shop\Payment\Entity\Payment;
use Baraja\Shop\Price\Price;
use Baraja\Shop\Product\Entity\Product;
use Baraja\Shop\Product\Entity\ProductVariant;
use Baraja\Shop\Product\Recommender\ProductRecommenderAccessor;
use Baraja\Shop\Product\Repository\ProductRepository;
use Baraja\StructuredApi\Attributes\PublicEndpoint;
use Baraja\StructuredApi\BaseEndpoint;
use Baraja\Url\Url;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

#[PublicEndpoint]
final class CartEndpoint extends BaseEndpoint
{
	private ProductRepository $productRepository;

	private CartItemRepository $cartItemRepository;

	private CartVoucherRepository $cartVoucherRepository;


	public function __construct(
		private CartManager $cartManager,
		private VoucherManager $voucherManager,
		private OrderManagerInterface $orderManager,
		private EntityManager $entityManager,
		private CurrencyManager $currencyManager,
		private ProductRecommenderAccessor $productRecommender,
	) {
		$productRepository = $entityManager->getRepository(Product::class);
		assert($productRepository instanceof ProductRepository);
		$this->productRepository = $productRepository;
		$cartItemRepository = $entityManager->getRepository(CartItem::class);
		assert($cartItemRepository instanceof CartItemRepository);
		$this->cartItemRepository = $cartItemRepository;
		$cartVoucherRepository = $entityManager->getRepository(CartVoucher::class);
		assert($cartVoucherRepository instanceof CartVoucherRepository);
		$this->cartVoucherRepository = $cartVoucherRepository;
	}


	public function actionDefault(): void
	{
		$items = [];
		$price = '0';
		$priceWithoutVat = '0';
		$cart = $this->cartManager->getCartFlushed();
		$currency = $cart->getCurrency();
		$products = [];
		foreach ($cart->getItems() as $cartItem) {
			$product = $cartItem->getProduct();
			assert($product instanceof Product);
			$items[] = [
				'id' => $cartItem->getId(),
				'url' => $this->linkSafe(':Front:Product:detail', [
					'slug' => $product->getSlug(),
				]),
				'mainImageUrl' => (static function (?ImageInterface $image): ?string {
					if ($image === null) {
						return null;
					}

					return sprintf(
						'%s/%s',
						Url::get()->getBaseUrl(),
						ImageGenerator::from($image->getRelativePath(), ['w' => 150, 'h' => 150]),
					);
				})($product->getMainImage()),
				'name' => $cartItem->getName(),
				'count' => $cartItem->getCount(),
				'price' => $cartItem->getBasicPrice()->render(true),
				'description' => $cartItem->getDescription(),
				'sale' => false,
			];
			$products[] = $product;
			$price = bcadd($price, $cartItem->getPrice()->getValue());
			$priceWithoutVat = bcadd($priceWithoutVat, $cartItem->getPriceWithoutVat()->getValue());
		}
		foreach ($cart->getSales() as $cartSale) {
			$voucher = $cartSale->getVoucher();
			$items[] = [
				'id' => sprintf('sale_%d', $cartSale->getId()),
				'url' => null,
				'mainImageUrl' => null,
				'name' => $voucher !== null ? $this->voucherManager->formatMessage($voucher) : 'Sleva',
				'count' => 1,
				'price' => null,
				'description' => null,
				'sale' => true,
			];
		}

		$related = [];
		foreach ($this->productRecommender->get()->getRelatedByCollection($products) as $relatedItem) {
			$related[] = $this->formatRelated($relatedItem, $currency);
		}

		$freeDelivery = $cart->getRuntimeContext()->getFreeDeliveryLimit();
		$this->sendJson([
			'items' => $items,
			'priceToFreeDelivery' => $price >= $freeDelivery ? null : (new Price((int) ($freeDelivery - $price), $cart->getCurrency()))->render(),
			'price' => [
				'final' => (new Price($price, $cart->getCurrency()))->render(),
				'withoutVat' => (new Price($priceWithoutVat, $cart->getCurrency()))->render(),
			],
			'related' => $related,
		]);
	}


	/**
	 * @param array<string, string> $variantOptions
	 */
	public function postCheckVariantStatus(int $productId, array $variantOptions = []): void
	{
		$product = $this->productRepository->getById($productId);

		$hash = ProductVariant::serializeParameters($variantOptions);

		/** @var ProductVariant[] $variants */
		$variants = $this->entityManager->getRepository(ProductVariant::class)
			->createQueryBuilder('variant')
			->where('variant.product = :productId')
			->setParameter('productId', $productId)
			->orderBy('variant.relationHash', 'ASC')
			->getQuery()
			->getResult();

		$currency = $this->currencyManager->getCurrencyResolver()->getCurrency();

		$exist = false;
		$variantId = null;
		$variantAvailable = false;
		$variantPrice = null;
		$regularPrice = null;
		$sale = false;
		$variantEntity = null;
		/** @var array<string, array<int, array{text: string, value: string, hash: string}>> $variantsFeed */
		$variantsFeed = [];
		$variantList = [];
		if ($variants === []) {
			$variantPrice = $product->getPrice();
			$regularPrice = $product->getPrice();
		} else {
			foreach ($variants as $variantItem) {
				$variantList[] = [
					'variantId' => $variantItem->getId(),
					'hash' => $variantItem->getRelationHash(),
					'available' => $variantItem->isSoldOut() === false,
					'price' => (new Price($variantItem->getPrice(), $currency))->render(true),
					'regularPrice' => (new Price($variantItem->getPrice(false), $currency))->render(true),
					'sale' => $variantItem->getProduct()->isSale(),
				];
				if ($variantItem->getRelationHash() === $hash) {
					$exist = true;
					$variantId = $variantItem->getId();
					$variantAvailable = $variantItem->isSoldOut() === false;
					$variantPrice = $variantItem->getPrice();
					$regularPrice = $variantItem->getPrice(false);
					$sale = $variantItem->getProduct()->isSale();
					$variantEntity = $variantItem;
				}
			}
			foreach ($variants as $variantItem) {
				$variantParameters = ProductVariant::unserializeParameters($variantItem->getRelationHash());
				if ($variantParameters === []) {
					continue;
				}
				if ($this->isVariantCompatibleWithOptions($variantOptions, $variantParameters) === false) {
					continue;
				}
				foreach ($variantParameters as $variantParameterKey => $variantParameterValue) {
					if (isset($variantsFeed[$variantParameterKey]) === false) {
						$variantsFeed[$variantParameterKey] = [];
					}
					[$tempVariantParams, $tempVariantOptions] = [$variantParameters, $variantOptions];
					if (isset($tempVariantParams[$variantParameterKey], $tempVariantOptions[$variantParameterKey])) {
						unset($tempVariantParams[$variantParameterKey], $tempVariantOptions[$variantParameterKey]);
					}
					if ($tempVariantParams !== $tempVariantOptions) {
						continue;
					}
					$variantsFeed[$variantParameterKey][] = [
						'text' => $variantParameterValue,
						'value' => $variantParameterValue,
						'id' => $variantItem->getId(),
						'hash' => $variantItem->getRelationHash(),
					];
				}
			}
		}

		$this->sendJson([
			'exist' => $exist,
			'variantId' => $variantId,
			'available' => $variantAvailable,
			'price' => $variantPrice !== null ? (new Price($variantPrice, $currency))->render(true) : null,
			'regularPrice' => $regularPrice !== null ? (new Price($regularPrice, $currency))->render(true) : null,
			'sale' => $sale,
			'dataLayer' => $this->getDataLayer($product, $variantEntity),
			'variantList' => $variantList,
			'variantsFeed' => $variantsFeed,
		]);
	}


	public function postBuy(int $productId, ?int $variantId = null, int $count = 1): void
	{
		$product = $this->productRepository->getById($productId);
		$currency = $this->currencyManager->getCurrencyResolver()->getCurrency();

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

		$related = [];
		foreach ($product->getProductRelatedBasic() as $relatedItem) {
			$related[] = $this->formatRelated($relatedItem->getRelatedProduct(), $currency);
		}

		$cartItem = $this->cartManager->buyProduct($product, $variant, $count);

		$this->sendJson([
			'count' => $this->cartManager->getItemsCount(),
			'dataLayer' => $this->getDataLayer(
				$cartItem->getProduct(),
				$cartItem->getVariant(),
				$cartItem->getCount(),
			),
			'related' => $related,
		]);
	}


	public function actionCheckVoucher(string $code): void
	{
		try {
			$voucher = $this->cartVoucherRepository->findByCode($code);
			$available = $voucher->isAvailable();
		} catch (NoResultException | NonUniqueResultException) {
			$available = false;
			$voucher = null;
		}

		$this->sendJson([
			'status' => $available,
			'info' => $available && $voucher !== null ? $this->voucherManager->getVoucherInfo($voucher) : null,
		]);
	}


	public function postUseVoucher(string $code): void
	{
		try {
			$voucher = $this->cartVoucherRepository->findByCode($code);
		} catch (NoResultException | NonUniqueResultException) {
			$this->sendError(sprintf('Voucher "%s" does not exist.', $code));
		}
		if ($voucher->isAvailable() === false) {
			$this->sendError(sprintf('Voucher "%s" is not available or has been used.', $code));
		}

		$this->voucherManager->useVoucher($voucher, $this->cartManager->getCartFlushed());
		$this->sendOk();
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
				$cartItem->getCount(),
			),
		]);
	}


	public function postDeliveryAndPayments(string $delivery, string $payment, ?int $branch = null): void
	{
		$cart = $this->cartManager->getCartFlushed();

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
		$cart = $this->cartManager->getCartFlushed();

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
			'price' => $cart->getPrice()->render(true),
			'itemsPrice' => $cart->getItemsPrice()->render(true),
			'deliveryPrice' => $cart->getDeliveryPrice()->render(true),
			'delivery' => [
				'name' => $delivery->getLabel(),
				'price' => (new Price($delivery->getPrice(), $cart->getCurrency()))->render(true),
			],
			'payment' => [
				'name' => $payment->getName(),
				'price' => (new Price($payment->getPrice(), $cart->getCurrency()))->render(true),
			],
		]);
	}


	public function postCustomer(OrderInfo $orderInfo): void
	{
		if ($orderInfo->getInfo()->isGdpr() === false) {
			$this->sendError('Musíte souhlasit s podmínkami služby.');
		}
		$cart = $this->cartManager->getCartFlushed();
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


	private function getDataLayer(
		ProductInterface $product,
		?ProductVariantInterface $variant = null,
		float $quantity = 1,
	): DataLayer {
		$brand = null; // TODO
		$mainCategory = $product->getMainCategory();
		if ($variant === null) {
			return new DataLayer(
				id: (string) $product->getId(),
				name: $product->getLabel(),
				price: $product->getPrice(),
				brand: $brand,
				category: $mainCategory?->getLabel(),
				variant: null,
				quantity: $quantity,
			);
		}

		return new DataLayer(
			id: $product->getId() . '-' . $variant->getId(),
			name: $product->getLabel(),
			price: $variant->getPrice(),
			brand: $brand,
			category: $mainCategory?->getLabel(),
			variant: $variant->getLabel(),
			quantity: $quantity,
		);
	}


	private function getItemById(int $id): ?CartItem
	{
		$cart = $this->cartManager->getCart();

		try {
			return $this->cartItemRepository->getById(
				id: $id,
				cartId: $cart->isFlushed() ? $cart->getId() : null,
			);
		} catch (NoResultException | NonUniqueResultException) {
			// Silence is golden.
		}

		return null;
	}


	/**
	 * @return array{id: int, name: string, mainImage: string|null, price: string, url: string|null}
	 */
	private function formatRelated(ProductInterface $product, CurrencyInterface $currency): array
	{
		$relativePath = $product->getMainImage()?->getRelativePath();

		return [
			'id' => $product->getId(),
			'name' => $product->getLabel(),
			'mainImage' => $relativePath !== null
				? sprintf(
					'%s/%s',
					Url::get()->getBaseUrl(),
					ImageGenerator::from($relativePath, ['w' => 150, 'h' => 150]),
				)
				: null,
			'price' => (new Price($product->getPrice(), $currency))->render(true),
			'url' => $this->linkSafe('Front:Product:detail', ['slug' => $product->getSlug()]),
		];
	}


	/**
	 * @param array<string, string> $userOptions
	 * @param array<string, string> $availableParameters
	 */
	private function isVariantCompatibleWithOptions(array $userOptions, array $availableParameters): bool
	{
		if ($userOptions === $availableParameters) {
			return true;
		}
		foreach ($availableParameters as $key => $value) {
			if (($userOptions[$key] ?? null) === $value) {
				unset($userOptions[$key]);
			}
		}

		return count($userOptions) === 1;
	}
}
