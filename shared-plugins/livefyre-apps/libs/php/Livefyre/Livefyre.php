<?php
namespace Livefyre;

use Livefyre\Core\Network;

class Livefyre { 
	public static function getNetwork($networkName, $networkKey) { 
		return new Network($networkName, $networkKey);
	}
}
