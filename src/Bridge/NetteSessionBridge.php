<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart\Bridge;


use Baraja\Shop\Cart\Session\SessionProvider;
use Nette\Http\Session;
use Nette\Http\SessionSection;

final class NetteSessionBridge implements SessionProvider
{
	private SessionSection $section;


	public function __construct(Session $session)
	{
		$this->section = $session->getSection(self::KEY);
	}


	public function getHash(): ?string
	{
		$hash = $this->section->offsetGet('hash');

		return is_string($hash) ? $hash : null;
	}


	public function setHash(string $hash): void
	{
		$this->section->offsetSet('hash', $hash);
	}
}
