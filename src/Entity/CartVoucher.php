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
		TypeFixValue = 'fix', // fix sale value in default currency for whole order
		TypePercentage = 'perc', // sale x % for whole order
		TypePercentageProduct = 'percprod', // sale x % for given product
		TypePercentageCategory = 'perccat', // sale x % for any product in given category
		TypeFreeProduct = 'freeprod'; // free product

	public const Types = [
		self::TypeFixValue,
		self::TypePercentage,
		self::TypePercentageProduct,
		self::TypePercentageCategory,
		self::TypeFreeProduct,
	];

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
	private ?int $percentage = null;

	#[ORM\Column(type: 'integer', nullable: true)]
	private ?int $usageLimit = null;

	#[ORM\Column(type: 'integer')]
	private int $usedCount = 0;

	#[ORM\Column(type: 'boolean')]
	private bool $active = true;

	#[ORM\Column(type: 'boolean')]
	private bool $mustBeUnique = true;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $note = null;

	/** @var Collection<CartSale> */
	#[ORM\OneToMany(mappedBy: 'voucher', targetEntity: CartSale::class)]
	private Collection $sales;

	#[ORM\Column(type: 'datetime', nullable: true)]
	private ?\DateTimeInterface $validFrom = null;

	#[ORM\Column(type: 'datetime', nullable: true)]
	private ?\DateTimeInterface $validTo = null;

	#[ORM\Column(type: 'datetime')]
	private \DateTimeInterface $insertedDate;

	#[ORM\Column(type: 'datetime', nullable: true)]
	private ?\DateTimeInterface $usedDate = null;


	/**
	 * @param numeric-string $value
	 */
	public function __construct(string $code, string $type, string $value)
	{
		if (!in_array($type, self::Types, true)) {
			throw new \InvalidArgumentException(
				sprintf('Type "%s" is not valid option from "%s".', $type, implode('", "', self::Types)),
			);
		}
		$code = strtoupper(trim((string) preg_replace('/[a-zA-Z0-9-]/', '', $code)));
		if ($code === '') {
			throw new \InvalidArgumentException('Voucher code can not be empty or contain invalid characters only.');
		}
		$this->code = $code;
		$this->type = $type;
		$this->value = $value;
		$this->insertedDate = new \DateTimeImmutable;
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
		$this->usedDate = new \DateTimeImmutable;
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


	public function getPercentage(): ?int
	{
		return $this->percentage;
	}


	public function setPercentage(?int $percentage): void
	{
		$this->percentage = $percentage;
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


	public function getNote(): ?string
	{
		return $this->note;
	}


	public function setNote(?string $note): void
	{
		$this->note = $note;
	}


	public function getValidFrom(): ?\DateTimeInterface
	{
		return $this->validFrom;
	}


	public function setValidFrom(?\DateTimeInterface $validFrom): void
	{
		$this->validFrom = $validFrom;
	}


	public function getValidTo(): ?\DateTimeInterface
	{
		return $this->validTo;
	}


	public function setValidTo(?\DateTimeInterface $validTo): void
	{
		$this->validTo = $validTo;
	}


	public function getInsertedDate(): \DateTimeInterface
	{
		return $this->insertedDate;
	}


	public function getUsedDate(): ?\DateTimeInterface
	{
		return $this->usedDate;
	}
}
