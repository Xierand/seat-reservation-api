<?php

namespace App\Enums;

enum SeatStatus: string
{
    case FREE = 'free';
    case LOCKED = 'locked';
    case SOLD = 'sold';
}
