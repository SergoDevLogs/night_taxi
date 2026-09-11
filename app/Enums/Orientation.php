<?php

namespace App\Enums;

enum Orientation: string
{
    case AS_IS       = 'как_есть';
    case ROTATE_XY   = 'поворот_XY';
    case ROTATE_XZ   = 'поворот_XZ';
    case ROTATE_YZ   = 'поворот_YZ';
    case UPSIDE_DOWN = 'вверх_дном';
    case ON_SIDE     = 'на_бок';

    /** Маппинг из rotation Packvium */
    public static function fromPackvium(string $rotation): self
    {
        return match (strtoupper($rotation)) {
            'AS_IS'       => self::AS_IS,
            'XY'          => self::ROTATE_XY,
            'XZ'          => self::ROTATE_XZ,
            'YZ'          => self::ROTATE_YZ,
            'UPSIDE_DOWN' => self::UPSIDE_DOWN,
            'ON_SIDE'     => self::ON_SIDE,
            default       => self::AS_IS,
        };
    }
}
