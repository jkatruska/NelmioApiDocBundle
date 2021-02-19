<?php

/*
 * This file is part of the NelmioApiDocBundle package.
 *
 * (c) Nelmio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Nelmio\ApiDocBundle\Tests\ModelDescriber\Annotations;

use Doctrine\Common\Annotations\AnnotationReader;
use Nelmio\ApiDocBundle\ModelDescriber\Annotations\SymfonyConstraintAnnotationReader;
use OpenApi\Annotations as OA;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints as Assert;

class SymfonyConstraintAnnotationReaderTest extends TestCase
{
    public function testUpdatePropertyFix1283()
    {
        $entity = new class() {
            /**
             * @Assert\NotBlank()
             * @Assert\Length(min = 1)
             */
            private $property1;
            /**
             * @Assert\NotBlank()
             */
            private $property2;
        };

        $schema = new OA\Schema([]);
        $schema->merge([new OA\Property(['property' => 'property1'])]);
        $schema->merge([new OA\Property(['property' => 'property2'])]);

        $symfonyConstraintAnnotationReader = new SymfonyConstraintAnnotationReader(new AnnotationReader());
        $symfonyConstraintAnnotationReader->setSchema($schema);

        $symfonyConstraintAnnotationReader->updateProperty(new \ReflectionProperty($entity, 'property1'), $schema->properties[0]);
        $symfonyConstraintAnnotationReader->updateProperty(new \ReflectionProperty($entity, 'property2'), $schema->properties[1]);

        // expect required to be numeric array with sequential keys (not [0 => ..., 2 => ...])
        $this->assertEquals($schema->required, ['property1', 'property2']);
    }

    public function testOptionalProperty()
    {
        if (!\property_exists(Assert\NotBlank::class, 'allowNull')) {
            $this->markTestSkipped('NotBlank::allowNull was added in symfony/validator 4.3.');
        }

        $entity = new class() {
            /**
             * @Assert\NotBlank(allowNull = true)
             * @Assert\Length(min = 1)
             */
            private $property1;
            /**
             * @Assert\NotBlank()
             */
            private $property2;
        };

        $schema = new OA\Schema([]);
        $schema->merge([new OA\Property(['property' => 'property1'])]);
        $schema->merge([new OA\Property(['property' => 'property2'])]);

        $symfonyConstraintAnnotationReader = new SymfonyConstraintAnnotationReader(new AnnotationReader());
        $symfonyConstraintAnnotationReader->setSchema($schema);

        $symfonyConstraintAnnotationReader->updateProperty(new \ReflectionProperty($entity, 'property1'), $schema->properties[0]);
        $symfonyConstraintAnnotationReader->updateProperty(new \ReflectionProperty($entity, 'property2'), $schema->properties[1]);

        // expect required to be numeric array with sequential keys (not [0 => ..., 2 => ...])
        $this->assertEquals($schema->required, ['property2']);
    }

    public function testAssertChoiceResultsInNumericArray()
    {
        define('TEST_ASSERT_CHOICE_STATUSES', [
            1 => 'active',
            2 => 'blocked',
        ]);

        $entity = new class() {
            /**
             * @Assert\Length(min = 1)
             * @Assert\Choice(choices=TEST_ASSERT_CHOICE_STATUSES)
             */
            private $property1;
        };

        $schema = new OA\Schema([]);
        $schema->merge([new OA\Property(['property' => 'property1'])]);

        $symfonyConstraintAnnotationReader = new SymfonyConstraintAnnotationReader(new AnnotationReader());
        $symfonyConstraintAnnotationReader->setSchema($schema);

        $symfonyConstraintAnnotationReader->updateProperty(new \ReflectionProperty($entity, 'property1'), $schema->properties[0]);

        // expect enum to be numeric array with sequential keys (not [1 => "active", 2 => "active"])
        $this->assertEquals($schema->properties[0]->enum, ['active', 'blocked']);
    }

    public function testMultieChoiceConstraintsApplyEnumToItems()
    {
        $entity = new class() {
            /**
             * @Assert\Choice(choices={"one", "two"}, multiple=true)
             */
            private $property1;
        };

        $schema = new OA\Schema([]);
        $schema->merge([new OA\Property(['property' => 'property1'])]);

        $symfonyConstraintAnnotationReader = new SymfonyConstraintAnnotationReader(new AnnotationReader());
        $symfonyConstraintAnnotationReader->setSchema($schema);

        $symfonyConstraintAnnotationReader->updateProperty(new \ReflectionProperty($entity, 'property1'), $schema->properties[0]);

        $this->asertInstanceOf(OA\Items::class, $schema->properties[0]->items);
        $this->assertEquals($schema->properties[0]->items->enum, ['one', 'two']);
    }

    /**
     * @group https://github.com/nelmio/NelmioApiDocBundle/issues/1780
     */
    public function testLengthConstraintDoesNotSetMaxLengthIfMaxIsNotSet()
    {
        $entity = new class() {
            /**
             * @Assert\Length(min = 1)
             */
            private $property1;
        };

        $schema = new OA\Schema([]);
        $schema->merge([new OA\Property(['property' => 'property1'])]);

        $symfonyConstraintAnnotationReader = new SymfonyConstraintAnnotationReader(new AnnotationReader());
        $symfonyConstraintAnnotationReader->setSchema($schema);

        $symfonyConstraintAnnotationReader->updateProperty(new \ReflectionProperty($entity, 'property1'), $schema->properties[0]);

        $this->assertSame(OA\UNDEFINED, $schema->properties[0]->maxLength);
        $this->assertSame(1, $schema->properties[0]->minLength);
    }

    /**
     * @group https://github.com/nelmio/NelmioApiDocBundle/issues/1780
     */
    public function testLengthConstraintDoesNotSetMinLengthIfMinIsNotSet()
    {
        $entity = new class() {
            /**
             * @Assert\Length(max = 100)
             */
            private $property1;
        };

        $schema = new OA\Schema([]);
        $schema->merge([new OA\Property(['property' => 'property1'])]);

        $symfonyConstraintAnnotationReader = new SymfonyConstraintAnnotationReader(new AnnotationReader());
        $symfonyConstraintAnnotationReader->setSchema($schema);

        $symfonyConstraintAnnotationReader->updateProperty(new \ReflectionProperty($entity, 'property1'), $schema->properties[0]);

        $this->assertSame(OA\UNDEFINED, $schema->properties[0]->minLength);
        $this->assertSame(100, $schema->properties[0]->maxLength);
    }
}
