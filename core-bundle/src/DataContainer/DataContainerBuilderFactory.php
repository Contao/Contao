<?php

namespace Contao\CoreBundle\DataContainer;

use Contao\System;

class DataContainerBuilderFactory implements DataContainerBuilderFactoryInterface
{
    /** 
     * @param DataContainerBuilderTemplateInterface[] $templates
     */
    public function __construct(private readonly iterable $templates)
    {
    }

    public function __invoke(string $name): DataContainerBuilderInterface
    {
        return (new DataContainerBuilder($name, $this->templates));
    }

    /** 
     * Helper function to get a builder instance from within DCA files.
     */
    public static function get(?string $name = null): DataContainerBuilderInterface
    {
        if (null === $name) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS|DEBUG_BACKTRACE_PROVIDE_OBJECT, 1);
            $name = pathinfo($trace[0]['file'], PATHINFO_FILENAME);
        }
    
        return (System::getContainer()->get('contao.data_container.builder_factory'))($name);
    }
}
