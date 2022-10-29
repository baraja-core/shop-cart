<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart;


use Baraja\CAS\User;
use Baraja\Doctrine\EntityManager;
use Baraja\EcommerceStandard\DTO\CartItemInterface;
use Baraja\EcommerceStandard\DTO\CartSaleInterface;
use Baraja\EcommerceStandard\DTO\CurrencyInterface;
use Baraja\EcommerceStandard\DTO\PaymentInterface;
use Baraja\EcommerceStandard\DTO\ProductInterface;
use Baraja\EcommerceStandard\DTO\ProductVariantInterface;
use Baraja\EcommerceStandard\Service\OrderManagerInterface;
use Baraja\ImageGenerator\ImageGenerator;
use Baraja\Shop\Cart\DTO\BuyResponse;
use Baraja\Shop\Cart\DTO\CartCustomer;
use Baraja\Shop\Cart\DTO\CartCustomerItem;
use Baraja\Shop\Cart\DTO\CartDeliveryItemResponse;
use Baraja\Shop\Cart\DTO\CartItemResponse;
use Baraja\Shop\Cart\DTO\CartPaymentItemResponse;
use Baraja\Shop\Cart\DTO\CartPrice;
use Baraja\Shop\Cart\DTO\CartResponse;
use Baraja\Shop\Cart\DTO\CartVoucherResponse;
use Baraja\Shop\Cart\DTO\CheckVariantFeedItem;
use Baraja\Shop\Cart\DTO\CheckVariantItem;
use Baraja\Shop\Cart\DTO\CheckVariantStatusResponse;
use Baraja\Shop\Cart\DTO\CreateCustomerResponse;
use Baraja\Shop\Cart\DTO\CustomerDefaultInfoResponse;
use Baraja\Shop\Cart\DTO\DataLayer;
use Baraja\Shop\Cart\DTO\DeliveryAndPaymentResponse;
use Baraja\Shop\Cart\DTO\RelatedProductResponse;
use Baraja\Shop\Cart\Entity\CartDeliveryAndPaymentRelation;
use Baraja\Shop\Cart\Entity\CartDeliveryAndPaymentRelationRepository;
use Baraja\Shop\Cart\Entity\CartItem;
use Baraja\Shop\Cart\Entity\CartItemRepository;
use Baraja\Shop\Cart\Entity\CartSale;
use Baraja\Shop\Cart\Entity\CartSaleRepository;
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
use Baraja\StructuredApi\Response\Status\ErrorResponse;
use Baraja\StructuredApi\Response\Status\OkResponse;
use Baraja\Url\Url;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

#[PublicEndpoint]
final class CartEndpoint extends BaseEndpoint
{
	private ProductRepository $productRepository;

	private CartItemRepository $cartItemRepository;

