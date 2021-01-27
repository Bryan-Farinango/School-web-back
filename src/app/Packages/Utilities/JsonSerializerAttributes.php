<?php

namespace App\Packages\Utilities;

use JsonSerializable;
use SimpleXMLElement;

/**
 * Serializador genérico JSON que si considera atributos dentro de los tags
 *
 * @package stupendo-JsonSerializerAttributes
 * @author MCO, asaltando a mano armada StackOverflow
 */
class JsonSerializerAttributes extends SimpleXmlElement implements JsonSerializable
{
    /**
     * SimpleXMLElement JSON serialization
     *
     * @return null|string
     *
     * @link http://php.net/JsonSerializable.jsonSerialize
     * @see JsonSerializable::jsonSerialize
     */
    function jsonSerialize()
    {
        if (count($this)) {
            // serialize children if there are children
            foreach ($this as $tag => $child) {
                // child is a single-named element -or- child are multiple elements with the same name - needs array
                if (count($child) > 1) {
                    $child = [$child->children()->getName() => iterator_to_array($child, false)];
                }
                $array[$tag] = $child;
            }
        } else {
            // serialize attributes and text for a leaf-elements
            foreach ($this->attributes() as $name => $value) {
                $array["$name"] = (string)$value;
            }
            $array["#text"] = (string)$this;
        }

        if ($this->xpath('/*') == array($this)) {
            // the root element needs to be named
            $array = [$this->getName() => $array];
        }

        return $array;
    }
}
