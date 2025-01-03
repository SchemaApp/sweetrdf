<?php

namespace Tests\EasyRdf;

use EasyRdf\Format;
use EasyRdf\Graph;
use EasyRdf\Parser;
use Test\MockClass\Parser\MockParser;
use Test\TestCase;

/**
 * EasyRdf
 *
 * LICENSE
 *
 * Copyright (c) 2009-2014 Nicholas J Humfrey.  All rights reserved.
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
 * @copyright  Copyright (c) 2009-2014 Nicholas J Humfrey
 * @license    https://www.opensource.org/licenses/bsd-license.php
 */
class ParserTest extends TestCase
{
    private $graph;
    private $resource;
    private MockParser $parser;
    private $foaf_data;

    /**
     * Set up the test suite before each test
     */
    protected function setUp(): void
    {
        $this->graph = new Graph();
        $this->resource = $this->graph->resource('http://www.example.com/');
        $this->parser = new MockParser();
        $this->foaf_data = readFixture('foaf.json');
    }

    public function testParse()
    {
        $this->assertTrue(
            $this->parser->parse(
                $this->graph,
                $this->foaf_data,
                'json',
                null
            )
        );
    }

    public function testParseFormatObject()
    {
        $format = Format::getFormat('json');
        $this->assertTrue(
            $this->parser->parse(
                $this->graph,
                $this->foaf_data,
                $format,
                null
            )
        );
    }

    public function testParseNullGraph()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            '$graph should be an EasyRdf\Graph object and cannot be null'
        );
        $this->parser->parse(null, $this->foaf_data, 'json', null);
    }

    public function testParseNonObjectGraph()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            '$graph should be an EasyRdf\Graph object and cannot be null'
        );
        $this->parser->parse('string', $this->foaf_data, 'json', null);
    }

    public function testParseNonGraph()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            '$graph should be an EasyRdf\Graph object and cannot be null'
        );
        $this->parser->parse($this->resource, $this->foaf_data, 'json', null);
    }

    public function testParseNullData()
    {
        $this->assertTrue(
            $this->parser->parse($this->graph, null, 'json', null)
        );
    }

    public function testParseEmptyData()
    {
        $this->assertTrue(
            $this->parser->parse($this->graph, '', 'json', null)
        );
    }

    public function testParseNullFormat()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            '$format cannot be null or empty'
        );
        $this->parser->parse($this->graph, $this->foaf_data, null, null);
    }

    public function testParseEmptyFormat()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            '$format cannot be null or empty'
        );
        $this->parser->parse($this->graph, $this->foaf_data, '', null);
    }

    public function testParseBadObjectFormat()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            '$format should be a string or an EasyRdf\Format object'
        );
        $this->parser->parse($this->graph, $this->foaf_data, $this, null);
    }

    public function testParseIntegerFormat()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            '$format should be a string or an EasyRdf\Format object'
        );
        $this->parser->parse($this->graph, $this->foaf_data, 1, null);
    }

    public function testParseNonStringBaseUri()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            '$baseUri should be a string'
        );
        $this->parser->parse($this->graph, $this->foaf_data, 'json', 1);
    }

    public function testParseUndefined()
    {
        $this->expectException('EasyRdf\Exception');
        $this->expectExceptionMessage(
            'This method should be overridden by sub-classes.'
        );

        /** @var \EasyRdf\Parser */
        $parser = $this->getMockForAbstractClass(Parser::class);
        $parser->parse($this->graph, 'data', 'format', 'baseUri');
    }
}
