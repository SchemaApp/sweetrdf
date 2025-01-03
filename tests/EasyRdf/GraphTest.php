<?php

namespace Tests\EasyRdf;

/*
 * EasyRdf
 *
 * LICENSE
 *
 * Copyright (c) 2021 Konrad Abicht <hi@inspirito.de>
 * Copyright (c) 2009-2020 Nicholas J Humfrey.  All rights reserved.
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
 * @copyright  Copyright (c) 2021 Konrad Abicht <hi@inspirito.de>
 * @copyright  Copyright (c) 2009-2020 Nicholas J Humfrey
 * @license    https://www.opensource.org/licenses/bsd-license.php
 */

use EasyRdf\Format;
use EasyRdf\Graph;
use EasyRdf\Http;
use EasyRdf\Literal;
use EasyRdf\ParsedUri;
use EasyRdf\RdfNamespace;
use EasyRdf\Resource;
use EasyRdf\Utils;
use Test\MockClass\Http\MockClient;
use Test\MockClass\Parser\MockRdfParser;
use Test\MockClass\Serialiser\MockRdfSerialiser;
use Test\TestCase;

class GraphTest extends TestCase
{
    /** @var \Test\EasyRdf\Http\MockClient */
    private $client;

    /** @var \EasyRdf\Graph */
    private $graph;

    /** @var string */
    private $uri;

    /**
     * Set up the test suite before each test
     */
    protected function setUp(): void
    {
        // Reset to built-in parsers
        Format::registerParser('ntriples', 'EasyRdf\Parser\Ntriples');
        Format::registerParser('rdfxml', 'EasyRdf\Parser\RdfXml');
        Format::registerParser('turtle', 'EasyRdf\Parser\Turtle');

        // Reset default namespace
        RdfNamespace::setDefault(null);

        Http::setDefaultHttpClient(
            $this->client = new MockClient()
        );
        $this->graph = new Graph('http://example.com/graph');
        $this->uri = 'http://example.com/#me';
        $this->graph->setType($this->uri, 'foaf:Person');
        $this->graph->add($this->uri, 'rdf:test', 'Test A');
        $this->graph->add($this->uri, 'rdf:test', new Literal('Test B', 'en'));
    }

    public function testGetUri()
    {
        $graph = new Graph('http://example.com/joe/foaf.rdf');
        $this->assertSame(
            'http://example.com/joe/foaf.rdf',
            $graph->getUri()
        );
    }

    public function testNewBNode()
    {
        $graph = new Graph();

        $bnodeOne = $graph->newBNode();
        $this->assertSame(
            '_:genid1',
            $bnodeOne->getUri()
        );

        $bnodeTwo = $graph->newBNode();
        $this->assertSame(
            '_:genid2',
            $bnodeTwo->getUri()
        );
    }

    public function testParseData()
    {
        $graph = new Graph();
        $data = readFixture('foaf.json');
        $count = $graph->parse($data, 'json');
        $this->assertSame(14, $count);

        $name = $graph->get('http://www.example.com/joe#me', 'foaf:name');
        $this->assertClass('EasyRdf\Literal', $name);
        $this->assertSame('Joe Bloggs', $name->getValue());
        $this->assertSame('en', $name->getLang());
        $this->assertStringEquals('', $name->getDatatype());
    }

    public function testParseDataGuess()
    {
        $graph = new Graph();
        $data = readFixture('foaf.json');
        $count = $graph->parse($data, 'guess');
        $this->assertSame(14, $count);

        $name = $graph->get('http://www.example.com/joe#me', 'foaf:name');
        $this->assertClass('EasyRdf\Literal', $name);
        $this->assertSame('Joe Bloggs', $name->getValue());
        $this->assertSame('en', $name->getLang());
        $this->assertStringEquals('', $name->getDatatype());
    }

    public function testParseFile()
    {
        $graph = new Graph();
        $count = $graph->parseFile(fixturePath('foaf.json'));
        $this->assertSame(14, $count);

        $name = $graph->get('http://www.example.com/joe#me', 'foaf:name');
        $this->assertClass('EasyRdf\Literal', $name);
        $this->assertSame('Joe Bloggs', $name->getValue());
        $this->assertSame('en', $name->getLang());
        $this->assertStringEquals('', $name->getDatatype());
    }

    public function testParseFileRelativeUri()
    {
        $graph = new Graph();
        $count = $graph->parseFile(fixturePath('foaf.rdf'));
        $this->assertSame(14, $count);

        $doc = $graph->get('foaf:PersonalProfileDocument', '^rdf:type');
        $this->assertClass('EasyRdf\Resource', $doc);

        $this->assertStringContainsString(
            'fixtures'.\DIRECTORY_SEPARATOR.'foaf.rdf',
            $doc->getUri()
        );
    }

    public function testParseUnknownFormat()
    {
        $this->expectException('EasyRdf\Exception');
        $this->expectExceptionMessage(
            'Unable to parse data of an unknown format.'
        );
        $graph = new Graph();
        $graph->parse('unknown');
    }

    public function testMockParser()
    {
        Format::registerParser('mock', MockRdfParser::class);

        $graph = new Graph();
        $graph->parse('data', 'mock');
        $this->assertStringEquals(
            'Joseph Bloggs',
            $graph->get('http://www.example.com/joe#me', 'foaf:name')
        );
    }

    public function testLoad()
    {
        $this->client->addMockOnce('GET', 'http://www.example.com/', readFixture('foaf.json'));
        $graph = new Graph();
        $count = $graph->load('http://www.example.com/', 'json');
        $this->assertSame(14, $count);
        $this->assertStringEquals(
            'Joe Bloggs',
            $graph->get('http://www.example.com/joe#me', 'foaf:name')
        );
    }

    public function testLoadNullUri()
    {
        $this->expectException('EasyRdf\Exception');
        $this->expectExceptionMessage(
            'No URI given to load() and the graph does not have a URI.'
        );
        $graph = new Graph();
        $graph->load(null);
    }

