<?php

declare(strict_types=1);

namespace app\models;

/**
 * A brand name to drop during cleaning (stage 4): site.pro's own brand plus competitor
 * brands. Editable in the admin area. See {@see TermListRecord}.
 */
class BrandTerm extends TermListRecord
{
    public static function tableName(): string
    {
        return '{{%brand_term}}';
    }
}
