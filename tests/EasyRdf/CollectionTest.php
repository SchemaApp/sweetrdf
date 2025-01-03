<?php

namespace Tests\EasyRdf;

use EasyRdf\Graph;
use EasyRdf\RdfNamespace;
use Test\TestCase;

/**
 * EasyRdf
 *
 * LICENSE
 *
 * Copyright (c) 2013-2014 Nicholas J Humfrey.  All rights reserved.
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
 * @copyright  Copyright (c) 2021 Konrad Abicht <hi@inspirito.de>
 * @copyright  Copyright (c) 2013-2014 Nicholas J Humfrey
 * @license    https://www.opensource.org/licenses/bsd-license.php
 */
class CollectionTest extends TestCase
{
    /** @var \EasyRdf\Graph */
    private $graph;

    protected function setUp(): void
    {
        $this->graph = new Graph();
        RdfNamespace::set('ex', 'http://example.org/');
    }

    protected function tearDown(): void
    {
        RdfNamespace::delete('ex');
    }

    public function testParseCollection()
    {
        $this->graph->parse(readFixture('rdf-collection.rdf'), 'rdfxml');

        $owner = $this->graph->resource('ex:owner');
        $pets = $owner->get('ex:pets');
        $this->assertClass('EasyRdf\Collection', $pets);

        $this->assertTrue($pets->valid());
        $this->assertSame(1, $pets->key());
        $this->assertStringEquals('http://example.org/rat', $pets->current());

        $pets->next();

        $this->assertTrue($pets->valid());
        $this->assertSame(2, $pets->key());
        $this->assertStringEquals('http://example.org/cat', $pets->current());

        $pets->next();

        $this->assertTrue($pets->valid());
        $this->assertSame(3, $pets->key());
        $this->assertStringEquals('http://example.org/goat', $pets->current());

        $pets->next();

        $this->assertFalse($pets->valid());
        $this->assertSame(4, $pets->key());
        $this->assertNull($pets->current());

        $pets->next();

        $this->assertFalse($pets->valid());
        $this->assertSame(5, $pets->key());
        $this->assertNull($pets->current());

        $pets->rewind();

        $this->assertTrue($pets->valid());
        $this->assertSame(1, $pets->key());
        $this->assertStringEquals('http://example.org/rat', $pets->current());
    }

    public function testForeach()
    {
        $this->graph->parse(readFixture('rdf-collection.rdf'), 'rdfxml');

        $owner = $this->graph->resource('ex:owner');
        $pets = $owner->get('ex:pets');

        $list = [];
        foreach ($pets as $pet) {
            $list[] = $pet->getUri();
        }

        $this->assertEquals(
            [
                'http://example.org/rat',
                'http://example.org/cat',
                'http://example.org/goat',
            ],
            $list
        );
    }

    public function testSeek()
    {
        $this->graph->parse(readFixture('rdf-collection.rdf'), 'rdfxml');

        $owner = $this->graph->resource('ex:owner');
        $pets = $owner->get('ex:pets');

        $pets->seek(1);
        $this->assertTrue($pets->valid());
        $this->assertStringEquals('http://example.org/rat', $pets->current());

        $pets->seek(2);
        $this->assertTrue($pets->valid());
        $this->assertStringEquals('http://example.org/cat', $pets->current());

        $pets->seek(3);
        $this->assertTrue($pets->valid());
        $this->assertStringEquals('http://example.org/goat', $pets->current());
    }

    public function testSeekInvalid()
    {
        $this->expectException('OutOfBoundsException');
        $this->expectExceptionMessage('Unable to seek to position 2 in the collection');

        /** @var \EasyRdf\Collection */
        $list = $this->graph->newBnode('rdf:List');
        $list->addLiteral('rdf:first', 'Item 1');
        $list->addResource('rdf:rest', 'rdf:nil');
        $list->seek(2);
    }

    public function testSeekZero()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Collection position must be a positive integer');

