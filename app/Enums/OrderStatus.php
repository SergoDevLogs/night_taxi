<?php

namespace App\Enums;

enum OrderStatus: string
{
    case NEW     = 'новый';
    case PACKED  = 'упакован';
    case CANCELED = 'отменён';
}
