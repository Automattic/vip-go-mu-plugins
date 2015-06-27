<?php
namespace Livefyre\Entity;

use Livefyre\Utils\BasicEnum;

abstract class SubscriptionType extends BasicEnum {
    const personalStream = 1;
}
