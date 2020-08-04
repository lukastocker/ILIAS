<?php
/* Copyright (c) 2020 Luka K. A. Stocker, Extended GPL, see docs/LICENSE */

namespace ILIAS\Refinery\KindlyTo\Transformation;

use ILIAS\Data\Result;
use ILIAS\Refinery\ConstraintViolationException;
use ILIAS\Refinery\DeriveApplyToFromTransform;
use ILIAS\Refinery\Transformation;

/**
 * Transform date format to DateTimeImmutable
 * Please note:
 * - RFC3339 & W3C format output on screen is the same as Atom
 * - RFC850 format output on screen is the same as Cookie
 * - RFC1036, RFC1123, RFC2822 & RSS format output on screen is the same as RFC822
 */
class DateTimeTransformation implements Transformation {

    use DeriveApplyToFromTransform;

    /**
     * @inheritdoc
     */
    public function transform($from)
    {
        $formats = [
            \DateTimeImmutable::ATOM,
            \DateTimeImmutable::COOKIE,
            \DateTimeImmutable::ISO8601,
            \DateTimeImmutable::RFC822,
            \DateTimeImmutable::RFC7231,
            \DateTimeImmutable::RFC3339_EXTENDED
            ];

        if(is_string($from)) {
            foreach ($formats as $format) {
                $res = \DateTimeImmutable::createFromFormat($format, $from);
                if ($res instanceof \DateTimeImmutable) {
                    return $res;
                }
            }
        }

        if(is_int($from) || is_float($from)) {
            $UnixTimestamp = strtotime($from);
            $date = date(DATE_ISO8601, $UnixTimestamp);
            return \DateTimeImmutable::createFromFormat(\DateTimeImmutable::ISO8601, $date);
        }

        if(!is_string($from) || !is_int($from) || !is_float($from)) {
            throw new ConstraintViolationException(
                sprintf('Value "%s" could not be transformed.', $from),
                'no_string',
                $from
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function __invoke($from)
    {
        return $this->transform($from);
    }
}