<?php

declare(strict_types=1);

namespace Contao\CoreBundle\Orm;

use Contao\CoreBundle\Orm\Annotation\AnnotationDumper;
use Contao\CoreBundle\Orm\Annotation\Extension;
use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\Mapping\Table;
use Laminas\Code\DeclareStatement;
use Laminas\Code\Generator\ClassGenerator;
use Laminas\Code\Generator\DocBlock\Tag\GenericTag;
use Laminas\Code\Generator\DocBlockGenerator;
use Laminas\Code\Generator\FileGenerator;
use Laminas\Code\Reflection\ClassReflection;
use Symfony\Component\Filesystem\Filesystem;

class EntityFactory
{
    private $annotationReader;
    private $annotationDumper;

    public function __construct(Reader $annotationReader, AnnotationDumper $annotationDumper)
    {
        $this->annotationReader = $annotationReader;
        $this->annotationDumper = $annotationDumper;
    }

    public function generateEntityClasses(string $directory, array $entities, array $extensions): void
    {
        if (0 === \count($entities)) {
            return;
        }

        $tree = [];
        foreach ($extensions as $extensionClass) {
            try {
                $reflectionClass = new ClassReflection($extensionClass);
            } catch (\ReflectionException $e) {
                continue;
            }

            /** @var Extension|null $extendsMetaData */
            $extendsMetaData = $this->annotationReader->getClassAnnotation($reflectionClass, Extension::class);

            if (null === $extendsMetaData) {
                continue;
            }

            $index = $extendsMetaData->index;

            if (!in_array($index, $entities)) {
                continue;
            }

            if (!array_key_exists($index, $tree)) {
                $tree[$index] = [
                    'extensions' => [],
                    'indexes' => []
                ];
            }

            $tree[$index]['extensions'][] = $extensionClass;
            $tree[$index]['indexes'] = array_merge($tree[$index]['indexes'], $extendsMetaData->indexes);
        }

        foreach ($tree as $entity => $config) {
            $extensions = $config['extensions'];
            $indexes = $config['indexes'];

            // Create entity
            try {
                $reflectionClass = new ClassReflection($entity);
            } catch (\ReflectionException $e) {
                continue;
            }

            $metaData = $this->annotationReader->getClassAnnotations($reflectionClass);

            $tags = [];
            foreach ($metaData as $annotation) {
                if ($annotation instanceof Table) {
                    if (null === $annotation->indexes) {
                        $annotation->indexes = [];
                    }

                    $annotation->indexes = array_merge($annotation->indexes, $indexes);
                }

                $tags[] = new GenericTag(
                    $this->getAnnotationNamespace($annotation),
                    $this->annotationDumper->dump($annotation)
                );
            }

            $docBlockGenerator = DocBlockGenerator::fromArray([
                'shortDescription' => 'This entity is auto generated.',
                'tags' => $tags
            ]);

            $classGenerator = ClassGenerator::fromReflection($reflectionClass);
            $classGenerator->setAbstract(false);
            $classGenerator->setDocBlock($docBlockGenerator);
            $classGenerator->setExtendedClass($entity);
            $classGenerator->setName($reflectionClass->getShortName());
            $classGenerator->setNamespaceName('Contao\CoreBundle\GeneratedEntity');

            foreach ($extensions as $trait) {
                $classGenerator->addTrait('\\' . $trait);
            }

            foreach ($classGenerator->getProperties() as $property) {
                $reflectionProperty = $reflectionClass->getProperty($property->getName());

                $propertyMetaData = $this->annotationReader->getPropertyAnnotations($reflectionProperty);
                $propertyDocBlockGenerator = new DocBlockGenerator();

                foreach ($propertyMetaData as $annotation) {
                    $propertyDocBlockGenerator->setTag(new GenericTag(
                        $this->getAnnotationNamespace($annotation),
                        $this->annotationDumper->dump($annotation)
                    ));
                }

                $property->setDocBlock($propertyDocBlockGenerator);
            }

            $fileDocs = new DocBlockGenerator();
            $fileDocs->setLongDescription('This file is auto generated');

            $fileGenerator = new FileGenerator();
            $fileGenerator->setDocBlock($fileDocs);
            $fileGenerator->setClass($classGenerator);
            $fileGenerator->setDeclares([
                DeclareStatement::strictTypes(1)
            ]);

            $filesystem = new Filesystem();
            $filesystem->dumpFile(sprintf('%s/%s.php', $directory, $reflectionClass->getShortName()), $fileGenerator->generate());
        }
    }

    private function getAnnotationNamespace($annotation): string
    {
        return '\\' . get_class($annotation);
    }
}
