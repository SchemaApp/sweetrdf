<?php
/**
 * EasyRdf
 *
 * LICENSE
 *
 * Copyright (c) 2009-2010 Nicholas J Humfrey.  All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 * 3. The name of the author 'Nicholas J Humfrey" may be used to endorse or
 *    promote products derived from this software without specific prior
 *    written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package    EasyRdf
 * @copyright  Copyright (c) 2009-2010 Nicholas J Humfrey
 * @license    http://www.opensource.org/licenses/bsd-license.php
 * @version    $Id$
 */

/**
 * Class to serialise an EasyRdf_Graph into RDF
 * with no external dependancies.
 *
 * @package    EasyRdf
 * @copyright  Copyright (c) 2009-2010 Nicholas J Humfrey
 * @license    http://www.opensource.org/licenses/bsd-license.php
 */
class EasyRdf_Serialiser_RdfXml extends EasyRdf_Serialiser
{
    protected $_prefixes = array();

    protected function addPrefix($qname)
    {
        list ($prefix) = explode(':', $qname);
        $this->_prefixes[$prefix] = true;
    }

    /**
     * Protected method to serialise an object node into an XML partial
     */
    protected function rdfxmlResource($res)
    {
        if (is_object($res)) {
            if ($res->isBNode()) {
                return $res->getURI();
            } else {
                return $res->getURI();
            }
        } else {
            $uri = EasyRdf_Namespace::expand($res);
            if ($uri) {
                return "$uri";
            } else {
                return "$res";
            }
        }
    }

    /**
     * Protected method to serialise an object node into an XML object
     */
    protected function rdfxmlObject($property, $obj)
    {
        if (is_object($obj) and $obj instanceof EasyRdf_Resource) {
            $value = $this->rdfxmlResource($obj);
            return "    <".$property.
                   " rdf:resource=\"".htmlspecialchars($value)."\"/>\n";
        } else if (is_object($obj) and $obj instanceof EasyRdf_Literal) {
            $value = htmlspecialchars($obj->getValue());
            $atrributes = "";
            if ($obj->getDatatype()) {
                $atrributes = ' rdf:datatype="'.$obj->getDatatype().'"';
            } elseif ($obj->getLang()) {
                $atrributes = ' xml:lang="'.$obj->getLang().'"';
            }

            // validators think that html entities are namespaces,
            // so encode the &
            // http://www.semanticoverflow.com/questions/984/html-entities-in-rdfxmlliteral
            return "    <".$property.$atrributes.">" .
                   str_replace('&', '&amp;', $value) . 
                   "</".$property.">\n";
        } else {
            throw new EasyRdf_Exception(
                "Unable to serialise object to xml: ".getType($obj)
            );
        }
    }

    /**
     * Method to serialise an EasyRdf_Graph into RDF/XML
     *
     * @param string $graph An EasyRdf_Graph object.
     * @param string $format The name of the format to convert to (rdfxml).
     * @return string The xml formatted RDF.
     */
    public function serialise($graph, $format)
    {
        parent::checkSerialiseParams($graph, $format);

        if ($format != 'rdfxml') {
            throw new EasyRdf_Exception(
                "EasyRdf_Serialiser_RdfXml does not support: $format"
            );
        }

        // store of namespaces to be appended to the rdf:RDF tag
        $this->_prefixes = array('rdf' => true);

        $resCount = 0;
        $xml = '';
        foreach ($graph->resources() as $resource) {
            $properties = $resource->properties();
            if (count($properties) == 0)
                continue;

            if ($resCount)
                $xml .= "\n";

            $xml .= '  <rdf:Description rdf:about="'.
                    $resource->get($resource).'">'."\n";
            foreach ($properties as $property) {
                $this->addPrefix($property);
                $objects = $resource->all($property);
                foreach ($objects as $object) {
                    $xml .= $this->rdfxmlObject($property, $object);
                }
            }
            $xml .= "  </rdf:Description>\n";
            $resCount++;
        }

        // iterate through namepsaces array prefix and output a string.
        $namespaceStr = '';
        foreach ($this->_prefixes as $prefix => $count) {
            $url = EasyRdf_Namespace::get($prefix);
            if (strlen($namespaceStr)) {
                $namespaceStr .= "\n        ";
            }
            $namespaceStr .= ' xmlns:'.$prefix.'="'.htmlspecialchars($url).'"';
        }

        return "<?xml version=\"1.0\" encoding=\"utf-8\" ?>\n".
               "<rdf:RDF". $namespaceStr . ">\n" . $xml . "</rdf:RDF>\n";
    }

}

EasyRdf_Format::registerSerialiser('rdfxml', 'EasyRdf_Serialiser_RdfXml');