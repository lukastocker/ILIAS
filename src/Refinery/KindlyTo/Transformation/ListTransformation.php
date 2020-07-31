<?php
declare(strict_types=1);
/* Copyright (c) 1998-2020 Luka Kai Alexander Stocker, Extended GPL, see docs/LICENSE */

namespace ILIAS\Refinery\KindlyTo\Transformation;

use ILIAS\Refinery\ConstraintViolationException;
use ILIAS\Refinery\DeriveApplyToFromTransform;
use ILIAS\Refinery\Transformation;

class ListTransformation implements Transformation {
    use DeriveApplyToFromTransform;

    private $transformation;

    public function __construct(Transformation $transformation) {
        $this->transformation = $transformation;
    }

    /**
     * @inheritdoc
     */
    public function transform($from) {
        if(!is_array($from)) {
            $from = [$from];
        }

        if([] === $from) {
            throw new ConstraintViolationException(
                sprintf('The array "%s" ist empty',$from),
                'value_array_is_empty',
                $from
            );
        }

        $result = [];
        foreach($from as $val) {
            $transformedVal = $this->transformation->transform($val);
            $result[] = $transformedVal;
        }
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function __invoke($from) {
        return $this->transform($from);
    }
}