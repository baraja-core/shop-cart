<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\Entity;


use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

final class CartVoucherRepository extends EntityRepository
{
	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function findByCode(string $code): CartVoucher
	{
		$voucher = $this->createQueryBuilder('voucher')
			->where('voucher.code = :code')
			->setParameter('code', $code)
			->setMaxResults(1)
			->getQuery()
			->getSingleResult();
		assert($voucher instanceof CartVoucher);

		return $voucher;
	}
}
