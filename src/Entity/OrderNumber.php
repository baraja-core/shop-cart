<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\Entity;


interface OrderNumber
{
	public function getId(): ?int;

	public function getHash(): string;
}
