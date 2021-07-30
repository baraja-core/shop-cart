<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\Entity;


interface Order
{
	public function getId(): ?int;

	public function getHash(): string;
}
