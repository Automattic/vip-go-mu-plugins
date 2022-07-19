<?php

namespace Automattic\VIP\Prometheus;

use Prometheus\RegistryInterface;

interface CollectorInterface {
	public function initialize( RegistryInterface $registry );
}
