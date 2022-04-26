<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\Entity;


use Baraja\EcommerceStandard\DTO\CartVoucherInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CartVoucherRepository::class)]
#[ORM\Table(name: 'shop__cart_voucher')]
class CartVoucher implements CartVoucherInterface
{
	public const
		TypeFixValue = 'fix',
		TypePercentage = 'perc',
		TypeFreeProduct = 'freeprod';

	public const Types = [self::TypeFixValue, self::TypePercentage, self::TypeFreeProduct];

	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	protected int $id;

	#[ORM\Column(type: 'string', length: 32, unique: true)]
	private string $code;

	#[ORM\Column(type: 'string', length: 8)]
	private string $type;

	/** @var numeric-string */
	#[ORM\Column(type: 'string', length: 64)]
	private string $value;

	#[ORM\Column(type: 'integer', nullable: true)]
	private ?int $usageLimit = null;

	#[ORM\Column(type: 'integer')]
	private int $usedCount = 0;

	#[ORM\Column(type: 'boolean')]
	private bool $active = true;

	#[ORM\Column(type: 'boolean')]
	private bool $mustBeUnique = true;

	/** @var Collection<CartSale> */
	#[ORM\OneToMany(mappedBy: 'voucher', targetEntity: CartSale::class)]
	private Collection $sales;


	public function __construct(string $code, string $type, string $value)
	{
		if (!in_array($type, self::Types, true)) {
			throw new \InvalidArgumentException(
				sprintf('Type "%s" is not valid option from "%s".', $type, implode('", "', self::Types)),
			);
		}
		$this->code = $code;
		$this->type = $type;
		$this->value = $value;
		$this->sales = new ArrayCollection;
	}


	public function getId(): int
	{
		return $this->id;
	}


	public function isAvailable(): bool
	{
		return $this->isActive() && ($this->usageLimit === null || $this->usageLimit >= $this->usedCount);
	}


	public function markAsUsed(): void
	{
		$this->setUsedCount($this->getUsedCount() + 1);
		if ($this->isAvailable() === false) {
			$this->setActive(false);
		}
	}


	public function getCode(): string
	{
		return $this->code;
	}


	public function getType(): string
	{
		return $this->type;
	}


	/**
	 * @return numeric-string
	 */
	public function getValue(): string
	{
		return $this->value;
	}


	/**
	 * @param numeric-string $value
	 */
	public function setValue(string $value): void
	{
		$this->value = $value;
	}


	public function getUsageLimit(): ?int
	{
		return $this->usageLimit;
	}


	public function setUsageLimit(?int $usageLimit): void
	{
		$this->usageLimit = $usageLimit;
	}


	public function getUsedCount(): int
	{
		return $this->usedCount;
	}


	public function setUsedCount(int $usedCount): void
	{
		if ($usedCount < 0) {
			$usedCount = 0;
		}
		$this->usedCount = $usedCount;
	}


	public function isActive(): bool
	{
		return $this->active;
	}


	public function setActive(bool $active): void
	{
		$this->active = $active;
	}


	public function isMustBeUnique(): bool
	{
		return $this->mustBeUnique;
	}


	public function setMustBeUnique(bool $mustBeUnique): void
	{
		$this->mustBeUnique = $mustBeUnique;
	}


	/**
	 * @return Collection<CartSale>
	 */
	public function getSales(): Collection
	{
		return $this->sales;
	}
}
