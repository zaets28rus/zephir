<?php

/*
 +--------------------------------------------------------------------------+
 | Zephir Language                                                          |
 +--------------------------------------------------------------------------+
 | Copyright (c) 2013-2015 Zephir Team and contributors                     |
 +--------------------------------------------------------------------------+
 | This source file is subject the MIT license, that is bundled with        |
 | this package in the file LICENSE, and is available through the           |
 | world-wide-web at the following url:                                     |
 | http://zephir-lang.com/license.html                                      |
 |                                                                          |
 | If you did not receive a copy of the MIT license and are unable          |
 | to obtain it through the world-wide-web, please send a note to           |
 | license@zephir-lang.com so we can mail you a copy immediately.           |
 +--------------------------------------------------------------------------+
*/

namespace Zephir\Cache;

use Zephir\Call;
use Zephir\ClassMethod;
use Zephir\Variable;
use Zephir\ClassDefinition;
use Zephir\CompilationContext;

/**
 * MethodCache
 *
 * Calls in Zephir implement monomorphic and polimorphic caches to
 * improve performance. Method/Functions lookups are cached in a standard
 * first-level method lookup cache.
 *
 * The concept of inline caching is based on the empirical observation
 * that the objects that occur at a particular call site are often of the same type
 * Internal functions are considered monomorphic since they do not change across execution.
 * Final and private methods are also monomorphic because of their own nature.
 * Due to the Ahead-Of-Time compilation approach provided by Zephir, is not possible
 * to implement inline caches, however is possible to add barriers/guards to
 * take advantage of profile guided optimizations (PGO) and branch prediction.
 *
 * This implementation is based on the work of Hölzle, Chambers and Ungar [1].
 *
 * [1] http://www.cs.ucsb.edu/~urs/oocsb/papers/ecoop91.pdf
 */
class MethodCache
{
    protected $cache = array();

    protected $gatherer;

    /**
     * MethodCache
     *
     * @param CallGathererPass $gatherer
     */
    public function __construct($gatherer)
    {
        $this->gatherer = $gatherer;
    }

    /**
     * Checks if the class is suitable for caching
     *
     * @param ClassDefinition $classDefinition
     * @return boolean
     */
    private function isClassCacheable($classDefinition)
    {
        if ($classDefinition instanceof ClassDefinition) {
            return true;
        }
        if ($classDefinition instanceof \ReflectionClass) {
            if ($classDefinition->isInternal() && $classDefinition->isInstantiable()) {
                $extension = $classDefinition->getExtension();
                switch ($extension->getName()) {
                    case 'Reflection':
                    case 'Core':
                    case 'SPL':
                        return true;
                }
            }
        }
        return false;
    }

    /**
     * Retrieves/Creates a function cache for a method call
     *
     * @param CompilationContext $compilationContext
     * @param ClassMethod $method
     * @param Variable $caller
     */
    public function get(CompilationContext $compilationContext, $method, $caller)
    {

        if (!is_object($method)) {
            /**
             * Try to generate a cache based on the fact the variable is not modified within the loop block
             */
            if ($compilationContext->insideCycle && !$caller->isTemporal()) {
                if (count($compilationContext->cycleBlocks) && $caller->getType() == 'variable') {
                    $currentBlock = $compilationContext->cycleBlocks[count($compilationContext->cycleBlocks) - 1];

                    if ($currentBlock->getMutateGatherer(true)->getNumberOfMutations($caller->getName()) == 0) {
                        $functionCache = $compilationContext->symbolTable->getTempVariableForWrite('zephir_fcall_cache_entry', $compilationContext);
                        $functionCache->setMustInitNull(true);
                        $functionCache->setReusable(false);

                        return '&' . $functionCache->getName();
                    }
                }
            }

            return 'NULL';
        }

        if (!($method instanceof \ReflectionMethod)) {
            /**
             * Avoid generate caches for external classes
             */
            if ($method->getClassDefinition()->isExternal()) {
                return 'NULL';
            }

            $completeName = $method->getClassDefinition()->getCompleteName();
            if (isset($this->cache[$completeName][$method->getName()])) {
                return '&' . $this->cache[$completeName][$method->getName()]->getName();
            }

            $gatherer = $this->gatherer;
            if (is_object($gatherer)) {
                $number = $gatherer->getNumberOfMethodCalls($method->getClassDefinition()->getCompleteName(), $method->getName());
            } else {
                $number = 0;
            }

            $staticCacheable = !$method->getClassDefinition()->isInterface() && ($method->getClassDefinition()->isFinal() || $method->isFinal() || $method->isPrivate());
            $cacheable = $staticCacheable || $number > 1;

        } else {
            $staticCacheable = false;
            $cacheable = false;
        }

        $associatedClass = false;
        if (!$compilationContext->insideCycle) {
            if ($caller->getName() != 'this_ptr') {
                $associatedClass = $caller->getAssociatedClass();
                if (!$this->isClassCacheable($associatedClass)) {
                    $associatedClass = false;
                } else {
                    $staticCacheable = true;
                }
            }
        }

        if (!$compilationContext->insideCycle && !$cacheable && !$associatedClass) {
            return 'NULL';
        }

        if (!($method instanceof \ReflectionMethod) && $staticCacheable) {
            $functionCache = $compilationContext->symbolTable->getTempVariableForWrite('static_zephir_fcall_cache_entry', $compilationContext);
        } else {
            $functionCache = $compilationContext->symbolTable->getTempVariableForWrite('zephir_fcall_cache_entry', $compilationContext);
        }

        $functionCache->setMustInitNull(true);
        $functionCache->setReusable(false);

        if (!($method instanceof \ReflectionMethod)) {
            $this->cache[$completeName][$method->getName()] = $functionCache;
        }

        return '&' . $functionCache->getName();
    }
}
