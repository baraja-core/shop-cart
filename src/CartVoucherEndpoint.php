<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart;


use Baraja\Shop\Cart\Entity\CartVoucher;
use Baraja\Shop\Cart\Entity\CartVoucherRepository;
use Baraja\StructuredApi\BaseEndpoint;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Nette\Utils\Random;

final class CartVoucherEndpoint extends BaseEndpoint
{
	private CartVoucherRepository $cartVoucherRepository;


	public function __construct(
		private EntityManagerInterface $entityManager,
	) {
		$cartVoucherRepository = $entityManager->getRepository(CartVoucher::class);
		assert($cartVoucherRepository instanceof CartVoucherRepository);
		$this->cartVoucherRepository = $cartVoucherRepository;
	}


	public function actionDefault(): void
	{
		$this->sendJson([
			'feed' => $this->cartVoucherRepository->getFeed(),
			'types' => $this->formatBootstrapSelectList(CartVoucher::Types),
		]);
	}


	public function actionGenerateRandomCode(): void
	{
		while (true) {
			$code = strtoupper(Random::generate());
			try {
				$this->cartVoucherRepository->findByCode($code);
			} catch (NoResultException|NonUniqueResultException) {
				break;
			}
		}
		$this->sendJson([
			'code' => $code,
		]);
	}


	/**
	 * @param numeric-string $value
	 */
	public function postNewVoucher(
		string $code,
		string $type,
		string $value,
		?int $percentage = null,
		?int $usageLimit = null,
		bool $mustBeUnique = true,
		?string $note = null,
		?string $validFrom = null,
		?string $validTo = null,
	): void {
		try {
			$this->cartVoucherRepository->findByCode($code);
		} catch (NoResultException|NonUniqueResultException) {
			$this->sendError(sprintf('Code "%s" already exist.', $code));
		}
		$type = strtolower($type);
		if (
			$percentage === null
			&& in_array($type, [
				CartVoucher::TypePercentage,
				CartVoucher::TypePercentageProduct,
				CartVoucher::TypePercentageCategory,
			], true)
		) {
			$this->sendError(sprintf('Percentage is required for type "%s".', $type));
		}
		$voucher = new CartVoucher($code, $type, $value);
		$voucher->setPercentage($percentage);
		$voucher->setUsageLimit($usageLimit);
		$voucher->setMustBeUnique($mustBeUnique);
		$voucher->setNote($note);
		if ($validFrom !== null) {
			$voucher->setValidFrom(new \DateTimeImmutable($validFrom));
		}
		if ($validTo !== null) {
			$voucher->setValidTo(new \DateTimeImmutable($validTo));
		}

		$this->entityManager->persist($voucher);
		$this->entityManager->flush();
		$this->flashMessage('Voucher has been created.', self::FlashMessageSuccess);
		$this->sendOk([
			'id' => $voucher->getId(),
		]);
	}
}