	private CartSaleRepository $cartSaleRepository;

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
		$cartItemRepository = $entityManager->getRepository(CartItem::class);
		$cartSaleRepository = $entityManager->getRepository(CartSale::class);
		$cartVoucherRepository = $entityManager->getRepository(CartVoucher::class);
		assert($productRepository instanceof ProductRepository);
		assert($cartItemRepository instanceof CartItemRepository);
		assert($cartSaleRepository instanceof CartSaleRepository);
		assert($cartVoucherRepository instanceof CartVoucherRepository);
		$this->productRepository = $productRepository;
		$this->cartItemRepository = $cartItemRepository;
		$this->cartSaleRepository = $cartSaleRepository;
		$this->cartVoucherRepository = $cartVoucherRepository;
	}


	public function actionDefault(): CartResponse
	{
		$items = [];
		$price = '0';
		$priceWithoutVat = '0';
		$cart = $this->cartManager->getCartFlushed();
		$delivery = $cart->getDelivery();
		$payment = $cart->getPayment();
		$currency = $cart->getCurrency();
		$products = [];
		foreach ($cart->getItems() as $cartItem) {
			$product = $cartItem->getProduct();
			assert($product instanceof Product);
			$image = $product->getMainImage();
			$items[] = new CartItemResponse(
				id: $cartItem->getId(),
				url: $this->linkSafe(':Front:Product:detail', [
					'slug' => $product->getSlug(),
				]),
				slug: $product->getSlug(),
				mainImageUrl: $image !== null
					? ImageGenerator::from($image->getUrl(), ['w' => 200, 'h' => 200])
					: null,
				name: $cartItem->getName(),
				count: $cartItem->getCount(),
				price: $cartItem->getBasicPrice()->render(true),
				description: $cartItem->getDescription(),
				sale: false,
			);
			$products[] = $product;
			$price = bcadd($price, $cartItem->getPrice()->getValue());
			$priceWithoutVat = bcadd($priceWithoutVat, $cartItem->getPriceWithoutVat()->getValue());
		}
		foreach ($cart->getSales() as $cartSale) {
			$voucher = $cartSale->getVoucher();
			$items[] = new CartItemResponse(
				id: sprintf('sale_%d', $cartSale->getId()),
				url: null,
				slug: null,
				mainImageUrl: null,
				name: $voucher !== null ? $this->voucherManager->formatMessage($voucher) : 'Sleva',
				count: 1,
				price: null,
				description: null,
				sale: true,
			);
		}

		$related = [];
		foreach ($this->productRecommender->get()->getRelatedByCollection($products) as $relatedItem) {
			$related[] = $this->formatRelated($relatedItem, $currency);
		}

		$freeDelivery = $cart->getRuntimeContext()->getFreeDeliveryLimit();

		return new CartResponse(
			items: $items,
			priceToFreeDelivery: $price >= $freeDelivery ? null : (new Price(
				(int) ($freeDelivery - $price),
				$cart->getCurrency(),
			))->render(),
			price: new CartPrice(
				final: (new Price($price, $cart->getCurrency()))->render(),
				withoutVat: (new Price($priceWithoutVat, $cart->getCurrency()))->render(),
			),
			delivery: $delivery !== null ? CartDeliveryItemResponse::fromEntity($cart, $delivery) : null,
			payment: $payment !== null ? CartPaymentItemResponse::fromEntity($cart, $payment) : null,
			related: $related,
		);
	}


	/**
	 * @param array<string, string> $variantOptions
	 */
	public function postCheckVariantStatus(int $productId, array $variantOptions = []): CheckVariantStatusResponse
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
		/** @var array<string, array<int, CheckVariantFeedItem>> $variantsFeed */
		$variantsFeed = [];
		$variantList = [];
		if ($variants === []) {
			$variantPrice = $product->getPrice();
			$regularPrice = $product->getPrice();
		} else {
			foreach ($variants as $variantItem) {
				$variantList[] = new CheckVariantItem(
					variantId: $variantItem->getId(),
					hash: $variantItem->getRelationHash(),
					available: $variantItem->isSoldOut() === false,
					price: (new Price($variantItem->getPrice(), $currency))->render(true),
					regularPrice: (new Price($variantItem->getPrice(false), $currency))->render(true),
					sale: $variantItem->getProduct()->isSale(),
				);
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
				if ($variantParameters === [] || !$this->isVariantCompatible($variantOptions, $variantParameters)) {
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
					$variantsFeed[$variantParameterKey][] = new CheckVariantFeedItem(
						text: $variantParameterValue,
						value: $variantParameterValue,
						id: $variantItem->getId(),
						hash: $variantItem->getRelationHash(),
					);
				}
			}
		}

		return new CheckVariantStatusResponse(
			exist: $exist,
			variantId: $variantId,
			available: $variantAvailable,
			price: $variantPrice !== null ? (new Price($variantPrice, $currency))->render(true) : null,
			regularPrice: $regularPrice !== null ? (new Price($regularPrice, $currency))->render(true) : null,
			sale: $sale,
			dataLayer: $this->getDataLayer($product, $variantEntity),
			variantList: $variantList,
			variantsFeed: $variantsFeed,
		);
	}


	public function postBuy(int $productId, ?int $variantId = null, int $count = 1): BuyResponse
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
				ErrorResponse::invoke(sprintf('Varianta "%s" neexistuje. Vyberte jinou variantu produktu.', $variantId));
			}
		}

		$related = [];
		foreach ($product->getProductRelatedBasic() as $relatedItem) {
			$related[] = $this->formatRelated($relatedItem->getRelatedProduct(), $currency);
		}

		$cartItem = $this->cartManager->buyProduct($product, $variant, $count);

		return new BuyResponse(
			count: $this->cartManager->getItemsCount(),
			dataLayer:  $this->getDataLayer(
				product: $cartItem->getProduct(),
				variant: $cartItem->getVariant(),
				quantity: $cartItem->getCount(),
			),
			related: $related,
		);
	}


	public function actionCheckVoucher(string $code): CartVoucherResponse
	{
		try {
			$voucher = $this->cartVoucherRepository->findByCode($code);

			return CartVoucherResponse::fromEntity(
				voucher: $voucher,
				message: $this->voucherManager->formatMessage($voucher),
			);
		} catch (NoResultException|NonUniqueResultException) {
			// Silence is golden.
		}

		return new CartVoucherResponse(
			status: false,
		);
	}


	public function postUseVoucher(string $code): OkResponse
	{
		try {
			$voucher = $this->cartVoucherRepository->findByCode($code);
		} catch (NoResultException|NonUniqueResultException) {
			ErrorResponse::invoke(sprintf('Voucher "%s" does not exist.', $code));
		}
		if ($voucher->isAvailable() === false) {
			ErrorResponse::invoke(sprintf('Voucher "%s" is not available or has been used.', $code));
		}

		$this->voucherManager->useVoucher($voucher, $this->cartManager->getCartFlushed());

		return new OkResponse;
	}


	public function actionChangeItemsCount(int $id, int $count): OkResponse
	{
		$item = $this->getItemById($id);
		if ($item !== null) {
			$this->checkItemInCurrentCart($item);
			if ($count === 0) {
				$this->actionDeleteItem((string) $id);
			}
			$item->setCount($count);
			$this->entityManager->flush();
		}

		return new OkResponse;
	}


	public function actionDeleteItem(string $id): void
	{
		if (preg_match('/^sale_(\d+)$/', $id, $saleParts) === 1) {
			assert(isset($saleParts[1]));
			try {
				$cartItem = $this->cartSaleRepository->getById((int) $saleParts[1]);
				$cartItem->getVoucher()?->markAsUnused();
			} catch (NoResultException|NonUniqueResultException) {
				$cartItem = null;
			}
		} else {
			$cartItem = $this->getItemById((int) $id);
		}
		if ($cartItem === null) {
			$this->sendError(sprintf('Cart item "%s" does not exist.', $id));
		}
		$this->checkItemInCurrentCart($cartItem);
		$this->entityManager->remove($cartItem);
		$this->entityManager->flush();

		$this->sendOk([
			'dataLayer' => $cartItem instanceof CartItem
				? $this->getDataLayer(
					$cartItem->getProduct(),
					$cartItem->getVariant(),
					$cartItem->getCount(),
				) : [],
		]);
	}


	public function actionDeliveryAndPayment(): DeliveryAndPaymentResponse
	{
		$cart = $this->cartManager->getCart();
		$cartDelivery = $cart->getDelivery();

		/** @var Delivery[] $deliveries */
		$deliveries = $this->entityManager->getRepository(Delivery::class)
			->createQueryBuilder('delivery')
			->select('PARTIAL delivery.{id, code, name, price}')
			->getQuery()
			->getResult();

		$relationRepository = $this->entityManager->getRepository(CartDeliveryAndPaymentRelation::class);
		assert($relationRepository instanceof CartDeliveryAndPaymentRelationRepository);

		return new DeliveryAndPaymentResponse(
			price: $cart->getItemsPrice()->render(true),
			deliveries: array_map(
				static fn(Delivery $delivery): CartDeliveryItemResponse => CartDeliveryItemResponse::fromEntity(
					$cart,
					$delivery,
				),
				$deliveries,
			),
			payments: array_map(
				static fn(PaymentInterface $payment): CartPaymentItemResponse => CartPaymentItemResponse::fromEntity(
					$cart,
					$payment,
				),
				$cartDelivery !== null
					? $relationRepository->getCompatiblePaymentsByDelivery($cartDelivery)
					: [],
			),
			isFreeDelivery: $this->cartManager->isFreeDelivery(),
			isFreePayment: $this->cartManager->isFreePayment(),
		);
	}


	public function postDeliveryAndPayments(
		?string $delivery = null,
		?string $payment = null,
		?int $branch = null,
	): OkResponse {
		$cart = $this->cartManager->getCartFlushed();
		if ($delivery !== null) {
			$deliveryEntity = $this->entityManager->getRepository(Delivery::class)
				->createQueryBuilder('delivery')
				->where('delivery.code = :code')
				->setParameter('code', $delivery)
				->setMaxResults(1)
				->getQuery()
				->getSingleResult();
			assert($deliveryEntity instanceof Delivery);
			if ($cart->getDelivery()?->getId() !== $deliveryEntity->getId()) {
				$cart->setPayment(null);
			}
			$this->cartManager->setDelivery($deliveryEntity);
		}
		if ($payment !== null) {
			$paymentEntity = $this->entityManager->getRepository(Payment::class)
				->createQueryBuilder('payment')
				->where('payment.code = :code')
				->setParameter('code', $payment)
				->setMaxResults(1)
				->getQuery()
				->getSingleResult();
			assert($paymentEntity instanceof Payment);
			$this->cartManager->setPayment($paymentEntity);
		}
		if ($branch !== null) {
			$cart->setDeliveryBranchId($branch);
		}

		$this->entityManager->flush();

		return new OkResponse;
	}


	public function actionCustomer(): CartCustomer
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
			$items[] = new CartCustomerItem(
				id: $item->getId(),
				mainImage: $img !== null
					? ImageGenerator::from($img, ['w' => 96, 'h' => 96])
					: null,
				url: $this->linkSafe('Front:Product:detail', [
					'slug' => $item->getProduct()->getSlug(),
				]),
				name: $item->getName(),
				price: $item->getPrice(),
			);
		}

		return new CartCustomer(
			loggedIn: $this->user->isLoggedIn(),
			items: $items,
			price: $cart->getPrice()->render(true),
			itemsPrice: $cart->getItemsPrice()->render(true),
			deliveryPrice: $cart->getDeliveryPrice()->render(true),
			delivery: CartDeliveryItemResponse::fromEntity($cart, $delivery),
			payment: CartPaymentItemResponse::fromEntity($cart, $payment),
		);
	}


	public function postCustomer(OrderInfo $orderInfo): CreateCustomerResponse
	{
		if ($orderInfo->getInfo()->isGdpr() === false) {
			$this->sendError('Musíte souhlasit s podmínkami služby.');
		}
		$cart = $this->cartManager->getCartFlushed();
		$order = $this->orderManager->createOrder($orderInfo, $cart);

		return new CreateCustomerResponse(
			id: $order->getId(),
			hash: $order->getHash(),
		);
	}


	public function actionCustomerDefaultInfo(): CustomerDefaultInfoResponse
	{
		$return = new CustomerDefaultInfoResponse;
		if ($this->user->isLoggedIn()) {
			$identity = $this->user->getIdentityEntity();
			$customer = null;
			if ($identity !== null) {
				try {
					$customer = $this->entityManager->getRepository(Customer::class)
						->createQueryBuilder('customer')
						->where('customer.email = :email')
						->setParameter('email', $identity->getEmail())
						->setMaxResults(1)
						->getQuery()
						->getSingleResult();
					assert($customer instanceof Customer);
				} catch (NoResultException|NonUniqueResultException) {
					// Admin customer does not exist.
				}
			}
			if ($customer !== null) {
				$return->ready = true;
				$return->firstName = $customer->getFirstName();
				$return->lastName = $customer->getLastName();
				$return->email = $customer->getEmail();
				$return->phone = $customer->getPhone() ?? '';
				$return->street = $customer->getStreet() ?? '';
				$return->city = $customer->getCity() ?? '';
				$return->zip = (string) $customer->getZip();
				$return->companyName = $customer->getCompanyName() ?? '';
				$return->ic = $customer->getIc() ?? '';
			}
		}

		return $return;
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
		} catch (NoResultException|NonUniqueResultException) {
			// Silence is golden.
		}

		return null;
	}


	private function formatRelated(ProductInterface $product, CurrencyInterface $currency): RelatedProductResponse
	{
		$relativePath = $product->getMainImage()?->getRelativePath();

		return new RelatedProductResponse(
			id: $product->getId(),
			name: $product->getLabel(),
			mainImage: $relativePath !== null
				? sprintf(
					'%s/%s',
					Url::get()->getBaseUrl(),
					ImageGenerator::from($relativePath, ['w' => 150, 'h' => 150]),
				)
				: null,
			price: (new Price($product->getPrice(), $currency))->render(true),
			url: $this->linkSafe('Front:Product:detail', ['slug' => $product->getSlug()]),
		);
	}


	/**
	 * @param array<string, string> $userOptions
	 * @param array<string, string> $availableParameters
	 */
	private function isVariantCompatible(array $userOptions, array $availableParameters): bool
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


	private function checkItemInCurrentCart(CartItem|CartSale|null $item): void
	{
		if ($item === null) {
			return;
		}

		$cart = $this->cartManager->getCart(false);
		if ($item instanceof CartItem) {
			$ids = array_map(static fn(CartItemInterface $item): int => $item->getId(), $cart->getAllItems());
		} else {
			$ids = array_map(static fn(CartSaleInterface $item): int => $item->getId(), $cart->getSales());
		}

		if (in_array($item->getId(), $ids, true) === false) {
			throw new \LogicException(sprintf(
				'Security issue: Cart item "%s" is not in available item list: "%s".',
				$item->getId(),
				implode('", "', $ids),
			));
		}
	}
}
