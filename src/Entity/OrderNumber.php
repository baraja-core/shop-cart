<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\Entity;


/**
 * @deprecated since 2022-01-14, use OrderInterface from baraja-core/ecommerce-standard
 */
interface OrderNumber
{
	public function getId(): ?int;

	public function getHash(): string;
}
