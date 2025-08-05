<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use ILIAS\IndividualAssessmentFormPool\FieldConfig;
use ILIAS\IndividualAssessmentFormPool\FieldType;
use ILIAS\IndividualAssessmentFormPool\FieldBuilder;
use ILIAS\UI\Component\Input\Field;
use ILIAS\IndividualAssessmentFormPool\Testing\FieldBuilderMockFactory;

require_once(__DIR__ . "/FieldBuilderMockFactory.php");

class FieldBuilderTest extends TestCase
{
    use FieldBuilderMockFactory;

    protected FieldBuilder $builder;

    public function setUp(): void
    {
        $this->builder = $this->getFieldBuilder();
    }

    public function fieldBuilderData(): array
    {
        return [
            [new FieldConfig(FieldType::MARKDOWN),
                'some value',
                Field\Markdown::class,
                'some value'
            ],
            [new FieldConfig(FieldType::FILE),
                'some_id',
                Field\File::class,
                ['some_id' => 'some_id']
            ],
            [new FieldConfig(FieldType::DATETIME),
                '1742784914',
                Field\DateTime::class,
                '2025-03-24 02:55'
            ],
            [new FieldConfig(FieldType::SINGLESELECT, '', '', null, ['AA', 'BB', 'CC']),
                'BB',
                Field\Select::class,
                'BB'
            ],
            [new FieldConfig(FieldType::TAG, '', '', null, ['a', 'b', 'c']),
                implode(\SpecifiedFormStorageDB::VALUE_DELIMITER, ['c' , 'a']),
                Field\Tag::class,
                ['c' , 'a']
            ],
            [new FieldConfig(FieldType::MARKDOWN),null,Field\Markdown::class,''],
            [new FieldConfig(FieldType::FILE),null,Field\File::class,[]],
            [new FieldConfig(FieldType::DATETIME),null,Field\DateTime::class,''],
            [new FieldConfig(FieldType::SINGLESELECT),null,Field\Select::class,''],
            [new FieldConfig(FieldType::TAG),null,Field\Tag::class,null],
            [new FieldConfig(FieldType::TAG),'[++3]#:#-2#:#somthing with space',Field\Tag::class,['[++3]', '-2', 'somthing with space']],

        ];
    }

    /**
     * @dataProvider fieldBuilderData
     */
    public function testBuildField(
        FieldConfig $cfg,
        mixed $value,
        string $expected_class,
        mixed $expected_value
    ): void {
        $f = $this->builder->build($cfg, $value);
        $this->assertInstanceOf($expected_class, $f);
        $this->assertEquals($expected_value, $f->getValue());
    }

}
