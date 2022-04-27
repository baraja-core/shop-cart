<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart;


use Baraja\EcommerceStandard\DTO\CartInterface;
use Baraja\EcommerceStandard\DTO\CartVoucherInterface;
use Baraja\Shop\Cart\Entity\Cart;
use Baraja\Shop\Cart\Entity\CartSale;
use Baraja\Shop\Cart\Entity\CartVoucher;
use Baraja\Shop\Currency\CurrencyManagerAccessor;
use Baraja\Shop\Product\Entity\Product;
use Baraja\Shop\Product\Entity\ProductCategory;
use Baraja\Shop\Product\Repository\ProductCategoryRepository;
use Baraja\Shop\Product\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

final class VoucherManager
{
	private ProductRepository $productRepository;

	private ProductCategoryRepository $productCategoryRepository;


	public function __construct(
		private EntityManagerInterface $entityManager,
		private CurrencyManagerAccessor $currencyManagerAccessor,
	) {
		$productRepository = $entityManager->getRepository(Product::class);
		assert($productRepository instanceof ProductRepository);
		$this->productRepository = $productRepository;
		$productCategoryRepository = $entityManager->getRepository(ProductCategory::class);
		assert($productCategoryRepository instanceof ProductCategoryRepository);
		$this->productCategoryRepository = $productCategoryRepository;
	}


	/**
	 * @return array{
	 *    code: string,
	 *    message: string,
	 *    type: string,
	 *    value: numeric-string,
	 *    available: bool,
	 *    active: bool,
	 *    mustBeUnique: bool
	 * }
	 */
	public function getVoucherInfo(CartVoucher $voucher): array
	{
		return [
			'code' => $voucher->getCode(),
			'message' => $this->formatMessage($voucher),
			'type' => $voucher->getType(),
			'value' => $voucher->getValue(),
			'available' => $voucher->isAvailable(),
			'active' => $voucher->isActive(),
			'mustBeUnique' => $voucher->isMustBeUnique(),
		];
	}


	public function useVoucher(CartVoucher $voucher, CartInterface $cart): void
	{
		assert($cart instanceof Cart);
		if ($voucher->isAvailable() === false) {
			throw new \OutOfRangeException(
				sprintf('Voucher "%s" is not available or has been used.', $voucher->getCode()),
			);
		}
		if ($voucher->isMustBeUnique() && $cart->getSales() !== []) {
			throw new \OutOfRangeException(
				sprintf(
					'Voucher "%s" can not be combined with other vouchers, but %d has been used.',
					$voucher->getCode(),
					count($cart->getSales()),
				),
			);
		}
		$voucher->markAsUsed();
		$sale = new CartSale($cart, $voucher->getType(), $voucher->getValue());
		$sale->setVoucher($voucher);
		$this->entityManager->persist($sale);
		$this->entityManager->flush();
	}


	public function formatMessage(CartVoucherInterface $voucher): string
	{
		assert($voucher instanceof CartVoucher);
		$type = $voucher->getType();
		$percentage = (string) $voucher->getPercentage();
		/** @param numeric-string $price */
		$renderPrice = fn(string $price): string => $this->currencyManagerAccessor
			->get()
			->getMainCurrency()
			->renderPrice($price);

		if ($type === CartVoucher::TypeFixValue) {
			return sprintf('Sleva %s na cokoli.', $renderPrice($voucher->getValue()));
		}
		if ($type === CartVoucher::TypePercentage) {
			return sprintf('Sleva %s %% na cokoli.', $percentage);
		}
		if ($type === CartVoucher::TypePercentageProduct) {
			try {
				$product = $this->productRepository->getById((int) $voucher->getValue());
			} catch (NoResultException|NonUniqueResultException) {
				throw new \InvalidArgumentException(sprintf('Product "%d" does not exist.', $voucher->getValue()));
			}

			return sprintf('Sleva %s %% na produkt "%s".', $voucher->getValue(), $product->getLabel());
		}
		if ($type === CartVoucher::TypePercentageCategory) {
			try {
				$category = $this->productCategoryRepository->getById((int) $voucher->getValue());
			} catch (NoResultException|NonUniqueResultException) {
				throw new \InvalidArgumentException(sprintf('Category "%d" does not exist.', $voucher->getValue()));
			}

			return sprintf(
				'Sleva "%s %%" na libovolný produkt v kategorii "%s".',
				$voucher->getValue(),
				$category->getLabel(),
			);
		}
		if ($type === CartVoucher::TypeFreeProduct) {
			try {
				$product = $this->productRepository->getById((int) $voucher->getValue());
			} catch (NoResultException|NonUniqueResultException) {
				throw new \InvalidArgumentException(sprintf('Product "%d" does not exist.', $voucher->getValue()));
			}

			return sprintf('Produkt "%s" zdarma.', $product->getLabel());
		}

		return sprintf('Voucher "%s".', $voucher->getCode());
	}
}
