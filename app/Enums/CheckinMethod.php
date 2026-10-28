<?php

namespace App\Enums;

enum CheckinMethod: string
{
    case CardFace = 'card_face';
    case Card = 'card';
    case Face = 'face';
    case Manual = 'manual';
}
