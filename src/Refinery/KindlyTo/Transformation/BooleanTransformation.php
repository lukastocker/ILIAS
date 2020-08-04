<?php
/* Copyright (c) 2020 Luka K. A. Stocker, Extended GPL, see docs/LICENSE */

namespace ILIAS\Refinery\KindlyTo\Transformation;

use ILIAS\Data\Result;
use ILIAS\Refinery\DeriveApplyToFromTransform;
use ILIAS\Refinery\Transformation;
use ILIAS\Refinery\ConstraintViolationException;
use phpDocumentor\Reflection\Types\Self_;

class BooleanTransformation implements Transformation {
    const BOOL_TRUE_STRING = 'true';
    const BOOL_FALSE_STRING = 'false';
    const BOOL_TRUE_NUMBER = 1;
    const BOOL_FALSE_NUMBER = 0;
    const BOOL_TRUE_NUMBER_STRING = '1';
    const BOOL_FALSE_NUMBER_STRING = '0';

    use DeriveApplyToFromTransform;

    /**
     * @inheritdoc
     */
    public function transform($from) {

        if($from != (self::BOOL_FALSE_NUMBER || self::BOOL_FALSE_NUMBER_STRING) &&
            $from != (self::BOOL_TRUE_NUMBER || self::BOOL_TRUE_NUMBER_STRING) &&
            mb_strtolower($from) != (self::BOOL_TRUE_STRING || self::BOOL_FALSE_STRING)
        ) {
            throw new ConstraintViolationException(
                sprintf('The value "%s" could not be transformed into boolean.', $from),
                'not_boolean',
                $from
            );
        }

        if($from === self::BOOL_TRUE_NUMBER || $from === self::BOOL_TRUE_NUMBER_STRING || mb_strtolower($from) === self::BOOL_TRUE_STRING) {
            return true;
        }

        if($from === self::BOOL_FALSE_NUMBER || $from === self::BOOL_FALSE_NUMBER_STRING || mb_strtolower($from) === self::BOOL_FALSE_STRING) {
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function __invoke($from) {
        return $this->transform($from);
    }
}