    public function testLoadEmptyUri()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            'got empty string'
        );
        $graph = new Graph();
        $graph->load('');
    }

    public function testLoadNonStringUri()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            '$resource should be either IRI, blank-node identifier or EasyRdf\Resource'
        );
        $graph = new Graph();
        $graph->load([]);
    }

    public function testLoadUnknownFormat()
    {
        $this->client->addMockOnce('GET', 'http://www.example.com/foaf.unknown', 'unknown');
        $this->expectException('EasyRdf\Exception');
        $this->expectExceptionMessage(
            'Unable to parse data of an unknown format.'
        );
        $graph = new Graph();
        $graph->load('http://www.example.com/foaf.unknown');
    }

    public function testLoadHttpError()
    {
        $this->client->addMockOnce(
            'GET',
            'http://www.example.com/404',
            'Not Found',
            ['status' => 404]
        );
        $this->expectException('EasyRdf\Exception');
        $this->expectExceptionMessage(
            'HTTP request for http://www.example.com/404 failed'
        );
        $graph = new Graph('http://www.example.com/404');
        $graph->load();
    }

    public function testLoadGraphUri()
    {
        $this->client->addMockOnce('GET', 'http://www.example.com/', readFixture('foaf.json'));
        $graph = new Graph('http://www.example.com/');
        $this->assertSame(14, $graph->load());
        $this->assertStringEquals(
            'Joe Bloggs',
            $graph->get('http://www.example.com/joe#me', 'foaf:name')
        );
    }

    public function testLoadWithContentType()
    {
        $checkRequest = function ($client) {
            $this->assertStringContainsString(',application/json,', $client->getHeader('Accept'));

            return true;
        };
        $this->client->addMockOnce(
            'GET',
            'http://www.example.com/',
            readFixture('foaf.json'),
            [
                'headers' => ['Content-Type' => 'application/json'],
                'callback' => $checkRequest,
            ]
        );
        $graph = new Graph('http://www.example.com/');
        $this->assertSame(14, $graph->load());
        $this->assertStringEquals(
            'Joe Bloggs',
            $graph->get('http://www.example.com/joe#me', 'foaf:name')
        );
    }

    public function testLoadWithFormat()
    {
        $checkRequest = function ($client) {
            $this->assertSame('text/turtle', $client->getHeader('Accept'));

            return true;
        };
        $this->client->addMock(
            'GET',
            'http://www.example.com/',
            readFixture('foaf.ttl'),
            ['callback' => $checkRequest]
        );
        $graph = new Graph();
        $this->assertSame(14, $graph->load('http://www.example.com/', 'turtle'));
    }

    public function testLoadWithMimeType()
    {
        $checkRequest = function ($client) {
            $this->assertSame('application/x-turtle', $client->getHeader('Accept'));

            return true;
        };
        $this->client->addMock(
            'GET',
            'http://www.example.com/',
            readFixture('foaf.ttl'),
            ['callback' => $checkRequest]
        );
        $graph = new Graph();
        $this->assertSame(14, $graph->load('http://www.example.com/', 'application/x-turtle'));
    }

    public function testLoadWithContentTypeAndCharset()
    {
        $this->client->addMockOnce(
            'GET',
            'http://www.example.com/',
            readFixture('foaf.nt'),
            ['headers' => ['Content-Type' => 'text/plain; charset=utf8']]
        );
        $graph = new Graph('http://www.example.com/');
        $this->assertSame(14, $graph->load());
        $this->assertStringEquals(
            'Joe Bloggs',
            $graph->get('http://www.example.com/joe#me', 'foaf:name')
        );
    }

    public function testLoadSameUrl()
    {
        // Check that loading the same URL multiple times
        // doesn't result in multiple HTTP GETs
        $this->client->addMockOnce('GET', 'http://www.example.com/', readFixture('foaf.json'));
        $graph = new Graph();
        $this->assertSame(0, $graph->countTriples());
        $this->assertSame(
            14,
            $graph->load('http://www.example.com/#foo', 'json')
        );
        $this->assertSame(14, $graph->countTriples());
        $this->assertSame(
            0,
            $graph->load('http://www.example.com/#bar', 'json')
        );
        $this->assertSame(14, $graph->countTriples());
        $this->assertStringEquals(
            'Joe Bloggs',
            $graph->get('http://www.example.com/joe#me', 'foaf:name')
        );
    }

    public function testLoadRedirect()
    {
        // Check that loading the same URL as a redirected request
        // doesn't result in multiple HTTP GETs
        $this->client->addMockRedirect('GET', 'http://www.example.org/', 'http://www.example.com/', 301);
        $this->client->addMockRedirect('GET', 'http://www.example.com/', 'http://www.example.com/foaf.rdf', 303);
        $this->client->addMockOnce('GET', 'http://www.example.com/foaf.rdf', readFixture('foaf.json'));
        $graph = new Graph();
        $this->assertSame(0, $graph->countTriples());
        $this->assertSame(
            14,
            $graph->load('http://www.example.org/', 'json')
        );
        $this->assertSame(14, $graph->countTriples());
        $this->assertSame(
            0,
            $graph->load('http://www.example.com/foaf.rdf', 'json')
        );
        $this->assertSame(14, $graph->countTriples());
        $this->assertStringEquals(
            'Joe Bloggs',
            $graph->get('http://www.example.com/joe#me', 'foaf:name')
        );
    }

    public function testNewAndLoad()
    {
        $this->client->addMockOnce('GET', 'http://www.example.com/', readFixture('foaf.json'));
        $graph = Graph::newAndLoad('http://www.example.com/', 'json');
        $this->assertClass(Graph::class, $graph);
        $this->assertStringEquals(
            'Joe Bloggs',
            $graph->get('http://www.example.com/joe#me', 'foaf:name')
        );
    }

    public function testNewAndLoadError()
    {
        $this->client->addMockOnce('GET', 'http://www.example.com/missing', 'Error text', ['status' => 404]);

        try {
            Graph::newAndLoad('http://www.example.com/missing', 'turtle');
            $this->fail('404 should lead to exception');
        } catch (Http\Exception $e) {
            $this->assertEquals(404, $e->getCode());
            $this->assertEquals('Error text', $e->getBody());
        }
    }

    public function testGetResourceSameGraph()
    {
        $graph = new Graph();
        $resource1 = $graph->resource('http://example.com/');
        $this->assertClass('EasyRdf\Resource', $resource1);
        $this->assertStringEquals('http://example.com/', $resource1->getUri());
        $resource2 = $graph->resource('http://example.com/');
        $this->assertTrue($resource1 === $resource2);
    }

    public function testGetResourceDifferentGraph()
    {
        $graph1 = new Graph();
        $resource1 = $graph1->resource('http://example.com/');
        $graph2 = new Graph();
        $resource2 = $graph2->resource('http://example.com/');
        $this->assertFalse($resource1 === $resource2);
    }

    public function testGetShortenedResource()
    {
        $graph = new Graph();
        $person = $graph->resource('foaf:Person');
        $this->assertSame(
            'http://xmlns.com/foaf/0.1/Person',
            $person->getUri()
        );
    }

    public function testGetRelativeResource()
    {
        $graph = new Graph('http://example.com/foo');
        $res = $graph->resource('#bar');
        $this->assertSame(
            'http://example.com/foo#bar',
            $res->getUri()
        );
    }

    public function testGetResourceForGraphUri()
    {
        $graph = new Graph('http://testGetResourceForGraphUri/');
        $resource = $graph->resource();
        $this->assertClass('EasyRdf\Resource', $resource);
        $this->assertSame(
            'http://testGetResourceForGraphUri/',
            $resource->getUri()
        );
    }

    public function testGetResourceUnknown()
    {
        $graph = new Graph();
        $this->assertSame(
            'http://www.foo.com/bar',
            $graph->resource('http://www.foo.com/bar')->getUri()
        );
    }

    public function testGetNullResourceForNullGraphUri()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            '$uri is null and EasyRdf\Graph object has no URI either.'
        );
        $graph = new Graph();
        $graph->resource(null);
    }

    public function testGetResourceEmptyUri()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            'got empty string'
        );
        $graph = new Graph();
        $graph->resource('');
    }

    public function testGetResourceNonStringUri()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            '$resource should be either IRI, blank-node identifier or EasyRdf\Resource'
        );
        $graph = new Graph();
        $graph->resource([]);
    }

    public function testResourceWithType()
    {
        $graph = new Graph();
        $graph->resource(
            'http://www.foo.com/bar',
            'foaf:Person'
        );
        $type = $graph->type('http://www.foo.com/bar');
        $this->assertStringEquals('foaf:Person', $type);
    }

    public function testResourceWithTypeUri()
    {
        $graph = new Graph();
        $graph->resource(
            'http://www.foo.com/bar',
            'http://xmlns.com/foaf/0.1/Person'
        );
        $type = $graph->type('http://www.foo.com/bar');
        $this->assertStringEquals('foaf:Person', $type);
    }

    public function testResourceWithMultipleTypes()
    {
        $graph = new Graph();
        $resource = $graph->resource(
            'http://www.foo.com/bar',
            ['rdf:Type1', 'rdf:Type2']
        );

        $types = $resource->types();
        $this->assertCount(2, $types);
        $this->assertStringEquals('rdf:Type1', $types[0]);
        $this->assertStringEquals('rdf:Type2', $types[1]);
    }

    public function testResources()
    {
        $data = readFixture('foaf.json');
        $graph = new Graph('http://example.com/joe/foaf', $data);
        $resources = $graph->resources();
        $this->assertTrue(\is_array($resources));
        $this->assertClass('EasyRdf\Resource', $resources['_:genid1']);

        $urls = array_keys($resources);
        sort($urls);

        $this->assertSame(
            [
                '_:genid1',
                'http://www.example.com/joe#me',
                'http://www.example.com/joe/',
                'http://www.example.com/joe/foaf.rdf',
                'http://www.example.com/project',
                'http://xmlns.com/foaf/0.1/Person',
                'http://xmlns.com/foaf/0.1/PersonalProfileDocument',
                'http://xmlns.com/foaf/0.1/Project',
            ],
            $urls
        );
    }

    public function testResourcesMatching()
    {
        $data = readFixture('foaf.json');
        $graph = new Graph('http://example.com/joe/foaf', $data);
        $matched = $graph->resourcesMatching('foaf:name');
        $this->assertCount(2, $matched);
        $this->assertSame(
            'http://www.example.com/joe#me',
            $matched[0]->getUri()
        );
        $this->assertSame(
            '_:genid1',
            $matched[1]->getUri()
        );
    }

    public function testResourcesMatchingValue()
    {
        $data = readFixture('foaf.json');
        $graph = new Graph('http://example.com/joe/foaf', $data);
        $matched = $graph->resourcesMatching('foaf:name', 'Joe Bloggs');
        $this->assertCount(1, $matched);
        $this->assertSame(
            'http://www.example.com/joe#me',
            $matched[0]->getUri()
        );
    }

    public function testResourcesMatchingObject()
    {
        $matched = $this->graph->resourcesMatching(
            'rdf:test',
            new Literal('Test B', 'en')
        );
        $this->assertCount(1, $matched);
        $this->assertStringEquals(
            'http://example.com/#me',
            $matched[0]
        );
    }

    public function testResourcesMatchingInverse()
    {
        $data = readFixture('foaf.json');
        $graph = new Graph('http://example.com/joe/foaf', $data);
        $matched = $graph->resourcesMatching('^foaf:homepage');
        $this->assertCount(2, $matched);
        $this->assertSame(
            'http://www.example.com/joe/',
            $matched[0]->getUri()
        );
        $this->assertSame(
            'http://www.example.com/project',
            $matched[1]->getUri()
        );
    }

    public function testGet()
    {
        $this->assertStringEquals(
            'Test A',
            $this->graph->get($this->uri, 'rdf:test')
        );
    }

    public function testGetWithFullUri()
    {
        $this->assertStringEquals(
            'Test A',
            $this->graph->get(
                '<http://example.com/#me>',
                '<http://www.w3.org/1999/02/22-rdf-syntax-ns#test>'
            )
        );
    }

    public function testGetResourceAngleBrackets()
    {
        $this->assertStringEquals(
            'Test A',
            $this->graph->get(
                '<'.$this->uri.'>',
                'rdf:test'
            )
        );
    }

    public function testGetWithLanguage()
    {
        $this->assertStringEquals(
            'Test B',
            $this->graph->get($this->uri, 'rdf:test', 'literal', 'en')
        );
    }

    public function testGetInverse()
    {
        $this->graph->addResource($this->uri, 'foaf:homepage', 'http://example.com/');
        $this->assertStringEquals(
            $this->uri,
            $this->graph->get('http://example.com/', '^foaf:homepage')
        );
    }

    public function testGetPropertyPath()
    {
        $this->graph->addResource($this->uri, 'foaf:homepage', 'http://example.com/');
        $this->graph->addLiteral('http://example.com/', 'dc:title', 'My Homepage');
        $this->assertStringEquals(
            'My Homepage',
            $this->graph->get($this->uri, 'foaf:homepage/dc11:title|dc:title')
        );
    }

    public function testGetPropertyPath2()
    {
        $this->graph->addResource('http://example.com/person1', 'foaf:knows', 'http://example.com/person2');
        $this->graph->addResource('http://example.com/person2', 'foaf:knows', 'http://example.com/person3');
        $this->graph->addLiteral('http://example.com/person3', 'foaf:name', 'Person 3');
        $this->assertStringEquals(
            'Person 3',
            $this->graph->get('http://example.com/person1', 'foaf:knows/foaf:knows/foaf:name')
        );
    }

    public function testGetPropertyPath3()
    {
        $this->graph->addResource('http://example.com/person1', 'foaf:knows', 'http://example.com/person2');
        $this->graph->addResource('http://example.com/person2', 'foaf:knows', 'http://example.com/person3');
        $this->graph->addResource('http://example.com/person3', 'foaf:knows', 'http://example.com/person4');
        $this->assertSame(
            $this->graph->resource('http://example.com/person4'),
            $this->graph->get('http://example.com/person1', 'foaf:knows/foaf:knows/foaf:knows')
        );
    }

    public function testGetPropertyPath4()
    {
        $this->graph->addResource('http://example.com/person1', 'foaf:homepage', 'http://example.com/');
        $this->graph->addResource('http://example.com/person1', 'foaf:knows', 'http://example.com/person2');
        $this->graph->addResource('http://example.com/person2', 'foaf:knows', 'http://example.com/person3');
        $this->graph->addLiteral('http://example.com/person3', 'foaf:name', 'Person 3');
        $this->assertStringEquals(
            'Person 3',
            $this->graph->get('http://example.com/', '^foaf:homepage/foaf:knows/foaf:knows/rdfs:label|foaf:name')
        );
    }

    public function testGetMultipleProperties()
    {
        $this->assertStringEquals(
            'Test A',
            $this->graph->get($this->uri, 'rdf:test|rdf:foobar')
        );
    }

    public function testGetMultipleProperties2()
    {
        $this->assertStringEquals(
            'Test A',
            $this->graph->get($this->uri, 'rdf:foobar|rdf:test')
        );
    }

    public function testGetPropertyWithBadLiteral()
    {
        $this->graph->addLiteral($this->uri, 'foaf:homepage', 'http://example.com/');
        $this->graph->addLiteral('http://example.com/', 'dc:title', 'My Homepage');
        $this->assertStringEquals('',
            $this->graph->get($this->uri, 'foaf:homepage/dc:title')
        );
    }

    public function testPropertyAsResource()
    {
        $rdfTest = $this->graph->resource('rdf:test');
        $this->assertStringEquals(
            'Test A',
            $this->graph->get($this->uri, $rdfTest)
        );
    }

    public function testGetLiteral()
    {
        $this->graph->addResource($this->uri, 'foaf:name', 'http://example.com/');
        $this->graph->addLiteral($this->uri, 'foaf:name', 'Joe');
        $this->assertStringEquals(
            'Joe',
            $this->graph->getLiteral($this->uri, 'foaf:name')
        );
    }

    public function testGetUriResource()
    {
        $this->graph->addLiteral($this->uri, 'foaf:homepage', 'Joe');
        $this->graph->addResource($this->uri, 'foaf:homepage', 'http://example.com/');
        $this->assertStringEquals(
            'http://example.com/',
            $this->graph->getResource($this->uri, 'foaf:homepage')
        );
    }

    public function testGetBnodeResource()
    {
        $bnode = $this->graph->newBnode('foaf:Project');
        $this->graph->addLiteral($this->uri, 'foaf:homepage', 'A Rubbish Project');
        $this->graph->addResource($this->uri, 'foaf:currentProject', $bnode);
        $this->assertSame(
            $bnode,
            $this->graph->getResource($this->uri, 'foaf:currentProject')
        );
    }

    public function testGetNonExistantLiteral()
    {
        $this->assertStringEquals('',
            $this->graph->getLiteral($this->uri, 'rdf:type')
        );
    }

    public function testGetNonExistantResource()
    {
        $this->assertStringEquals('',
            $this->graph->get('foo:bar', 'foo:bar')
        );
    }

    public function testGetNonExistantProperty()
    {
        $this->assertStringEquals('', $this->graph->get($this->uri, 'foo:bar'));
    }

    public function testGetNullResource()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            'got null'
        );
        $this->graph->get(null, 'rdf:test');
    }

    public function testGetEmptyResource()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            'got empty string'
        );
        $this->graph->get('', 'rdf:test');
    }

    public function testGetNullProperty()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            '$propertyPath should be a string, array or EasyRdf\Resource and cannot be null'
        );
        $this->graph->get($this->uri, null);
    }

    public function testGetEmptyProperty()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            '$propertyPath cannot be an empty string'
        );
        $this->graph->get($this->uri, '');
    }

    public function testGetNonStringProperty()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            '$propertyPath should be a string, array or EasyRdf\Resource and cannot be null'
        );
        $this->graph->get($this->uri, $this);
    }

    public function testAll()
    {
        $all = $this->graph->all($this->uri, 'rdf:test');
        $this->assertCount(2, $all);
        $this->assertStringEquals('Test A', $all[0]);
        $this->assertStringEquals('Test B', $all[1]);
    }

    public function testAllWithPropertyUri()
    {
        $all = $this->graph->all(
            $this->uri,
            '<http://www.w3.org/1999/02/22-rdf-syntax-ns#test>'
        );
        $this->assertCount(2, $all);
        $this->assertStringEquals('Test A', $all[0]);
        $this->assertStringEquals('Test B', $all[1]);
    }

    public function testAllWithPropertyResource()
    {
        $prop = $this->graph->resource('http://www.w3.org/1999/02/22-rdf-syntax-ns#test');
        $all = $this->graph->all($this->uri, $prop);
        $this->assertCount(2, $all);
        $this->assertStringEquals('Test A', $all[0]);
        $this->assertStringEquals('Test B', $all[1]);
    }

    public function testAllWithLang()
    {
        $all = $this->graph->all($this->uri, 'rdf:test', 'literal', 'en');
        $this->assertCount(1, $all);
        $this->assertStringEquals('Test B', $all[0]);
    }

    public function testAllInverse()
    {
        $all = $this->graph->all('foaf:Person', '^rdf:type');
        $this->assertCount(1, $all);
        $this->assertStringEquals($this->uri, $all[0]);
    }

    public function testAllPropertyPath()
    {
        $this->graph->addResource($this->uri, 'foaf:knows', 'http://example.com/bob');
        $this->graph->addLiteral('http://example.com/bob', 'foaf:name', 'Bob');
        $this->graph->addResource($this->uri, 'foaf:knows', 'http://example.com/alice');
        $this->graph->addLiteral('http://example.com/alice', 'foaf:name', 'Alice');
        $all = $this->graph->all($this->uri, 'foaf:knows/foaf:name');

        $this->assertCount(2, $all);
        $this->assertStringEquals('Bob', $all[0]);
        $this->assertStringEquals('Alice', $all[1]);
    }

    public function testAllMultipleProperties()
    {
        $this->graph->addLiteral($this->uri, 'rdf:foobar', 'Test C');
        $all = $this->graph->all($this->uri, 'rdf:test|rdf:foobar');
        $this->assertCount(3, $all);

        $strings = array_map('strval', $all);
        $this->assertSame(
            ['Test A', 'Test B', 'Test C'],
            $strings
        );
    }

    public function testAllPropertyPathIgnoreLiterals()
    {
        $this->graph->addResource('http://example.com/person', 'foaf:homepage', 'http://example.com/');
        $this->graph->addLiteral('http://example.com/person', 'foaf:homepage', 'http://literal.com/');
        $this->graph->addLiteral('http://example.com/', 'rdfs:label', 'My Homepage');
        $this->graph->addLiteral('http://literal.com/', 'rdfs:label', 'Not My Homepage');
        $this->assertEquals(
            [new Literal('My Homepage')],
            $this->graph->all('http://example.com/person', 'foaf:homepage/rdfs:label')
        );
    }

    public function testAllPropertyPathNoMatch()
    {
        $this->assertEquals(
            [],
            $this->graph->all('http://example.com/person', 'foaf:homepage/rdfs:label')
        );
    }

    public function testAllNonExistantResource()
    {
        $this->assertSame(
            [],
            $this->graph->all('foo:bar', 'foo:bar')
        );
    }

    public function testAllNonExistantProperty()
    {
        $this->assertSame(
            [],
            $this->graph->all($this->uri, 'foo:bar')
        );
    }

    public function testAllNullKey()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            '$propertyPath should be a string or EasyRdf\Resource and cannot be null'
        );
        $this->graph->all($this->uri, null);
    }

    public function testAllEmptyKey()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            '$propertyPath cannot be an empty string'
        );
        $this->graph->all($this->uri, '');
    }

    public function testAllNonStringKey()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            '$propertyPath should be a string or EasyRdf\Resource and cannot be null'
        );
        $this->graph->all($this->uri, []);
    }

    public function testAllOfType()
    {
        $data = readFixture('foaf.json');
        $graph = new Graph('http://example.com/joe/foaf', $data);
        $resources = $graph->allOfType('foaf:Person');
        $this->assertTrue(\is_array($resources));
        $this->assertCount(1, $resources);
        $this->assertSame(
            'http://www.example.com/joe#me',
            $resources[0]->getUri()
        );
    }

    public function testAllOfTypeUnknown()
    {
        $graph = new Graph();
        $resources = $graph->allOfType('unknown:type');
        $this->assertTrue(\is_array($resources));
        $this->assertCount(0, $resources);
    }

    public function testAllLiterals()
    {
        $all = $this->graph->allLiterals($this->uri, 'rdf:test');
        $this->assertCount(2, $all);
        $this->assertStringEquals('Test A', $all[0]);
        $this->assertStringEquals('Test B', $all[1]);
    }

    public function testAllLiteralsEmpty()
    {
        $all = $this->graph->allLiterals($this->uri, 'rdf:type');
        $this->assertTrue(\is_array($all));
        $this->assertCount(0, $all);
    }

    public function testAllResources()
    {
        $this->graph->addResource($this->uri, 'rdf:test', 'http://example.com/thing');
        $this->graph->addResource($this->uri, 'rdf:test', '_:bnode1');
        $all = $this->graph->allResources($this->uri, 'rdf:test');
        $this->assertCount(2, $all);
        $this->assertStringEquals('http://example.com/thing', $all[0]);
        $this->assertFalse($all[0]->isBNode());
        $this->assertStringEquals('_:bnode1', $all[1]);
        $this->assertTrue($all[1]->isBNode());
    }

    public function testCountValues()
    {
        $this->assertSame(2, $this->graph->countValues($this->uri, 'rdf:test'));
    }

    public function testCountValuesWithUri()
    {
        $this->assertSame(
            2,
            $this->graph->countValues(
                $this->uri,
                '<http://www.w3.org/1999/02/22-rdf-syntax-ns#test>'
            )
        );
    }

    public function testCountValuesWithResource()
    {
        $prop = $this->graph->resource('http://www.w3.org/1999/02/22-rdf-syntax-ns#test');
        $this->assertSame(
            2,
            $this->graph->countValues($this->uri, $prop)
        );
    }

    public function testCountValuesWithType()
    {
        $this->assertSame(0, $this->graph->countValues($this->uri, 'rdf:test', 'uri'));
        $this->assertSame(2, $this->graph->countValues($this->uri, 'rdf:test', 'literal'));
    }

    public function testCountValuesWithLang()
    {
        $this->assertSame(1, $this->graph->countValues($this->uri, 'rdf:test', 'literal', 'en'));
    }

    public function testCountValuesNonExistantProperty()
    {
        $this->assertSame(0, $this->graph->countValues($this->uri, 'foo:bar'));
    }

    public function testJoinDefaultGlue()
    {
        $this->assertSame(
            'Test A Test B',
            $this->graph->join($this->uri, 'rdf:test')
        );
    }

    public function testJoinWithUri()
    {
        $this->assertSame(
            'Test A Test B',
            $this->graph->join(
                $this->uri,
                '<http://www.w3.org/1999/02/22-rdf-syntax-ns#test>'
            )
        );
    }

    public function testJoinWithResource()
    {
        $prop = $this->graph->resource('http://www.w3.org/1999/02/22-rdf-syntax-ns#test');
        $this->assertSame(
            'Test A Test B',
            $this->graph->join($this->uri, $prop)
        );
    }

    public function testJoinWithLang()
    {
        $this->assertSame(
            'Test B',
            $this->graph->join($this->uri, 'rdf:test', ' ', 'en')
        );
    }

    public function testJoinNonExistantProperty()
    {
        $this->assertSame('', $this->graph->join($this->uri, 'foo:bar'));
    }

    public function testJoinCustomGlue()
    {
        $this->assertSame(
            'Test A:Test B',
            $this->graph->join($this->uri, 'rdf:test', ':')
        );
    }

    public function testJoinMultipleProperties()
    {
        $this->graph->addLiteral($this->uri, 'rdf:foobar', 'Test C');
        $str = $this->graph->join($this->uri, 'rdf:test|rdf:foobar', ', ');
        $this->assertSame('Test A, Test B, Test C', $str);
    }

    public function testJoinNullKey()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            '$propertyPath should be a string or EasyRdf\Resource and cannot be null'
        );
        $this->graph->join($this->uri, null, 'Test C');
    }

    public function testJoinEmptyKey()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            '$propertyPath cannot be an empty string'
        );
        $this->graph->join($this->uri, '', 'Test C');
    }

    public function testJoinNonStringKey()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            '$propertyPath should be a string or EasyRdf\Resource and cannot be null'
        );
        $this->graph->join($this->uri, [], 'Test C');
    }

    public function testAdd()
    {
        $count = $this->graph->add($this->uri, 'rdf:test', 'Test C');
        $this->assertSame(1, $count);
        $all = $this->graph->all($this->uri, 'rdf:test');
        $this->assertCount(3, $all);
        $this->assertStringEquals('Test A', $all[0]);
        $this->assertStringEquals('Test B', $all[1]);
        $this->assertStringEquals('Test C', $all[2]);
    }

    public function testAddWithUri()
    {
        $count = $this->graph->add(
            $this->uri,
            'http://www.w3.org/1999/02/22-rdf-syntax-ns#test',
            'Test C'
        );
        $this->assertSame(1, $count);
        $all = $this->graph->all($this->uri, 'rdf:test');
        $this->assertCount(3, $all);
        $this->assertStringEquals('Test A', $all[0]);
        $this->assertStringEquals('Test B', $all[1]);
        $this->assertStringEquals('Test C', $all[2]);
    }

    public function testAddLiteralWithLanguage()
    {
        $count = $this->graph->addLiteral($this->uri, 'dc:title', 'English Title', 'en');
        $this->assertSame(1, $count);
        $title = $this->graph->get($this->uri, 'dc:title');
        $this->assertSame('English Title', $title->getValue());
        $this->assertSame('en', $title->getLang());
        $this->assertStringEquals('', $title->getDataType());
    }

    public function testAddMultipleLiterals()
    {
        $count = $this->graph->addLiteral($this->uri, 'rdf:test', ['Test C', 'Test D']);
        $this->assertSame(2, $count);
        $all = $this->graph->all($this->uri, 'rdf:test');
        $this->assertCount(4, $all);
        $this->assertStringEquals('Test A', $all[0]);
        $this->assertStringEquals('Test B', $all[1]);
        $this->assertStringEquals('Test C', $all[2]);
        $this->assertStringEquals('Test D', $all[3]);
    }

    public function testAddLiteralMultipleTimes()
    {
        $count = $this->graph->add($this->uri, 'rdf:test2', 'foobar');
        $this->assertSame(1, $count);
        $count = $this->graph->add($this->uri, 'rdf:test2', 'foobar');
        $this->assertSame(0, $count);
        $all = $this->graph->all($this->uri, 'rdf:test2');
        $this->assertCount(1, $all);
        $this->assertStringEquals('foobar', $all[0]);
    }

    public function testAddLiteralDifferentLanguages()
    {
        $count = $this->graph->set($this->uri, 'rdf:test', new Literal('foobar', 'en'));
        $this->assertSame(1, $count);
        $count = $this->graph->add($this->uri, 'rdf:test', new Literal('foobar', 'fr'));
        $this->assertSame(1, $count);
        $all = $this->graph->all($this->uri, 'rdf:test');
        $this->assertCount(2, $all);
        $this->assertStringEquals('foobar', $all[0]);
        $this->assertStringEquals('foobar', $all[1]);
    }

    public function testAddDateTime()
    {
        $dt = new \DateTime('Fri 25 Jan 2013 19:43:19 GMT');
        $count = $this->graph->add($this->uri, 'rdf:test2', $dt);
        $this->assertSame(1, $count);

        $literal = $this->graph->get($this->uri, 'rdf:test2');
        $this->assertClass('EasyRdf\Literal\DateTime', $literal);
        $this->assertStringEquals('2013-01-25T19:43:19Z', $literal);
        $this->assertStringEquals('', $literal->getLang());
        $this->assertSame('xsd:dateTime', $literal->getDataType());
    }

    public function testAddLiteralDateTime()
    {
        $dt = new \DateTime('Fri 25 Jan 2013 19:43:19 GMT');
        $this->graph->addLiteral($this->uri, 'rdf:test2', $dt);
        $this->assertStringEquals(
            '2013-01-25T19:43:19Z',
            $this->graph->get($this->uri, 'rdf:test2')
        );
    }

    public function testAddNull()
    {
        $count = $this->graph->add($this->uri, 'rdf:test', null);
        $this->assertSame(0, $count);
        $all = $this->graph->all($this->uri, 'rdf:test');
        $this->assertCount(2, $all);
        $this->assertStringEquals('Test A', $all[0]);
        $this->assertStringEquals('Test B', $all[1]);
    }

    public function testAddNullKey()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            '$property should be a string or EasyRdf\Resource and cannot be null'
        );
        $this->graph->add($this->uri, null, 'Test C');
    }

    public function testAddEmptyKey()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            '$property cannot be an empty string'
        );
        $this->graph->add($this->uri, '', 'Test C');
    }

    public function testAddNonStringKey()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            '$property should be a string or EasyRdf\Resource and cannot be null'
        );
        $this->graph->add($this->uri, [], 'Test C');
    }

    public function testAddInvalidObject()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage(
            'Object of class Tests\EasyRdf\GraphTest could not be converted to string'
        );
        $this->graph->add($this->uri, 'rdf:foo', $this);
    }

    public function testAddMissingArrayType()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            '$value is missing a \'type\' key'
        );
        $this->graph->add($this->uri, 'rdf:foo', ['value' => 'bar']);
    }

    public function testAddMissingArrayValue()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            '$value is missing a \'value\' key'
        );
        $this->graph->add($this->uri, 'rdf:foo', ['type' => 'literal']);
    }

    public function testAddInvalidArrayType()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            '$value does not have a valid type (foo)'
        );
        $this->graph->add($this->uri, 'rdf:foo', ['type' => 'foo', 'value' => 'bar']);
    }

    public function testAddArrayWithLangAndDatatype()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            '$value cannot have both and language and a datatype'
        );
        $this->graph->add(
            $this->uri,
            'rdf:foo',
            [
                'type' => 'literal',
                'value' => 'Rat',
                'lang' => 'en',
                'datatype' => 'http://www.w3.org/2001/XMLSchema#string',
            ]
        );
    }

    public function testAddSingleValueToString()
    {
        $graph = new Graph();
        $count = $graph->add('http://www.example.com/joe#me', 'foaf:name', 'Joe');
        $this->assertSame(1, $count);
        $this->assertStringEquals('Joe', $graph->get('http://www.example.com/joe#me', 'foaf:name'));
    }

    public function testAddSingleValueToResource()
    {
        $graph = new Graph();
        $count = $graph->add('http://www.example.com/joe#me', 'foaf:name', 'Joe');
        $this->assertSame(1, $count);
        $this->assertStringEquals('Joe', $graph->get('http://www.example.com/joe#me', 'foaf:name'));
    }

    public function testAddPropertiesInvalidResourceClass()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            '$resource should be either IRI, blank-node identifier or EasyRdf\Resource'
        );
        $graph = new Graph();
        $invalidResource = new Utils();
        $graph->add($invalidResource, 'foaf:name', 'value');
    }

    /**
     * @see https://github.com/easyrdf/easyrdf/issues/239
     */
    public function testDeleteAndGetProperty()
    {
        $graph = new Graph();
        $graph->add('http://www.example.com/joe#me', 'rdf:test', 'Test A');
        $graph->add('http://www.example.com/joe#me', 'rdf:test', 'Test B');
        $graph->add('http://www.example.com/joe#me', 'rdf:test', 'Test C');
        $graph->add('http://www.example.com/joe#me', 'rdf:test', 'Test D');

        $graph->delete('http://www.example.com/joe#me', 'rdf:test', 'Test A');
        $result = $graph->get('http://www.example.com/joe#me', 'rdf:test');
        $this->assertStringEquals('Test B', $result);

        $graph->delete('http://www.example.com/joe#me', 'rdf:test', 'Test B');
        $result = $graph->get('http://www.example.com/joe#me', 'rdf:test');
        $this->assertStringEquals('Test C', $result);

        $graph->delete('http://www.example.com/joe#me', 'rdf:test');
        $result = $graph->get('http://www.example.com/joe#me', 'rdf:test');
        $this->assertEmpty($result);
    }

    public function testAddZero()
    {
        $this->assertStringEquals('', $this->graph->get($this->uri, 'rdf:test2'));
        $count = $this->graph->add($this->uri, 'rdf:test2', 0);
        $this->assertSame(1, $count);
        $this->assertStringEquals('0', $this->graph->get($this->uri, 'rdf:test2'));
    }

    public function testAddLiteralZero()
    {
        $this->assertStringEquals('', $this->graph->get($this->uri, 'rdf:test2'));
        $count = $this->graph->addLiteral($this->uri, 'rdf:test2', 0);
        $this->assertSame(1, $count);
        $this->assertStringEquals('0', $this->graph->get($this->uri, 'rdf:test2'));
    }

    public function testAddResource()
    {
        $count = $this->graph->addResource($this->uri, 'foaf:homepage', 'http://www.example.com/');
        $this->assertSame(1, $count);
        $res = $this->graph->get($this->uri, 'foaf:homepage');
        $this->assertClass('EasyRdf\Resource', $res);
        $this->assertStringEquals('http://www.example.com/', $res);
    }

    public function testAddBnodeResource()
    {
        $count = $this->graph->addResource($this->uri, 'foaf:interest', '_:abc');
        $this->assertSame(1, $count);
        $res = $this->graph->get($this->uri, 'foaf:interest');
        $this->assertClass('EasyRdf\Resource', $res);
        $this->assertTrue($res->isBNode());
        $this->assertStringEquals('_:abc', $res);
    }

    public function testAddDulicateTriple()
    {
        $homepage = $this->graph->resource('http://example.com/');
        $count = $this->graph->add($this->uri, 'foaf:homepage', $homepage);
        $this->assertSame(1, $count);
        $count = $this->graph->addResource($this->uri, 'foaf:homepage', $homepage);
        $this->assertSame(0, $count);
        $count = $this->graph->addResource($this->uri, 'foaf:homepage', $homepage);
        $this->assertSame(0, $count);
        $all = $this->graph->all($this->uri, 'foaf:homepage');
        $this->assertCount(1, $all);
        $this->assertStringEquals($homepage, $all[0]);

        // Check inverse too
        $all = $this->graph->all($homepage, '^foaf:homepage');
        $this->assertCount(1, $all);
        $this->assertStringEquals('http://example.com/#me', $all[0]);
    }

    public function testSet()
    {
        $count = $this->graph->set($this->uri, 'rdf:foobar', 'baz');
        $this->assertSame(1, $count);
        $all = $this->graph->all($this->uri, 'rdf:foobar');
        $this->assertCount(1, $all);
        $this->assertStringEquals('baz', $all[0]);
    }

    public function testSetReplaces()
    {
        $count = $this->graph->add($this->uri, 'rdf:test', 'Test D');
        $this->assertSame(1, $count);
        $count = $this->graph->set($this->uri, 'rdf:test', 'Test E');
        $this->assertSame(1, $count);
        $all = $this->graph->all($this->uri, 'rdf:test');
        $this->assertCount(1, $all);
        $this->assertStringEquals('Test E', $all[0]);
    }

    public function testDelete()
    {
        $this->assertStringEquals('Test A', $this->graph->get($this->uri, 'rdf:test'));
        $this->assertSame(2, $this->graph->delete($this->uri, 'rdf:test'));
        $this->assertSame([], $this->graph->all($this->uri, 'rdf:test'));
    }

    public function testDeleteWithPropertyUri()
    {
        $this->assertStringEquals('Test A', $this->graph->get($this->uri, 'rdf:test'));
        $this->assertSame(2, $this->graph->delete($this->uri, '<http://www.w3.org/1999/02/22-rdf-syntax-ns#test>'));
        $this->assertSame([], $this->graph->all($this->uri, 'rdf:test'));
    }

    public function testDeleteWithPropertyResource()
    {
        $prop = $this->graph->resource('http://www.w3.org/1999/02/22-rdf-syntax-ns#test');
        $this->assertStringEquals('Test A', $this->graph->get($this->uri, 'rdf:test'));
        $this->assertSame(2, $this->graph->delete($this->uri, $prop));
        $this->assertSame([], $this->graph->all($this->uri, 'rdf:test'));
    }

    public function testDeleteWithUri()
    {
        $this->assertStringEquals('Test A', $this->graph->get($this->uri, 'rdf:test'));
        $this->assertSame(
            2,
            $this->graph->delete(
                $this->uri,
                'http://www.w3.org/1999/02/22-rdf-syntax-ns#test'
            )
        );
        $this->assertSame([], $this->graph->all($this->uri, 'rdf:test'));
    }

    public function testDeleteNonExistantProperty()
    {
        $this->assertSame(0, $this->graph->delete($this->uri, 'foo:bar'));
    }

    public function testDeleteNullKey()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            '$property should be a string or EasyRdf\Resource and cannot be null'
        );
        $this->graph->delete($this->uri, null);
    }

    public function testDeleteEmptyKey()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            '$property cannot be an empty string'
        );
        $this->graph->delete($this->uri, '');
    }

    public function testDeleteNonStringKey()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            '$property should be a string or EasyRdf\Resource and cannot be null'
        );
        $this->graph->delete($this->uri, []);
    }

    public function testDeletePropertyResource()
    {
        $this->graph->addResource($this->uri, 'foaf:homepage', 'http://example.com/');
        $this->graph->addResource($this->uri, 'foaf:homepage', 'http://example.com/');
        $this->assertTrue($this->graph->hasProperty($this->uri, 'foaf:homepage'));
        $this->assertTrue($this->graph->hasProperty('http://example.com/', '^foaf:homepage'));
        $this->assertSame(1, $this->graph->delete($this->uri, 'foaf:homepage'));
        $this->assertFalse($this->graph->hasProperty($this->uri, 'foaf:homepage'));
        $this->assertFalse($this->graph->hasProperty('http://example.com/', '^foaf:homepage'));
    }

    public function testDeleteLiteralValue()
    {
        $this->assertSame(2, $this->graph->countValues($this->uri, 'rdf:test'));
        $this->assertSame(1, $this->graph->delete($this->uri, 'rdf:test', 'Test A'));
        $this->assertSame(1, $this->graph->countValues($this->uri, 'rdf:test'));
        $this->assertSame(
            1,
            $this->graph->delete(
                $this->uri,
                'rdf:test',
                new Literal('Test B', 'en')
            )
        );
        $this->assertSame(0, $this->graph->countValues($this->uri, 'rdf:test'));
    }

    public function testDeleteResourceValue()
    {
        $res = $this->graph->resource('http://www.example.com/');
        $this->graph->add($this->uri, 'foaf:homepage', $res);
        $this->assertSame(1, $this->graph->countValues($this->uri, 'foaf:homepage'));
        $this->assertSame(1, $this->graph->delete($this->uri, 'foaf:homepage', $res));
        $this->assertSame(0, $this->graph->countValues($this->uri, 'foaf:homepage'));
    }

    public function testDeleteLiteralArrayValue()
    {
        // Keys are deliberately in the wrong order
        $testa = ['value' => 'Test A', 'type' => 'literal'];
        $this->assertSame(2, $this->graph->countValues($this->uri, 'rdf:test'));
        $this->assertSame(1, $this->graph->delete($this->uri, 'rdf:test', $testa));
        $this->assertSame(1, $this->graph->countValues($this->uri, 'rdf:test'));
    }

    public function testDeleteResourceArrayValue()
    {
        // Keys are deliberately in the wrong order
        $res = ['value' => 'http://www.example.com/', 'type' => 'uri'];
        $this->graph->addResource($this->uri, 'foaf:homepage', 'http://www.example.com/');
        $this->assertSame(1, $this->graph->countValues($this->uri, 'foaf:homepage'));
        $this->assertSame(1, $this->graph->delete($this->uri, 'foaf:homepage', $res));
        $this->assertSame(0, $this->graph->countValues($this->uri, 'foaf:homepage'));
    }

    public function testDeleteResource()
    {
        $res = $this->graph->resource('http://www.example.com/');
        $this->graph->addResource($this->uri, 'foaf:homepage', $res);
        $this->assertSame(1, $this->graph->countValues($this->uri, 'foaf:homepage'));
        $this->assertSame(1, $this->graph->deleteResource($this->uri, 'foaf:homepage', $res));
        $this->assertSame(0, $this->graph->countValues($this->uri, 'foaf:homepage'));
    }

    public function testDeleteResourceString()
    {
        $res = 'http://www.example.com/';
        $this->graph->addResource($this->uri, 'foaf:homepage', $res);
        $this->assertSame(1, $this->graph->countValues($this->uri, 'foaf:homepage'));
        $this->assertSame(1, $this->graph->deleteResource($this->uri, 'foaf:homepage', $res));
        $this->assertSame(0, $this->graph->countValues($this->uri, 'foaf:homepage'));
    }

    public function testDeleteLiteral()
    {
        $this->assertSame(2, $this->graph->countValues($this->uri, 'rdf:test'));
        $this->assertSame(1, $this->graph->deleteLiteral($this->uri, 'rdf:test', 'Test A'));
        $this->assertSame(1, $this->graph->countValues($this->uri, 'rdf:test'));
    }

    public function testDeleteLiteralWithLang()
    {
        $this->assertSame(2, $this->graph->countValues($this->uri, 'rdf:test'));
        $this->assertSame(1, $this->graph->deleteLiteral($this->uri, 'rdf:test', 'Test B', 'en'));
        $this->assertSame(1, $this->graph->countValues($this->uri, 'rdf:test'));
    }

    public function testGetType()
    {
        $data = readFixture('foaf.json');
        $graph = new Graph('http://www.example.com/joe/foaf.rdf', $data, 'json');
        $this->assertStringEquals(
            'foaf:PersonalProfileDocument',
            $graph->type()
        );
    }

    public function testTypeUnknown()
    {
        $graph = new Graph();
        $this->assertStringEquals('', $graph->type());
    }

    public function testPrimaryTopic()
    {
        $data = readFixture('foaf.json');
        $graph = new Graph(
            'http://www.example.com/joe/foaf.rdf',
            $data,
            'json'
        );
        $this->assertStringEquals(
            'http://www.example.com/joe#me',
            $graph->primaryTopic()
        );
    }

    public function testPrimaryTopicUnknown()
    {
        $graph = new Graph();
        $this->assertStringEquals('', $graph->primaryTopic());
    }

    public function testSerialise()
    {
        Format::registerSerialiser('mock', MockRdfSerialiser::class);
        $graph = new Graph();
        $this->assertSame('<rdf></rdf>', $graph->serialise('mock'));
    }

    public function testSerialiseByMime()
    {
        Format::registerSerialiser('mock', MockRdfSerialiser::class);
        Format::register('mock', 'Mock', null, ['mock/mime' => 1.0]);
        $graph = new Graph();
        $this->assertSame(
            '<rdf></rdf>',
            $graph->serialise('mock/mime')
        );
    }

    public function testSerialiseByFormatObject()
    {
        $format = Format::register('mock', 'Mock Format');
        $format->setSerialiserClass(MockRdfSerialiser::class);
        $graph = new Graph();
        $this->assertSame('<rdf></rdf>', $graph->serialise($format));
    }

    public function testIsEmpty()
    {
        $graph = new Graph();
        $this->assertTrue($graph->isEmpty());
    }

    public function testIsNotEmpty()
    {
        $graph = new Graph();
        $graph->add('http://example.com/', 'rdfs:label', 'Example');
        $this->assertFalse($graph->isEmpty());
    }

    public function testIsEmptyAfterDelete()
    {
        $graph = new Graph();
        $graph->add('http://example.com/', 'rdfs:label', 'Example');
        $graph->delete('http://example.com/', 'rdfs:label');
        $this->assertTrue($graph->isEmpty());
    }

    public function testProperties()
    {
        $this->assertSame(
            ['rdf:type', 'rdf:test'],
            $this->graph->properties($this->uri)
        );
    }

    public function testPropertiesForNonExistantResource()
    {
        $this->assertSame(
            [],
            $this->graph->properties('http://doesnotexist.com/')
        );
    }

    public function testPropertyUris()
    {
        $this->assertSame(
            [
                'http://www.w3.org/1999/02/22-rdf-syntax-ns#type',
                'http://www.w3.org/1999/02/22-rdf-syntax-ns#test',
            ],
            $this->graph->propertyUris($this->uri)
        );
    }

    public function testNoReversePropertyUris()
    {
        $this->assertSame(
            [],
            $this->graph->reversePropertyUris('foaf:Document')
        );
    }

    public function testReversePropertyUris()
    {
        $this->assertSame(
            [
                'http://www.w3.org/1999/02/22-rdf-syntax-ns#type',
            ],
            $this->graph->reversePropertyUris('foaf:Person')
        );
    }

    public function testPropertyUrisForNonExistantResource()
    {
        $this->assertSame(
            [],
            $this->graph->propertyUris('http://doesnotexist.com/')
        );
    }

    public function testHasProperty()
    {
        $this->assertTrue(
            $this->graph->hasProperty($this->uri, 'rdf:type')
        );
    }

    public function testHasPropertyWithLiteralValue()
    {
        $this->assertTrue(
            $this->graph->hasProperty($this->uri, 'rdf:test', 'Test A')
        );
    }

    public function testHasPropertyWithLangValue()
    {
        $literal = new Literal('Test B', 'en');
        $this->assertTrue(
            $this->graph->hasProperty($this->uri, 'rdf:test', $literal)
        );
    }

    public function testHasPropertyWithResourceValue()
    {
        $person = $this->graph->resource('foaf:Person');
        $this->assertTrue(
            $this->graph->hasProperty($this->uri, 'rdf:type', $person)
        );
    }

    public function testHasResourceProperty()
    {
        $property = new Resource('http://www.w3.org/1999/02/22-rdf-syntax-ns#type');
        $this->assertTrue(
            $this->graph->hasProperty($this->uri, $property)
        );
    }

    public function testHasParsedUriProperty()
    {
        $property = new ParsedUri('http://www.w3.org/1999/02/22-rdf-syntax-ns#type');
        $this->assertTrue(
            $this->graph->hasProperty($this->uri, $property)
        );
    }

    public function testHasInverseProperty()
    {
        $this->assertTrue(
            $this->graph->hasProperty('foaf:Person', '^rdf:type')
        );
    }

    public function testHasInversePropertyWithValue()
    {
        $resource = $this->graph->resource($this->uri);
        $this->assertTrue(
            $this->graph->hasProperty('foaf:Person', '^rdf:type', $resource)
        );
    }

    public function testDoesntHaveProperty()
    {
        $this->assertFalse(
            $this->graph->hasProperty($this->uri, 'rdf:doesntexist')
        );
    }

    public function testDoesntHavePropertyWithLiteralValue()
    {
        $this->assertFalse(
            $this->graph->hasProperty($this->uri, 'rdf:test', 'Test Z')
        );
    }

    public function testDoesntHavePropertyWithLangValue()
    {
        $literal = new Literal('Test A', 'fr');
        $this->assertFalse(
            $this->graph->hasProperty($this->uri, 'rdf:test', $literal)
        );
    }

    public function testDoesntHaveInverseProperty()
    {
        $this->assertFalse(
            $this->graph->hasProperty($this->uri, '^rdf:doesntexist')
        );
    }

    public function testDoesntHasBnodeProperty()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            '$property cannot be a blank node'
        );
        $this->graph->hasProperty($this->uri, '_:foo');
    }

    public function testDumpText()
    {
        $text = $this->graph->dump('text');
        $this->assertStringContainsString('Graph: http://example.com/graph', $text);
        $this->assertStringContainsString('http://example.com/#me (EasyRdf\Resource)', $text);
        $this->assertStringContainsString('  -> rdf:type -> foaf:Person', $text);
        $this->assertStringContainsString('  -> rdf:test -> "Test A"', $text);
    }

    public function testDumpEmptyGraph()
    {
        $graph = new Graph('http://example.com/graph2');
        $this->assertSame("Graph: http://example.com/graph2\n", $graph->dump('text'));
        $this->assertStringContainsString('>Graph: http://example.com/graph2</div>', $graph->dump('html'));
    }

    public function testDumpHtml()
    {
        $html = $this->graph->dump('html');
        $this->assertStringContainsString('Graph: http://example.com/graph', $html);
        $this->assertStringContainsString('http://example.com/#me', $html);
        $this->assertStringContainsString('>rdf:test</span>', $html);
        $this->assertStringContainsString('>&quot;Test A&quot;</span>', $html);
    }

    public function testDumpLiterals()
    {
        $graph = new Graph();
        $graph->add('http://example.com/joe#me', 'foaf:name', 'Joe');
        $graph->add('http://example.com/joe#me', 'foaf:age', Literal::create(52));
        $deutschland = new Literal('Deutschland', 'de');
        $graph->add('http://example.com/joe#me', 'foaf:birthPlace', $deutschland);
        $graph->add('http://example.com/joe#me', '<http://v.example.com/foo>', 'bar');

        $text = $graph->dump('text');
        $this->assertStringContainsString('http://example.com/joe#me', $text);
        $this->assertStringContainsString('-> foaf:name -> "Joe"', $text);
        $this->assertStringContainsString('-> foaf:age -> "52"^^xsd:integer', $text);
        $this->assertStringContainsString('-> foaf:birthPlace -> "Deutschland"@de', $text);
        $this->assertStringContainsString('-> <http://v.example.com/foo> -> "bar"', $text);

        $html = $graph->dump('html');
        $this->assertStringContainsString('http://example.com/joe#me', $html);
        $this->assertStringContainsString('>foaf:name</span>', $html);
        $this->assertStringContainsString('>&quot;Joe&quot;</span>', $html);
        $this->assertStringContainsString('>foaf:age</span>', $html);
        $this->assertStringContainsString('>&quot;52&quot;^^xsd:integer</span>', $html);
        $this->assertStringContainsString('>foaf:birthPlace</span>', $html);
        $this->assertStringContainsString('>&quot;Deutschland&quot;@de</span>', $html);
        $this->assertStringContainsString('>&lt;http://v.example.com/foo&gt;</span>', $html);
        $this->assertStringContainsString('>&quot;bar&quot;</span>', $html);
    }

    public function testDumpResource()
    {
        $graph = new Graph();
        $graph->addResource('http://example.com/joe#me', 'rdf:type', 'foaf:Person');
        $graph->addResource('http://example.com/joe#me', 'foaf:homepage', 'http://example.com/');
        $graph->add('http://example.com/joe#me', 'foaf:knows', $graph->newBnode());

        $text = $graph->dumpResource('http://example.com/joe#me', 'text');
        $this->assertStringContainsString('http://example.com/joe#me', $text);
        $this->assertStringContainsString('-> rdf:type -> foaf:Person', $text);
        $this->assertStringContainsString('-> foaf:homepage -> http://example.com/', $text);
        $this->assertStringContainsString('-> foaf:knows -> _:genid1', $text);

        $html = $graph->dumpResource('http://example.com/joe#me', 'html');
        $this->assertStringContainsString('http://example.com/joe#me', $html);
        $this->assertStringContainsString('>rdf:type</span>', $html);
        $this->assertStringContainsString('>foaf:Person</a>', $html);
        $this->assertStringContainsString('>foaf:homepage</span>', $html);
        $this->assertStringContainsString('>http://example.com/</a>', $html);
        $this->assertStringContainsString('>foaf:knows</span>', $html);
        $this->assertStringContainsString('>_:genid1</a>', $html);
    }

    public function testDumpResourceWithNoProperties()
    {
        $graph = new Graph();
        $this->assertSame('', $graph->dumpResource('http://example.com/empty', 'text'));
        $this->assertSame('', $graph->dumpResource('http://example.com/empty', 'html'));
    }

    public function testDumpResourceInjectJavascript()
    {
        $graph = new Graph();
        $graph->addType("wikibase_id:Q' onload='alert(1234)", 'foaf:Person');
        $this->assertStringContainsString(
            "<div id='wikibase_id:Q&#039; onload=&#039;alert(1234)' ".
            "style='font-family:arial; padding:0.5em; background-color:lightgrey;border:dashed 1px grey;'>",
            $graph->dumpResource("wikibase_id:Q' onload='alert(1234)")
        );
    }

    public function testTypes()
    {
        $types = $this->graph->types($this->uri);
        $this->assertCount(1, $types);
        $this->assertStringEquals('foaf:Person', $types[0]);
    }

    public function testTypesNotLiteral()
    {
        $this->graph->addResource($this->uri, 'rdf:type', 'foaf:Rat');
        $this->graph->addLiteral($this->uri, 'rdf:type', 'Literal');
        $types = $this->graph->types($this->uri);
        $this->assertCount(2, $types);
        $this->assertStringEquals('foaf:Person', $types[0]);
        $this->assertStringEquals('foaf:Rat', $types[1]);
    }

    public function testType()
    {
        $this->assertStringEquals('foaf:Person', $this->graph->type($this->uri));
    }

    public function testTypeForResourceWithNoType()
    {
        $resource = $this->graph->resource('http://example.com/notype');
        $this->assertStringEquals('', $resource->type());
    }

    public function testTypeForUnamedGraph()
    {
        $graph = new Graph();
        $this->assertStringEquals('', $graph->type());
    }

    public function testTypeAsResource()
    {
        $type = $this->graph->typeAsResource($this->uri);
        $this->assertClass('EasyRdf\Resource', $type);
        $this->assertStringEquals('http://xmlns.com/foaf/0.1/Person', $type);
    }

    public function testTypeAsResourceForUnamedGraph()
    {
        $graph = new Graph();
        $this->assertStringEquals('', $graph->typeAsResource());
    }

    public function testIsAQNameNotation()
    {
        $this->assertTrue($this->graph->isA($this->uri, 'foaf:Person'));
    }

    public function testIsAUriNotation()
    {
        $this->assertTrue(
            $this->graph->isA($this->uri, 'http://xmlns.com/foaf/0.1/Person')
        );
    }

    public function testIsAResourceNotation()
    {
        $this->assertTrue(
            $this->graph->isA($this->uri, '<http://xmlns.com/foaf/0.1/Person>')
        );
    }

    public function testIsntA()
    {
        $this->assertFalse($this->graph->isA($this->uri, 'foaf:Rat'));
    }

    public function testAddType()
    {
        $count = $this->graph->addType($this->uri, 'rdf:newType');
        $this->assertSame(1, $count);
        $this->assertTrue(
            $this->graph->isA($this->uri, 'rdf:newType')
        );
    }

    public function testSetType()
    {
        $count = $this->graph->setType($this->uri, 'foaf:Rat');
        $this->assertSame(1, $count);
        $this->assertTrue(
            $this->graph->isA($this->uri, 'foaf:Rat')
        );
        $this->assertFalse(
            $this->graph->isA($this->uri, 'http://xmlns.com/foaf/0.1/Person')
        );
    }

    public function testLabelForUnnamedGraph()
    {
        $graph = new Graph();
        $this->assertStringEquals('', $graph->label());
    }

    public function testLabelWithSkosPrefLabel()
    {
        $this->graph->addLiteral($this->uri, 'skos:prefLabel', 'Preferred Label');
        $this->graph->addLiteral($this->uri, 'rdfs:label', 'Label Text');
        $this->graph->addLiteral($this->uri, 'foaf:name', 'Foaf Name');
        $this->graph->addLiteral($this->uri, 'dc:title', 'Dc Title');
        $this->assertStringEquals('Preferred Label', $this->graph->label($this->uri));
    }

    public function testLabelWithRdfsLabel()
    {
        $this->graph->addLiteral($this->uri, 'rdfs:label', 'Label Text');
        $this->graph->addLiteral($this->uri, 'foaf:name', 'Foaf Name');
        $this->graph->addLiteral($this->uri, 'dc:title', 'Dc Title');
        $this->assertStringEquals('Label Text', $this->graph->label($this->uri));
    }

    public function testLabelWithFoafName()
    {
        $this->graph->addLiteral($this->uri, 'foaf:name', 'Foaf Name');
        $this->graph->addLiteral($this->uri, 'dc:title', 'Dc Title');
        $this->assertStringEquals('Foaf Name', $this->graph->label($this->uri));
    }

    public function testLabelWithDc11Title()
    {
        $this->graph->addLiteral($this->uri, 'dc11:title', 'Dc11 Title');
        $this->assertStringEquals('Dc11 Title', $this->graph->label($this->uri));
    }

    public function testLabelNoRdfsLabel()
    {
        $this->assertStringEquals('', $this->graph->label($this->uri));
    }

    public function testCountTriples()
    {
        $this->assertSame(3, $this->graph->countTriples());
        $this->graph->add($this->uri, 'foaf:nick', 'Nick');
        $this->assertSame(4, $this->graph->countTriples());
    }

    public function testToRdfPhp()
    {
        $this->assertSame(
            [
                'http://example.com/#me' => [
                    'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' => [
                        [
                            'type' => 'uri',
                            'value' => 'http://xmlns.com/foaf/0.1/Person',
                        ],
                    ],
                    'http://www.w3.org/1999/02/22-rdf-syntax-ns#test' => [
                        [
                            'type' => 'literal',
                            'value' => 'Test A',
                        ],
                        [
                            'type' => 'literal',
                            'value' => 'Test B',
                            'lang' => 'en',
                        ],
                    ],
                ],
            ],
            $this->graph->toRdfPhp()
        );
    }

    public function testToString()
    {
        $graph = new Graph('http://example.com/joe/foaf.rdf');
        $this->assertStringEquals('http://example.com/joe/foaf.rdf', $graph);
    }

    public function testMagicGet()
    {
        RdfNamespace::setDefault('rdf');
        $this->graph->add($this->graph->getUri(), 'rdf:test', 'testMagicGet');
        $this->assertStringEquals(
            'testMagicGet',
            $this->graph->test
        );
    }

    public function testMagicGetNonExistant()
    {
        RdfNamespace::setDefault('rdf');
        $this->assertStringEquals('',
            $this->graph->test
        );
    }

    public function testMagicSet()
    {
        RdfNamespace::setDefault('rdf');
        $this->graph->test = 'testMagicSet';
        $this->assertStringEquals(
            'testMagicSet',
            $this->graph->get($this->graph->getUri(), 'rdf:test')
        );
    }

    public function testMagicIsSet()
    {
        RdfNamespace::setDefault('rdf');
        $this->assertFalse(isset($this->graph->test));
        $this->graph->add($this->graph->getUri(), 'rdf:test', 'testMagicIsSet');
        $this->assertTrue(isset($this->graph->test));
    }

    public function testMagicUnset()
    {
        RdfNamespace::setDefault('rdf');
        $this->graph->add($this->graph->getUri(), 'rdf:test', 'testMagicUnset');
        unset($this->graph->test);
        $this->assertStringEquals(
            null,
            $this->graph->get($this->graph->getUri(), 'rdf:test')
        );
    }
}
