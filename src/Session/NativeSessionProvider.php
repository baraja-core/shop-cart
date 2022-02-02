<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\Session;


final class NativeSessionProvider implements SessionProvider
{
	public function getHash(): ?string
	{
		$hash = $_SESSION[self::KEY] ?? null;

		return is_string($hash) ? $hash : null;
	}


	public function setHash(string $hash): void
	{
		if (session_status() !== PHP_SESSION_ACTIVE) {
			session_start();
		}
		$_SESSION[self::KEY] = $hash;
	}
}
