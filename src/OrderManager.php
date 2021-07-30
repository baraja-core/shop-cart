<?php

declare(strict_types=1);

namespace Baraja\Shop\Cart;


use Baraja\Shop\Cart\Entity\Cart;
use Baraja\Shop\Cart\Entity\Order;

interface OrderManager
{
	public function createOrder(OrderInfo $orderInfo, Cart $cart): Order;
}
