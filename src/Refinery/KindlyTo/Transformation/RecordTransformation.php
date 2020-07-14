<?php
declare(strict_types=1);
/* Copyright (c) 1998-2020 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * @author Luka Stocker <lstocker@concepts-and-training.de>
 */

namespace ILIAS\Refinery\KindlyTo\Transformation;

use ILIAS\Refinery\DeriveApplyToFromTransform;
use ILIAS\Refinery\Transformation;
use ILIAS\Refinery\ConstraintViolationException;

class RecordTransformation implements Transformation
{
    use DeriveApplyToFromTransform;

    /**
     *@var Transformation[]
     */
    private $transformations;

    /**
     *@param Transformation[] $transformations
     */

    public function __construct(array $transformations)
    {
        foreach($transformations as $key => $transformation)
        {
            if (!$transformation instanceof Transformation) {
                $transformationClassName = Transformation::class;

                throw new ConstraintViolationException(
                    sprintf('The array MUST contain only "%s" instances', $transformationClassName),
                    'not_a_transformation',
                    $transformationClassName
                );
            }

            if(false === is_string($key))
            {
                throw new ConstraintViolationException(
                    'The array key must be a string',
                    'key_is_not_a_string'
                );
            }
        }
        $this->transformations = $transformations;
    }

    /**
     * @inheritDoc
     */
    public function transform($from)
    {
        if(false == is_array($from))
        {
            $from = array($from);
            if(array() === $from)
            {
                throw new ConstraintViolationException(
                    'The array ist empty',
                    'value_array_is_empty'
                ) ;
            }
        }

        $result = array();
        foreach($from as $key => $value)
        {
            if (false === is_string($key))
            {
                throw new ConstraintViolationException(
                    'Array key must be a string',
                    'key_is_not_a_string'
                );
            }

            if(false === isset($this->transformations[$key]))
            {
                throw new ConstraintViolationException(
                    sprintf('Could not find transformation for key "%s"', $key),
                    'no_array_key_existing'
                );
            }

            $transformation = $this->transformations[$key];
            $transformedValue = $transformation->transform($value);
            $result[$key] = $transformedValue;
        }
        return $result;
    }

     /**
     * @inheritDoc
     */
    public function __invoke($from)
    {
        return $this->transform($from);
    }
}