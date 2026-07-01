<?php

declare(strict_types=1);

namespace app\models;

/**
 * A term never allowed into a campaign. Managed here alongside the brand list; consumed by
 * preparation (stage 5). See {@see TermListRecord}.
 */
class ForbiddenTerm extends TermListRecord
{
    public static function tableName(): string
    {
        return '{{%forbidden_term}}';
    }
}
