<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\Session;


interface SessionProvider
{
	public const KEY = '__BRJ-shop-cart';

	public function getHash(): ?string;

	public function setHash(string $hash): void;
}