        /** @var \EasyRdf\Collection */
        $list = $this->graph->newBnode('rdf:List');
        $list->seek(0);
    }

    public function testSeekMinusOne()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            'Collection position must be a positive integer'
        );

        /** @var \EasyRdf\Collection */
        $list = $this->graph->newBnode('rdf:List');
        $list->seek(-1);
    }

    public function testSeekNonInteger()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            'Collection position must be a positive integer'
        );

        /** @var \EasyRdf\Collection */
        $list = $this->graph->newBnode('rdf:List');
        $list->seek('foo');
    }

    public function testCountEmpty()
    {
        $list = $this->graph->newBnode('rdf:List');
        $this->assertSame(0, \count($list));
    }

    public function testCountOne()
    {
        /** @var \EasyRdf\Collection */
        $list = $this->graph->newBnode('rdf:List');
        $list->append('Item');
        $this->assertSame(1, \count($list));
    }

    public function testCountTwo()
    {
        /** @var \EasyRdf\Collection */
        $list = $this->graph->newBnode('rdf:List');
        $list->append('Item 1');
        $list->append('Item 2');
        $this->assertSame(2, \count($list));
    }

    public function testCountThree()
    {
        /** @var \EasyRdf\Collection */
        $list = $this->graph->newBnode('rdf:List');
        $list->append('Item 1');
        $list->append('Item 2');
        $list->append('Item 3');
        $this->assertSame(3, \count($list));
    }

    public function testArrayOffsetExists()
    {
        /** @var \EasyRdf\Collection */
        $list = $this->graph->newBnode('rdf:List');
        $list->addLiteral('rdf:first', 'Item');
        $list->addResource('rdf:rest', 'rdf:nil');

        $this->assertTrue(isset($list[1]));
    }

    public function testArrayOffsetDoesntExist()
    {
        /** @var \EasyRdf\Collection */
        $list = $this->graph->newBnode('rdf:List');
        $list->addLiteral('rdf:first', 'Item');
        $list->addResource('rdf:rest', 'rdf:nil');

        $this->assertFalse(isset($list[2]));
    }

    public function testArrayOffsetDoesntExistEmpty()
    {
        /** @var \EasyRdf\Collection */
        $list = $this->graph->newBnode('rdf:List');
        $this->assertFalse(isset($list[1]));
    }

    public function testArrayOffsetExistsZero()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            'Collection offset must be a positive integer'
        );

        $this->assertNull($this->graph->newBnode('rdf:List')[0]);
    }

    public function testArrayOffsetExistsMinusOne()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            'Collection offset must be a positive integer'
        );

        $this->assertNull($this->graph->newBnode('rdf:List')[-1]);
    }

    public function testArrayOffsetExistsNonInteger()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            'Collection offset must be a positive integer'
        );

        $this->assertNull($this->graph->newBnode('rdf:List')['foo']);
    }

    public function testArrayOffsetGet()
    {
        $this->graph->parse(readFixture('rdf-collection.rdf'), 'rdfxml');

        $owner = $this->graph->resource('ex:owner');
        $pets = $owner->get('ex:pets');

        $this->assertStringEquals('http://example.org/rat', $pets[1]);
        $this->assertStringEquals('http://example.org/cat', $pets[2]);
        $this->assertStringEquals('http://example.org/goat', $pets[3]);
    }

    public function testArrayOffsetGetNonexistent()
    {
        /** @var \EasyRdf\Collection */
        $list = $this->graph->newBnode('rdf:List');
        $list->append('foo');
        $this->assertNull($list[2]);
    }

    public function testArrayOffsetGetEmptyNonexistent()
    {
        /** @var \EasyRdf\Collection */
        $list = $this->graph->newBnode('rdf:List');
        $this->assertNull($list[1]);
    }

    public function testArrayOffsetGetZero()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            'Collection offset must be a positive integer'
        );

        $this->assertNull($this->graph->newBnode('rdf:List')[0]);
    }

    public function testArrayOffsetGetMinusOne()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            'Collection offset must be a positive integer'
        );

        $this->assertNull($this->graph->newBnode('rdf:List')[-1]);
    }

    public function testArrayOffsetGetNonInteger()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            'Collection offset must be a positive integer'
        );

        $this->assertNull($this->graph->newBnode('rdf:List')['foo']);
    }

    public function testArrayOffsetSet()
    {
        /** @var \EasyRdf\Collection */
        $list = $this->graph->newBnode('rdf:List');

        $list[1] = 'Item 1';
        $list[2] = 'Item 2';
        $list[3] = 'Item 3';

        $strings = [];
        foreach ($list as $item) {
            $strings[] = (string) $item;
        }

        $this->assertEquals(
            ['Item 1', 'Item 2', 'Item 3'],
            $strings
        );
    }

    public function testArrayOffsetSetReplace()
    {
        $list = $this->graph->newBnode('rdf:List');
        $list->add('rdf:first', 'Item 1');
        $list->addResource('rdf:rest', 'rdf:nil');

        $this->assertStringEquals('Item 1', $list->get('rdf:first'));
        $list[1] = 'Replace';
        $this->assertStringEquals('Replace', $list->get('rdf:first'));
    }

    public function testArrayOffsetAppend()
    {
        $list = $this->graph->newBnode('rdf:List');

        $list[] = 'Item 1';
        $list[] = 'Item 2';
        $list[] = 'Item 3';

        $cur = $list;
        $this->assertStringEquals('Item 1', $cur->get('rdf:first'));
        $cur = $cur->get('rdf:rest');
        $this->assertStringEquals('Item 2', $cur->get('rdf:first'));
        $cur = $cur->get('rdf:rest');
        $this->assertStringEquals('Item 3', $cur->get('rdf:first'));
    }

    public function testArrayOffsetSetZero()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            'Collection offset must be a positive integer'
        );
        $list = $this->graph->newBnode('rdf:List');
        $list[0] = 'Item 1';
    }

    public function testArrayOffsetSetMinusOne()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            'Collection offset must be a positive integer'
        );
        $list = $this->graph->newBnode('rdf:List');
        $list[-1] = 'Item 1';
    }

    public function testArrayOffsetSetNonInteger()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            'Collection offset must be a positive integer'
        );
        $list = $this->graph->newBnode('rdf:List');
        $list['foo'] = 'Item 1';
    }

    public function testArrayOffsetUnsetFirst()
    {
        /** @var \EasyRdf\Collection */
        $list = $this->graph->newBnode('rdf:List');

        $list->append('Item 1');
        $list->append('Item 2');
        $list->append('Item 3');
        unset($list[1]);

        $this->assertStringEquals('Item 2', $list[1]);
        $this->assertStringEquals('Item 3', $list[2]);
        $this->assertNull($list[3]);
    }

    public function testArrayOffsetUnsetSingle()
    {
        /** @var \EasyRdf\Collection */
        $list = $this->graph->newBnode('rdf:List');
        $list->addResource('rdf:first', 'Item 1');
        unset($list[1]);

        $this->assertNull($list[1]);
    }

    public function testArrayOffsetUnsetUnterminated()
    {
        /** @var \EasyRdf\Collection */
        $list = $this->graph->newBnode('rdf:List');
        $list->addResource('rdf:first', 'Item 1');
        $next = $this->graph->newBnode();
        $list->addResource('rdf:rest', $next);
        $next->add('rdf:first', 'Item 2');

        $this->assertStringEquals('Item 1', $list[1]);
        $this->assertStringEquals('Item 2', $list[2]);

        unset($list[2]);

        $this->assertStringEquals('Item 1', $list[1]);
        $this->assertNull($list[2]);
    }

    public function testArrayOffsetUnsetMiddle()
    {
        /** @var \EasyRdf\Collection */
        $list = $this->graph->newBnode('rdf:List');

        $list->append('Item 1');
        $list->append('Item 2');
        $list->append('Item 3');
        unset($list[2]);

        $this->assertStringEquals('Item 1', $list[1]);
        $this->assertStringEquals('Item 3', $list[2]);
        $this->assertNull($list[3]);
    }

    public function testArrayOffsetUnsetLast()
    {
        /** @var \EasyRdf\Collection */
        $list = $this->graph->newBnode('rdf:List');

        $list->append('Item 1');
        $list->append('Item 2');
        $list->append('Item 3');
        unset($list[3]);

        $this->assertStringEquals('Item 1', $list[1]);
        $this->assertStringEquals('Item 2', $list[2]);
        $this->assertNull($list[3]);
    }

    public function testArrayOffsetUnsetZero()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            'Collection offset must be a positive integer'
        );

        /** @var \EasyRdf\Collection */
        $list = $this->graph->newBnode('rdf:List');
        unset($list[0]);
    }

    public function testArrayOffsetUnsetMinusOne()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            'Collection offset must be a positive integer'
        );

        /** @var \EasyRdf\Collection */
        $list = $this->graph->newBnode('rdf:List');
        unset($list[-1]);
    }

    public function testArrayOffsetUnsetNonInteger()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage(
            'Collection offset must be a positive integer'
        );
        $list = $this->graph->newBnode('rdf:List');
        unset($list['foo']);
    }

    public function testAppend()
    {
        /** @var \EasyRdf\Collection */
        $animals = $this->graph->newBnode('rdf:List');
        $this->assertSame('rdf:List', $animals->type());
        $this->assertClass('EasyRdf\Collection', $animals);

        $this->assertEquals(1, $animals->append('Rat'));
        $this->assertEquals(1, $animals->append('Cat'));
        $this->assertEquals(1, $animals->append('Dog'));

        $list = [];
        foreach ($animals as $animal) {
            $list[] = (string) $animal;
        }

        $this->assertEquals(
            ['Rat', 'Cat', 'Dog'],
            $list
        );
    }
}
