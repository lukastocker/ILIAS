<?php
declare(strict_types=1);
/* Copyright (c) 2020 Luka K. A. Stocker, Extended GPL, see docs/LICENSE */

namespace ILIAS\Refinery\KindlyTo\Transformation;

use ILIAS\Refinery\DeriveApplyToFromTransform;
use ILIAS\Refinery\Transformation;
use ILIAS\Refinery\ConstraintViolationException;

class TupleTransformation implements Transformation {
    use DeriveApplyToFromTransform;

    private $transformations;

    /**
     * @param Transformation[] $transformations;
     */
    public function __construct($transformations) {
        foreach ($transformations as $transformation) {
            if (!$transformation instanceof Transformation) {
                $transformationClassName = Transformation::class;

                throw new ConstraintViolationException(
                    sprintf('The array must contain only "%s" instances', $transformationClassName),
                    'not_a_transformation',
                    $transformationClassName
                );
            }
        }
        $this->transformations = $transformations;
    }

    /**
     * @inheritDoc
     */
    public function transform($from) {
        $this->ValueLength($from);

        if(!is_array($from)) {
            $from = [$from];
        }

        if([] === $from) {
            throw new ConstraintViolationException(
                sprintf('The array "%s" ist empty', $from),
                'value_array_is_empty',
                $from
            ) ;
        }

        $result = [];
        foreach($from as $key => $value)
        {
            if(!array_key_exists($key, $this->transformations)) {
                throw new ConstraintViolationException(
                    sprintf('Matching value "%s" not found', $value),
                    'matching_values_not_found',
                    $value
                );
            }
            $transformedValue = $this->transformations[$key]->transform($value);
            $result[] = $transformedValue;
        }
        return $result;
    }

    private function ValueLength($values) {
        $countOfValues = count($values);
        $countOfTransformations = count($this->transformations);

        if ($countOfValues !== $countOfTransformations) {
            throw new ConstraintViolationException(
                sprintf('The length of given value "%s" does not match with the given transformations', $countOfValues),
                'given_values_',
                $countOfValues
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function __invoke($from) {
        return $this->transform($from);
    }
}