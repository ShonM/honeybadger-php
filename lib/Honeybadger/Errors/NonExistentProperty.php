<?php

namespace Honeybadger\Errors;

/**
 * Thrown when a non-existent property is called.
 *
 * @package   Honeybadger
 * @category  Errors
 */
class NonExistentProperty extends HoneybadgerError
{
    public function __construct($class, $property)
    {
        parent::__construct('Missing method or property :property for :class', array(
            ':class'    => get_class($class),
            ':property' => $property,
        ));
    }

}
